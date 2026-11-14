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
        $this->assertNotContains('tickets.create', $routes);
        $this->assertEmpty(array_filter($routes, fn (string $route): bool => str_starts_with($route, 'admin.')));
    }

    public function test_admins_see_assignment_reports_and_system_pages(): void
    {
        $routes = $this->routes(UserRole::Admin);

        foreach (['admin.assignments.index', 'admin.tickets.index', 'admin.reports.index', 'admin.users.index', 'admin.categories.index', 'admin.sla.edit', 'admin.settings.index'] as $route) {
            $this->assertContains($route, $routes);
        }
        $this->assertNotContains('tickets.create', $routes);
    }

    public function test_only_staff_have_the_new_ticket_shortcut(): void
    {
        foreach (UserRole::cases() as $role) {
            $shortcuts = array_filter(array_map(fn (NavigationItem $item): ?string => $item->shortcut, Navigation::for($role)));

            $this->assertSame($role === UserRole::User, in_array('new-ticket', $shortcuts, true), $role->value);
            $this->assertContains('dashboard', $shortcuts);
        }
    }
}
