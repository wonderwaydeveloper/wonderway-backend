<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users|regex:/^[a-zA-Z0-9_]+$/',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            'date_of_birth' => 'required|date|before:today',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'نام الزامی است',
            'name.max' => 'نام نباید بیشتر از 255 کاراکتر باشد',
            'username.required' => 'نام کاربری الزامی است',
            'username.unique' => 'این نام کاربری قبلاً استفاده شده است',
            'username.regex' => 'نام کاربری فقط می‌تواند شامل حروف، اعداد و _ باشد',
            'email.required' => 'ایمیل الزامی است',
            'email.email' => 'فرمت ایمیل صحیح نیست',
            'email.unique' => 'این ایمیل قبلاً ثبت شده است',
            'password.required' => 'رمز عبور الزامی است',
            'password.min' => 'رمز عبور باید حداقل 8 کاراکتر باشد',
            'password.regex' => 'رمز عبور باید شامل حروف کوچک، بزرگ، عدد و کاراکتر خاص باشد',
            'password.confirmed' => 'تأیید رمز عبور مطابقت ندارد',
            'date_of_birth.required' => 'تاریخ تولد الزامی است',
            'date_of_birth.date' => 'فرمت تاریخ تولد صحیح نیست',
            'date_of_birth.before' => 'تاریخ تولد باید قبل از امروز باشد',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'نام',
            'username' => 'نام کاربری',
            'email' => 'ایمیل',
            'password' => 'رمز عبور',
            'date_of_birth' => 'تاریخ تولد',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Clean and normalize data
        $this->merge([
            'name' => trim($this->input('name')),
            'username' => strtolower(trim($this->input('username'))),
            'email' => strtolower(trim($this->input('email'))),
        ]);
    }
}
