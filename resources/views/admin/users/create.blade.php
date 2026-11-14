<x-layout.app title="New user">
    <x-page-header title="New user" :breadcrumbs="['Administration' => null, 'Users' => route('admin.users.index'), 'New user' => null]" />

    <form method="POST" action="{{ route('admin.users.store') }}" novalidate>
        @csrf

        <div class="row">
            <div class="col-lg-6">
                <x-card title="Account details">
                    <x-form.input name="name" label="Full name" autocomplete="off" autofocus :required="true" />
                    <x-form.input name="email" label="Email" type="email" autocomplete="off" :required="true" hint="We email this address a link to set a password. The link works for 60 minutes." />
                    <x-form.select name="role" label="Role" :options="collect($roles)->mapWithKeys(fn ($role) => [$role->value => $role->label()])->all()" value="user" :required="true" />
                    <x-form.input name="department" label="Department" autocomplete="off" hint="Optional." />
                </x-card>
            </div>
        </div>

        <div class="mt-3 d-flex gap-2">
            <x-button type="submit" loading-text="Creating user…">Create user</x-button>
            <x-button variant="secondary" :href="route('admin.users.index')">Cancel</x-button>
        </div>
    </form>
</x-layout.app>
