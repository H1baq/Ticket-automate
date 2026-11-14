@props(['ticket', 'agents', 'compact' => false])
@php
    $reassigning = $ticket->agent_id !== null;
    $choices = $agents->reject(fn ($agent) => $agent->id === $ticket->agent_id);
@endphp
@if ($choices->isEmpty())
    <span class="text-muted small">No other active agent to choose.</span>
@else
    <form method="POST" action="{{ route('admin.tickets.assign', $ticket) }}" @class(['d-flex gap-1 align-items-start', 'flex-wrap' => ! $compact])>
        @csrf
        @method('PUT')
        <label class="visually-hidden" for="agent-{{ $ticket->id }}">Agent for {{ $ticket->ticket_no }}</label>
        <select id="agent-{{ $ticket->id }}" name="agent_id" class="form-select form-select-sm" required>
            <option value="">Choose an agent</option>
            @foreach ($choices as $agent)
                <option value="{{ $agent->id }}">{{ $agent->name }} · {{ $agent->open_tickets_count }} open</option>
            @endforeach
        </select>
        <x-button type="submit" size="sm" loading-text="Saving…">{{ $reassigning ? 'Reassign' : 'Assign' }}</x-button>
    </form>
@endif
