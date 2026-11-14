<x-layout.guest title="Choose a new password">
    <h1>Choose a new password</h1>
    <p class="text-muted">Pick a password you do not use anywhere else.</p>

    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-form.input name="email" label="Email" type="email" :value="$request->query('email')" autocomplete="username" :required="true" />
        <x-form.input name="password" label="New password" type="password" autocomplete="new-password" :required="true" :hint="'At least '.config('hdtms.auth.password_min_length').' characters.'" />
        <x-form.input name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" :required="true" />

        <x-button type="submit" class="w-100" loading-text="Saving…">Save password</x-button>
    </form>
</x-layout.guest>
