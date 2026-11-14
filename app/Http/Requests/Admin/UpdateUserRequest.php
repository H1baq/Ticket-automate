<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && ($this->user()?->can('update', $target) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['sometimes', 'required', Rule::enum(UserRole::class)],
            'department' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * The role of the signed-in administrator is fixed. Sending the same role is harmless; a different one is refused.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $target = $this->route('user');

                if (! $target instanceof User || ! $this->has('role') || $validator->errors()->has('role')) {
                    return;
                }

                $newRole = UserRole::from((string) $this->input('role'));

                if ($newRole !== $target->role && ! ($this->user()?->can('changeRole', $target) ?? false)) {
                    $validator->errors()->add('role', 'You cannot change your own role.');
                }
            },
        ];
    }

    /**
     * @return array{name: string, department: string|null, role?: UserRole}
     */
    public function accountData(): array
    {
        $data = [
            'name' => (string) $this->validated('name'),
            'department' => $this->validated('department') !== null ? (string) $this->validated('department') : null,
        ];

        if ($this->validated('role') !== null) {
            $data['role'] = UserRole::from((string) $this->validated('role'));
        }

        return $data;
    }
}
