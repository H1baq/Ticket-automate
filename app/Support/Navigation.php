<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\UserRole;

/**
 * The sidebar menu per role. A user only ever sees the items of their own role.
 */
final class Navigation
{
    /**
     * The page the topbar search box sends a query to. Each role searches its own list, so a search
     * never lands on a page the role may not open.
     */
    public static function searchRoute(UserRole $role): string
    {
        return match ($role) {
            UserRole::User => 'tickets.index',
            UserRole::Agent => 'agent.queue',
            UserRole::Admin => 'admin.tickets.index',
        };
    }

    /**
     * @return list<NavigationItem>
     */
    public static function for(UserRole $role): array
    {
        $dashboard = new NavigationItem('Dashboard', 'dashboard', 'home', ['dashboard'], 'dashboard');
        $notifications = new NavigationItem('Notifications', 'notifications.index', 'bell', ['notifications.*']);
        $newTicket = new NavigationItem('New ticket', 'tickets.create', 'plus', ['tickets.create'], 'new-ticket');

        return match ($role) {
            UserRole::User => [
                $dashboard,
                new NavigationItem('My tickets', 'tickets.index', 'ticket', ['tickets.index', 'tickets.show']),
                $newTicket,
                $notifications,
            ],
            UserRole::Agent => [
                $dashboard,
                new NavigationItem('My queue', 'agent.queue', 'inbox', ['agent.*']),
                new NavigationItem('My tickets', 'tickets.index', 'ticket', ['tickets.index']),
                $newTicket,
                $notifications,
            ],
            UserRole::Admin => [
                $dashboard,
                new NavigationItem('Assignment queue', 'admin.assignments.index', 'inbox', ['admin.assignments.*']),
                new NavigationItem('All tickets', 'admin.tickets.index', 'ticket', ['admin.tickets.*']),
                new NavigationItem('Reports', 'admin.reports.index', 'bar-chart', ['admin.reports.*']),
                new NavigationItem('My tickets', 'tickets.index', 'ticket', ['tickets.index']),
                $newTicket,
                new NavigationItem('Users', 'admin.users.index', 'users', ['admin.users.*'], null, 'System'),
                new NavigationItem('Categories', 'admin.categories.index', 'tag', ['admin.categories.*'], null, 'System'),
                new NavigationItem('SLA settings', 'admin.sla.edit', 'clock', ['admin.sla.*'], null, 'System'),
                new NavigationItem('Settings', 'admin.settings.index', 'sliders', ['admin.settings.*'], null, 'System'),
                $notifications,
            ],
        };
    }
}
