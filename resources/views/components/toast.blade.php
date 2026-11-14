@props(['type' => 'info', 'message', 'autoshow' => true])
<div {{ $attributes->class(['toast', 'toast-'.$type]) }}
     data-toast-type="{{ $type }}"
     role="{{ $type === 'error' ? 'alert' : 'status' }}"
     aria-atomic="true"
     data-bs-autohide="{{ $type === 'error' ? 'false' : 'true' }}"
     data-bs-delay="6000"
     @if ($autoshow) data-toast-autoshow @endif>
    <div class="toast-body">
        <x-icon :name="match ($type) { 'success' => 'check-circle', 'error' => 'alert', default => 'info' }" class="toast-icon" />
        <span class="toast-message">{{ $message }}</span>
        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Dismiss"></button>
    </div>
</div>
