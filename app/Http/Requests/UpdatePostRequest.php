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
            'content' => [
                'required',
                'string',
                'max:280',
                'min:1'
            ],
            'edit_reason' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Post content is required',
            'content.max' => 'Post content cannot exceed 280 characters',
            'edit_reason.max' => 'Edit reason cannot exceed 100 characters',
        ];
    }
}