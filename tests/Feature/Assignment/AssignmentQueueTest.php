<?php

declare(strict_types=1);

namespace Tests\Feature\Assignment;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Events\TicketAssigned;
use App\Exceptions\AssignmentNotAllowed;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use App\Services\AssignmentService;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssignmentQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->admin()->create(['name' => 'Head of ICT']);
        $this->agent = User::factory()->agent()->create(['name' => 'Brian Otieno']);
    }

    // --- access -----------------------------------------------------------------------------

    public function test_a_visitor_is_sent_to_sign_in(): void
    {
        $this->get('/admin/assignments')->assertRedirect('/login');
    }

    public function test_staff_and_agents_get_403_on_the_queue_and_on_assigning(): void
    {
        $ticket = Ticket::factory()->create();

        foreach ([User::factory()->create(), User::factory()->agent()->create()] as $user) {
            $this->actingAs($user)->get('/admin/assignments')->assertForbidden();
            $this->actingAs($user)->put("/admin/tickets/{$ticket->ticket_no}/agent", ['agent_id' => $this->agent->id])->assertForbidden();
        }

        $this->assertNull($ticket->fresh()->agent_id);
        $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);
    }

    public function test_a_deactivated_administrator_is_signed_out(): void
    {
        $this->actingAs(User::factory()->admin()->inactive()->create())->get('/admin/assignments')->assertRedirect('/login');
    }

    public function test_every_route_of_the_queue_needs_sign_in_an_active_account_and_the_admin_role(): void
    {
        foreach (['admin.assignments.index', 'admin.tickets.assign'] as $name) {
            $middleware = app('router')->getRoutes()->getByName($name)->gatherMiddleware();

            foreach (['auth', 'active', 'role:admin'] as $required) {
                $this->assertContains($required, $middleware, "{$name} is missing {$required}");
            }
        }
    }

    public function test_the_menu_item_is_only_for_administrators(): void
    {
        $this->actingAs($this->admin)->get('/dashboard')->assertSee('href="'.route('admin.assignments.index').'"', false);
        $this->actingAs(User::factory()->agent()->create())->get('/dashboard')->assertDontSee('Assignment queue');
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertDontSee('Assignment queue');
    }

    // --- the unassigned queue -----------------------------------------------------------------

    public function test_the_queue_lists_only_unassigned_unresolved_tickets(): void
    {
        Ticket::factory()->create(['title' => 'Nobody has this yet']);
        Ticket::factory()->assigned($this->agent)->create(['title' => 'Already has an agent']);
        Ticket::factory()->closed()->create(['title' => 'Long since closed']);

        $this->actingAs($this->admin)->get('/admin/assignments')
            ->assertOk()
            ->assertSee('Nobody has this yet')
            ->assertDontSee('Already has an agent')
            ->assertDontSee('Long since closed');
    }

    public function test_unassigned_tickets_are_ordered_by_due_time_most_urgent_first(): void
    {
        Ticket::factory()->create(['title' => 'Due last', 'due_at' => now()->addHours(20), 'created_at' => now()->subDays(3)]);
        Ticket::factory()->create(['title' => 'Due first', 'due_at' => now()->addHours(1), 'created_at' => now()->subHour()]);
        Ticket::factory()->create(['title' => 'Due in the middle', 'due_at' => now()->addHours(9), 'created_at' => now()->subDay()]);

        $this->actingAs($this->admin)->get('/admin/assignments')->assertSeeInOrder(['Due first', 'Due in the middle', 'Due last']);
    }

    public function test_the_queue_shows_what_an_administrator_needs_to_choose_an_agent(): void
    {
        $requester = User::factory()->create(['name' => 'Wanjiku Kamau']);
        Ticket::factory()->for($requester, 'requester')->priority(TicketPriority::High)->create(['ticket_no' => 'HD-2026-00142', 'title' => 'Printer jam']);

        $this->actingAs($this->admin)->get('/admin/assignments')
            ->assertSee('HD-2026-00142')
            ->assertSee('Printer jam')
            ->assertSee('Wanjiku Kamau')
            ->assertSee('High')
            ->assertSee('Open')
            ->assertSee('Assign to');
    }

    public function test_overdue_tickets_are_marked_in_red_with_the_word_overdue(): void
    {
        Ticket::factory()->overdue()->create();

        $this->actingAs($this->admin)->get('/admin/assignments')->assertSee('Overdue')->assertSee('is-overdue', false)->assertSee('overdue-flag', false);
    }

    public function test_tickets_that_are_not_late_carry_no_overdue_label(): void
    {
        Ticket::factory()->create(['due_at' => now()->addHours(5)]);

        $this->actingAs($this->admin)->get('/admin/assignments')->assertDontSee('overdue-flag', false)->assertDontSee('is-overdue', false);
    }

    // --- the agent picker ---------------------------------------------------------------------

    public function test_each_active_agent_is_offered_with_their_open_ticket_count(): void
    {
        $busy = User::factory()->agent()->create(['name' => 'Grace Chebet']);
        Ticket::factory()->count(3)->assigned($busy)->create();
        Ticket::factory()->resolved($busy)->create();
        Ticket::factory()->create();

        $this->actingAs($this->admin)->get('/admin/assignments')
            ->assertSee('Brian Otieno · 0 open')
            ->assertSee('Grace Chebet · 3 open');
    }

    public function test_agents_with_the_fewest_tickets_are_listed_first(): void
    {
        $busy = User::factory()->agent()->create(['name' => 'Aaron Busy']);
        Ticket::factory()->count(2)->assigned($busy)->create();
        Ticket::factory()->create();

        $this->actingAs($this->admin)->get('/admin/assignments')->assertSeeInOrder(['Brian Otieno · 0 open', 'Aaron Busy · 2 open']);
    }

    public function test_deactivated_agents_staff_and_administrators_are_not_offered(): void
    {
        User::factory()->agent()->inactive()->create(['name' => 'Gone Agent']);
        User::factory()->create(['name' => 'Ordinary Staff']);
        Ticket::factory()->create();

        $html = $this->actingAs($this->admin)->get('/admin/assignments')->getContent();

        $this->assertStringNotContainsString('Gone Agent ·', (string) $html);
        $this->assertStringNotContainsString('Ordinary Staff ·', (string) $html);
        $this->assertStringNotContainsString('Head of ICT ·', (string) $html);
    }

    public function test_with_no_active_agent_the_page_says_what_to_do_and_offers_no_form(): void
    {
        $this->agent->forceFill(['is_active' => false])->save();
        Ticket::factory()->create();

        $this->actingAs($this->admin)->get('/admin/assignments')
            ->assertSee('There are no active support agents to assign tickets to.')
            ->assertSee('Create or reactivate an agent under Users.')
            ->assertSee('No other active agent to choose.');
    }

    // --- TC06: assigning ----------------------------------------------------------------------

    public function test_tc06_an_administrator_assigns_a_ticket_to_an_agent(): void
    {
        Event::fake([TicketAssigned::class]);
        $ticket = Ticket::factory()->create(['ticket_no' => 'HD-2026-00142']);

        $this->actingAs($this->admin)->from('/admin/assignments')
            ->put('/admin/tickets/HD-2026-00142/agent', ['agent_id' => $this->agent->id])
            ->assertRedirect('/admin/assignments')
            ->assertSessionHas('toast', ['type' => 'success', 'message' => 'Ticket HD-2026-00142 assigned to Brian Otieno.']);

        $fresh = $ticket->fresh();
        $this->assertSame($this->agent->id, $fresh->agent_id);
        $this->assertSame(TicketStatus::Assigned, $fresh->status);
        Event::assertDispatched(TicketAssigned::class);
    }

    public function test_tc06_the_assignment_is_recorded_in_the_history_and_shown_on_the_ticket(): void
    {
        $ticket = Ticket::factory()->create(['ticket_no' => 'HD-2026-00142']);

        $this->actingAs($this->admin)->put('/admin/tickets/HD-2026-00142/agent', ['agent_id' => $this->agent->id]);

        $history = TicketHistory::query()->where('ticket_id', $ticket->id)->sole();
        $this->assertSame('assigned', $history->action);
        $this->assertSame('Assigned to Brian Otieno.', $history->details);
        $this->actingAs($this->admin)->get('/tickets/HD-2026-00142')->assertSee('Assigned to Brian Otieno.')->assertSee('Head of ICT');
    }

    public function test_tc06_the_assigned_ticket_leaves_the_unassigned_queue_and_moves_to_assigned(): void
    {
        $ticket = Ticket::factory()->create(['title' => 'Moves between views']);

        $this->actingAs($this->admin)->put("/admin/tickets/{$ticket->ticket_no}/agent", ['agent_id' => $this->agent->id]);

        $this->actingAs($this->admin)->get('/admin/assignments')->assertDontSee('Moves between views');
        $this->actingAs($this->admin)->get('/admin/assignments?view=assigned')->assertSee('Moves between views')->assertSee('Brian Otieno');
    }

    public function test_the_agents_count_goes_up_after_an_assignment(): void
    {
        $ticket = Ticket::factory()->create();
        Ticket::factory()->create();

        $this->actingAs($this->admin)->put("/admin/tickets/{$ticket->ticket_no}/agent", ['agent_id' => $this->agent->id]);

        $this->actingAs($this->admin)->get('/admin/assignments')->assertSee('Brian Otieno · 1 open');
    }

    public function test_an_assigned_ticket_can_be_reassigned_from_the_assigned_view(): void
    {
        $other = User::factory()->agent()->create(['name' => 'Grace Chebet']);
        $ticket = Ticket::factory()->inProgress($other)->create(['ticket_no' => 'HD-2026-00150']);

        $this->actingAs($this->admin)->get('/admin/assignments?view=assigned')
            ->assertSee('Reassign')
            ->assertSee('Brian Otieno · 0 open')
            ->assertDontSee('Grace Chebet · 1 open', false);

        $this->actingAs($this->admin)->from('/admin/assignments?view=assigned')
            ->put('/admin/tickets/HD-2026-00150/agent', ['agent_id' => $this->agent->id])
            ->assertSessionHas('toast', ['type' => 'success', 'message' => 'Ticket HD-2026-00150 reassigned to Brian Otieno.']);

        $this->assertSame(TicketStatus::InProgress, $ticket->fresh()->status);
        $this->assertSame($this->agent->id, $ticket->fresh()->agent_id);
    }

    public function test_a_resolved_ticket_can_still_be_reassigned_but_a_closed_one_cannot(): void
    {
        $resolved = Ticket::factory()->resolved()->create();
        $closed = Ticket::factory()->closed()->create();

        $this->actingAs($this->admin)->put("/admin/tickets/{$resolved->ticket_no}/agent", ['agent_id' => $this->agent->id])->assertSessionHasNoErrors();
        $this->assertSame($this->agent->id, $resolved->fresh()->agent_id);
        $this->assertSame(TicketStatus::Resolved, $resolved->fresh()->status);

        $originalAgent = $closed->agent_id;
        $this->actingAs($this->admin)->put("/admin/tickets/{$closed->ticket_no}/agent", ['agent_id' => $this->agent->id])->assertForbidden();
        $this->assertSame($originalAgent, $closed->fresh()->agent_id);
    }

    public function test_a_finished_ticket_without_an_agent_never_appears_in_the_unassigned_queue(): void
    {
        // Not a state the application creates, but the queue must never offer a finished ticket whatever the data says.
        Ticket::factory()->create(['title' => 'Finished without agent', 'status' => TicketStatus::Closed, 'closed_at' => now(), 'agent_id' => null]);
        Ticket::factory()->create(['title' => 'Resolved without agent', 'status' => TicketStatus::Resolved, 'resolved_at' => now(), 'agent_id' => null]);

        $this->actingAs($this->admin)->get('/admin/assignments')->assertDontSee('Finished without agent')->assertDontSee('Resolved without agent');
        $this->assertSame(0, app(AssignmentService::class)->queueCounts()['unassigned']);
    }

    public function test_closed_tickets_are_not_offered_for_assignment_anywhere(): void
    {
        Ticket::factory()->closed()->create(['title' => 'Final and closed']);

        foreach (['unassigned', 'assigned', 'overdue'] as $view) {
            $this->actingAs($this->admin)->get("/admin/assignments?view={$view}")->assertDontSee('Final and closed');
        }
    }

    // --- forbidden assignments ------------------------------------------------------------------

    /**
     * @return array<string, array{Closure, string}>
     */
    public static function badAgents(): array
    {
        return [
            'a deactivated agent' => [fn (self $t): int => User::factory()->agent()->inactive()->create()->id, 'Choose an active support agent.'],
            'a staff user' => [fn (self $t): int => User::factory()->create()->id, 'Choose an active support agent.'],
            'an administrator' => [fn (self $t): int => $t->admin->id, 'Choose an active support agent.'],
            'an agent that does not exist' => [fn (self $t): int => 999999, 'Choose an active support agent.'],
            'text instead of a number' => [fn (self $t): string => 'brian', 'Choose an active support agent.'],
            'nothing chosen' => [fn (self $t): ?string => null, 'Choose an agent.'],
        ];
    }

    /**
     */
    #[DataProvider('badAgents')]
    public function test_an_invalid_agent_is_refused_in_plain_words_and_nothing_changes(Closure $agent, string $message): void
    {
        Event::fake([TicketAssigned::class]);
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->admin)->from('/admin/assignments')
            ->put("/admin/tickets/{$ticket->ticket_no}/agent", ['agent_id' => $agent($this)])
            ->assertRedirect('/admin/assignments')
            ->assertSessionHasErrors(['agent_id' => $message]);

        $this->assertNull($ticket->fresh()->agent_id);
        $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);
        $this->assertDatabaseCount('ticket_history', 0);
        Event::assertNotDispatched(TicketAssigned::class);
    }

    public function test_the_error_is_shown_on_the_queue_page(): void
    {
        $ticket = Ticket::factory()->create();
        $this->actingAs($this->admin)->from('/admin/assignments')->put("/admin/tickets/{$ticket->ticket_no}/agent", ['agent_id' => '']);

        $this->actingAs($this->admin)->get('/admin/assignments')->assertSee('Choose an agent.');
    }

    public function test_the_person_who_reported_a_ticket_cannot_be_its_agent(): void
    {
        $ticket = Ticket::factory()->for($this->agent, 'requester')->create();

        $this->actingAs($this->admin)->from('/admin/assignments')
            ->put("/admin/tickets/{$ticket->ticket_no}/agent", ['agent_id' => $this->agent->id])
            ->assertSessionHas('toast', ['type' => 'error', 'message' => 'A ticket cannot be assigned to the person who reported it.']);

        $this->assertNull($ticket->fresh()->agent_id);
    }

    public function test_assigning_to_the_agent_a_ticket_already_has_is_refused_kindly(): void
    {
        $ticket = Ticket::factory()->assigned($this->agent)->create();

        $this->actingAs($this->admin)->from('/admin/assignments?view=assigned')
            ->put("/admin/tickets/{$ticket->ticket_no}/agent", ['agent_id' => $this->agent->id])
            ->assertSessionHas('toast', ['type' => 'error', 'message' => 'This ticket is already assigned to Brian Otieno.']);

        $this->assertDatabaseCount('ticket_history', 0);
    }

    public function test_a_refusal_that_happens_while_saving_is_shown_as_a_message_not_an_error_page(): void
    {
        $this->mock(AssignmentService::class, function ($mock): void {
            $mock->shouldReceive('assign')->andThrow(new AssignmentNotAllowed('Choose an active support agent.'));
        });
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->admin)->from('/admin/assignments')
            ->put("/admin/tickets/{$ticket->ticket_no}/agent", ['agent_id' => $this->agent->id])
            ->assertRedirect('/admin/assignments')
            ->assertSessionHas('toast', ['type' => 'error', 'message' => 'Choose an active support agent.']);
    }

    public function test_an_unknown_ticket_number_is_404_and_a_database_id_does_not_work(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->admin)->put('/admin/tickets/HD-2026-99999/agent', ['agent_id' => $this->agent->id])->assertNotFound();
        $this->actingAs($this->admin)->put("/admin/tickets/{$ticket->id}/agent", ['agent_id' => $this->agent->id])->assertNotFound();
    }

    // --- the other views, filters and paging ------------------------------------------------------------

    public function test_the_tabs_show_how_many_tickets_each_view_holds(): void
    {
        Ticket::factory()->count(3)->create();
        Ticket::factory()->count(2)->assigned($this->agent)->create();
        Ticket::factory()->overdue()->create();

        $counts = app(AssignmentService::class)->queueCounts();

        $this->assertSame(['unassigned' => 4, 'assigned' => 2, 'overdue' => 1], $counts);
        $this->actingAs($this->admin)->get('/admin/assignments')->assertSeeInOrder(['Unassigned', '4', 'Assigned', '2', 'Overdue', '1']);
    }

    public function test_the_overdue_view_holds_every_late_unresolved_ticket_assigned_or_not(): void
    {
        Ticket::factory()->overdue()->create(['title' => 'Late and unassigned']);
        Ticket::factory()->overdue()->assigned($this->agent)->create(['title' => 'Late and assigned']);
        Ticket::factory()->overdue()->resolved()->create(['title' => 'Late but resolved']);
        Ticket::factory()->create(['title' => 'Not late']);

        $this->actingAs($this->admin)->get('/admin/assignments?view=overdue')
            ->assertSee('Late and unassigned')
            ->assertSee('Late and assigned')
            ->assertDontSee('Late but resolved')
            ->assertDontSee('Not late');
    }

    public function test_the_assigned_view_shows_the_agent_and_flags_a_deactivated_one(): void
    {
        $gone = User::factory()->agent()->inactive()->create(['name' => 'Gone Agent']);
        Ticket::factory()->assigned($gone)->create(['title' => 'Orphaned ticket']);
        Ticket::factory()->assigned($this->agent)->create(['title' => 'Healthy ticket']);

        $this->actingAs($this->admin)->get('/admin/assignments?view=assigned')
            ->assertSee('Gone Agent')
            ->assertSee('(deactivated)')
            ->assertSee('Healthy ticket');
    }

    public function test_search_and_priority_narrow_the_queue(): void
    {
        Ticket::factory()->priority(TicketPriority::High)->create(['title' => 'Wi-Fi is down', 'ticket_no' => 'HD-2026-00011']);
        Ticket::factory()->priority(TicketPriority::Low)->create(['title' => 'Monitor flickers', 'ticket_no' => 'HD-2026-00012']);

        $this->actingAs($this->admin)->get('/admin/assignments?search=wi-fi')->assertSee('Wi-Fi is down')->assertDontSee('Monitor flickers');
        $this->actingAs($this->admin)->get('/admin/assignments?search=00012')->assertSee('Monitor flickers')->assertDontSee('Wi-Fi is down');
        $this->actingAs($this->admin)->get('/admin/assignments?priority=low')->assertSee('Monitor flickers')->assertDontSee('Wi-Fi is down');
        $this->actingAs($this->admin)->get('/admin/assignments?search=zzzz')->assertSee('No tickets match these filters')->assertSee('Clear filters');
    }

    public function test_unexpected_parameters_are_refused(): void
    {
        foreach (['view=everything', 'priority=urgent', 'search='.str_repeat('a', 101)] as $query) {
            $this->actingAs($this->admin)->from('/admin/assignments')->get('/admin/assignments?'.$query)->assertRedirect('/admin/assignments');
        }
    }

    public function test_empty_views_say_so_with_a_useful_message(): void
    {
        $this->actingAs($this->admin)->get('/admin/assignments')->assertSee('Every ticket has an agent');
        $this->actingAs($this->admin)->get('/admin/assignments?view=overdue')->assertSee('Nothing is overdue');
        $this->actingAs($this->admin)->get('/admin/assignments?view=assigned')->assertSee('No assigned tickets');
    }

    public function test_the_queue_shows_twenty_tickets_a_page_and_keeps_the_view_in_the_links(): void
    {
        Ticket::factory()->count(45)->create();

        $html = $this->actingAs($this->admin)->get('/admin/assignments')->assertSee('Showing 1–20 of 45 tickets')->getContent();

        $this->assertStringContainsString('page=2', (string) $html);
        $this->actingAs($this->admin)->get('/admin/assignments?page=3')->assertSee('Showing 41–45 of 45 tickets');
    }

    public function test_a_full_page_does_not_query_once_per_ticket(): void
    {
        Ticket::factory()->count(20)->create();

        DB::enableQueryLog();
        $this->actingAs($this->admin)->get('/admin/assignments')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(16, $queries, "The queue used {$queries} queries.");
    }

    public function test_ticket_titles_and_names_are_escaped(): void
    {
        Ticket::factory()->create(['title' => '<script>alert(1)</script>']);

        $this->actingAs($this->admin)->get('/admin/assignments')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    // --- assigning from the ticket page ------------------------------------------------------------------

    public function test_an_administrator_sees_the_assignment_card_on_a_ticket_and_can_use_it(): void
    {
        User::factory()->agent()->create(['name' => 'Grace Chebet']);
        $ticket = Ticket::factory()->create(['ticket_no' => 'HD-2026-00142']);

        $this->actingAs($this->admin)->get('/tickets/HD-2026-00142')->assertOk()->assertSee('Assignment')->assertSee('Not assigned yet.')->assertSee('Brian Otieno · 0 open');

        $this->actingAs($this->admin)->from('/tickets/HD-2026-00142')
            ->put('/admin/tickets/HD-2026-00142/agent', ['agent_id' => $this->agent->id])
            ->assertRedirect('/tickets/HD-2026-00142');

        $this->actingAs($this->admin)->get('/tickets/HD-2026-00142')->assertSee('Assigned to')->assertSee('Brian Otieno')->assertSee('Reassign');
    }

    public function test_nobody_but_an_administrator_sees_the_assignment_card(): void
    {
        $ticket = Ticket::factory()->assigned($this->agent)->create(['ticket_no' => 'HD-2026-00142']);

        $this->actingAs($ticket->requester)->get('/tickets/HD-2026-00142')->assertOk()->assertDontSee('Reassign')->assertDontSee('name="agent_id"', false);
        $this->actingAs($this->agent)->get('/tickets/HD-2026-00142')->assertOk()->assertDontSee('name="agent_id"', false);
    }

    public function test_a_closed_ticket_has_no_assignment_card_even_for_an_administrator(): void
    {
        $ticket = Ticket::factory()->closed()->create(['ticket_no' => 'HD-2026-00142']);

        $this->actingAs($this->admin)->get('/tickets/HD-2026-00142')->assertOk()->assertDontSee('name="agent_id"', false)->assertDontSee('<h2 class="h4 m-0">Assignment</h2>', false);
    }
}
