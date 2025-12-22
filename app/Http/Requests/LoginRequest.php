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
            'email.required' => 'ایمیل الزامی است',
            'email.email' => 'فرمت ایمیل صحیح نیست',
            'password.required' => 'رمز عبور الزامی است',
            'two_factor_code.size' => 'کد تأیید دو مرحلهای باید 6 رقم باشد',
            'two_factor_code.regex' => 'کد تأیید دو مرحلهای فقط باید شامل اعداد باشد',
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'ایمیل',
            'password' => 'رمز عبور',
            'two_factor_code' => 'کد تأیید دو مرحلهای',
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
