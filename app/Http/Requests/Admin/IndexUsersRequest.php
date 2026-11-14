<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\UserFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'status' => ['nullable', Rule::in(['active', 'deactivated'])],
            'sort' => ['nullable', Rule::in(UserFilters::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    public function filters(): UserFilters
    {
        $status = $this->validated('status');

        return new UserFilters(
            search: ($search = trim((string) $this->validated('search'))) !== '' ? $search : null,
            role: UserRole::tryFrom((string) $this->validated('role')),
            active: $status === null ? null : $status === 'active',
            sort: (string) ($this->validated('sort') ?? 'name'),
            direction: (string) ($this->validated('direction') ?? 'asc'),
        );
    }
}
