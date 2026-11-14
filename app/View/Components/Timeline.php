<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Models\TicketHistory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\Component;

class Timeline extends Component
{
    private const TITLES = [
        'created' => 'Ticket submitted',
        'assigned' => 'Assigned to an agent',
        'reassigned' => 'Reassigned to another agent',
        'status_changed' => 'Status changed',
        'resolved' => 'Marked as resolved',
        'reopened' => 'Reopened by the requester',
        'closed' => 'Closed',
        'auto_closed' => 'Closed automatically',
        'escalated' => 'Escalated',
    ];

    /**
     * @param  Collection<int, TicketHistory>  $history  Loaded with its changedBy relation.
     */
    public function __construct(public Collection $history)
    {
    }

    public function title(TicketHistory $entry): string
    {
        return self::TITLES[$entry->action] ?? Str::headline($entry->action);
    }

    public function render(): View
    {
        return view('components.timeline');
    }
}
