<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A ticket was assigned, or reassigned, to an agent. Dispatched after the transaction has committed.
 * $previousAgent is null the first time a ticket is assigned.
 */
class TicketAssigned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly User $agent,
        public readonly ?User $previousAgent,
        public readonly User $assignedBy,
    ) {
    }
}
