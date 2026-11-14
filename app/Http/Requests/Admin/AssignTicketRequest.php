<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return $ticket instanceof Ticket && ($this->user()?->can('assign', $ticket) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'agent_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Agent->value)->where('is_active', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'agent_id.required' => 'Choose an agent.',
            'agent_id.integer' => 'Choose an active support agent.',
            'agent_id.exists' => 'Choose an active support agent.',
        ];
    }

    public function agentId(): int
    {
        return (int) $this->validated('agent_id');
    }
}
