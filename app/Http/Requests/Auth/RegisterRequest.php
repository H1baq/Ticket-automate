<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Rules\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => $this->emailRules(),
            'department' => ['nullable', 'string', 'max:100'],
            'password' => [...PasswordPolicy::rules(), 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.ends_with' => 'Use your work email address. It must end with :values.',
            'email.unique' => 'An account with this email address already exists.',
        ];
    }

    /**
     * @return list<string>
     */
    private function emailRules(): array
    {
        $rules = ['required', 'string', 'email', 'max:255', 'unique:users,email'];

        $domains = config('hdtms.auth.allowed_email_domains');
        if (is_array($domains) && $domains !== []) {
            $rules[] = 'ends_with:'.implode(',', array_map(fn (string $domain): string => '@'.Str::lower(ltrim($domain, '@')), $domains));
        }

        return $rules;
    }
}
