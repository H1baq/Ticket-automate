<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Events\TicketCreated;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use App\Support\LikeSearch;
use App\Support\TicketFilters;
use BackedEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TicketService
{
    /**
     * Should MySQL still cancel a transaction as a deadlock victim, Laravel runs it again.
     */
    private const TRANSACTION_ATTEMPTS = 5;

    /**
     * Ticket numbers have five digits (HD-2026-00001), so a year holds 99,999 tickets.
     */
    private const MAX_SEQUENCE = 99999;

    private const ATTACHMENT_DISK = 'local';

    /**
     * Submit a ticket for a requester.
     *
     * The number, due time, ticket row and "created" history row are written in one transaction. The
     * attachment is stored first, privately, and removed again if the database work fails.
     *
     * @param  array{title: string, description: string, category_id: int, priority: TicketPriority}  $data
     */
    public function create(User $requester, array $data, ?UploadedFile $attachment = null): Ticket
    {
        $attachmentPath = $attachment instanceof UploadedFile ? $this->storeAttachment($attachment) : null;

        try {
            $ticket = DB::transaction(
                fn (): Ticket => $this->insertTicket($requester, $data, $attachmentPath),
                self::TRANSACTION_ATTEMPTS,
            );
        } catch (Throwable $failure) {
            if ($attachmentPath !== null) {
                Storage::disk(self::ATTACHMENT_DISK)->delete($attachmentPath);
            }

            throw $failure;
        }

        TicketCreated::dispatch($ticket);

        return $ticket;
    }

    /**
     * @param  array{title: string, description: string, category_id: int, priority: TicketPriority}  $data
     */
    private function insertTicket(User $requester, array $data, ?string $attachmentPath): Ticket
    {
        $priority = $data['priority'];

        $policies = $this->lockSlaPolicies();

        $ticket = new Ticket([
            'ticket_no' => $this->nextTicketNumber(),
            'requester_id' => $requester->id,
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'priority' => $priority,
            'status' => TicketStatus::Open,
            'attachment_path' => $attachmentPath,
            'due_at' => now()->addHours($this->resolutionHours($policies, $priority)),
        ]);
        $ticket->save();

        TicketHistory::query()->create([
            'ticket_id' => $ticket->id,
            'changed_by' => $requester->id,
            'action' => 'created',
            'old_status' => null,
            'new_status' => TicketStatus::Open,
            'details' => "Ticket submitted with {$priority->value} priority.",
        ]);

        return $ticket;
    }

    /**
     * Take the lock that makes ticket creation one-at-a-time, and read the SLA policies under it.
     *
     * Locking only the newest ticket number is not enough: when several people submit at once, InnoDB's
     * gap locks make them deadlock, and a test with parallel processes proved it. So everyone first locks
     * the same rows in the same order (the SLA policies, which creation needs anyway for the due time).
     * Only one transaction gets past this point at a time, and the same order can never deadlock.
     * Anything that updates SLA policies must also lock them in id order.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SlaPolicy>
     */
    private function lockSlaPolicies(): \Illuminate\Database\Eloquent\Collection
    {
        return SlaPolicy::query()->orderBy('id')->lockForUpdate()->get();
    }

    /**
     * The next number for the current year, for example HD-2026-00142.
     *
     * Called while holding the SLA lock, so nobody else is numbering a ticket at the same moment. The
     * newest number of the year is read with a lock to see every committed ticket, and the unique index
     * on ticket_no is the final safety net.
     */
    private function nextTicketNumber(): string
    {
        $prefix = 'HD-'.now()->year.'-';

        $latest = Ticket::query()
            ->where('ticket_no', 'like', $prefix.'%')
            ->orderByDesc('ticket_no')
            ->lockForUpdate()
            ->value('ticket_no');

        $sequence = is_string($latest) ? (int) substr($latest, strlen($prefix)) + 1 : 1;

        if ($sequence > self::MAX_SEQUENCE) {
            throw new RuntimeException("Ticket numbers for {$prefix} are used up: a year holds ".self::MAX_SEQUENCE.' tickets.');
        }

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, SlaPolicy>  $policies
     */
    private function resolutionHours(\Illuminate\Database\Eloquent\Collection $policies, TicketPriority $priority): int
    {
        $policy = $policies->first(fn (SlaPolicy $candidate): bool => $candidate->priority === $priority);

        if (! $policy instanceof SlaPolicy) {
            throw new RuntimeException("No SLA policy is set for {$priority->value} priority.");
        }

        return $policy->resolution_hours;
    }

    /**
     * Store the file on the private disk under a random name. The extension comes from the file's
     * content, not from the name the browser sent.
     */
    private function storeAttachment(UploadedFile $file): string
    {
        $name = Str::random(40).'.'.($file->guessExtension() ?? 'bin');

        $path = $file->storeAs('tickets/'.now()->format('Y/m'), $name, self::ATTACHMENT_DISK);

        if ($path === false) {
            throw new RuntimeException('The attachment could not be saved.');
        }

        return $path;
    }

    /**
     * One page of a requester's own tickets, narrowed and sorted as asked. Only the requester's tickets
     * are ever queried, so no filter can reach anyone else's.
     *
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function paginateForRequester(User $requester, TicketFilters $filters, int $perPage): LengthAwarePaginator
    {
        $query = Ticket::query()->forRequester($requester)->with('category');

        if ($filters->search !== null) {
            LikeSearch::apply($query, ['ticket_no', 'title'], $filters->search);
        }
        if ($filters->statuses !== []) {
            $query->whereIn('status', array_map(fn (TicketStatus $status): string => $status->value, $filters->statuses));
        }
        if ($filters->priority instanceof TicketPriority) {
            $query->where('priority', $filters->priority);
        }
        if ($filters->categoryId !== null) {
            $query->where('category_id', $filters->categoryId);
        }
        if ($filters->overdueOnly) {
            $query->overdue();
        }

        $this->applySort($query, $filters);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * The numbers on the staff member's list header, counted by the database.
     *
     * @return array{unresolved: int, overdue: int, resolved: int, closed: int}
     */
    public function summaryFor(User $requester): array
    {
        /** @var \Illuminate\Support\Collection<string, int> $perStatus */
        $perStatus = Ticket::query()->forRequester($requester)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $unresolved = 0;
        foreach (TicketStatus::unresolved() as $status) {
            $unresolved += (int) ($perStatus[$status->value] ?? 0);
        }

        return [
            'unresolved' => $unresolved,
            'overdue' => Ticket::query()->forRequester($requester)->overdue()->count(),
            'resolved' => (int) ($perStatus[TicketStatus::Resolved->value] ?? 0),
            'closed' => (int) ($perStatus[TicketStatus::Closed->value] ?? 0),
        ];
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    private function applySort(Builder $query, TicketFilters $filters): void
    {
        $direction = $filters->direction === 'asc' ? 'asc' : 'desc';

        // Status and priority sort by meaning (workflow order, severity), not alphabetically. The CASE
        // expressions are built only from enum values defined in code, never from request data.
        match ($filters->sort) {
            'status' => $query->orderByRaw($this->caseOrder('status', TicketStatus::cases()).' '.$direction),
            'priority' => $query->orderByRaw($this->caseOrder('priority', [TicketPriority::Low, TicketPriority::Medium, TicketPriority::High]).' '.$direction),
            'ticket_no', 'title', 'due_at', 'created_at' => $query->orderBy($filters->sort, $direction),
            default => $query->orderBy('created_at', 'desc'),
        };

        $query->orderByDesc('id');
    }

    /**
     * @param  list<BackedEnum>  $cases  In ascending order of meaning.
     */
    private function caseOrder(string $column, array $cases): string
    {
        $branches = '';
        foreach ($cases as $position => $case) {
            $branches .= " WHEN '{$case->value}' THEN {$position}";
        }

        return "CASE {$column}{$branches} END";
    }
}
