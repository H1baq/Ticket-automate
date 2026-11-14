<x-layout.guest title="Create an account">
    <h1>Create an account</h1>
    <p class="text-muted">You will be able to report problems and follow them here.</p>

    <form method="POST" action="{{ route('register.store') }}" novalidate>
        @csrf

        <x-form.input name="name" label="Full name" autocomplete="name" autofocus :required="true" />
        <x-form.input
            name="email"
            label="Work email"
            type="email"
            autocomplete="username"
            :required="true"
            :hint="count($domains) > 0 ? 'Use your work email address, ending in '.implode(' or ', array_map(fn ($domain) => '@'.ltrim($domain, '@'), $domains)).'.' : null" />
        <x-form.input name="department" label="Department" autocomplete="organization-title" hint="Optional." />
        <x-form.input name="password" label="Password" type="password" autocomplete="new-password" :required="true" :hint="'At least '.config('hdtms.auth.password_min_length').' characters.'" />
        <x-form.input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" :required="true" />

        <x-button type="submit" class="w-100" loading-text="Creating account…">Create account</x-button>
    </form>

    <p class="small mt-3 mb-0">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
</x-layout.guest>
