<x-layout.app title="Users">
    <x-page-header title="Users" :breadcrumbs="['Administration' => null, 'Users' => null]">
        <x-slot:actions>
            <x-button :href="route('admin.users.create')">
                <x-icon name="plus" /> New user
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        caption="User accounts"
        :columns="[
            ['key' => 'name', 'label' => 'Name', 'sortable' => true],
            ['key' => 'email', 'label' => 'Email', 'sortable' => true],
            ['key' => 'role', 'label' => 'Role', 'sortable' => true],
            ['key' => 'department', 'label' => 'Department', 'sortable' => true],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'actions', 'label' => 'Actions', 'align' => 'end'],
        ]"
        :sort="$filters->sort"
        :direction="$filters->direction"
        :is-empty="$users->isEmpty()">

        <x-slot:filters>
            <form method="GET" action="{{ route('admin.users.index') }}" class="d-flex flex-wrap gap-2 align-items-end" role="search">
                <div>
                    <label for="filter-search" class="form-label small mb-1">Search</label>
                    <input id="filter-search" type="search" name="search" class="form-control form-control-sm" value="{{ $filters->search }}" placeholder="Name or email" data-shortcut-search>
                </div>
                <div>
                    <label for="filter-role" class="form-label small mb-1">Role</label>
                    <select id="filter-role" name="role" class="form-select form-select-sm">
                        <option value="">All roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected($filters->role === $role)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter-status" class="form-label small mb-1">Status</label>
                    <select id="filter-status" name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="active" @selected($filters->active === true)>Active</option>
                        <option value="deactivated" @selected($filters->active === false)>Deactivated</option>
                    </select>
                </div>
                @if ($filters->sort !== 'name' || $filters->direction !== 'asc')
                    <input type="hidden" name="sort" value="{{ $filters->sort }}">
                    <input type="hidden" name="direction" value="{{ $filters->direction }}">
                @endif
                <x-button type="submit" size="sm">Apply filters</x-button>
                <x-button variant="secondary" size="sm" :href="route('admin.users.index')">Reset</x-button>
            </form>
        </x-slot:filters>

        @foreach ($users as $account)
            <tr>
                <td>
                    <a href="{{ route('admin.users.edit', $account) }}" class="fw-semibold">{{ $account->name }}</a>
                    @if ($account->is(auth()->user()))
                        <span class="text-muted small">(you)</span>
                    @endif
                </td>
                <td>{{ $account->email }}</td>
                <td>{{ $account->role->label() }}</td>
                <td>{{ $account->department ?? '—' }}</td>
                <td>
                    @if ($account->is_active)
                        <span class="status-badge status-badge--resolved">Active</span>
                    @else
                        <span class="status-badge status-badge--closed">Deactivated</span>
                    @endif
                </td>
                <td class="num">
                    <div class="d-inline-flex gap-1">
                        <x-button variant="secondary" size="sm" :href="route('admin.users.edit', $account)">Edit</x-button>
                        @can('deactivate', $account)
                            <x-button variant="secondary" size="sm"
                                data-bs-toggle="modal" data-bs-target="#confirm-deactivate"
                                data-confirm-action="{{ route('admin.users.deactivate', $account) }}"
                                data-confirm-message="Deactivate {{ $account->name }}? They will be signed out and cannot sign in until you reactivate them. Their tickets stay in the system.">Deactivate</x-button>
                        @endcan
                        @can('reactivate', $account)
                            <x-button variant="secondary" size="sm"
                                data-bs-toggle="modal" data-bs-target="#confirm-reactivate"
                                data-confirm-action="{{ route('admin.users.reactivate', $account) }}"
                                data-confirm-message="Reactivate {{ $account->name }}? They will be able to sign in again.">Reactivate</x-button>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach

        <x-slot:footer>
            <x-pagination :paginator="$users" noun="users" />
        </x-slot:footer>

        <x-slot:empty>
            <x-empty-state icon="users" title="No users match these filters" text="Try a different search, or clear the filters to see everyone.">
                <x-button variant="secondary" :href="route('admin.users.index')">Clear filters</x-button>
            </x-empty-state>
        </x-slot:empty>
    </x-data-table>

    <x-modal-confirm id="confirm-deactivate" title="Deactivate this user?" action="" method="PATCH" confirm-label="Deactivate" variant="danger">
        Deactivate this user?
    </x-modal-confirm>
    <x-modal-confirm id="confirm-reactivate" title="Reactivate this user?" action="" method="PATCH" confirm-label="Reactivate" variant="primary">
        Reactivate this user?
    </x-modal-confirm>
</x-layout.app>
