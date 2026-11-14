<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;

/**
 * What a person asked for on a ticket list: a search, filters and a sort order.
 */
final readonly class TicketFilters
{
    public const SORTABLE = ['ticket_no', 'title', 'status', 'priority', 'created_at', 'due_at'];

    /**
     * @param  list<TicketStatus>  $statuses  Empty means every status.
     */
    public function __construct(
        public ?string $search = null,
        public array $statuses = [],
        public ?string $statusChoice = null,
        public ?TicketPriority $priority = null,
        public ?int $categoryId = null,
        public bool $overdueOnly = false,
        public string $sort = 'created_at',
        public string $direction = 'desc',
    ) {
    }

    /**
     * True when the list is narrowed in any way, so an empty result means "no match" and not "nothing yet".
     */
    public function isNarrowed(): bool
    {
        return $this->search !== null
            || $this->statuses !== []
            || $this->priority instanceof TicketPriority
            || $this->categoryId !== null
            || $this->overdueOnly;
    }
}
