<x-layout.app title="Dashboard">
    <x-page-header title="Dashboard" />

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
