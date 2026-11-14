@props(['variant' => 'primary', 'type' => 'button', 'href' => null, 'size' => null, 'loadingText' => null])
@php
    $classes = ['btn', 'btn-'.$variant, 'btn-sm' => $size === 'sm'];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }} @if ($loadingText) data-loading-text="{{ $loadingText }}" @endif>
        <span class="btn-label">{{ $slot }}</span>
    </button>
@endif
