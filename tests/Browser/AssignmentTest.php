<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use App\Services\SlaService;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * An administrator assigning tickets and setting resolution times in a real browser. They run against
 * the development database, so each test creates uniquely named records, removes them afterwards and
 * puts the SLA hours back. Nothing here runs the escalation command: that would escalate every overdue
 * ticket in the development database for good, and PHPUnit covers it.
 */
class AssignmentTest extends DuskTestCase
{
    /**
     * @var list<string>
     */
    private array $emails = [];

    /**
     * @var array<string, int>|null
     */
    private ?array $originalSla = null;

    protected function tearDown(): void
    {
        if ($this->originalSla !== null) {
            $admin = User::query()->where('role', 'admin')->firstOrFail();
            app(SlaService::class)->update($this->originalSla, $admin);
            $this->originalSla = null;
        }

        if ($this->emails !== []) {
            $ids = User::query()->whereIn('email', $this->emails)->pluck('id');
            Ticket::query()->where(fn ($query) => $query->whereIn('requester_id', $ids)->orWhereIn('agent_id', $ids))->delete();
            User::query()->whereIn('email', $this->emails)->delete();
            $this->emails = [];
        }

        parent::tearDown();
    }

    private function person(string $label, string $name, string $role = 'user'): User
    {
        $email = $this->emails[] = "dusk.{$label}.".Str::lower(Str::random(8)).'@example.test';
        $factory = User::factory();
        $factory = match ($role) {
            'admin' => $factory->admin(),
            'agent' => $factory->agent(),
            default => $factory,
        };

        return $factory->create(['name' => $name, 'email' => $email]);
    }

