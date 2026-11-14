@props(['title' => null, 'previewRole' => null, 'current' => null])
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

    <div class="app-shell">
        <x-sidebar :role="$previewRole" :preview="$previewRole !== null" :current="$current" />

        <div class="app-main">
            <x-topbar :preview="$previewRole !== null" :role="$previewRole" />

            <main id="main" class="app-content" tabindex="-1">
                {{ $slot }}
            </main>
        </div>
    </div>

    <x-toast-region />
</body>
</html>
