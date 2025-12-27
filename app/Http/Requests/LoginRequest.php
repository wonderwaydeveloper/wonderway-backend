<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string',
            'two_factor_code' => 'nullable|string|size:6|regex:/^[0-9]+$/',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email is required',
            'email.email' => 'Email format is invalid',
            'password.required' => 'Password is required',
            'two_factor_code.size' => 'Two-factor code must be 6 digits',
            'two_factor_code.regex' => 'Two-factor code must contain only numbers',
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'Email',
            'password' => 'Password',
            'two_factor_code' => 'Two-factor code',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normalize email
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->input('email'))),
            ]);
        }

        // Clean 2FA code
        if ($this->has('two_factor_code')) {
            $this->merge([
                'two_factor_code' => preg_replace('/\D/', '', $this->input('two_factor_code')),
            ]);
        }
    }
}
