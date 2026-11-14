<?php

declare(strict_types=1);

namespace Tests\Feature\Assignment;

use App\Enums\TicketStatus;
use App\Events\TicketAssigned;
use App\Exceptions\AssignmentNotAllowed;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use App\Services\AssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Head of ICT']);
        $this->agent = User::factory()->agent()->create(['name' => 'Brian Otieno']);
    }

    private function service(): AssignmentService
    {
        return app(AssignmentService::class);
    }

    // --- first assignment --------------------------------------------------------------------

    public function test_the_first_assignment_gives_the_ticket_an_agent_and_moves_it_from_open_to_assigned(): void
    {
        $ticket = Ticket::factory()->create();

        $result = $this->service()->assign($ticket, $this->agent, $this->admin);

        $fresh = $ticket->fresh();
        $this->assertSame($this->agent->id, $fresh->agent_id);
        $this->assertSame(TicketStatus::Assigned, $fresh->status);
        $this->assertSame($this->agent->id, $result->agent_id);
    }

    public function test_the_first_assignment_is_recorded_in_the_history_by_the_administrator(): void
    {
        $ticket = Ticket::factory()->create();

        $this->service()->assign($ticket, $this->agent, $this->admin);

        $history = TicketHistory::query()->where('ticket_id', $ticket->id)->sole();
        $this->assertSame('assigned', $history->action);
        $this->assertSame($this->admin->id, $history->changed_by);
        $this->assertSame(TicketStatus::Open, $history->old_status);
        $this->assertSame(TicketStatus::Assigned, $history->new_status);
        $this->assertSame('Assigned to Brian Otieno.', $history->details);
    }

    public function test_the_assigned_event_is_dispatched_once_with_no_previous_agent(): void
    {
        Event::fake([TicketAssigned::class]);
        $ticket = Ticket::factory()->create();

        $this->service()->assign($ticket, $this->agent, $this->admin);

        Event::assertDispatchedTimes(TicketAssigned::class, 1);
        Event::assertDispatched(TicketAssigned::class, fn (TicketAssigned $event): bool => $event->ticket->is($ticket)
            && $event->agent->is($this->agent)
            && $event->previousAgent === null
            && $event->assignedBy->is($this->admin));
    }

    // --- reassignment ---------------------------------------------------------------------------

    /**
     * @return array<string, array{TicketStatus}>
     */
    public static function reassignableStatuses(): array
    {
        return [
            'assigned' => [TicketStatus::Assigned],
            'in progress' => [TicketStatus::InProgress],
            'resolved' => [TicketStatus::Resolved],
        ];
    }

    #[DataProvider('reassignableStatuses')]
    public function test_reassigning_changes_only_the_agent_and_keeps_the_status(TicketStatus $status): void
    {
        $previous = User::factory()->agent()->create(['name' => 'Grace Chebet']);
        $ticket = Ticket::factory()->create(['status' => $status, 'agent_id' => $previous->id, 'resolved_at' => $status === TicketStatus::Resolved ? now() : null, 'resolution' => $status === TicketStatus::Resolved ? 'Fixed.' : null]);

        $this->service()->assign($ticket, $this->agent, $this->admin);

        $fresh = $ticket->fresh();
        $this->assertSame($this->agent->id, $fresh->agent_id);
        $this->assertSame($status, $fresh->status, 'Reassigning must not change the status.');
    }

    public function test_reassigning_names_both_agents_in_the_history_without_a_status_change(): void
    {
        $previous = User::factory()->agent()->create(['name' => 'Grace Chebet']);
        $ticket = Ticket::factory()->inProgress($previous)->create();

        $this->service()->assign($ticket, $this->agent, $this->admin);

        $history = TicketHistory::query()->where('ticket_id', $ticket->id)->sole();
        $this->assertSame('reassigned', $history->action);
        $this->assertSame($this->admin->id, $history->changed_by);
        $this->assertSame('Reassigned from Grace Chebet to Brian Otieno.', $history->details);
        $this->assertNull($history->old_status);
        $this->assertNull($history->new_status);
    }

    public function test_the_event_for_a_reassignment_carries_the_previous_agent(): void
    {
        Event::fake([TicketAssigned::class]);
        $previous = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assigned($previous)->create();

        $this->service()->assign($ticket, $this->agent, $this->admin);

        Event::assertDispatched(TicketAssigned::class, fn (TicketAssigned $event): bool => $event->previousAgent?->is($previous) === true && $event->agent->is($this->agent));
    }

    public function test_an_overdue_ticket_stays_overdue_after_reassignment(): void
    {
        $ticket = Ticket::factory()->overdue()->escalated(2)->assigned()->create();

        $this->service()->assign($ticket, $this->agent, $this->admin);

        $fresh = $ticket->fresh();
        $this->assertTrue($fresh->isOverdue());
        $this->assertSame(2, $fresh->escalation_level, 'Reassigning must not reset or change the escalation.');
    }

    // --- refusals ---------------------------------------------------------------------------------

    /**
     * @return array<string, array{callable(self): array{Ticket, User}, string}>
     */
    public static function forbiddenAssignments(): array
    {
        return [
            'a closed ticket' => [fn (self $t): array => [Ticket::factory()->closed()->create(), $t->agent], 'Closed tickets cannot be assigned.'],
            'a deactivated agent' => [fn (self $t): array => [Ticket::factory()->create(), User::factory()->agent()->inactive()->create()], 'Choose an active support agent.'],
            'a staff user' => [fn (self $t): array => [Ticket::factory()->create(), User::factory()->create()], 'Choose an active support agent.'],
            'an administrator' => [fn (self $t): array => [Ticket::factory()->create(), User::factory()->admin()->create()], 'Choose an active support agent.'],
            'the agent it already has' => [fn (self $t): array => [Ticket::factory()->assigned($t->agent)->create(), $t->agent], 'This ticket is already assigned to Brian Otieno.'],
            'the person who reported it' => [fn (self $t): array => [Ticket::factory()->for($t->agent, 'requester')->create(), $t->agent], 'A ticket cannot be assigned to the person who reported it.'],
        ];
    }

    /**
     * @param  callable(self): array{Ticket, User}  $make
     */
    #[DataProvider('forbiddenAssignments')]
    public function test_forbidden_assignments_are_refused_with_a_plain_message_and_change_nothing(callable $make, string $message): void
    {
        Event::fake([TicketAssigned::class]);
        [$ticket, $agent] = $make($this);
        $before = $ticket->fresh()->only(['agent_id', 'status']);

        try {
            $this->service()->assign($ticket, $agent, $this->admin);
            $this->fail('The assignment must be refused.');
        } catch (AssignmentNotAllowed $exception) {
            $this->assertSame($message, $exception->getMessage());
        }

        $this->assertSame($before, $ticket->fresh()->only(['agent_id', 'status']));
        $this->assertDatabaseCount('ticket_history', 0);
        Event::assertNotDispatched(TicketAssigned::class);
    }

    public function test_an_agent_who_does_not_exist_is_refused(): void
    {
        $ghost = new User();
        $ghost->id = 999999;

        $this->expectException(AssignmentNotAllowed::class);

        $this->service()->assign(Ticket::factory()->create(), $ghost, $this->admin);
    }

    // --- stale pages: the world changed after the form was shown ------------------------------------

    public function test_a_ticket_closed_after_the_page_was_loaded_is_still_refused(): void
    {
        $ticket = Ticket::factory()->create();
        $stale = Ticket::query()->findOrFail($ticket->id);
        $ticket->forceFill(['status' => TicketStatus::Closed, 'closed_at' => now(), 'agent_id' => User::factory()->agent()->create()->id])->save();

        $this->expectException(AssignmentNotAllowed::class);

        $this->service()->assign($stale, $this->agent, $this->admin);
    }

    public function test_an_agent_deactivated_after_the_page_was_loaded_is_still_refused(): void
    {
        $ticket = Ticket::factory()->create();
        $stale = User::query()->findOrFail($this->agent->id);
        $this->agent->forceFill(['is_active' => false])->save();

        $this->expectException(AssignmentNotAllowed::class);

        $this->service()->assign($ticket, $stale, $this->admin);
    }

    public function test_a_ticket_assigned_by_someone_else_in_the_meantime_is_treated_as_a_reassignment(): void
    {
        $ticket = Ticket::factory()->create();
        $stale = Ticket::query()->findOrFail($ticket->id);
        $other = User::factory()->agent()->create(['name' => 'Grace Chebet']);
        $this->service()->assign($ticket, $other, $this->admin);

        $this->service()->assign($stale, $this->agent, $this->admin);

        $this->assertSame(['assigned', 'reassigned'], TicketHistory::query()->where('ticket_id', $ticket->id)->orderBy('id')->pluck('action')->all());
        $this->assertSame(TicketStatus::Assigned, $ticket->fresh()->status);
    }

    // --- atomicity ----------------------------------------------------------------------------------

    public function test_if_the_history_row_cannot_be_written_the_assignment_is_undone(): void
    {
        Event::fake([TicketAssigned::class]);
        $ticket = Ticket::factory()->create();
        TicketHistory::creating(function (): never {
            throw new RuntimeException('History write failed.');
        });

        try {
            $this->service()->assign($ticket, $this->agent, $this->admin);
            $this->fail('The failure must reach the caller.');
        } catch (RuntimeException) {
            // expected
        }

        $fresh = $ticket->fresh();
        $this->assertNull($fresh->agent_id);
        $this->assertSame(TicketStatus::Open, $fresh->status);
        Event::assertNotDispatched(TicketAssigned::class);
    }

    // --- who has room ----------------------------------------------------------------------------------

    public function test_the_agent_list_counts_unresolved_tickets_per_active_agent_only(): void
    {
        $busy = User::factory()->agent()->create(['name' => 'Busy Agent']);
        $idle = User::factory()->agent()->create(['name' => 'Aaron Idle']);
        Ticket::factory()->assigned($busy)->create();
        Ticket::factory()->inProgress($busy)->create();
        Ticket::factory()->resolved($busy)->create();
        Ticket::factory()->closed($busy)->create();
        Ticket::factory()->assigned($idle)->create();
        User::factory()->agent()->inactive()->create(['name' => 'Gone Agent']);

        $counts = $this->service()->agentsWithOpenTickets()->pluck('open_tickets_count', 'name');

        $this->assertSame(2, $counts['Busy Agent']);
        $this->assertSame(1, $counts['Aaron Idle']);
        $this->assertSame(0, $counts['Brian Otieno']);
        $this->assertArrayNotHasKey('Gone Agent', $counts->all());
        $this->assertArrayNotHasKey('Head of ICT', $counts->all(), 'Administrators are not offered as agents.');
    }

    public function test_the_agent_list_puts_the_agents_with_the_fewest_tickets_first(): void
    {
        $busy = User::factory()->agent()->create(['name' => 'Aaron Busy']);
        Ticket::factory()->count(3)->assigned($busy)->create();

        $names = $this->service()->agentsWithOpenTickets()->pluck('name')->all();

        $this->assertSame(['Brian Otieno', 'Aaron Busy'], $names);
    }
}
