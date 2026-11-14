<?php

declare(strict_types=1);

namespace Tests\Feature\Assignment;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Several administrators assign the same open ticket at the same moment, each to a different agent. Real
 * parallel PHP processes are used because only separate database connections can race.
 *
 * With the lock the assignments happen one after another: one first assignment and then reassignments,
 * each starting from the agent the previous one chose. Without it two of them could both see the ticket as
 * open and both record a "first" assignment.
 */
class AssignmentConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private const ADMINS = 6;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Parallel assignment needs a real database server (MySQL).');
        }
    }

    public function test_simultaneous_assignments_are_applied_one_at_a_time(): void
    {
        $ticket = Ticket::factory()->create();
        $admin = User::factory()->admin()->create();
        $agents = User::factory()->agent()->count(self::ADMINS)->create();

        $results = Process::pool(function (Pool $pool) use ($ticket, $admin, $agents): void {
            foreach ($agents as $index => $agent) {
                $script = sprintf(
                    'app(App\Services\AssignmentService::class)->assign(App\Models\Ticket::findOrFail(%d), App\Models\User::findOrFail(%d), App\Models\User::findOrFail(%d));',
                    $ticket->id,
                    $agent->id,
                    $admin->id,
                );

                $pool->as('admin-'.($index + 1))
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
                    ])
                    ->timeout(120)
                    ->command([PHP_BINARY, 'artisan', 'tinker', '--execute='.$script]);
            }
        })->start()->wait();

        // tinker exits with 0 even when the script throws, so any output at all means something went wrong.
        foreach ($results->collect() as $name => $result) {
            $this->assertSame('', trim($result->output().$result->errorOutput()), "{$name} reported an error.");
        }

        $history = TicketHistory::query()->where('ticket_id', $ticket->id)->orderBy('id')->get();

        $this->assertCount(self::ADMINS, $history, 'Every assignment must be recorded.');
        $this->assertSame(['assigned'], $history->where('action', 'assigned')->pluck('action')->unique()->values()->all());
        $this->assertSame(1, $history->where('action', 'assigned')->count(), 'Only one assignment can be the first.');
        $this->assertSame('assigned', $history->first()->action);
        $this->assertSame(self::ADMINS - 1, $history->where('action', 'reassigned')->count());

        // Each reassignment must start from the agent the one before it chose: no assignment was lost or overwritten.
        $previousName = null;
        foreach ($history as $row) {
            if ($previousName !== null) {
                $this->assertStringStartsWith("Reassigned from {$previousName} to ", (string) $row->details);
            }
            $previousName = (string) str((string) $row->details)->afterLast(' to ')->beforeLast('.');
        }

        $this->assertSame(TicketStatus::Assigned, $ticket->fresh()->status);
        $this->assertSame($previousName, $agents->firstWhere('id', $ticket->fresh()->agent_id)?->name, 'The ticket belongs to the agent of the last assignment.');
    }
}
