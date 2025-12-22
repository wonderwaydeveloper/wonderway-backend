<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:280',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
            'video' => 'nullable|file|mimes:mp4,mov,avi,mkv,webm|max:102400', // 100MB
            'gif_url' => 'nullable|url|max:500',
            'reply_settings' => 'nullable|in:everyone,following,mentioned,none',
            'quoted_post_id' => 'nullable|exists:posts,id',
            'is_draft' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'محتوای پست الزامی است',
            'content.max' => 'محتوای پست نباید بیشتر از 280 کاراکتر باشد',
            'image.image' => 'فایل باید تصویر باشد',
            'image.mimes' => 'فرمت تصویر باید jpeg، jpg، png، gif یا webp باشد',
            'image.max' => 'حجم تصویر نباید بیشتر از 2MB باشد',
            'video.file' => 'فایل ویدیو معتبر نیست',
            'video.mimes' => 'فرمت ویدیو باید mp4، mov، avi، mkv یا webm باشد',
            'video.max' => 'حجم ویدیو نباید بیشتر از 100MB باشد',
            'gif_url.url' => 'آدرس GIF معتبر نیست',
            'gif_url.max' => 'آدرس GIF خیلی طولانی است',
            'reply_settings.in' => 'تنظیمات پاسخ معتبر نیست',
            'quoted_post_id.exists' => 'پست مورد نظر برای نقل قول وجود ندارد',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'content' => 'محتوای پست',
            'image' => 'تصویر',
            'gif_url' => 'آدرس GIF',
            'reply_settings' => 'تنظیمات پاسخ',
            'quoted_post_id' => 'پست نقل قول',
            'is_draft' => 'حالت پیش‌نویس',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean content from extra spaces
        if ($this->has('content')) {
            $this->merge([
                'content' => trim($this->input('content')),
            ]);
        }

        // Set default reply settings
        if (! $this->has('reply_settings')) {
            $this->merge(['reply_settings' => 'everyone']);
        }
    }
}
