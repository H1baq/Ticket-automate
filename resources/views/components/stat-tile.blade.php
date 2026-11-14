@props(['label', 'value', 'hint' => null, 'href' => null, 'overdue' => false])
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class(['stat-tile', 'is-link', 'is-overdue' => $overdue]) }}>
        <span class="stat-tile-label">{{ $label }}</span>
        <span class="stat-tile-value">{{ $value }}</span>
        @if ($hint)
            <span class="stat-tile-hint">{{ $hint }}</span>
        @endif
    </a>
@else
    <div {{ $attributes->class(['stat-tile', 'is-overdue' => $overdue]) }}>
        <span class="stat-tile-label">{{ $label }}</span>
        <span class="stat-tile-value">{{ $value }}</span>
        @if ($hint)
            <span class="stat-tile-hint">{{ $hint }}</span>
        @endif
    </div>
@endif
