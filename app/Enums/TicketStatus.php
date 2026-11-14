<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Assigned => 'Assigned',
            self::InProgress => 'In progress',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    /**
     * Hex colour of the status badge. Matches the tokens in resources/scss/_tokens.scss.
     */
    public function colour(): string
    {
        return match ($this) {
            self::Open => '#2563EB',
            self::Assigned => '#7C3AED',
            self::InProgress => '#D97706',
            self::Resolved => '#059669',
            self::Closed => '#6B7280',
        };
    }

    /**
     * The one place that defines which manual status changes are allowed.
     *
     * Open to Assigned is deliberately absent: a ticket only becomes assigned
     * through the assignment workflow, never through a status change.
     */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Assigned => $next === self::InProgress,
            self::InProgress => $next === self::Resolved,
            self::Resolved => $next === self::Closed || $next === self::InProgress,
            self::Open, self::Closed => false,
        };
    }

    /**
     * Statuses where the ticket still needs work from the support team.
     *
     * @return list<self>
     */
    public static function unresolved(): array
    {
        return [self::Open, self::Assigned, self::InProgress];
    }

    /**
     * Statuses that are finished from the SLA point of view.
     *
     * @return list<self>
     */
    public static function finished(): array
    {
        return [self::Resolved, self::Closed];
    }
}
