<?php

declare(strict_types=1);

namespace Tests\Feature\Escalation;

use App\Models\Ticket;
use App\Models\TicketHistory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Several escalation runs start at the same moment, as when a slow run overlaps the next one or someone
 * runs the command by hand while the schedule fires. Each overdue ticket must still be escalated exactly once.
 * Small batches make the runs interleave; real processes are used because only separate database
 * connections can race.
 */
class EscalationConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private const RUNNERS = 5;

    private const TICKETS = 60;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Parallel escalation needs a real database server (MySQL).');
        }
    }

    public function test_overlapping_runs_escalate_each_ticket_exactly_once(): void
    {
        $tickets = Ticket::factory()->count(self::TICKETS)->overdue()->create();

        $results = Process::pool(function (Pool $pool): void {
            for ($i = 1; $i <= self::RUNNERS; $i++) {
                $pool->as("run-{$i}")
                    ->path(base_path())
                    ->env([
                        'APP_ENV' => 'testing',
                        'DB_CONNECTION' => 'mysql',
                        'DB_DATABASE' => (string) config('database.connections.mysql.database'),
                        'DB_HOST' => (string) config('database.connections.mysql.host'),
                        'DB_PORT' => (string) config('database.connections.mysql.port'),
                        'DB_USERNAME' => (string) config('database.connections.mysql.username'),
                        'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
                        'CACHE_STORE' => 'array',
                        'QUEUE_CONNECTION' => 'sync',
                        'SESSION_DRIVER' => 'array',
                        'ESCALATION_BATCH_SIZE' => '7',
                    ])
                    ->timeout(120)
                    ->command([PHP_BINARY, 'artisan', 'tinker', '--execute=echo app(App\Services\EscalationService::class)->escalateOverdue();']);
            }
        })->start()->wait();

        $this->assertCount(self::RUNNERS, $results->collect());

        // Each run prints how many tickets it escalated. Anything that is not a plain number is an error message.
        $escalatedPerRun = [];
        foreach ($results->collect() as $name => $result) {
            $output = trim($result->output().$result->errorOutput());
            $this->assertMatchesRegularExpression('/^\d+$/', $output, "{$name} reported: {$output}");
            $escalatedPerRun[] = (int) $output;
        }

        $this->assertSame(self::TICKETS, array_sum($escalatedPerRun), 'Together the runs must escalate every ticket once, no more and no less.');

        foreach ($tickets as $ticket) {
            $this->assertSame(1, $ticket->fresh()->escalation_level, "{$ticket->ticket_no} was escalated the wrong number of times.");
        }
        $this->assertSame(self::TICKETS, TicketHistory::query()->where('action', 'escalated')->count());
        $this->assertSame(self::TICKETS, TicketHistory::query()->where('action', 'escalated')->distinct()->count('ticket_id'));
    }
}
