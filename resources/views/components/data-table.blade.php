<div {{ $attributes->class(['table-card']) }}>
    @isset($filters)
        <div class="filter-bar">{{ $filters }}</div>
    @endisset

    @if ($isEmpty)
        {{ $empty }}
    @else
        <div class="table-responsive">
            <table class="table table-hover data-table align-middle">
                <caption class="visually-hidden">{{ $caption }}</caption>
                <thead>
                    <tr>
                        @foreach ($headers() as $header)
                            <th scope="col" class="{{ $header['class'] }}" @if ($header['aria']) aria-sort="{{ $header['aria'] }}" @endif>
                                @if ($header['sortable'])
                                    <a href="{{ $header['url'] }}" title="{{ $header['hint'] }}">
                                        {{ $header['label'] }}
                                        <x-icon :name="$header['arrow']" />
                                    </a>
                                @else
                                    {{ $header['label'] }}
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    {{ $slot }}
                </tbody>
            </table>
        </div>

        @isset($footer)
            {{ $footer }}
        @endisset
    @endif
</div>
