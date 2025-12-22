<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:280|min:1',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
            'gif_url' => 'nullable|url|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'محتوای کامنت الزامی است',
            'content.max' => 'محتوای کامنت نباید بیشتر از 280 کاراکتر باشد',
            'content.min' => 'محتوای کامنت نباید خالی باشد',
            'image.image' => 'فایل باید تصویر باشد',
            'image.mimes' => 'فرمت تصویر باید jpeg، jpg، png، gif یا webp باشد',
            'image.max' => 'حجم تصویر نباید بیشتر از 2MB باشد',
            'gif_url.url' => 'آدرس GIF معتبر نیست',
            'gif_url.max' => 'آدرس GIF خیلی طولانی است',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('content')) {
            $this->merge(['content' => trim($this->input('content'))]);
        }
    }
}