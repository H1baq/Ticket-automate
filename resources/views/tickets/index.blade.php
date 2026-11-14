<x-layout.app title="My tickets">
    <x-page-header title="My tickets" />

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-stat-tile label="Unresolved" :value="$summary['unresolved']" hint="Open or being worked on" :href="route('tickets.index', ['status' => 'unresolved'])" /></div>
        <div class="col-6 col-lg-3"><x-stat-tile label="Overdue" :value="$summary['overdue']" hint="Past their due time" :overdue="$summary['overdue'] > 0" :href="route('tickets.index', ['overdue' => 1])" /></div>
        <div class="col-6 col-lg-3"><x-stat-tile label="Resolved" :value="$summary['resolved']" hint="Waiting for you to confirm" :href="route('tickets.index', ['status' => 'resolved'])" /></div>
        <div class="col-6 col-lg-3"><x-stat-tile label="Closed" :value="$summary['closed']" :href="route('tickets.index', ['status' => 'closed'])" /></div>
    </div>

    <x-data-table
        caption="Tickets you have reported"
        :columns="[
            ['key' => 'ticket_no', 'label' => 'Ticket', 'sortable' => true],
            ['key' => 'title', 'label' => 'Title', 'sortable' => true],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'status', 'label' => 'Status', 'sortable' => true],
            ['key' => 'priority', 'label' => 'Priority', 'sortable' => true],
            ['key' => 'created_at', 'label' => 'Reported', 'sortable' => true],
            ['key' => 'due_at', 'label' => 'Due', 'sortable' => true],
        ]"
        :sort="$filters->sort"
        :direction="$filters->direction"
        :is-empty="$tickets->isEmpty()">

        <x-slot:filters>
            <form method="GET" action="{{ route('tickets.index') }}" class="d-flex flex-wrap gap-2 align-items-end" role="search">
                <div>
                    <label for="filter-search" class="form-label small mb-1">Search</label>
                    <input id="filter-search" type="search" name="search" class="form-control form-control-sm" value="{{ $filters->search }}" placeholder="Number or title" data-shortcut-search>
                </div>
                <div>
                    <label for="filter-status" class="form-label small mb-1">Status</label>
                    <select id="filter-status" name="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        <option value="unresolved" @selected($filters->statusChoice === 'unresolved')>Unresolved</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($filters->statusChoice === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
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
                <div>
                    <label for="filter-category" class="form-label small mb-1">Category</label>
                    <select id="filter-category" name="category" class="form-select form-select-sm">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($filters->categoryId === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-check mb-1">
                    <input id="filter-overdue" class="form-check-input" type="checkbox" name="overdue" value="1" @checked($filters->overdueOnly)>
                    <label for="filter-overdue" class="form-check-label small">Overdue only</label>
                </div>
                @if ($filters->sort !== 'created_at' || $filters->direction !== 'desc')
                    <input type="hidden" name="sort" value="{{ $filters->sort }}">
                    <input type="hidden" name="direction" value="{{ $filters->direction }}">
                @endif
                <x-button type="submit" size="sm">Apply filters</x-button>
                <x-button variant="secondary" size="sm" :href="route('tickets.index')">Reset</x-button>
            </form>
        </x-slot:filters>

        @foreach ($tickets as $ticket)
            <tr @class(['is-overdue' => $ticket->isOverdue()])>
                <td><x-ticket-no :number="$ticket->ticket_no" /></td>
                <td>{{ $ticket->title }}</td>
                <td>{{ $ticket->category->name }}</td>
                <td><x-status-badge :status="$ticket->status" :overdue="$ticket->isOverdue()" /></td>
                <td><x-priority-badge :priority="$ticket->priority" /></td>
                <td>{{ $ticket->created_at->format('j M Y') }}</td>
                <td>{{ $ticket->due_at->format('j M, H:i') }}</td>
            </tr>
        @endforeach

        <x-slot:footer>
            <x-pagination :paginator="$tickets" noun="tickets" />
        </x-slot:footer>

        <x-slot:empty>
            @if ($filters->isNarrowed())
                <x-empty-state icon="search" title="No tickets match these filters" text="Try a different search, or clear the filters to see all your tickets.">
                    <x-button variant="secondary" :href="route('tickets.index')">Clear filters</x-button>
                </x-empty-state>
            @else
                <x-empty-state icon="ticket" title="You have not reported a problem yet" text="When something is not working, tell us and an agent will pick it up." />
            @endif
        </x-slot:empty>
    </x-data-table>
</x-layout.app>
