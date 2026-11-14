<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;

/**
 * Who may see a ticket: the person who reported it, the agent it is assigned to, and administrators.
 * Everyone else, and anyone whose account is deactivated, is refused. Staff see only their own tickets.
 */
class TicketPolicy
{
    /**
     * Only staff users report problems. Agents and administrators work on tickets, they do not submit them.
     */
    public function create(User $user): bool
    {
        return $user->is_active && $user->role === UserRole::User;
    }

    /**
     * Viewing a ticket also covers its attachment, the conversation and the history.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Agent => $ticket->agent_id === $user->id,
            UserRole::User => $ticket->requester_id === $user->id,
        };
    }
}
