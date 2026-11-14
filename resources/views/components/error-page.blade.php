@props(['code', 'title', 'text'])
<x-layout.guest :title="$title" :toasts="false">
    <span class="error-code">Error {{ $code }}</span>
    <h1>{{ $title }}</h1>
    <p class="text-muted">{{ $text }}</p>

    <div class="d-flex flex-wrap gap-2 mt-3">
        {{ $slot }}
        <x-button variant="secondary" :href="url('/')">Go to the home page</x-button>
    </div>
</x-layout.guest>
