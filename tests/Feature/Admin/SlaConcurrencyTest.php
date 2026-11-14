<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SlaPolicySeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * People report tickets while an administrator saves the SLA settings, all at the same moment, in separate
 * PHP processes with their own database connections.
 *
 * Ticket creation and the SLA settings both lock the SLA rows, so they must take those locks in the same
 * order. If either took them in a different order the two would wait for each other and MySQL would cancel
 * one of them as a deadlock victim; this test fails when that happens.
 */
class SlaConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private const CREATORS = 4;

    private const TICKETS_PER_CREATOR = 10;

    private const SAVERS = 3;

    private const SAVES_PER_SAVER = 12;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Lock ordering needs a real database server (MySQL).');
        }
    }

    /**
     * @return array<string, string>
     */
    private function environment(): array
    {
        return [
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
        ];
    }

    public function test_saving_sla_settings_while_tickets_are_being_created_never_deadlocks(): void
    {
        $this->seed([CategorySeeder::class, SlaPolicySeeder::class]);
        $reporter = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $categoryId = Category::query()->value('id');

        $create = sprintf(
            'for ($i = 0; $i < %d; $i++) { app(App\Services\TicketService::class)->create(App\Models\User::findOrFail(%d), ["title" => "Created during a save", "description" => "Created by a parallel process.", "category_id" => %d, "priority" => App\Enums\TicketPriority::Medium]); }',
            self::TICKETS_PER_CREATOR,
            $reporter->id,
            $categoryId,
        );

        $results = Process::pool(function (Pool $pool) use ($create, $admin): void {
            for ($i = 1; $i <= self::CREATORS; $i++) {
                $pool->as("creator-{$i}")->path(base_path())->env($this->environment())->timeout(180)
                    ->command([PHP_BINARY, 'artisan', 'tinker', '--execute='.$create]);
            }

            for ($i = 1; $i <= self::SAVERS; $i++) {
                // Medium goes between 8 and 12 hours, so every ticket's due time must fall in that range.
                $save = sprintf(
                    'for ($i = 0; $i < %d; $i++) { app(App\Services\SlaService::class)->update(["high" => 2 + (($i + %d) %% 3), "medium" => 8 + (($i + %d) %% 5), "low" => 24 + (($i + %d) %% 4)], App\Models\User::findOrFail(%d)); }',
                    self::SAVES_PER_SAVER,
                    $i,
                    $i,
                    $i,
                    $admin->id,
                );

                $pool->as("saver-{$i}")->path(base_path())->env($this->environment())->timeout(180)
                    ->command([PHP_BINARY, 'artisan', 'tinker', '--execute='.$save]);
            }
        })->start()->wait();

        $this->assertCount(self::CREATORS + self::SAVERS, $results->collect(), 'Every process must have run.');
        // tinker exits with 0 even when its script throws, so any output at all means something went wrong,
        // for example "Deadlock found when trying to get lock".
        foreach ($results->collect() as $name => $result) {
            $this->assertSame('', trim($result->output().$result->errorOutput()), "{$name} reported an error.");
        }

        $expected = self::CREATORS * self::TICKETS_PER_CREATOR;
        $tickets = Ticket::query()->orderBy('ticket_no')->get();

        $this->assertCount($expected, $tickets, 'Some tickets were lost.');
        $this->assertSame(
            array_map(fn (int $n): string => sprintf('HD-%d-%05d', now()->year, $n), range(1, $expected)),
            $tickets->pluck('ticket_no')->all(),
            'Ticket numbers must be consecutive with no gaps or repeats.',
        );

        foreach ($tickets as $ticket) {
            $hours = (int) round($ticket->created_at->diffInSeconds($ticket->due_at) / 3600);
            $this->assertGreaterThanOrEqual(8, $hours, "{$ticket->ticket_no} has a due time shorter than any saved setting.");
            $this->assertLessThanOrEqual(12, $hours, "{$ticket->ticket_no} has a due time longer than any saved setting.");
        }

        $this->assertDatabaseCount('sla_policies', 3);
    }
}
