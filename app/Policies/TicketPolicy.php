<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;

/**
 * Who may see a ticket: the person who reported it, the agent it is assigned to, and administrators.
 * Everyone else, and anyone whose account is deactivated, is refused. Every active user may report
 * problems of their own, so every role has a "My tickets" list of what they reported themselves.
 */
class TicketPolicy
{
    /**
     * The list of tickets a person reported themselves.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Anyone with an active account may report a problem of their own. Raising a ticket on behalf of
     * somebody else is not part of this release.
     */
    public function create(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Viewing a ticket also covers its attachment, the conversation and the history.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        if (! $user->is_active) {
            return false;
        }


        if ($ticket->requester_id === $user->id) {
            return true;
        }

        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Agent => $ticket->agent_id === $user->id,
            UserRole::User => false,
        };
    }
}
