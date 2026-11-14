<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TicketPriority;

/**
 * Which tickets the assignment queue shows: one of three views, optionally narrowed by a search and a priority.
 */
final readonly class QueueFilters
{
    public const UNASSIGNED = 'unassigned';

    public const ASSIGNED = 'assigned';

    public const OVERDUE = 'overdue';

    public const VIEWS = [self::UNASSIGNED, self::ASSIGNED, self::OVERDUE];

    public function __construct(
        public string $view = self::UNASSIGNED,
        public ?string $search = null,
        public ?TicketPriority $priority = null,
    ) {
    }

    public function isNarrowed(): bool
    {
        return $this->search !== null || $this->priority instanceof TicketPriority;
    }
}
