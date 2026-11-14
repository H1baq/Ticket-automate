<?php

declare(strict_types=1);

namespace Tests\Feature\Escalation;

use App\Models\Ticket;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EscalationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_command_escalates_overdue_tickets_and_reports_how_many(): void
    {
        Ticket::factory()->count(2)->overdue()->create();
        $onTime = Ticket::factory()->create(['due_at' => now()->addHours(3)]);

        $this->artisan('tickets:escalate-overdue')->expectsOutput('Escalated 2 overdue tickets.')->assertSuccessful();

        $this->assertSame(2, Ticket::query()->where('escalation_level', 1)->count());
        $this->assertSame(0, $onTime->fresh()->escalation_level);
    }

    public function test_the_message_uses_the_singular_for_one_ticket(): void
    {
        Ticket::factory()->overdue()->create();

        $this->artisan('tickets:escalate-overdue')->expectsOutput('Escalated 1 overdue ticket.')->assertSuccessful();
    }

    public function test_the_command_says_so_when_there_is_nothing_to_do_and_still_succeeds(): void
    {
        $this->artisan('tickets:escalate-overdue')->expectsOutput('No overdue tickets to escalate.')->assertSuccessful();
    }

    public function test_running_the_command_twice_in_a_row_escalates_only_once(): void
    {
        $ticket = Ticket::factory()->overdue()->create();

        $this->artisan('tickets:escalate-overdue')->assertSuccessful();
        $this->artisan('tickets:escalate-overdue')->expectsOutput('No overdue tickets to escalate.')->assertSuccessful();

        $this->assertSame(1, $ticket->fresh()->escalation_level);
    }

    private function scheduled(): ScheduledEvent
    {
        $events = collect(app(Schedule::class)->events())->filter(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'tickets:escalate-overdue'));

        $this->assertCount(1, $events, 'The command must be scheduled exactly once.');

        return $events->first();
    }

    public function test_the_command_is_scheduled_every_fifteen_minutes(): void
    {
        $this->assertSame('*/15 * * * *', $this->scheduled()->expression);
    }

    public function test_the_schedule_never_lets_two_runs_overlap(): void
    {
        $this->assertTrue($this->scheduled()->withoutOverlapping);
    }

    public function test_a_crashed_run_cannot_block_escalation_for_a_whole_day(): void
    {
        // The default lock lasts 24 hours. A short one means the next cycles resume soon after a crash.
        $this->assertSame(10, $this->scheduled()->expiresAt);
    }
}
