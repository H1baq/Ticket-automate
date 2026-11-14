<?php

declare(strict_types=1);

namespace Tests\Feature\Escalation;

use App\Enums\TicketStatus;
use App\Events\TicketEscalated;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use App\Services\EscalationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class EscalationServiceTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $due;

    protected function setUp(): void
    {
        parent::setUp();

        $this->due = CarbonImmutable::parse('2026-11-14 10:00:00');
        // Carbon's test clock: time only moves when a test moves it.
        $this->travelTo($this->due->addSecond());
    }

    private function service(): EscalationService
    {
        return app(EscalationService::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function overdueTicket(array $attributes = []): Ticket
    {
        return Ticket::factory()->create(['due_at' => $this->due, ...$attributes]);
    }

    // --- TC17: levels over successive cycles -------------------------------------------------------

    public function test_tc17_a_ticket_past_its_due_time_goes_to_level_one(): void
    {
        $ticket = $this->overdueTicket();

        $escalated = $this->service()->escalateOverdue();

        $fresh = $ticket->fresh();
        $this->assertSame(1, $escalated);
        $this->assertSame(1, $fresh->escalation_level);
        $this->assertTrue($fresh->escalated_at->equalTo(now()));
    }

    public function test_tc17_after_another_cycle_it_goes_to_level_two(): void
    {
        $ticket = $this->overdueTicket();
        $this->service()->escalateOverdue();

        $this->travel(15)->minutes();
        $escalated = $this->service()->escalateOverdue();

        $this->assertSame(1, $escalated);
        $this->assertSame(2, $ticket->fresh()->escalation_level);
        $this->assertTrue($ticket->fresh()->escalated_at->equalTo(now()));
    }

    public function test_tc17_it_is_not_escalated_twice_within_fifteen_minutes(): void
    {
        $ticket = $this->overdueTicket();
        $this->service()->escalateOverdue();

        $this->assertSame(0, $this->service()->escalateOverdue(), 'Running again at once must change nothing.');

        $this->travel(14)->minutes();
        $this->travel(59)->seconds();
        $this->assertSame(0, $this->service()->escalateOverdue(), 'One second short of 15 minutes is still too soon.');

        $this->assertSame(1, $ticket->fresh()->escalation_level);
        $this->assertSame(1, TicketHistory::query()->where('ticket_id', $ticket->id)->where('action', 'escalated')->count());
    }

    public function test_tc17_exactly_fifteen_minutes_after_the_last_escalation_is_enough(): void
    {
        $ticket = $this->overdueTicket();
        $this->service()->escalateOverdue();

        $this->travel(15)->minutes();

        $this->assertSame(1, $this->service()->escalateOverdue());
        $this->assertSame(2, $ticket->fresh()->escalation_level);
    }

    public function test_tc17_it_keeps_climbing_one_level_each_cycle_until_resolved(): void
    {
        $ticket = $this->overdueTicket();

        $levels = [];
        for ($cycle = 1; $cycle <= 5; $cycle++) {
            $this->service()->escalateOverdue();
            $levels[] = $ticket->fresh()->escalation_level;
            $this->travel(15)->minutes();
        }
        $this->assertSame([1, 2, 3, 4, 5], $levels);

        $ticket->forceFill(['status' => TicketStatus::Resolved, 'resolved_at' => now()])->save();
        $this->assertSame(0, $this->service()->escalateOverdue());
        $this->assertSame(5, $ticket->fresh()->escalation_level, 'Resolving stops escalation.');
    }

    // --- who is escalated -----------------------------------------------------------------------------

    public function test_a_ticket_that_is_not_yet_due_is_left_alone_and_one_due_this_very_second_too(): void
    {
        $this->travelTo($this->due);
        $notYet = Ticket::factory()->create(['due_at' => $this->due->addMinute()]);
        $dueNow = Ticket::factory()->create(['due_at' => $this->due]);

        $this->assertSame(0, $this->service()->escalateOverdue());

        $this->assertSame(0, $notYet->fresh()->escalation_level);
        $this->assertSame(0, $dueNow->fresh()->escalation_level, 'Overdue means strictly after the due time.');
    }

    public function test_resolved_and_closed_tickets_are_never_escalated(): void
    {
        $resolved = $this->overdueTicket(['status' => TicketStatus::Resolved, 'resolved_at' => now(), 'agent_id' => User::factory()->agent()->create()->id]);
        $closed = $this->overdueTicket(['status' => TicketStatus::Closed, 'closed_at' => now(), 'agent_id' => User::factory()->agent()->create()->id]);

        $this->assertSame(0, $this->service()->escalateOverdue());

        $this->assertSame(0, $resolved->fresh()->escalation_level);
        $this->assertSame(0, $closed->fresh()->escalation_level);
        $this->assertDatabaseCount('ticket_history', 0);
    }

    public function test_every_unresolved_status_is_escalated_assigned_or_not(): void
    {
        $agent = User::factory()->agent()->create();
        $open = $this->overdueTicket();
        $assigned = $this->overdueTicket(['status' => TicketStatus::Assigned, 'agent_id' => $agent->id]);
        $inProgress = $this->overdueTicket(['status' => TicketStatus::InProgress, 'agent_id' => $agent->id]);

        $this->assertSame(3, $this->service()->escalateOverdue());

        foreach ([$open, $assigned, $inProgress] as $ticket) {
            $this->assertSame(1, $ticket->fresh()->escalation_level, $ticket->status->value);
        }
    }

    public function test_only_the_tickets_that_qualify_are_escalated_in_a_mixed_batch(): void
    {
        $late = $this->overdueTicket();
        $fine = Ticket::factory()->create(['due_at' => now()->addHours(2)]);
        $done = $this->overdueTicket(['status' => TicketStatus::Resolved, 'resolved_at' => now(), 'agent_id' => User::factory()->agent()->create()->id]);
        $recent = $this->overdueTicket(['escalation_level' => 1, 'escalated_at' => now()->subMinutes(5)]);

        $this->assertSame(1, $this->service()->escalateOverdue());

        $this->assertSame(1, $late->fresh()->escalation_level);
        $this->assertSame(0, $fine->fresh()->escalation_level);
        $this->assertSame(0, $done->fresh()->escalation_level);
        $this->assertSame(1, $recent->fresh()->escalation_level);
    }

    public function test_a_ticket_already_at_the_highest_level_is_left_instead_of_overflowing_the_column(): void
    {
        $ticket = $this->overdueTicket(['escalation_level' => 255, 'escalated_at' => now()->subHour()]);

        $this->assertSame(0, $this->service()->escalateOverdue());

        $this->assertSame(255, $ticket->fresh()->escalation_level);
    }

    public function test_one_level_below_the_highest_is_the_last_escalation(): void
    {
        $ticket = $this->overdueTicket(['escalation_level' => 254, 'escalated_at' => now()->subHour()]);

        $this->assertSame(1, $this->service()->escalateOverdue());
        $this->travel(15)->minutes();
        $this->assertSame(0, $this->service()->escalateOverdue());

        $this->assertSame(255, $ticket->fresh()->escalation_level);
    }

    // --- the record --------------------------------------------------------------------------------------

    public function test_each_escalation_writes_a_history_row_by_the_system(): void
    {
        $ticket = $this->overdueTicket();

        $this->service()->escalateOverdue();
        $this->travel(15)->minutes();
        $this->service()->escalateOverdue();

        $rows = TicketHistory::query()->where('ticket_id', $ticket->id)->orderBy('id')->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame('escalated', $row->action);
            $this->assertNull($row->changed_by, 'The system, not a person, escalates.');
            $this->assertNull($row->old_status);
            $this->assertNull($row->new_status);
        }
        $this->assertSame('Escalated to level 1: past its due time.', $rows[0]->details);
        $this->assertSame('Escalated to level 2: past its due time.', $rows[1]->details);
        $this->assertTrue($rows[1]->created_at->equalTo(now()));
    }

    public function test_escalation_never_changes_the_status_or_the_agent(): void
    {
        $agent = User::factory()->agent()->create();
        $ticket = $this->overdueTicket(['status' => TicketStatus::InProgress, 'agent_id' => $agent->id]);

        $this->service()->escalateOverdue();

        $fresh = $ticket->fresh();
        $this->assertSame(TicketStatus::InProgress, $fresh->status);
        $this->assertSame($agent->id, $fresh->agent_id);
    }

    public function test_the_escalated_event_is_dispatched_once_per_ticket_with_its_new_level(): void
    {
        Event::fake([TicketEscalated::class]);
        $first = $this->overdueTicket();
        $second = $this->overdueTicket(['escalation_level' => 1, 'escalated_at' => now()->subMinutes(20)]);

        $this->service()->escalateOverdue();

        Event::assertDispatchedTimes(TicketEscalated::class, 2);
        Event::assertDispatched(TicketEscalated::class, fn (TicketEscalated $event): bool => $event->ticket->is($first) && $event->level === 1);
        Event::assertDispatched(TicketEscalated::class, fn (TicketEscalated $event): bool => $event->ticket->is($second) && $event->level === 2);
    }

    public function test_no_event_is_dispatched_when_nothing_is_escalated(): void
    {
        Event::fake([TicketEscalated::class]);
        Ticket::factory()->create(['due_at' => now()->addHour()]);

        $this->service()->escalateOverdue();

        Event::assertNotDispatched(TicketEscalated::class);
    }

    public function test_the_event_is_dispatched_only_after_the_database_work_has_committed(): void
    {
        $this->overdueTicket();
        $levelWhenDispatched = null;
        Event::listen(TicketEscalated::class, function () use (&$levelWhenDispatched): void {
            $levelWhenDispatched = DB::transactionLevel();
        });

        $this->service()->escalateOverdue();

        // The test itself runs inside one wrapping transaction (level 1); the service's own transaction is over.
        $this->assertSame(1, $levelWhenDispatched);
    }

    public function test_if_the_history_cannot_be_written_no_ticket_is_escalated(): void
    {
        Event::fake([TicketEscalated::class]);
        $ticket = $this->overdueTicket();
        DB::listen(function ($query): void {
            if (str_starts_with(strtolower($query->sql), 'insert into `ticket_history`')) {
                throw new RuntimeException('History write failed.');
            }
        });

        try {
            $this->service()->escalateOverdue();
            $this->fail('The failure must reach the caller.');
        } catch (RuntimeException $exception) {
            $this->assertSame('History write failed.', $exception->getMessage());
        }

        $fresh = $ticket->fresh();
        $this->assertSame(0, $fresh->escalation_level);
        $this->assertNull($fresh->escalated_at);
        Event::assertNotDispatched(TicketEscalated::class);
    }

    // --- batches ---------------------------------------------------------------------------------------------

    public function test_many_tickets_are_escalated_in_batches_and_each_exactly_once(): void
    {
        config(['hdtms.escalation.batch_size' => 4]);
        $tickets = Ticket::factory()->count(11)->create(['due_at' => $this->due]);

        $this->assertSame(11, $this->service()->escalateOverdue());

        foreach ($tickets as $ticket) {
            $this->assertSame(1, $ticket->fresh()->escalation_level);
        }
        $this->assertSame(11, TicketHistory::query()->where('action', 'escalated')->count());
        $this->assertSame(0, $this->service()->escalateOverdue());
    }

    public function test_a_run_that_fills_a_batch_exactly_still_terminates(): void
    {
        config(['hdtms.escalation.batch_size' => 3]);
        Ticket::factory()->count(6)->create(['due_at' => $this->due]);

        $this->assertSame(6, $this->service()->escalateOverdue());
    }

    public function test_a_large_backlog_needs_a_handful_of_queries_not_one_per_ticket(): void
    {
        Ticket::factory()->count(40)->create(['due_at' => $this->due]);

        DB::enableQueryLog();
        $this->service()->escalateOverdue();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(12, $queries, "Escalating 40 tickets used {$queries} queries.");
    }

    public function test_an_explicit_time_can_be_given_so_a_run_can_be_replayed_for_a_moment_in_time(): void
    {
        $ticket = Ticket::factory()->create(['due_at' => $this->due->addDay()]);

        $this->assertSame(0, $this->service()->escalateOverdue());
        $this->assertSame(1, $this->service()->escalateOverdue($this->due->addDays(2)));
        $this->assertTrue($ticket->fresh()->escalated_at->equalTo($this->due->addDays(2)));
    }

    // --- who is told -------------------------------------------------------------------------------------------

    public function test_administrators_are_told_at_every_level_and_inactive_ones_are_not(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        User::factory()->admin()->inactive()->create();
        $ticket = $this->overdueTicket();

        foreach ([1, 2, 3, 9] as $level) {
            $this->assertEqualsCanonicalizing([$admin->id, $other->id], $this->service()->recipientsFor($ticket, $level)->pluck('id')->all(), "level {$level}");
        }
    }

    public function test_the_assigned_agent_is_told_from_level_two_and_not_at_level_one(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create();
        $ticket = $this->overdueTicket(['status' => TicketStatus::Assigned, 'agent_id' => $agent->id]);

        $this->assertSame([$admin->id], $this->service()->recipientsFor($ticket, 1)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$admin->id, $agent->id], $this->service()->recipientsFor($ticket, 2)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$admin->id, $agent->id], $this->service()->recipientsFor($ticket, 7)->pluck('id')->all());
    }

    public function test_an_unassigned_ticket_or_a_deactivated_agent_means_only_administrators_are_told(): void
    {
        $admin = User::factory()->admin()->create();
        $gone = User::factory()->agent()->inactive()->create();
        $unassigned = $this->overdueTicket();
        $orphaned = $this->overdueTicket(['status' => TicketStatus::Assigned, 'agent_id' => $gone->id]);

        $this->assertSame([$admin->id], $this->service()->recipientsFor($unassigned, 3)->pluck('id')->all());
        $this->assertSame([$admin->id], $this->service()->recipientsFor($orphaned, 3)->pluck('id')->all());
    }

    public function test_nobody_is_told_twice_and_a_requester_who_is_an_admin_is_still_an_admin_recipient_once(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = $this->overdueTicket(['requester_id' => $admin->id]);

        $this->assertSame([$admin->id], $this->service()->recipientsFor($ticket, 2)->pluck('id')->all());
    }
}
