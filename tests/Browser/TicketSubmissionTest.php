<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Staff reporting a problem in a real browser, including real file uploads through PHP's upload handling.
 * They run against the development database, so what they create is removed afterwards.
 */
class TicketSubmissionTest extends DuskTestCase
{
    /**
     * @var list<string>
     */
    private array $emails = [];

    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        if ($this->emails !== []) {
            $users = User::query()->whereIn('email', $this->emails)->get();
            foreach (Ticket::query()->whereIn('requester_id', $users->modelKeys())->get() as $ticket) {
                if ($ticket->attachment_path !== null) {
                    Storage::disk('local')->delete($ticket->attachment_path);
                }
                $ticket->delete();
            }
            User::query()->whereIn('email', $this->emails)->delete();
            $this->emails = [];
        }

        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        $this->tempFiles = [];

        parent::tearDown();
    }

    private function staffUser(): User
    {
        $email = $this->emails[] = 'dusk.ticket.'.Str::lower(Str::random(8)).'@example.test';

        return User::factory()->create(['name' => 'Wanjiku Dusk', 'email' => $email]);
    }

    /**
     * A file whose first bytes are those of a PDF, padded to the given size.
     */
    private function pdfOfSize(int $bytes): string
    {
        $path = sys_get_temp_dir().'/dusk-'.Str::random(8).'.pdf';
        file_put_contents($path, str_pad("%PDF-1.4\n", $bytes, ' '));

        return $this->tempFiles[] = $path;
    }

    public function test_staff_report_a_problem_with_a_three_megabyte_attachment_and_follow_it(): void
    {
        $user = $this->staffUser();
        $pdf = $this->pdfOfSize(3 * 1024 * 1024);

        $this->browse(function (Browser $browser) use ($user, $pdf): void {
            $browser->loginAs($user)
                ->visit('/tickets')
                ->assertSee('You have not reported a problem yet')
                ->clickLink('Report a problem')
                ->waitForLocation('/tickets/create')
                ->assertSee('Which category?')
                ->assertSee('We aim to resolve it within 4 hours.')
                ->screenshot('new-ticket')
                ->type('title', 'Printer by the registry shows paper jam')
                ->select('category_id', (string) \App\Models\Category::query()->where('name', 'Printing')->value('id'))
                ->radio('priority', 'high')
                ->type('description', 'The printer says paper jam. I cleared every tray twice and it still says so.')
                ->attach('attachment', $pdf)
                ->press('Submit ticket')
                ->waitUntil('window.location.pathname.startsWith("/tickets/HD-")', 20)
                ->waitFor('.toast-success')
                ->assertSeeIn('.toast-success', 'created. We\'ll email you when an agent picks it up.')
                ->assertSee('Printer by the registry shows paper jam')
                ->assertSee('Printing')
                ->assertSee('High')
                ->assertSee('Waiting for an agent to be assigned.')
                ->assertSee('Download attachment')
                ->assertSee('Ticket submitted with high priority.')
                ->screenshot('ticket-detail');
        });

        $ticket = Ticket::query()->where('requester_id', $user->id)->sole();
        $this->assertNotNull($ticket->attachment_path);
        $this->assertSame(3 * 1024 * 1024, Storage::disk('local')->size($ticket->attachment_path), 'The whole file must have arrived.');
    }

    public function test_an_agent_reports_a_problem_of_their_own_from_their_menu(): void
    {
        $email = $this->emails[] = 'dusk.agent.'.Str::lower(Str::random(8)).'@example.test';
        $agent = User::factory()->agent()->create(['name' => 'Agent Dusk', 'email' => $email]);

        $this->browse(function (Browser $browser) use ($agent): void {
            $browser->loginAs($agent)
                ->visit('/dashboard')
                ->assertSeeIn('#sidebar', 'My tickets')
                ->assertSeeIn('#sidebar', 'New ticket')
                ->keys('', 'n')
                ->waitForLocation('/tickets/create')
                ->type('title', 'My own laptop will not charge')
                ->select('category_id', (string) \App\Models\Category::query()->where('name', 'Hardware')->value('id'))
                ->radio('priority', 'low')
                ->type('description', 'The charger light is on but the battery stays at 12 percent.')
                ->press('Submit ticket')
                ->waitUntil('window.location.pathname.startsWith("/tickets/HD-")', 20)
                ->assertSee('My own laptop will not charge')
                ->assertSee('Waiting for an agent to be assigned.')
                ->visit('/tickets')
                ->assertSee('My own laptop will not charge');
        });

        $this->assertNull(Ticket::query()->where('requester_id', $agent->id)->sole()->agent_id);
    }

    public function test_the_ticket_appears_in_my_tickets_and_its_number_can_be_copied(): void
    {
        $user = $this->staffUser();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/tickets/create')
                ->type('title', 'Cannot open the finance system')
                ->select('category_id', (string) \App\Models\Category::query()->where('name', 'Software')->value('id'))
                ->radio('priority', 'medium')
                ->type('description', 'It shows a blank page after I sign in, since this morning.')
                ->press('Submit ticket')
                ->waitUntil('window.location.pathname.startsWith("/tickets/HD-")', 20)
                ->waitFor('.toast-success')
                ->click('.page-header + .row .copy-button, .detail-list .copy-button')
                ->waitFor('.toast-success + .toast-success, .toast-success')
                ->visit('/tickets')
                ->assertSee('Cannot open the finance system')
                ->assertSee('Unresolved')
                ->screenshot('my-tickets');
        });
    }

    public function test_a_mistake_shows_a_plain_message_and_keeps_what_was_typed(): void
    {
        $user = $this->staffUser();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/tickets/create')
                ->type('title', 'abc')
                ->type('description', 'My typed description is long enough')
                ->press('Submit ticket')
                ->waitForText('The title needs at least 5 characters.')
                ->assertSee('Choose a category.')
                ->assertSee('Choose how urgent this is.')
                ->assertInputValue('title', 'abc')
                ->assertInputValue('description', 'My typed description is long enough')
                ->assertPathIs('/tickets/create')
                ->screenshot('new-ticket-errors');
        });

        $this->assertDatabaseMissingTickets($user);
    }

    public function test_a_file_over_five_megabytes_is_refused_with_a_clear_message(): void
    {
        $user = $this->staffUser();
        $tooBig = $this->pdfOfSize((int) (5.5 * 1024 * 1024));

        $this->browse(function (Browser $browser) use ($user, $tooBig): void {
            $browser->loginAs($user)
                ->visit('/tickets/create')
                ->type('title', 'A valid title here')
                ->select('category_id', (string) \App\Models\Category::query()->value('id'))
                ->type('description', 'A valid description that is long enough.')
                ->attach('attachment', $tooBig)
                ->press('Submit ticket')
                ->waitForText('The file is larger than 5 MB. Choose a smaller one.');
        });

        $this->assertDatabaseMissingTickets($user);
    }

    public function test_a_file_larger_than_php_will_accept_gets_a_kind_message_not_an_error_page(): void
    {
        $user = $this->staffUser();
        $huge = $this->pdfOfSize(7 * 1024 * 1024);

        $this->browse(function (Browser $browser) use ($user, $huge): void {
            $browser->loginAs($user)
                ->visit('/tickets/create')
                ->type('title', 'A valid title here')
                ->select('category_id', (string) \App\Models\Category::query()->value('id'))
                ->type('description', 'A valid description that is long enough.')
                ->attach('attachment', $huge)
                ->press('Submit ticket')
                ->waitForText('The file could not be uploaded. It may be larger than 5 MB.')
                ->assertPathIs('/tickets/create');
        });

        $this->assertDatabaseMissingTickets($user);
    }

    public function test_the_n_shortcut_opens_the_form_from_the_list(): void
    {
        $user = $this->staffUser();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/tickets')
                ->keys('', 'n')
                ->waitForLocation('/tickets/create')
                ->assertSee('Report a problem');
        });
    }

    public function test_the_ticket_screens_fit_a_360px_phone_without_sideways_scrolling(): void
    {
        $user = $this->staffUser();
        // An existing category is used on purpose: a factory would add a random category to the development database.
        $ticket = Ticket::factory()->for($user, 'requester')->create([
            'title' => 'A rather long ticket title that has to wrap on a very small phone screen',
            'category_id' => \App\Models\Category::query()->value('id'),
        ]);

        $this->browse(function (Browser $browser) use ($user, $ticket): void {
            $this->emulatePhone($browser);
            $browser->loginAs($user);

            foreach (['/tickets', '/tickets/create', '/tickets/'.$ticket->ticket_no] as $path) {
                $browser->visit($path);
                $this->assertTrue(
                    $browser->script('return document.documentElement.scrollWidth <= document.documentElement.clientWidth;')[0],
                    "{$path} scrolls sideways on a phone",
                );
            }

            $browser->visit('/tickets/create')->screenshot('new-ticket-phone');
        });
    }

    private function assertDatabaseMissingTickets(User $user): void
    {
        $this->assertSame(0, Ticket::query()->where('requester_id', $user->id)->count(), 'No ticket may be created from a refused form.');
    }
}
