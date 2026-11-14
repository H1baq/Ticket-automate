<x-layout.app :title="$ticket->ticket_no">
    <x-page-header
        :title="$ticket->title"
        :breadcrumbs="auth()->user()->can('viewAny', App\Models\Ticket::class)
            ? ['My tickets' => route('tickets.index'), $ticket->ticket_no => null]
            : ['Tickets' => null, $ticket->ticket_no => null]" />

    <div class="row g-3">
        <div class="col-lg-8">
            <x-card title="Description" class="mb-3">
                <div class="text-muted small mb-2">
                    Reported by {{ $ticket->requester->name }} on {{ $ticket->created_at->format('j M Y, H:i') }}
                </div>
                <div class="ticket-text">{{ $ticket->description }}</div>

                @if ($ticket->attachment_path)
                    <div class="mt-3 pt-3 border-top">
                        <a href="{{ route('tickets.attachment', $ticket) }}" class="d-inline-flex align-items-center gap-1">
                            <x-icon name="paperclip" /> Download attachment
                        </a>
                        <span class="text-muted small">({{ strtoupper(pathinfo($ticket->attachment_path, PATHINFO_EXTENSION)) }})</span>
                    </div>
                @endif
            </x-card>

            @if ($ticket->resolution)
                <x-card title="Resolution" class="mb-3">
                    <div class="ticket-text">{{ $ticket->resolution }}</div>
                    @if ($ticket->resolved_at)
                        <div class="text-muted small mt-2">Resolved on {{ $ticket->resolved_at->format('j M Y, H:i') }}</div>
                    @endif
                </x-card>
            @endif

            <x-card title="Conversation">
                @forelse ($ticket->responses as $response)
                    <div @class(['conversation-item', 'is-internal' => $response->is_internal])>
                        <div class="d-flex flex-wrap gap-2 align-items-baseline">
                            <strong>{{ $response->user->name }}</strong>
                            <span class="text-muted small">{{ $response->user->role->label() }} · {{ $response->created_at->format('j M Y, H:i') }}</span>
                            @if ($response->is_internal)
                                <span class="internal-note-label">Internal note</span>
                            @endif
                        </div>
                        <div class="ticket-text mt-1">{{ $response->message }}</div>
                    </div>
                @empty
                    <p class="text-muted mb-0">No replies yet. When an agent replies, you will see it here.</p>
                @endforelse
            </x-card>
        </div>

        <div class="col-lg-4">
            <x-card title="Status" class="mb-3">
                <div class="mb-2"><x-status-badge :status="$ticket->status" :overdue="$ticket->isOverdue()" /></div>
                <p class="text-muted mb-0">{{ $ticket->status->requesterHint($ticket->agent?->name) }}</p>
            </x-card>

            <x-card title="Details" class="mb-3">
                <dl class="detail-list mb-0">
                    <dt>Ticket</dt>
                    <dd><x-ticket-no :number="$ticket->ticket_no" /></dd>
                    <dt>Category</dt>
                    <dd>{{ $ticket->category->name }}</dd>
                    <dt>Priority</dt>
                    <dd><x-priority-badge :priority="$ticket->priority" /></dd>
                    <dt>Reported</dt>
                    <dd>{{ $ticket->created_at->format('j M Y, H:i') }}</dd>
                    <dt>Due</dt>
                    <dd @class(['text-overdue' => $ticket->isOverdue()])>{{ $ticket->due_at->format('j M Y, H:i') }}</dd>
                    <dt>Assigned to</dt>
                    <dd>{{ $ticket->agent?->name ?? 'Not assigned yet' }}</dd>
                    @if ($viewer->id !== $ticket->requester_id)
                        <dt>Reported by</dt>
                        <dd>{{ $ticket->requester->name }}@if ($ticket->requester->department), {{ $ticket->requester->department }}@endif</dd>
                    @endif
                </dl>
            </x-card>

            <x-card title="History">
                <x-timeline :history="$ticket->history" />
            </x-card>
        </div>
    </div>
</x-layout.app>