    /**
     * A ticket made without creating a random category, which would be left in the development database.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function ticket(User $requester, array $attributes = []): Ticket
    {
        return Ticket::factory()->for($requester, 'requester')->create([
            'category_id' => Category::query()->value('id'),
            'title' => 'Dusk ticket '.Str::lower(Str::random(6)),
            ...$attributes,
        ]);
    }

    public function test_an_administrator_assigns_a_ticket_and_then_reassigns_it(): void
    {
        $admin = $this->person('admin', 'Admin Dusk', 'admin');
        $first = $this->person('first', 'First Agent Dusk', 'agent');
        $second = $this->person('second', 'Second Agent Dusk', 'agent');
        $ticket = $this->ticket($this->person('staff', 'Staff Dusk'));

        $this->browse(function (Browser $browser) use ($admin, $first, $second, $ticket): void {
            $browser->loginAs($admin)
                ->visit('/dashboard')
                ->assertSee('Needs attention')
                ->clickLink('Assignment queue')
                ->waitForLocation('/admin/assignments')
                ->assertSee('Unassigned')
                ->type('search', $ticket->ticket_no)
                ->press('Apply filters')
                ->waitForText($ticket->title)
                ->assertSee('First Agent Dusk · 0 open')
                ->screenshot('assignment-queue')
                ->select("#agent-{$ticket->id}", (string) $first->id)
                ->press('Assign')
                ->waitFor('.toast-success')
                ->assertSeeIn('.toast-success', "Ticket {$ticket->ticket_no} assigned to First Agent Dusk.");

            // It has an agent now, so it left the unassigned queue and is in the assigned view, where it can be moved.
            $browser->visit('/admin/assignments?search='.urlencode($ticket->ticket_no))
                ->assertDontSee($ticket->title)
                ->visit('/admin/assignments?view=assigned&search='.urlencode($ticket->ticket_no))
                ->assertSee($ticket->title)
                ->assertSee('First Agent Dusk')
                ->assertSee('Second Agent Dusk · 0 open')
                ->select("#agent-{$ticket->id}", (string) $second->id)
                ->press('Reassign')
                ->waitFor('.toast-success')
                ->assertSeeIn('.toast-success', "Ticket {$ticket->ticket_no} reassigned to Second Agent Dusk.");

            $browser->visit('/tickets/'.$ticket->ticket_no)
                ->assertSee('Assigned to')
                ->assertSee('Second Agent Dusk')
                ->assertSee('Reassigned from First Agent Dusk to Second Agent Dusk.')
                ->assertSee('Assigned to First Agent Dusk.')
                ->screenshot('ticket-assigned');
        });

        $fresh = $ticket->fresh();
        $this->assertSame($second->id, $fresh->agent_id);
        $this->assertSame(TicketStatus::Assigned, $fresh->status);
        $this->assertSame(['assigned', 'reassigned'], TicketHistory::query()->where('ticket_id', $ticket->id)->orderBy('id')->pluck('action')->all());
    }

    public function test_an_administrator_assigns_from_the_ticket_page(): void
    {
        $admin = $this->person('admin', 'Admin Dusk', 'admin');
        $agent = $this->person('agent', 'Page Agent Dusk', 'agent');
        $ticket = $this->ticket($this->person('staff', 'Staff Dusk'));

        $this->browse(function (Browser $browser) use ($admin, $agent, $ticket): void {
            $browser->loginAs($admin)
                ->visit('/tickets/'.$ticket->ticket_no)
                ->assertSee('Not assigned yet.')
                ->select("#agent-{$ticket->id}", (string) $agent->id)
                ->press('Assign')
                ->waitFor('.toast-success')
                ->assertPathIs('/tickets/'.$ticket->ticket_no)
                ->assertSee('Page Agent Dusk');
        });

        $this->assertSame($agent->id, $ticket->fresh()->agent_id);
    }

    public function test_an_escalated_overdue_ticket_shows_the_red_overdue_label_in_every_place(): void
    {
        $admin = $this->person('admin', 'Admin Dusk', 'admin');
        $ticket = $this->ticket($this->person('staff', 'Staff Dusk'), ['due_at' => now()->subHours(5), 'escalation_level' => 2, 'escalated_at' => now()->subMinutes(5)]);
        TicketHistory::factory()->for($ticket)->bySystem()->create(['action' => 'escalated', 'details' => 'Escalated to level 2: past its due time.']);

        $this->browse(function (Browser $browser) use ($admin, $ticket): void {
            $browser->loginAs($admin)
                ->visit('/admin/assignments?view=overdue&search='.urlencode($ticket->ticket_no))
                ->assertSee($ticket->title)
                ->assertPresent('.overdue-flag')
                ->assertPresent('tr.is-overdue')
                ->assertSeeIn('.overdue-flag', 'Overdue')
                ->visit('/tickets/'.$ticket->ticket_no)
                ->assertPresent('.overdue-flag')
                ->assertSee('Escalated')
                ->assertSee('System')
                ->assertSee('Escalated to level 2: past its due time.')
                ->screenshot('ticket-escalated')
                ->visit('/dashboard')
                ->assertPresent('.stat-tile.is-overdue')
                ->screenshot('dashboard-admin');
        });
    }

    public function test_an_administrator_changes_the_resolution_times_and_sees_a_clear_error_for_a_bad_one(): void
    {
        $admin = $this->person('admin', 'Admin Dusk', 'admin');
        $this->originalSla = app(SlaService::class)->hoursByPriority();
        $original = $this->originalSla['high'];

        $this->browse(function (Browser $browser) use ($admin, $original): void {
            $browser->loginAs($admin)
                ->visit('/dashboard')
                ->clickLink('SLA settings')
                ->waitForLocation('/admin/sla')
                ->assertInputValue('high_hours', (string) $original)
                ->assertSee('after you save')
                ->screenshot('sla-settings')
                ->type('high_hours', '0')
                ->press('Save resolution times')
                ->waitForText('Enter a whole number of hours from 1 to 720.')
                ->assertInputValue('high_hours', '0')
                ->type('high_hours', (string) ($original + 1))
                ->press('Save resolution times')
                ->waitFor('.toast-success')
                ->assertSeeIn('.toast-success', 'Resolution times saved. They apply to tickets reported from now on.')
                ->assertInputValue('high_hours', (string) ($original + 1));
        });

        $this->assertSame($original + 1, app(SlaService::class)->hoursByPriority()['high']);
    }

    public function test_staff_and_agents_who_type_the_admin_addresses_get_the_friendly_403_page(): void
    {
        $staff = $this->person('staff', 'Staff Dusk');
        $agent = $this->person('agent', 'Agent Dusk', 'agent');

        $this->browse(function (Browser $browser) use ($staff, $agent): void {
            foreach ([$staff, $agent] as $user) {
                $browser->loginAs($user);
                foreach (['/admin/assignments', '/admin/sla'] as $path) {
                    $browser->visit($path)->assertSee('Error 403')->assertSee("You don't have access to this page");
                }
            }
        });
    }

    public function test_the_queue_settings_and_dashboard_fit_a_360px_phone_without_sideways_scrolling(): void
    {
        $admin = $this->person('admin', 'Admin Dusk', 'admin');
        $this->ticket($this->person('staff', 'Staff Dusk'), ['title' => 'A rather long ticket title that has to wrap on a very small phone screen']);

        $this->browse(function (Browser $browser) use ($admin): void {
            $this->emulatePhone($browser);
            $browser->loginAs($admin);

            foreach (['/dashboard', '/admin/assignments', '/admin/assignments?view=assigned', '/admin/sla'] as $path) {
                $browser->visit($path);
                $this->assertTrue(
                    $browser->script('return document.documentElement.scrollWidth <= document.documentElement.clientWidth;')[0],
                    "{$path} scrolls sideways on a phone",
                );
            }

            $browser->visit('/admin/assignments')->screenshot('assignment-queue-phone');
        });
    }
}
