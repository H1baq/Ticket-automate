<ol class="timeline">
    @foreach ($history as $entry)
        <li @class(['is-system' => $entry->changed_by === null])>
            <div class="timeline-title">{{ $title($entry) }}</div>
            <div class="timeline-meta">
                {{ $entry->changedBy?->name ?? 'System' }} ·
                <time datetime="{{ $entry->created_at->toIso8601String() }}" title="{{ $entry->created_at->diffForHumans() }}">{{ $entry->created_at->format('j M Y, H:i') }}</time>
            </div>
            @if ($entry->old_status && $entry->new_status)
                <div class="timeline-detail">
                    <x-status-badge :status="$entry->old_status" />
                    <x-icon name="arrow-right" class="mx-1" />
                    <x-status-badge :status="$entry->new_status" />
                </div>
            @endif
            @if ($entry->details)
                <div class="timeline-detail">{{ $entry->details }}</div>
            @endif
        </li>
    @endforeach
</ol>
