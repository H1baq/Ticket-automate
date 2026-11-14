<x-layout.guest title="Reset your password">
    <h1>Reset your password</h1>
    <p class="text-muted">Enter the email address of your account and we will send you a link to choose a new password.</p>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <x-form.input name="email" label="Email" type="email" autocomplete="username" autofocus :required="true" />

        <x-button type="submit" class="w-100" loading-text="Sending…">Send reset link</x-button>
    </form>

    <p class="small mt-3 mb-0"><a href="{{ route('login') }}">Back to sign in</a></p>
</x-layout.guest>
