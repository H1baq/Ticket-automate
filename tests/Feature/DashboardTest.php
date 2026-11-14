<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_visitor_is_sent_to_sign_in(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_staff_see_only_the_tickets_they_reported_and_a_red_overdue_count(): void
    {
        $me = User::factory()->create();
        Ticket::factory()->count(2)->for($me, 'requester')->create();
        Ticket::factory()->for($me, 'requester')->overdue()->create();
        Ticket::factory()->count(9)->overdue()->create();

        $html = $this->actingAs($me)->get('/dashboard')->assertOk()->assertSee('Reported by me')->assertSee('Overdue')->getContent();

        $this->assertStringContainsString('stat-tile is-link is-overdue', (string) $html);
        $this->assertStringContainsString('overdue=1', (string) $html);
        $this->assertSame(3, app(TicketService::class)->summaryFor($me)['unresolved']);
        $this->assertSame(1, app(TicketService::class)->summaryFor($me)['overdue']);
    }

    public function test_the_overdue_tile_is_not_red_when_nothing_is_overdue(): void
    {
        $me = User::factory()->create();
        Ticket::factory()->for($me, 'requester')->create();

        $this->actingAs($me)->get('/dashboard')->assertOk()->assertSee('Overdue')->assertDontSee('is-overdue', false);
    }

    public function test_staff_see_neither_the_agent_nor_the_administrator_sections(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertDontSee('Assigned to me')->assertDontSee('Needs attention');
    }

    public function test_an_agent_sees_what_is_assigned_to_them_apart_from_what_they_reported(): void
    {
        $agent = User::factory()->agent()->create();
        Ticket::factory()->count(3)->assigned($agent)->create();
        Ticket::factory()->overdue()->inProgress($agent)->create();
        Ticket::factory()->resolved($agent)->create();
        Ticket::factory()->assigned()->create();
        Ticket::factory()->for($agent, 'requester')->create();

        $this->actingAs($agent)->get('/dashboard')->assertOk()->assertSee('Assigned to me')->assertSee('Reported by me')->assertDontSee('Needs attention');

        $service = app(TicketService::class);
        $this->assertSame(['unresolved' => 4, 'overdue' => 1], $service->assignedSummaryFor($agent));
        $this->assertSame(1, $service->summaryFor($agent)['unresolved'], 'What an agent reported is counted separately.');
    }

    public function test_an_administrator_sees_the_queue_counts_with_links_into_the_queue(): void
    {
        $admin = User::factory()->admin()->create();
        Ticket::factory()->count(3)->create();
        Ticket::factory()->overdue()->count(2)->create();
        Ticket::factory()->count(4)->assigned()->create();

        $html = $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Needs attention')->assertSee('Unassigned')->getContent();

        $this->assertStringContainsString(route('admin.assignments.index'), (string) $html);
        $this->assertStringContainsString('view=overdue', (string) $html);
        $this->assertStringContainsString('view=assigned', (string) $html);
        $this->assertStringContainsString('is-overdue', (string) $html);
        $this->assertSame(['unassigned' => 5, 'assigned' => 4, 'overdue' => 2], app(AssignmentService::class)->queueCounts());
    }

    public function test_an_administrator_dashboard_has_no_agent_section_and_no_red_when_nothing_is_late(): void
    {
        $admin = User::factory()->admin()->create();
        Ticket::factory()->create();

        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertDontSee('Assigned to me')->assertDontSee('is-overdue', false);
    }

    public function test_every_role_keeps_the_account_summary(): void
    {
        foreach ([User::factory()->create(), User::factory()->agent()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Your account')->assertSee($user->email);
        }
    }

    public function test_the_dashboard_does_not_run_a_query_per_ticket(): void
    {
        $admin = User::factory()->admin()->create();
        Ticket::factory()->count(30)->overdue()->create();

        DB::enableQueryLog();
        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(16, $queries, "The dashboard used {$queries} queries.");
    }
}
