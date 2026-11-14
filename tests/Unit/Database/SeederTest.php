<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Notification;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\SlaPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_seeder_creates_the_six_default_categories_once(): void
    {
        $this->seed(CategorySeeder::class);
        Category::query()->where('name', 'Network')->update(['description' => 'Edited by an admin']);
        $this->seed(CategorySeeder::class);

        $this->assertEqualsCanonicalizing(
            ['Hardware', 'Software', 'Network', 'Email and Accounts', 'Printing', 'Other'],
            Category::query()->pluck('name')->all(),
        );
        $this->assertSame('Edited by an admin', Category::query()->where('name', 'Network')->value('description'));
    }

    public function test_sla_seeder_sets_four_eight_and_twenty_four_hours(): void
    {
        $this->seed(SlaPolicySeeder::class);

        $hours = SlaPolicy::query()->get()->mapWithKeys(
            fn (SlaPolicy $policy): array => [$policy->priority->value => $policy->resolution_hours],
        );

        $this->assertSame(['high' => 4, 'medium' => 8, 'low' => 24], $hours->all());
    }

    public function test_admin_seeder_creates_the_admin_from_the_environment_once(): void
    {
        config(['hdtms.seed_admin' => ['email' => 'head.ict@example.test', 'password' => 'a-strong-secret']]);

        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class);

        $admin = User::query()->where('email', 'head.ict@example.test')->sole();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('a-strong-secret', $admin->password));
        $this->assertSame(1, User::query()->count());
    }

    public function test_admin_seeder_refuses_to_run_without_credentials(): void
    {
        config(['hdtms.seed_admin' => ['email' => null, 'password' => null]]);

        $this->expectException(RuntimeException::class);

        $this->seed(AdminSeeder::class);
    }

    public function test_admin_seeder_refuses_a_short_password(): void
    {
        config(['hdtms.seed_admin' => ['email' => 'head.ict@example.test', 'password' => 'short']]);

        $this->expectException(RuntimeException::class);

        $this->seed(AdminSeeder::class);
    }

    public function test_demo_seeder_builds_a_realistic_dataset(): void
    {
        config(['hdtms.seed_admin' => ['email' => 'head.ict@example.test', 'password' => 'a-strong-secret']]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(150, Ticket::query()->count());
        $this->assertSame(1, User::query()->where('role', UserRole::Admin)->count());
        $this->assertSame(5, User::query()->where('role', UserRole::Agent)->count());
        $this->assertSame(24, User::query()->where('role', UserRole::User)->count());

        foreach (TicketStatus::cases() as $status) {
            $this->assertGreaterThan(0, Ticket::query()->where('status', $status)->count(), "No {$status->value} tickets");
        }
        foreach (TicketPriority::cases() as $priority) {
            $this->assertGreaterThan(0, Ticket::query()->where('priority', $priority)->count(), "No {$priority->value} tickets");
        }
        $this->assertGreaterThan(0, Ticket::query()->overdue()->count());
        $this->assertGreaterThan(0, Ticket::query()->where('escalation_level', '>', 0)->count());
        $this->assertGreaterThan(0, Notification::query()->count());
    }

    public function test_demo_tickets_are_internally_consistent(): void
    {
        config(['hdtms.seed_admin' => ['email' => 'head.ict@example.test', 'password' => 'a-strong-secret']]);
        $this->seed(DatabaseSeeder::class);

        $tickets = Ticket::query()->with(['history', 'agent', 'category'])->get();

        $this->assertSame($tickets->count(), $tickets->pluck('ticket_no')->unique()->count());

        foreach ($tickets as $ticket) {
            $this->assertMatchesRegularExpression('/^HD-\d{4}-\d{5}$/', $ticket->ticket_no);
            $this->assertSame($ticket->created_at->year, (int) substr($ticket->ticket_no, 3, 4));
            $this->assertTrue($ticket->due_at > $ticket->created_at);
            $this->assertTrue($ticket->category->is_active);
            $this->assertContains('created', $ticket->history->pluck('action')->all());
            $this->assertSame($ticket->status === TicketStatus::Open, $ticket->agent_id === null);
            $this->assertSame(in_array($ticket->status, TicketStatus::finished(), true), $ticket->resolved_at !== null);
            $this->assertSame($ticket->status === TicketStatus::Closed, $ticket->closed_at !== null);
            $this->assertTrue($ticket->created_at->lte(now()), "{$ticket->ticket_no} is created in the future");

            foreach ($ticket->history as $row) {
                $this->assertTrue($row->created_at->gte($ticket->created_at), "{$ticket->ticket_no} has history before it was created");
                $this->assertTrue($row->created_at->lte(now()), "{$ticket->ticket_no} has history in the future");
            }
        }
    }

    public function test_demo_ticket_numbers_follow_creation_order_within_a_year(): void
    {
        config(['hdtms.seed_admin' => ['email' => 'head.ict@example.test', 'password' => 'a-strong-secret']]);
        $this->seed(DatabaseSeeder::class);

        $tickets = Ticket::query()->orderBy('created_at')->get(['ticket_no', 'created_at']);

        foreach ($tickets->groupBy(fn (Ticket $ticket): int => $ticket->created_at->year) as $inYear) {
            $numbers = $inYear->map(fn (Ticket $ticket): int => (int) substr($ticket->ticket_no, -5))->all();
            $this->assertSame(range(1, count($numbers)), $numbers);
        }
    }

    public function test_demo_history_records_the_system_escalations(): void
    {
        config(['hdtms.seed_admin' => ['email' => 'head.ict@example.test', 'password' => 'a-strong-secret']]);
        $this->seed(DatabaseSeeder::class);

        $escalations = TicketHistory::query()->where('action', 'escalated')->get();

        $this->assertGreaterThan(0, $escalations->count());
        $this->assertTrue($escalations->every(fn (TicketHistory $row): bool => $row->changed_by === null));
    }

    public function test_demo_seeder_never_runs_in_production(): void
    {
        config(['hdtms.seed_admin' => ['email' => 'head.ict@example.test', 'password' => 'a-strong-secret']]);
        $this->app['env'] = 'production';

        $this->app->make(DemoSeeder::class)->run();

        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame(0, User::query()->count());
    }

    public function test_demo_seeder_is_skipped_when_demo_data_already_exists(): void
    {
        config(['hdtms.seed_admin' => ['email' => 'head.ict@example.test', 'password' => 'a-strong-secret']]);
        $this->seed(DatabaseSeeder::class);

        $this->seed(DemoSeeder::class);

        $this->assertSame(150, Ticket::query()->count());
    }
}
