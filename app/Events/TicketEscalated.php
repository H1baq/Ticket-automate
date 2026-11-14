<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A ticket passed its due time unresolved and was escalated. $level is the level it reached.
 * Who is told is decided by EscalationService::recipientsFor(): administrators always, and the
 * assigned agent too from level 2.
 */
class TicketEscalated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Ticket $ticket, public readonly int $level)
    {
    }
}
