@props([
    'name',
    'label',
    'hint' => null,
    'accept' => null,
    'required' => false,
    'id' => null,
])
@php
    $id ??= 'field-'.$name;
    $error = $errors->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="form-label">{{ $label }}@if ($required) <span class="required" aria-hidden="true">*</span><span class="visually-hidden">(required)</span>@endif</label>
    <input id="{{ $id }}"
           name="{{ $name }}"
           type="file"
           {{ $attributes->class(['form-control', 'is-invalid' => (bool) $error]) }}
           @if ($accept) accept="{{ $accept }}" @endif
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
