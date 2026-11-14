<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Events\TicketCreated;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SlaPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class SubmitTicketTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private Category $network;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        $this->seed([CategorySeeder::class, SlaPolicySeeder::class]);
        $this->me = User::factory()->create(['name' => 'Wanjiku Kamau']);
        $this->network = Category::query()->where('name', 'Network')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        return [
            'title' => 'Wi-Fi keeps disconnecting',
            'category_id' => $this->network->id,
            'priority' => 'medium',
            'description' => 'It drops every ten minutes in the training room.',
            ...$overrides,
        ];
    }

    // --- TC04: submission -----------------------------------------------------------------

    public function test_tc04_a_staff_user_submits_a_ticket_and_lands_on_its_page(): void
    {
        Event::fake([TicketCreated::class]);

        $response = $this->actingAs($this->me)->post('/tickets', $this->validInput());

        $ticket = Ticket::query()->sole();
        $response->assertRedirect(route('tickets.show', $ticket));
        $this->assertSame('HD-'.now()->year.'-00001', $ticket->ticket_no);
        $this->assertSame($this->me->id, $ticket->requester_id);
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertSame('Wi-Fi keeps disconnecting', $ticket->title);
        $this->assertSame('It drops every ten minutes in the training room.', $ticket->description);
        $this->assertNull($ticket->agent_id);
    }

    public function test_tc04_the_confirmation_names_the_ticket_and_says_what_happens_next(): void
    {
        $this->actingAs($this->me)->post('/tickets', $this->validInput());

        $this->get('/tickets/HD-'.now()->year.'-00001')
            ->assertOk()
            ->assertSee('Ticket HD-'.now()->year.'-00001 created. We&#039;ll email you when an agent picks it up.', false);
    }

    public function test_tc04_the_created_event_fires_and_the_history_row_exists(): void
    {
        Event::fake([TicketCreated::class]);

        $this->actingAs($this->me)->post('/tickets', $this->validInput());

        Event::assertDispatchedTimes(TicketCreated::class, 1);
        $history = TicketHistory::query()->sole();
        $this->assertSame('created', $history->action);
        $this->assertSame($this->me->id, $history->changed_by);
        $this->assertSame(TicketStatus::Open, $history->new_status);
    }

    public function test_the_new_ticket_appears_in_my_tickets(): void
    {
        $this->actingAs($this->me)->post('/tickets', $this->validInput(['title' => 'Unique printer fault']));

        $this->get('/tickets')->assertSee('Unique printer fault')->assertSee('Unresolved');
    }

    public function test_an_attachment_is_saved_privately_and_downloadable_by_the_requester(): void
    {
        $file = UploadedFile::fake()->create('screenshot.pdf', 200, 'application/pdf');

        $this->actingAs($this->me)->post('/tickets', $this->validInput(['attachment' => $file]))->assertRedirect();

        $ticket = Ticket::query()->sole();
        $this->assertNotNull($ticket->attachment_path);
        Storage::disk('local')->assertExists($ticket->attachment_path);
        $this->assertStringNotContainsString('screenshot', $ticket->attachment_path);
        $this->get(route('tickets.attachment', $ticket))->assertOk();
    }

    public function test_a_ticket_without_an_attachment_is_fine(): void
    {
        $this->actingAs($this->me)->post('/tickets', $this->validInput())->assertRedirect();

        $this->assertNull(Ticket::query()->sole()->attachment_path);
    }

    public function test_the_requester_is_always_the_signed_in_user_whatever_the_form_says(): void
    {
        $other = User::factory()->create();

        $this->actingAs($this->me)->post('/tickets', $this->validInput(['requester_id' => $other->id]));

        $this->assertSame($this->me->id, Ticket::query()->sole()->requester_id);
    }

    public function test_fields_the_form_does_not_offer_cannot_be_forced(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($this->me)->post('/tickets', $this->validInput([
            'status' => 'closed',
            'agent_id' => $agent->id,
            'ticket_no' => 'HD-2000-00001',
            'due_at' => '2099-01-01 00:00:00',
            'escalation_level' => 5,
            'resolution' => 'Already fixed',
            'resolved_at' => now()->toDateTimeString(),
            'attachment_path' => '../../.env',
        ]));

        $ticket = Ticket::query()->sole();
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertNull($ticket->agent_id);
        $this->assertSame('HD-'.now()->year.'-00001', $ticket->ticket_no);
        $this->assertTrue($ticket->due_at->lessThan(now()->addDay()));
        $this->assertSame(0, $ticket->escalation_level);
        $this->assertNull($ticket->resolution);
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->attachment_path);
    }

    public function test_a_second_ticket_gets_the_next_number(): void
    {
        $this->actingAs($this->me)->post('/tickets', $this->validInput());
        $this->actingAs($this->me)->post('/tickets', $this->validInput());

        $this->assertSame(['HD-'.now()->year.'-00001', 'HD-'.now()->year.'-00002'], Ticket::query()->orderBy('id')->pluck('ticket_no')->all());
    }

    // --- TC05: category, priority and due time -----------------------------------------------

    public function test_tc05_the_chosen_category_and_priority_are_saved(): void
    {
        $printing = Category::query()->where('name', 'Printing')->firstOrFail();

        $this->actingAs($this->me)->post('/tickets', $this->validInput(['category_id' => $printing->id, 'priority' => 'high']));

        $ticket = Ticket::query()->sole();
        $this->assertSame($printing->id, $ticket->category_id);
        $this->assertSame(TicketPriority::High, $ticket->priority);
    }

    public function test_tc05_a_high_priority_ticket_is_due_four_hours_later(): void
    {
        $this->travelTo(now()->startOfHour());

        $this->actingAs($this->me)->post('/tickets', $this->validInput(['priority' => 'high']));

        $this->assertTrue(Ticket::query()->sole()->due_at->equalTo(now()->addHours(4)));
    }

    public function test_tc05_each_priority_gets_its_own_due_time(): void
    {
        $this->travelTo(now()->startOfHour());

        foreach (['high' => 4, 'medium' => 8, 'low' => 24] as $priority => $hours) {
            $this->actingAs($this->me)->post('/tickets', $this->validInput(['priority' => $priority, 'title' => "A {$priority} ticket"]));

            $ticket = Ticket::query()->where('title', "A {$priority} ticket")->sole();
            $this->assertTrue($ticket->due_at->equalTo(now()->addHours($hours)), "{$priority} should be due in {$hours} hours");
        }
    }

    // --- who may submit ---------------------------------------------------------------------

    public function test_agents_and_administrators_cannot_submit_tickets(): void
    {
        foreach ([User::factory()->agent()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->get('/tickets/create')->assertForbidden();
            $this->actingAs($user)->post('/tickets', $this->validInput())->assertForbidden();
        }

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_a_visitor_who_is_not_signed_in_is_sent_to_sign_in(): void
    {
        $this->get('/tickets/create')->assertRedirect('/login');
        $this->post('/tickets', $this->validInput())->assertRedirect('/login');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_a_deactivated_user_cannot_submit(): void
    {
        $this->actingAs(User::factory()->inactive()->create())->post('/tickets', $this->validInput())->assertRedirect('/login');

        $this->assertDatabaseCount('tickets', 0);
    }

    // --- abuse and failure ------------------------------------------------------------------

    public function test_submitting_tickets_is_rate_limited_per_person(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($this->me)->post('/tickets', $this->validInput())->assertRedirect();
        }

        $this->actingAs($this->me)->post('/tickets', $this->validInput())->assertStatus(429);
        $this->assertDatabaseCount('tickets', 5);

        $this->actingAs(User::factory()->create())->post('/tickets', $this->validInput())->assertRedirect();
    }

    public function test_when_creation_fails_the_person_sees_an_error_page_and_nothing_is_saved(): void
    {
        $this->withoutExceptionHandling();
        \App\Models\SlaPolicy::query()->delete();

        try {
            $this->actingAs($this->me)->post('/tickets', $this->validInput(['attachment' => UploadedFile::fake()->image('p.png')]));
            $this->fail('Creation without SLA policies must not succeed silently.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('tickets', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
    }
}
