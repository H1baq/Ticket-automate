<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Events\TicketEscalated;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EscalationService
{
    /**
     * The assigned agent is told from this level on. Administrators are told at every level.
     */
    private const AGENT_NOTIFIED_FROM_LEVEL = 2;

    private const TRANSACTION_ATTEMPTS = 5;

    /**
     * Escalate every ticket that is unresolved past its due time and was not escalated in the last
     * 15 minutes: its level goes up by one, the time is recorded, a history row is written for the
     * system (no user), and TicketEscalated is dispatched.
     *
     * The work is done in batches. Each batch locks its tickets in id order and checks them again
     * under the lock, so two runs at the same moment can never escalate the same ticket twice.
     *
     * @return int How many tickets were escalated.
     */
    public function escalateOverdue(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $batchSize = max(1, (int) config('hdtms.escalation.batch_size'));
        $total = 0;

        do {
            $escalated = $this->escalateBatch($now, $batchSize);
            $total += $escalated->count();

            foreach ($escalated as $ticket) {
                TicketEscalated::dispatch($ticket, $ticket->escalation_level);
            }
        } while ($escalated->count() === $batchSize);

        return $total;
    }

    /**
     * Who is told about an escalation: every active administrator always, and the agent the ticket
     * is assigned to as well from level 2, when that agent is still active.
     *
     * @return Collection<int, User>
     */
    public function recipientsFor(Ticket $ticket, int $level): Collection
    {
        $recipients = User::query()->where('role', UserRole::Admin)->where('is_active', true)->orderBy('id')->get();

        if ($level >= self::AGENT_NOTIFIED_FROM_LEVEL && $ticket->agent_id !== null) {
            $agent = User::query()->activeAgents()->whereKey($ticket->agent_id)->first();

            if ($agent instanceof User) {
                $recipients->push($agent);
            }
        }

        return $recipients;
    }

    /**
     * @return Collection<int, Ticket> The tickets escalated in this batch, with their new level.
     */
    private function escalateBatch(CarbonInterface $now, int $batchSize): Collection
    {
        return DB::transaction(function () use ($now, $batchSize): Collection {
            $ids = Ticket::query()
                ->escalatable($now, (int) config('hdtms.escalation.interval_minutes'), (int) config('hdtms.escalation.max_level'))
                ->orderBy('id')
                ->limit($batchSize)
                ->lockForUpdate()
                ->pluck('id');

            if ($ids->isEmpty()) {
                return new Collection();
            }

            $stamp = $now->toDateTimeString();
            Ticket::query()->whereIn('id', $ids)->increment('escalation_level', 1, ['escalated_at' => $stamp, 'updated_at' => $stamp]);

            $tickets = Ticket::query()->with('agent')->whereIn('id', $ids)->orderBy('id')->get();

            TicketHistory::query()->insert($tickets->map(fn (Ticket $ticket): array => [
                'ticket_id' => $ticket->id,
                'changed_by' => null,
                'action' => 'escalated',
                'old_status' => null,
                'new_status' => null,
                'details' => "Escalated to level {$ticket->escalation_level}: past its due time.",
                'created_at' => $stamp,
            ])->all());

            return $tickets;
        }, self::TRANSACTION_ATTEMPTS);
    }
}
