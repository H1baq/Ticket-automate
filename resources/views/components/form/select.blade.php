@props([
    'name',
    'label',
    'options',
    'value' => null,
    'hint' => null,
    'required' => false,
    'placeholder' => null,
    'id' => null,
])
@php
    $id ??= 'field-'.$name;
    $error = $errors->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
    $selected = (string) old($name, $value);
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="form-label">{{ $label }}@if ($required) <span class="required" aria-hidden="true">*</span><span class="visually-hidden">(required)</span>@endif</label>
    <select id="{{ $id }}"
            name="{{ $name }}"
            {{ $attributes->class(['form-select', 'is-invalid' => (bool) $error]) }}
            @if ($required) required @endif
            @if ($error) aria-invalid="true" @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($hint)
        <div id="{{ $id }}-hint" class="form-text">{{ $hint }}</div>
    @endif
    @if ($error)
        <div id="{{ $id }}-error" class="field-error"><x-icon name="alert" /> <span>{{ $error }}</span></div>
    @endif
</div>
