<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('post'));
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:280|min:1',
            'edit_reason' => 'nullable|string|max:100',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
            'gif_url' => 'nullable|url|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'محتوای پست الزامی است',
            'content.max' => 'محتوای پست نباید بیشتر از 280 کاراکتر باشد',
            'content.min' => 'محتوای پست نباید خالی باشد',
            'edit_reason.max' => 'دلیل ویرایش نباید بیشتر از 100 کاراکتر باشد',
            'image.image' => 'فایل باید تصویر باشد',
            'image.mimes' => 'فرمت تصویر باید jpeg، jpg، png، gif یا webp باشد',
            'image.max' => 'حجم تصویر نباید بیشتر از 2MB باشد',
            'gif_url.url' => 'آدرس GIF معتبر نیست',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('content')) {
            $this->merge(['content' => trim($this->input('content'))]);
        }
    }
}
