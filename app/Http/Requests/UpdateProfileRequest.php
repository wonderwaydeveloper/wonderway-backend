<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255|min:2',
            'bio' => 'sometimes|nullable|string|max:500',
            'avatar' => 'sometimes|nullable|string|url|max:255',
            'cover_image' => 'sometimes|nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'location' => 'sometimes|nullable|string|max:100',
            'website' => 'sometimes|nullable|url|max:255',
            'birth_date' => 'sometimes|nullable|date|before:today',
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'نام باید متن باشد',
            'name.max' => 'نام نباید بیشتر از 255 کاراکتر باشد',
            'name.min' => 'نام باید حداقل 2 کاراکتر باشد',
            'bio.string' => 'بیوگرافی باید متن باشد',
            'bio.max' => 'بیوگرافی نباید بیشتر از 500 کاراکتر باشد',
            'avatar.url' => 'آدرس آواتار معتبر نیست',
            'avatar.max' => 'آدرس آواتار خیلی طولانی است',
            'cover_image.image' => 'تصویر کاور باید تصویر باشد',
            'cover_image.mimes' => 'فرمت تصویر کاور باید jpeg، jpg، png یا webp باشد',
            'cover_image.max' => 'حجم تصویر کاور نباید بیشتر از 5MB باشد',
            'location.max' => 'موقعیت مکانی نباید بیشتر از 100 کاراکتر باشد',
            'website.url' => 'آدرس وبسایت معتبر نیست',
            'website.max' => 'آدرس وبسایت خیلی طولانی است',
            'birth_date.date' => 'تاریخ تولد معتبر نیست',
            'birth_date.before' => 'تاریخ تولد باید قبل از امروز باشد',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'نام',
            'bio' => 'بیوگرافی',
            'avatar' => 'آواتار',
            'cover_image' => 'تصویر کاور',
            'location' => 'موقعیت مکانی',
            'website' => 'وبسایت',
            'birth_date' => 'تاریخ تولد',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Clean and trim data
        if ($this->has('name')) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
        
        if ($this->has('bio')) {
            $this->merge(['bio' => trim($this->input('bio'))]);
        }

        if ($this->has('location')) {
            $this->merge(['location' => trim($this->input('location'))]);
        }
    }
}