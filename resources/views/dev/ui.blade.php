<x-layout.app title="Component gallery" :preview-role="$role" current="Dashboard">
    <x-page-header title="Component gallery" :breadcrumbs="['Development' => '#', 'Component gallery' => null]">
        <x-slot:actions>
            @foreach (['user' => 'Staff', 'agent' => 'Agent', 'admin' => 'Admin'] as $value => $label)
                <x-button variant="secondary" size="sm" :href="route('dev.ui', ['role' => $value])" :aria-current="$role->value === $value ? 'true' : null">{{ $label }} menu</x-button>
            @endforeach
        </x-slot:actions>
    </x-page-header>

    <p class="text-muted">Local only. Every component below is what the feature screens are built from. Sample data is not stored.</p>

    <section class="gallery-section" id="colour">
        <h2>Colour and type</h2>
        <div class="gallery-row mb-3">
            @foreach ([
                'Ink #1B2430' => '#1b2430', 'Muted #5B6675' => '#5b6675', 'Border #DDE1E6' => '#dde1e6', 'Surface #F6F7F9' => '#f6f7f9',
                'Primary #0F5C4D' => '#0f5c4d', 'Primary hover #0B4A3E' => '#0b4a3e', 'Focus #3B82F6' => '#3b82f6', 'Overdue #B91C1C' => '#b91c1c',
            ] as $name => $hex)
                <span class="swatch"><i style="--swatch: {{ $hex }}"></i>{{ $name }}</span>
            @endforeach
        </div>
        <h1>Page title, 22px semibold</h1>
        <h2 class="h2 border-0 p-0">Section heading, 18px semibold</h2>
        <p class="mb-1">Body text in Source Sans 3 at 15px. Plain, readable, dense enough for an all-day tool.</p>
        <p class="text-muted mb-1">Muted text for hints and secondary information.</p>
        <p class="mb-0">Ticket numbers and IDs use <span class="mono">JetBrains Mono: HD-2026-00142</span>, with tabular numbers 0123456789 in tables.</p>
    </section>

    <section class="gallery-section" id="badges">
        <h2>Status, priority and ticket number</h2>
        <div class="gallery-row mb-3">
            @foreach ($statuses as $status)
                <x-status-badge :status="$status" />
            @endforeach
            <x-status-badge :status="App\Enums\TicketStatus::InProgress" :overdue="true" />
        </div>
        <div class="gallery-row mb-3">
            @foreach ($priorities as $priority)
                <x-priority-badge :priority="$priority" />
            @endforeach
        </div>
        <x-ticket-no number="HD-2026-00142" />
    </section>

    <section class="gallery-section" id="buttons">
        <h2>Buttons</h2>
        <div class="gallery-row mb-3">
            <x-button>Create ticket</x-button>
            <x-button variant="secondary">Cancel</x-button>
            <x-button variant="danger">Deactivate user</x-button>
            <x-button size="sm">Small</x-button>
            <x-button disabled>Disabled</x-button>
            <x-button variant="secondary" href="#buttons">As a link</x-button>
        </div>
        <form method="POST" action="{{ route('dev.ui.submit', ['delay' => 2]) }}" class="gallery-row">
            @csrf
            <x-button type="submit" loading-text="Creating ticket…" id="slow-submit">Submit and wait 2 seconds</x-button>
            <span class="text-muted">The button locks and shows a spinner while the form is sent, then a toast confirms.</span>
        </form>
    </section>

    <section class="gallery-section" id="tiles">
        <h2>Stat tiles</h2>
        <div class="row g-3">
            <div class="col-6 col-lg-3"><x-stat-tile label="Open" value="18" hint="4 added today" href="#tiles" /></div>
            <div class="col-6 col-lg-3"><x-stat-tile label="Unassigned" value="7" hint="Oldest waiting 3 h" href="#tiles" /></div>
            <div class="col-6 col-lg-3"><x-stat-tile label="Overdue" value="3" hint="Past their due time" :overdue="true" href="#tiles" /></div>
            <div class="col-6 col-lg-3"><x-stat-tile label="Resolved this month" value="64" /></div>
        </div>
    </section>

    <section class="gallery-section" id="table">
        <h2>Data table with filters, sorting and pagination</h2>
        <x-data-table
            caption="Sample tickets"
            :columns="[
                ['key' => 'number', 'label' => 'Ticket', 'sortable' => true],
                ['key' => 'title', 'label' => 'Title'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'priority', 'label' => 'Priority'],
                ['key' => 'requester', 'label' => 'Requester'],
                ['key' => 'due', 'label' => 'Due', 'sortable' => true, 'align' => 'end'],
            ]"
            :sort="request('sort')"
            :direction="request('direction', 'asc')">
            <x-slot:filters>
                <form method="GET" action="{{ route('dev.ui', ['role' => $role->value]) }}" class="d-flex flex-wrap gap-2 align-items-end">
                    <div>
                        <label for="filter-search" class="form-label small mb-1">Search</label>
                        <input id="filter-search" type="search" name="search" class="form-control form-control-sm" value="{{ $filters['search'] ?? '' }}" placeholder="Number or title">
                    </div>
                    <div>
                        <label for="filter-status" class="form-label small mb-1">Status</label>
                        <select id="filter-status" name="status" class="form-select form-select-sm">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="filter-priority" class="form-label small mb-1">Priority</label>
                        <select id="filter-priority" name="priority" class="form-select form-select-sm">
                            <option value="">All priorities</option>
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? '') === $priority->value)>{{ $priority->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-button type="submit" size="sm">Apply filters</x-button>
                    <x-button variant="secondary" size="sm" :href="route('dev.ui', ['role' => $role->value])">Reset</x-button>
                </form>
            </x-slot:filters>

            @foreach ($rows as $row)
                <tr @class(['is-overdue' => $row['overdue']])>
                    <td><x-ticket-no :number="$row['number']" link="#table" /></td>
                    <td>{{ $row['title'] }}</td>
                    <td><x-status-badge :status="$row['status']" :overdue="$row['overdue']" /></td>
                    <td><x-priority-badge :priority="$row['priority']" /></td>
                    <td>{{ $row['requester'] }}</td>
                    <td class="num">{{ $row['due']->format('j M, H:i') }}</td>
                </tr>
            @endforeach

            <x-slot:footer>
                <x-pagination :paginator="$paginator" noun="tickets" />
            </x-slot:footer>

            <x-slot:empty></x-slot:empty>
        </x-data-table>
    </section>

    <section class="gallery-section" id="cards">
        <h2>Cards, empty state and timeline</h2>
        <div class="row g-3">
            <div class="col-lg-6">
                <x-card title="Ticket history">
                    <x-timeline :history="$history" />
                </x-card>
            </div>
            <div class="col-lg-6">
                <x-card title="My tickets">
                    <x-empty-state title="You have not reported a problem yet" text="When something is not working, tell us here and an agent will pick it up.">
                        <x-button :href="'#form'" data-shortcut-demo>Report a problem</x-button>
                    </x-empty-state>
                </x-card>
            </div>
        </div>
    </section>

    <section class="gallery-section" id="form">
        <h2>Form fields</h2>
        <form method="POST" action="{{ route('dev.ui.submit') }}" enctype="multipart/form-data" novalidate>
            @csrf
            <div class="row g-3">
                <div class="col-lg-6">
                    <x-card title="Report a problem">
                        <x-form.input name="title" label="Title" hint="A short summary, 5 to 150 characters." :required="true" maxlength="150" />
                        <x-form.select name="category" label="Category" :options="$categories" placeholder="Choose a category" :required="true" hint="Hardware: computers, laptops, monitors and other equipment." />
                        <x-form.textarea name="description" label="Description" :required="true" :maxlength="5000" hint="What happened, and what did you expect?" />
                        <x-form.file name="attachment" label="Attachment" accept=".jpg,.jpeg,.png,.pdf" hint="One file: JPG, PNG or PDF, up to 5 MB." />
                    </x-card>
                </div>
                <div class="col-lg-6">
                    <x-card title="Field with an error">
                        <x-form.input name="email" label="Email" type="email" value="wanjiku.kamau" :required="true" />
                        <p class="text-muted small mb-0">Errors appear under the field, in words, with an icon. The field border turns red too.</p>
                    </x-card>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <x-button type="submit" loading-text="Creating ticket…">Create ticket</x-button>
                <x-button variant="secondary" href="#form">Cancel</x-button>
            </div>
        </form>
    </section>

    <section class="gallery-section" id="feedback">
        <h2>Toasts and confirmation</h2>
        <div class="gallery-row mb-3">
            <x-button variant="secondary" data-show-toast="success" data-toast-message="Ticket HD-2026-00142 created. We will email you when an agent picks it up.">Success toast</x-button>
            <x-button variant="secondary" data-show-toast="info" data-toast-message="Your filters were cleared.">Info toast</x-button>
            <x-button variant="secondary" data-show-toast="error" data-toast-message="That file is larger than 5 MB. Choose a smaller one.">Error toast</x-button>
            <x-button variant="danger" data-bs-toggle="modal" data-bs-target="#confirm-deactivate" data-confirm-action="{{ route('dev.ui.submit') }}" data-confirm-message="Deactivate Wanjiku Kamau? They will no longer be able to sign in, and their tickets stay in the system.">Deactivate user</x-button>
        </div>
        <x-modal-confirm id="confirm-deactivate" title="Deactivate this user?" :action="route('dev.ui.submit')" confirm-label="Deactivate">
            Deactivate this user? They will no longer be able to sign in.
        </x-modal-confirm>
    </section>

    <section class="gallery-section" id="icons">
        <h2>Icons</h2>
        <div class="gallery-row">
            @foreach (App\View\Components\Icon::names() as $icon)
                <span class="d-inline-flex align-items-center gap-1 small"><x-icon :name="$icon" /> {{ $icon }}</span>
            @endforeach
        </div>
    </section>
</x-layout.app>
