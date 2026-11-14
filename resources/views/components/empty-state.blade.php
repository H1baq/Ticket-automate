@props(['title', 'text' => null, 'icon' => 'inbox'])
<div {{ $attributes->class(['empty-state']) }}>
    <span class="empty-icon"><x-icon :name="$icon" /></span>
    <h2 class="h4">{{ $title }}</h2>
    @if ($text)
        <p>{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
