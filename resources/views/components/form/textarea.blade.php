@props([
    'name',
    'label',
    'value' => null,
    'hint' => null,
    'required' => false,
    'rows' => 5,
    'maxlength' => null,
    'id' => null,
])
@php
    $id ??= 'field-'.$name;
    $error = $errors->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="form-label">{{ $label }}@if ($required) <span class="required" aria-hidden="true">*</span><span class="visually-hidden">(required)</span>@endif
        @if ($maxlength)
            <span class="char-count" data-char-count-for="{{ $id }}" data-max="{{ $maxlength }}" aria-hidden="true"></span>
        @endif
    </label>
    <textarea id="{{ $id }}"
              name="{{ $name }}"
              rows="{{ $rows }}"
              {{ $attributes->class(['form-control', 'is-invalid' => (bool) $error]) }}
              @if ($maxlength) maxlength="{{ $maxlength }}" @endif
              @if ($required) required @endif
              @if ($error) aria-invalid="true" @endif
              @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>{{ old($name, $value) }}</textarea>
    @if ($hint)
        <div id="{{ $id }}-hint" class="form-text">{{ $hint }}</div>
    @endif
    @if ($error)
        <div id="{{ $id }}-error" class="field-error"><x-icon name="alert" /> <span>{{ $error }}</span></div>
    @endif
</div>
