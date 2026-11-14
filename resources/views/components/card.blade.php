@props(['title' => null, 'flush' => false])
<section {{ $attributes->class(['card']) }}>
    @if ($title || isset($actions))
        <header class="card-header">
            <h2 class="h4 m-0">{{ $title }}</h2>
            @isset($actions)
                <div>{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class(['card-body', 'p-0' => $flush])>{{ $slot }}</div>

    @isset($footer)
        <footer class="card-footer bg-white">{{ $footer }}</footer>
    @endisset
</section>
