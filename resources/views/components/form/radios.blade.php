@props([
    'name',
    'label',
    'options',
    'value' => null,
    'hint' => null,
    'required' => false,
])
@php
    $error = $errors->first($name);
    $selected = (string) old($name, $value);
    $describedBy = trim(($hint ? 'field-'.$name.'-hint ' : '').($error ? 'field-'.$name.'-error' : ''));
@endphp
<fieldset class="mb-3" @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
    <legend class="form-label">{{ $label }}@if ($required) <span class="required" aria-hidden="true">*</span><span class="visually-hidden">(required)</span>@endif</legend>

    @foreach ($options as $optionValue => $option)
        <div class="choice @if ($error) is-invalid @endif">
            <input class="form-check-input"
                   type="radio"
                   name="{{ $name }}"
                   id="field-{{ $name }}-{{ $optionValue }}"
                   value="{{ $optionValue }}"
                   aria-describedby="field-{{ $name }}-{{ $optionValue }}-hint"
                   @checked($selected === (string) $optionValue)
                   @if ($required) required @endif>
            <label class="choice-label" for="field-{{ $name }}-{{ $optionValue }}">
                <span class="choice-title">{{ $option['label'] }}</span>
                <span class="choice-hint" id="field-{{ $name }}-{{ $optionValue }}-hint">{{ $option['description'] }}</span>
            </label>
        </div>
    @endforeach

    @if ($hint)
        <div id="field-{{ $name }}-hint" class="form-text">{{ $hint }}</div>
    @endif
    @if ($error)
        <div id="field-{{ $name }}-error" class="field-error"><x-icon name="alert" /> <span>{{ $error }}</span></div>
    @endif
</fieldset>
