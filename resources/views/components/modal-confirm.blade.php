@props([
    'id',
    'title',
    'action' => '',
    'method' => 'POST',
    'confirmLabel' => 'Confirm',
    'variant' => 'danger',
])
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}-title" aria-hidden="true" data-confirm-modal>
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="{{ $action }}" data-confirm-form>
            @csrf
            @if (strtoupper($method) !== 'POST')
                @method($method)
            @endif

            <div class="modal-header">
                <h2 class="modal-title" id="{{ $id }}-title">{{ $title }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" data-confirm-message>{{ $slot }}</p>
            </div>
            <div class="modal-footer">
                <x-button variant="secondary" data-bs-dismiss="modal" data-confirm-cancel>Cancel</x-button>
                <x-button :variant="$variant" type="submit" loading-text="Working…">{{ $confirmLabel }}</x-button>
            </div>
        </form>
    </div>
</div>
