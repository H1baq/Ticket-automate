@props(['number', 'link' => null])
<span {{ $attributes->class(['ticket-no']) }}>
    @if ($link)
        <a href="{{ $link }}">{{ $number }}</a>
    @else
        <span>{{ $number }}</span>
    @endif
    <button type="button" class="copy-button" data-copy="{{ $number }}" aria-label="Copy ticket number {{ $number }}">
        <x-icon name="copy" />
    </button>
</span>
