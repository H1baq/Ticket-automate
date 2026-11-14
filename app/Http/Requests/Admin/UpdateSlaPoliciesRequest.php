<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\TicketPriority;
use App\Models\SlaPolicy;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSlaPoliciesRequest extends FormRequest
{
    public const MIN_HOURS = 1;

    public const MAX_HOURS = 720;

    public function authorize(): bool
    {
        return $this->user()?->can('update', SlaPolicy::class) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $rules = [];
        foreach (TicketPriority::cases() as $priority) {
            $rules[$priority->value.'_hours'] = ['required', 'integer', 'min:'.self::MIN_HOURS, 'max:'.self::MAX_HOURS];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $message = 'Enter a whole number of hours from '.self::MIN_HOURS.' to '.self::MAX_HOURS.'.';
        $messages = [];
        foreach (TicketPriority::cases() as $priority) {
            foreach (['required', 'integer', 'min', 'max'] as $rule) {
                $messages[$priority->value.'_hours.'.$rule] = $message;
            }
        }

        return $messages;
    }

    /**
     * @return array<string, int>
     */
    public function hours(): array
    {
        $hours = [];
        foreach (TicketPriority::cases() as $priority) {
            $hours[$priority->value] = (int) $this->validated($priority->value.'_hours');
        }

        return $hours;
    }
}
