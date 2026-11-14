<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Response;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Tests\TestCase;

class TicketDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->me = User::factory()->create(['name' => 'Wanjiku Kamau', 'department' => 'Finance']);
        $this->ticket = Ticket::factory()->for($this->me, 'requester')->create([
            'ticket_no' => 'HD-2026-00142',
            'title' => 'Printer by the registry shows paper jam',
            'description' => "The printer says paper jam.\nI cleared every tray twice.",
            'category_id' => Category::factory()->create(['name' => 'Printing'])->id,
            'priority' => TicketPriority::High,
            'due_at' => now()->addHours(3),
        ]);
    }

    /**
     * @return TestResponse<HttpResponse>
     */
    private function open(?User $user = null, ?Ticket $ticket = null): TestResponse
    {
        return $this->actingAs($user ?? $this->me)->get('/tickets/'.($ticket ?? $this->ticket)->ticket_no);
    }

    // --- TC09: who may see a ticket ---------------------------------------------------------

    public function test_tc09_the_requester_sees_their_own_ticket(): void
    {
        $this->open()
            ->assertOk()
            ->assertSee('Printer by the registry shows paper jam')
            ->assertSee('HD-2026-00142')
            ->assertSee('Printing')
            ->assertSee('High')
            ->assertSee('Open')
            ->assertSee('Reported by Wanjiku Kamau')
            ->assertSee('Not assigned yet')
            ->assertSee('Waiting for an agent to be assigned.');
    }

    public function test_tc09_another_staff_user_gets_403_on_someone_elses_ticket(): void
    {
        $this->open(User::factory()->create())->assertForbidden();
    }

    public function test_a_visitor_who_is_not_signed_in_is_sent_to_sign_in(): void
    {
        $this->get('/tickets/HD-2026-00142')->assertRedirect('/login');
    }

    public function test_the_assigned_agent_may_open_the_ticket_and_other_agents_may_not(): void
    {
        $agent = User::factory()->agent()->create(['name' => 'Brian Otieno']);
        $this->ticket->forceFill(['agent_id' => $agent->id, 'status' => TicketStatus::Assigned])->save();

        $this->open($agent)->assertOk()->assertSee('Brian Otieno');
        $this->open(User::factory()->agent()->create())->assertForbidden();
    }

    public function test_an_agent_cannot_open_an_unassigned_ticket(): void
    {
        $this->open(User::factory()->agent()->create())->assertForbidden();
    }

    public function test_administrators_may_open_any_ticket(): void
    {
        $this->open(User::factory()->admin()->create())->assertOk()->assertSee('Wanjiku Kamau, Finance');
    }

    public function test_a_deactivated_user_cannot_open_even_their_own_ticket(): void
    {
        $this->me->forceFill(['is_active' => false])->save();

        $this->open()->assertRedirect('/login');
    }

    public function test_an_unknown_ticket_number_is_404_not_403(): void
    {
        $this->actingAs($this->me)->get('/tickets/HD-2026-99999')->assertNotFound();
    }

    public function test_only_ticket_numbers_open_a_ticket_not_database_ids_or_other_text(): void
    {
        $this->actingAs($this->me)->get('/tickets/'.$this->ticket->id)->assertNotFound();
        $this->actingAs($this->me)->get('/tickets/not-a-ticket')->assertNotFound();
        $this->actingAs($this->me)->get("/tickets/1' OR '1'='1")->assertNotFound();
    }

    // --- content --------------------------------------------------------------------------

    public function test_the_page_shows_the_dates_and_the_due_time(): void
    {
        $this->open()->assertSee($this->ticket->created_at->format('j M Y, H:i'))->assertSee($this->ticket->due_at->format('j M Y, H:i'));
    }

    public function test_an_overdue_ticket_says_so_in_words(): void
    {
        $this->ticket->forceFill(['due_at' => now()->subHours(2)])->save();

        $this->open()->assertSee('Overdue')->assertSee('text-overdue', false);
    }

    public function test_the_description_keeps_its_line_breaks_in_an_escaped_block(): void
    {
        $this->open()->assertSee("The printer says paper jam.\nI cleared every tray twice.", false)->assertSee('class="ticket-text"', false);
    }

    public function test_everything_a_person_typed_is_escaped(): void
    {
        $payload = '<script>alert("x")</script>';
        $this->ticket->forceFill(['title' => $payload, 'description' => $payload])->save();
        $agent = User::factory()->agent()->create(['name' => $payload]);
        Response::factory()->for($this->ticket)->for($agent)->create(['message' => $payload]);

        $response = $this->open(User::factory()->admin()->create());

        $response->assertDontSee($payload, false)->assertSee('&lt;script&gt;', false);
    }

    public function test_the_status_sentence_changes_with_the_status_and_names_the_agent(): void
    {
        $agent = User::factory()->agent()->create(['name' => 'Brian Otieno']);

        $this->ticket->forceFill(['agent_id' => $agent->id, 'status' => TicketStatus::InProgress])->save();
        $this->open()->assertSee('Brian Otieno is working on this.');

        $this->ticket->forceFill(['status' => TicketStatus::Resolved, 'resolution' => 'Replaced the toner.', 'resolved_at' => now()])->save();
        $this->open()->assertSee('Marked as resolved. Please check that it works, then confirm or reopen it.')->assertSee('Replaced the toner.')->assertSee('Resolution');
    }

    public function test_an_unresolved_ticket_has_no_resolution_section(): void
    {
        $this->open()->assertDontSee('Resolution');
    }

    // --- conversation ---------------------------------------------------------------------

    public function test_replies_are_shown_oldest_first_with_who_wrote_them(): void
    {
        $agent = User::factory()->agent()->create(['name' => 'Brian Otieno']);
        Response::factory()->for($this->ticket)->for($agent)->create(['message' => 'Second: I am on my way.', 'created_at' => now()->subMinutes(5)]);
        Response::factory()->for($this->ticket)->for($this->me)->create(['message' => 'First: it is the third floor printer.', 'created_at' => now()->subMinutes(30)]);

        $this->open()->assertSeeInOrder(['First: it is the third floor printer.', 'Second: I am on my way.'])->assertSee('Brian Otieno')->assertSee('Support agent');
    }

    public function test_without_replies_the_page_says_so_kindly(): void
    {
        $this->open()->assertSee('No replies yet. When an agent replies, you will see it here.');
    }

    public function test_internal_notes_are_hidden_from_the_person_who_reported_the_ticket(): void
    {
        $agent = User::factory()->agent()->create();
        Response::factory()->for($this->ticket)->for($agent)->create(['message' => 'Public: we are looking into it.']);
        Response::factory()->for($this->ticket)->for($agent)->internal()->create(['message' => 'Internal: the user keeps unplugging the cable.']);

        $this->open()->assertSee('Public: we are looking into it.')->assertDontSee('Internal: the user keeps unplugging the cable.')->assertDontSee('Internal note');
    }

    public function test_internal_notes_are_visible_to_the_support_team_and_marked(): void
    {
        $agent = User::factory()->agent()->create();
        $this->ticket->forceFill(['agent_id' => $agent->id])->save();
        Response::factory()->for($this->ticket)->for($agent)->internal()->create(['message' => 'Internal: the user keeps unplugging the cable.']);

        $this->open($agent)->assertSee('Internal: the user keeps unplugging the cable.')->assertSee('Internal note');
        $this->open(User::factory()->admin()->create())->assertSee('Internal: the user keeps unplugging the cable.');
    }

    // --- history --------------------------------------------------------------------------

    public function test_the_history_shows_every_change_with_who_made_it(): void
    {
        $agent = User::factory()->agent()->create(['name' => 'Brian Otieno']);
        TicketHistory::factory()->for($this->ticket)->create(['changed_by' => $this->me->id, 'action' => 'created', 'new_status' => TicketStatus::Open, 'details' => 'Ticket submitted with high priority.']);
        TicketHistory::factory()->for($this->ticket)->create(['changed_by' => $agent->id, 'action' => 'status_changed', 'old_status' => TicketStatus::Assigned, 'new_status' => TicketStatus::InProgress]);
        TicketHistory::factory()->for($this->ticket)->bySystem()->create(['action' => 'escalated', 'details' => 'Escalated to level 1.']);

        $this->open()
            ->assertSeeInOrder(['Ticket submitted', 'Status changed', 'Escalated'])
            ->assertSee('Ticket submitted with high priority.')
            ->assertSee('Brian Otieno')
            ->assertSee('System');
    }

    // --- efficiency and links -------------------------------------------------------------

    public function test_the_page_does_not_query_once_per_reply_or_history_row(): void
    {
        $agent = User::factory()->agent()->create();
        Response::factory()->count(15)->for($this->ticket)->for($agent)->create();
        TicketHistory::factory()->count(15)->for($this->ticket)->create();

        DB::enableQueryLog();
        $this->open()->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(14, $queries, "The page used {$queries} queries.");
    }

    public function test_the_list_links_each_ticket_to_its_page(): void
    {
        $this->actingAs($this->me)->get('/tickets')->assertSee('href="'.route('tickets.show', $this->ticket).'"', false);
    }

    public function test_the_person_who_reported_a_ticket_gets_a_way_back_to_their_list_and_the_agent_working_on_it_does_not(): void
    {
        $breadcrumb = '<li class="breadcrumb-item"><a href="'.route('tickets.index').'">My tickets</a></li>';
        $this->open()->assertSee($breadcrumb, false);

        $agent = User::factory()->agent()->create();
        $this->ticket->forceFill(['agent_id' => $agent->id])->save();
        $this->open($agent)->assertDontSee($breadcrumb, false);
    }

    public function test_an_agent_who_reported_the_ticket_themselves_gets_the_way_back(): void
    {
        $agent = User::factory()->agent()->create();
        $own = Ticket::factory()->for($agent, 'requester')->create(['ticket_no' => 'HD-2026-00200']);

        $this->open($agent, $own)->assertOk()->assertSee('<li class="breadcrumb-item"><a href="'.route('tickets.index').'">My tickets</a></li>', false);
    }

    public function test_internal_notes_are_hidden_from_the_requester_even_when_the_requester_is_an_agent_or_admin(): void
    {
        $worker = User::factory()->agent()->create();

        foreach ([User::factory()->agent()->create(), User::factory()->admin()->create()] as $requester) {
            $own = Ticket::factory()->for($requester, 'requester')->assigned($worker)->create();
            Response::factory()->for($own)->for($worker)->create(['message' => "Public reply for {$requester->role->value}"]);
            Response::factory()->for($own)->for($worker)->internal()->create(['message' => "Secret note for {$requester->role->value}"]);

            $this->open($requester, $own)->assertSee("Public reply for {$requester->role->value}")->assertDontSee("Secret note for {$requester->role->value}");
            $this->open($worker, $own)->assertSee("Secret note for {$requester->role->value}");
        }
    }

    public function test_the_ticket_number_can_be_copied(): void
    {
        $this->open()->assertSee('data-copy="HD-2026-00142"', false);
    }

    public function test_a_ticket_number_in_the_address_is_matched_exactly(): void
    {
        $this->actingAs($this->me)->get('/tickets/hd-2026-00142')->assertNotFound();
    }
}
