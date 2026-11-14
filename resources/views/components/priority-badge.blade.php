@props(['priority'])
<span {{ $attributes->class(['priority-badge']) }}>
    <span class="bars" aria-hidden="true">
        @foreach ([1, 2, 3] as $bar)
            <i @class(['on' => $bar <= $priority->level()])></i>
        @endforeach
    </span>
    {{ $priority->label() }}
</span>
