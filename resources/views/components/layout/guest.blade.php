@props(['title' => null, 'wide' => false, 'toasts' => true])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="preload" href="{{ asset('fonts/source-sans-3-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
    <a class="skip-link" href="#main">Skip to main content</a>

    <div class="guest-shell">
        <a class="guest-brand" href="{{ url('/') }}">
            <span class="brand-mark" aria-hidden="true">{{ mb_substr(config('app.name'), 0, 1) }}</span>
            <span class="brand-text">
                <strong>{{ config('app.name') }}</strong>
                <span>{{ config('hdtms.organization.name') }}</span>
            </span>
        </a>

        <main id="main" @class(['guest-card', 'is-wide' => $wide]) tabindex="-1">
            {{ $slot }}
        </main>

        <p class="guest-footer">{{ config('hdtms.organization.name') }}</p>
    </div>

    @if ($toasts)
        <x-toast-region />
    @endif
</body>
</html>
