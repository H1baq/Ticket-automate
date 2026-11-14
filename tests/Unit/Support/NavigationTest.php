<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Enums\UserRole;
use App\Support\Navigation;
use App\Support\NavigationItem;
use PHPUnit\Framework\TestCase;

class NavigationTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function routes(UserRole $role): array
    {
        return array_map(fn (NavigationItem $item): string => $item->route, Navigation::for($role));
    }

    public function test_staff_users_see_only_their_own_pages(): void
    {
        $routes = $this->routes(UserRole::User);

        $this->assertContains('tickets.index', $routes);
        $this->assertContains('tickets.create', $routes);
        $this->assertEmpty(array_filter($routes, fn (string $route): bool => str_starts_with($route, 'admin.')));
        $this->assertNotContains('agent.queue', $routes);
    }

    public function test_agents_see_their_queue_and_no_admin_pages(): void
    {
        $routes = $this->routes(UserRole::Agent);

        $this->assertContains('agent.queue', $routes);
        $this->assertContains('tickets.index', $routes);
        $this->assertContains('tickets.create', $routes);
        $this->assertEmpty(array_filter($routes, fn (string $route): bool => str_starts_with($route, 'admin.')));
    }

    public function test_admins_see_assignment_reports_and_system_pages(): void
    {
        $routes = $this->routes(UserRole::Admin);

        foreach (['admin.assignments.index', 'admin.tickets.index', 'admin.reports.index', 'admin.users.index', 'admin.categories.index', 'admin.sla.edit', 'admin.settings.index'] as $route) {
            $this->assertContains($route, $routes);
        }
        $this->assertContains('tickets.index', $routes);
        $this->assertContains('tickets.create', $routes);
        $this->assertNotContains('agent.queue', $routes);
    }

    public function test_every_role_can_report_a_problem_with_the_n_shortcut(): void
    {
        foreach (UserRole::cases() as $role) {
            $shortcuts = array_filter(array_map(fn (NavigationItem $item): ?string => $item->shortcut, Navigation::for($role)));

            $this->assertContains('new-ticket', $shortcuts, $role->value);
            $this->assertContains('dashboard', $shortcuts);
        }
    }

    public function test_the_my_tickets_item_is_only_current_on_the_list_for_agents_and_admins(): void
    {
        $patterns = fn (UserRole $role): array => collect(Navigation::for($role))->firstWhere('route', 'tickets.index')->activeOn;

        $this->assertContains('tickets.show', $patterns(UserRole::User));
        $this->assertNotContains('tickets.show', $patterns(UserRole::Agent), 'An agent opening a ticket assigned to them is not on their own list.');
        $this->assertNotContains('tickets.show', $patterns(UserRole::Admin));
    }
}
