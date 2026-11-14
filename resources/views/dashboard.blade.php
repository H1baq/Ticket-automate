<x-layout.app title="Dashboard">
    <x-page-header title="Dashboard" />

    @if ($queue !== null)
        <h2 class="h4 mb-2">Needs attention</h2>
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><x-stat-tile label="Unassigned" :value="$queue['unassigned']" hint="Waiting for an agent" :href="route('admin.assignments.index')" /></div>
            <div class="col-6 col-lg-3"><x-stat-tile label="Overdue" :value="$queue['overdue']" hint="Past their due time" :overdue="$queue['overdue'] > 0" :href="route('admin.assignments.index', ['view' => 'overdue'])" /></div>
            <div class="col-6 col-lg-3"><x-stat-tile label="Assigned" :value="$queue['assigned']" hint="With an agent, not closed" :href="route('admin.assignments.index', ['view' => 'assigned'])" /></div>
        </div>
    @endif

    @if ($assigned !== null)
        <h2 class="h4 mb-2">Assigned to me</h2>
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><x-stat-tile label="Unresolved" :value="$assigned['unresolved']" hint="Open or in progress" /></div>
            <div class="col-6 col-lg-3"><x-stat-tile label="Overdue" :value="$assigned['overdue']" hint="Past their due time" :overdue="$assigned['overdue'] > 0" /></div>
        </div>
    @endif

    <h2 class="h4 mb-2">Reported by me</h2>
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-stat-tile label="Unresolved" :value="$reported['unresolved']" hint="Open or being worked on" :href="route('tickets.index', ['status' => 'unresolved'])" /></div>
        <div class="col-6 col-lg-3"><x-stat-tile label="Overdue" :value="$reported['overdue']" hint="Past their due time" :overdue="$reported['overdue'] > 0" :href="route('tickets.index', ['overdue' => 1])" /></div>
        <div class="col-6 col-lg-3"><x-stat-tile label="Resolved" :value="$reported['resolved']" hint="Waiting for you to confirm" :href="route('tickets.index', ['status' => 'resolved'])" /></div>
    </div>

    <x-card title="Your account" class="mb-3">
        <dl class="row mb-0">
            <dt class="col-sm-3">Name</dt>
            <dd class="col-sm-9">{{ $user->name }}</dd>
            <dt class="col-sm-3">Email</dt>
            <dd class="col-sm-9">{{ $user->email }}</dd>
            <dt class="col-sm-3">Role</dt>
            <dd class="col-sm-9">{{ $user->role->label() }}</dd>
            <dt class="col-sm-3">Department</dt>
            <dd class="col-sm-9 mb-0">{{ $user->department ?? 'Not set' }}</dd>
        </dl>
    </x-card>
</x-layout.app>
