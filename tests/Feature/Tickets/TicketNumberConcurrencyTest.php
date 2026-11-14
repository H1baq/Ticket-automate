<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SlaPolicySeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Real parallel PHP processes submit tickets at the same moment. Each one is its own database
 * connection, so this exercises the number lock the way simultaneous staff would, which a loop cannot.
 *
 * It needs committed data, so it migrates the test database itself instead of using a wrapping transaction.
 */
class TicketNumberConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private const PROCESSES = 6;

    private const TICKETS_PER_PROCESS = 8;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Parallel numbering needs a real database server (MySQL).');
        }
    }

    public function test_simultaneous_submissions_never_share_a_ticket_number(): void
    {
        $this->seed([CategorySeeder::class, SlaPolicySeeder::class]);
        $requester = User::factory()->create();

        $script = sprintf(
            'for ($i = 0; $i < %d; $i++) { app(App\Services\TicketService::class)->create(App\Models\User::findOrFail(%d), ["title" => "Parallel ticket", "description" => "Created by a parallel process.", "category_id" => %d, "priority" => App\Enums\TicketPriority::Medium]); }',
            self::TICKETS_PER_PROCESS,
            $requester->id,
            \App\Models\Category::query()->value('id'),
        );

        $results = Process::pool(function (Pool $pool) use ($script): void {
            for ($i = 1; $i <= self::PROCESSES; $i++) {
                $pool->as("worker-{$i}")
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

        $this->assertCount(self::PROCESSES, $results->collect(), 'Every worker must have run.');
        foreach ($results->collect() as $name => $result) {
            // tinker exits with 0 even when the script throws, so any output at all means something went wrong.
            $this->assertSame('', trim($result->output().$result->errorOutput()), "{$name} reported an error.");
        }

        $expected = self::PROCESSES * self::TICKETS_PER_PROCESS;
        $numbers = Ticket::query()->orderBy('ticket_no')->pluck('ticket_no')->all();

        $this->assertCount($expected, $numbers, 'Some submissions were lost.');
        $this->assertCount($expected, array_unique($numbers), 'Two tickets got the same number.');
        $this->assertSame(
            array_map(fn (int $n): string => sprintf('HD-%d-%05d', now()->year, $n), range(1, $expected)),
            $numbers,
            'Numbers must be consecutive with no gaps.',
        );
    }
}
