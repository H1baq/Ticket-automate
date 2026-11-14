<x-layout.app title="Assignment queue">
    <x-page-header title="Assignment queue" :breadcrumbs="['Administration' => null, 'Assignment queue' => null]" />

    <nav class="queue-tabs mb-3" aria-label="Queue views">
        @foreach (['unassigned' => 'Unassigned', 'assigned' => 'Assigned', 'overdue' => 'Overdue'] as $view => $label)
            <a href="{{ route('admin.assignments.index', ['view' => $view]) }}"
               @class(['queue-tab', 'is-active' => $filters->view === $view, 'is-overdue' => $view === 'overdue' && $counts['overdue'] > 0])
               @if ($filters->view === $view) aria-current="page" @endif>
                {{ $label }} <span class="queue-count">{{ $counts[$view] }}</span>
            </a>
        @endforeach
    </nav>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($agents->isEmpty())
        <div class="alert alert-warning" role="status">
            There are no active support agents to assign tickets to.
            <a href="{{ route('admin.users.index', ['role' => 'agent']) }}">Create or reactivate an agent under Users.</a>
        </div>
    @endif

    <x-data-table
        caption="Tickets in the assignment queue, the most urgent first"
        :columns="[
            ['key' => 'ticket_no', 'label' => 'Ticket'],
            ['key' => 'title', 'label' => 'Title'],
            ['key' => 'priority', 'label' => 'Priority'],
            ['key' => 'requester', 'label' => 'Reported by'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'due_at', 'label' => 'Due'],
            ['key' => 'agent', 'label' => $filters->view === 'unassigned' ? 'Assign to' : 'Agent'],
        ]"
        :is-empty="$tickets->isEmpty()">

        <x-slot:filters>
            <form method="GET" action="{{ route('admin.assignments.index') }}" class="d-flex flex-wrap gap-2 align-items-end" role="search">
                <input type="hidden" name="view" value="{{ $filters->view }}">
                <div>
                    <label for="filter-search" class="form-label small mb-1">Search</label>
                    <input id="filter-search" type="search" name="search" class="form-control form-control-sm" value="{{ $filters->search }}" placeholder="Number or title" data-shortcut-search>
                </div>
                <div>
                    <label for="filter-priority" class="form-label small mb-1">Priority</label>
                    <select id="filter-priority" name="priority" class="form-select form-select-sm">
                        <option value="">All priorities</option>
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected($filters->priority === $priority)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" size="sm">Apply filters</x-button>
                <x-button variant="secondary" size="sm" :href="route('admin.assignments.index', ['view' => $filters->view])">Reset</x-button>
            </form>
        </x-slot:filters>

        @foreach ($tickets as $ticket)
            <tr @class(['is-overdue' => $ticket->isOverdue()])>
                <td><x-ticket-no :number="$ticket->ticket_no" :link="route('tickets.show', $ticket)" /></td>
                <td>{{ $ticket->title }}</td>
                <td><x-priority-badge :priority="$ticket->priority" /></td>
                <td>{{ $ticket->requester->name }}</td>
                <td><x-status-badge :status="$ticket->status" :overdue="$ticket->isOverdue()" /></td>
                <td>{{ $ticket->due_at->format('j M, H:i') }}</td>
                <td class="assign-cell">
                    @if ($filters->view !== 'unassigned' && $ticket->agent)
                        <div class="mb-1">
                            {{ $ticket->agent->name }}
                            @unless ($ticket->agent->is_active)
                                <span class="text-overdue small">(deactivated)</span>
                            @endunless
                        </div>
                    @endif
                    @can('assign', $ticket)
                        <x-assign-form :ticket="$ticket" :agents="$agents" :compact="true" />
                    @endcan
                </td>
            </tr>
        @endforeach

        <x-slot:footer>
            <x-pagination :paginator="$tickets" noun="tickets" />
        </x-slot:footer>

        <x-slot:empty>
            @if ($filters->isNarrowed())
                <x-empty-state icon="search" title="No tickets match these filters" text="Try a different search, or clear the filters.">
                    <x-button variant="secondary" :href="route('admin.assignments.index', ['view' => $filters->view])">Clear filters</x-button>
                </x-empty-state>
            @elseif ($filters->view === 'unassigned')
                <x-empty-state icon="check-circle" title="Every ticket has an agent" text="New tickets appear here as soon as they are reported." />
            @elseif ($filters->view === 'overdue')
                <x-empty-state icon="check-circle" title="Nothing is overdue" text="Tickets appear here when they pass their due time." />
            @else
                <x-empty-state icon="inbox" title="No assigned tickets" text="Tickets you assign appear here, where you can move them to another agent." />
            @endif
        </x-slot:empty>
    </x-data-table>
</x-layout.app>
