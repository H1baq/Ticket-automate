<div class="toast-region" id="toast-region" aria-live="polite" aria-relevant="additions">
    @foreach ($flashed() as $toast)
        <x-toast :type="$toast['type']" :message="$toast['message']" />
    @endforeach
</div>

<template id="toast-template">
    @foreach (['info', 'success', 'error'] as $type)
        <x-toast :type="$type" message="" :autoshow="false" />
    @endforeach
</template>
