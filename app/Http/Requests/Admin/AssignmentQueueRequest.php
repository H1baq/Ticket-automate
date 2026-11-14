<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\TicketPriority;
use App\Models\Ticket;
use App\Support\QueueFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignmentQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageAssignments', Ticket::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'view' => ['nullable', Rule::in(QueueFilters::VIEWS)],
            'search' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
        ];
    }

    public function filters(): QueueFilters
    {
        $search = trim((string) $this->validated('search'));

        return new QueueFilters(
            view: (string) ($this->validated('view') ?? QueueFilters::UNASSIGNED),
            search: $search !== '' ? $search : null,
            priority: TicketPriority::tryFrom((string) $this->validated('priority')),
        );
    }
}
