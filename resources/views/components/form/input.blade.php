@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
    'id' => null,
])
@php
    $id ??= 'field-'.$name;
    $error = $errors->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
    $keepValue = ! in_array($type, ['password', 'file'], true);
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="form-label">{{ $label }}@if ($required) <span class="required" aria-hidden="true">*</span><span class="visually-hidden">(required)</span>@endif</label>
    <input id="{{ $id }}"
           name="{{ $name }}"
           type="{{ $type }}"
           {{ $attributes->class(['form-control', 'is-invalid' => (bool) $error]) }}
           @if ($keepValue) value="{{ old($name, $value) }}" @endif
           @if ($required) required @endif
           @if ($error) aria-invalid="true" @endif
           @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
    @if ($hint)
        <div id="{{ $id }}-hint" class="form-text">{{ $hint }}</div>
    @endif
    @if ($error)
        <div id="{{ $id }}-error" class="field-error"><x-icon name="alert" /> <span>{{ $error }}</span></div>
    @endif
</div>
