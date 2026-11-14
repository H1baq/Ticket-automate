<x-layout.app :title="'Edit '.$account->name">
    <x-page-header :title="$account->name" :breadcrumbs="['Administration' => null, 'Users' => route('admin.users.index'), $account->name => null]" />

    <form method="POST" action="{{ route('admin.users.update', $account) }}" novalidate>
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-lg-6">
                <x-card title="Account details">
                    <x-form.input name="name" label="Full name" :value="$account->name" autocomplete="off" :required="true" />

                    <div class="mb-3">
                        <span class="form-label d-block">Email</span>
                        <div>{{ $account->email }}</div>
                        <div class="form-text">The email address is the sign-in name and cannot be changed here.</div>
                    </div>

                    @if ($canChangeRole)
                        <x-form.select name="role" label="Role" :options="collect($roles)->mapWithKeys(fn ($role) => [$role->value => $role->label()])->all()" :value="$account->role->value" :required="true" />
                    @else
                        <div class="mb-3">
                            <span class="form-label d-block">Role</span>
                            <div>{{ $account->role->label() }}</div>
                            <div class="form-text">You cannot change your own role.</div>
                        </div>
                    @endif

                    <x-form.input name="department" label="Department" :value="$account->department" autocomplete="off" hint="Optional." />
                </x-card>
            </div>
        </div>

        <div class="mt-3 d-flex gap-2">
            <x-button type="submit" loading-text="Saving…">Save changes</x-button>
            <x-button variant="secondary" :href="route('admin.users.index')">Cancel</x-button>
        </div>
    </form>
</x-layout.app>
