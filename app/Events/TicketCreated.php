<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A ticket was submitted and is saved. Dispatched after the database transaction has committed,
 * so a listener never sees a ticket that could still be rolled back.
 */
class TicketCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Ticket $ticket)
    {
    }
}
