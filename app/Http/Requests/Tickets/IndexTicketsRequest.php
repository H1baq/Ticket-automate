<?php

declare(strict_types=1);

namespace App\Http\Requests\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Support\TicketFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTicketsRequest extends FormRequest
{
    /**
     * "unresolved" means open, assigned or in progress together.
     */
    private const UNRESOLVED = 'unresolved';

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Ticket::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([self::UNRESOLVED, ...array_map(fn (TicketStatus $status): string => $status->value, TicketStatus::cases())])],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'category' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'overdue' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(TicketFilters::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    public function filters(): TicketFilters
    {
        $status = $this->validated('status');
        $search = trim((string) $this->validated('search'));

        return new TicketFilters(
            search: $search !== '' ? $search : null,
            statuses: match (true) {
                $status === null => [],
                $status === self::UNRESOLVED => TicketStatus::unresolved(),
                default => [TicketStatus::from((string) $status)],
            },
            statusChoice: $status !== null ? (string) $status : null,
            priority: TicketPriority::tryFrom((string) $this->validated('priority')),
            categoryId: $this->validated('category') !== null ? (int) $this->validated('category') : null,
            overdueOnly: $this->boolean('overdue'),
            sort: (string) ($this->validated('sort') ?? 'created_at'),
            direction: (string) ($this->validated('direction') ?? 'desc'),
        );
    }
}
