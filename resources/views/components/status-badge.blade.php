@props(['status', 'overdue' => false])
<span {{ $attributes->class(['status-badge', 'status-badge--'.str_replace('_', '-', $status->value)]) }}>{{ $status->label() }}</span>@if ($overdue)<span class="overdue-flag ms-1"><x-icon name="clock" /> Overdue</span>@endif
