<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Events\TicketAssigned;
use App\Exceptions\AssignmentNotAllowed;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AssignmentService
{
    /**
     * Give a ticket to an agent, or move it to another one, at any status before closed.
     *
     * The first assignment also moves the ticket from open to assigned. A later one only changes the
     * agent and keeps the status. Everything is checked again under a lock on the ticket, so a ticket
     * that was closed, or an agent who was deactivated, since the page was shown is still refused.
     *
     * @throws AssignmentNotAllowed
     */
    public function assign(Ticket $ticket, User $agent, User $assignedBy): Ticket
    {
        $previousAgent = null;

        $assigned = DB::transaction(function () use ($ticket, $agent, $assignedBy, &$previousAgent): Ticket {
            $current = Ticket::query()->with('agent')->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            // Shared lock: the agent cannot be deactivated until this assignment has finished.
            $candidate = $this->validatedAgent($current, User::query()->whereKey($agent->id)->sharedLock()->first());
            $previousAgent = $current->agent;
            $wasOpen = $current->status === TicketStatus::Open;

            $current->agent_id = $candidate->id;
            if ($wasOpen) {
                $current->status = TicketStatus::Assigned;
            }
            $current->save();

            TicketHistory::query()->create([
                'ticket_id' => $current->id,
                'changed_by' => $assignedBy->id,
                'action' => $previousAgent instanceof User ? 'reassigned' : 'assigned',
                'old_status' => $wasOpen ? TicketStatus::Open : null,
                'new_status' => $wasOpen ? TicketStatus::Assigned : null,
                'details' => $previousAgent instanceof User
                    ? "Reassigned from {$previousAgent->name} to {$candidate->name}."
                    : "Assigned to {$candidate->name}.",
            ]);

            return $current->setRelation('agent', $candidate);
        });

        TicketAssigned::dispatch($assigned, $assigned->agent ?? $agent, $previousAgent, $assignedBy);

        return $assigned;
    }

    /**
     * Active agents with how many unresolved tickets each already has, fewest first, so the person
     * choosing sees who has room.
     *
     * @return Collection<int, User>
     */
    public function agentsWithOpenTickets(): Collection
    {
        return User::query()
            ->activeAgents()
            ->withCount(['assignedTickets as open_tickets_count' => fn ($tickets) => $tickets->open()])
            ->orderBy('open_tickets_count')
            ->orderBy('name')
            ->get();
    }

    /**
     * Check the ticket and the chosen agent, and return the agent when the assignment is allowed.
     *
     * @throws AssignmentNotAllowed
     */
    private function validatedAgent(Ticket $ticket, ?User $agent): User
    {
        if ($ticket->status === TicketStatus::Closed) {
            throw new AssignmentNotAllowed('Closed tickets cannot be assigned.');
        }

        if (! $agent instanceof User || ! $agent->is_active || $agent->role !== UserRole::Agent) {
            throw new AssignmentNotAllowed('Choose an active support agent.');
        }

        if ($agent->id === $ticket->requester_id) {
            throw new AssignmentNotAllowed('A ticket cannot be assigned to the person who reported it.');
        }

        if ($ticket->agent_id === $agent->id) {
            throw new AssignmentNotAllowed("This ticket is already assigned to {$agent->name}.");
        }

        return $agent;
    }
}
