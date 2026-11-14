<aside id="sidebar" class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" aria-label="Main menu">
    <div class="sidebar-brand">
        <span class="brand-mark" aria-hidden="true">
            @if ($logo())
                <img src="{{ $logo() }}" alt="">
            @else
                {{ mb_substr(config('app.name'), 0, 1) }}
            @endif
        </span>
        <span class="brand-text">
            <strong>{{ config('app.name') }}</strong>
            <span>{{ $organization() }}</span>
        </span>
        <button type="button" class="btn-close ms-auto d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close menu"></button>
    </div>

    <nav class="sidebar-nav" aria-label="Main">
        @foreach ($groups() as $heading => $items)
            @if (! $loop->first)
                <div class="nav-group-label">{{ $heading }}</div>
            @endif

            @foreach ($items as $item)
                <a class="nav-item-link"
                   href="{{ $item['href'] }}"
                   @if ($item['active']) aria-current="page" @endif
                   @if ($item['shortcut']) data-shortcut="{{ $item['shortcut'] }}" @endif>
                    <x-icon :name="$item['icon']" />
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        @endforeach
    </nav>

    <div class="sidebar-footer">
        @foreach ($shortcuts() as $shortcut)
            <div>
                @foreach ($shortcut['keys'] as $key)
                    @if (! $loop->first) then @endif<kbd>{{ $key }}</kbd>
                @endforeach
                {{ $shortcut['label'] }}
            </div>
        @endforeach
    </div>
</aside>
