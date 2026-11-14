<x-layout.guest title="Sign in">
    <h1>Sign in</h1>
    <p class="text-muted">Use the email address and password for your account.</p>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf

        <x-form.input name="email" label="Email" type="email" autocomplete="username" autofocus :required="true" />
        <x-form.input name="password" label="Password" type="password" autocomplete="current-password" :required="true" />

        <x-button type="submit" class="w-100" loading-text="Signing in…">Sign in</x-button>
    </form>

    <p class="small mt-3 mb-0">
        @if (Route::has('password.request'))
            <a href="{{ route('password.request') }}">Forgot your password?</a>
        @endif
        @if (config('hdtms.auth.self_registration') && Route::has('register'))
            <span class="text-muted mx-1" aria-hidden="true">·</span>
            <a href="{{ route('register') }}">Create an account</a>
        @endif
    </p>
</x-layout.guest>
