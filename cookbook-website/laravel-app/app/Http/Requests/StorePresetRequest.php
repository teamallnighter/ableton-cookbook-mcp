<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StorePresetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'nullable|string|max:50',
            'file' => [
                'required',
                File::types(['adv'])
                    ->min(1024) // 1KB minimum
                    ->max(5 * 1024), // 5MB maximum
            ],
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:50',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A preset file is required.',
            'file.mimes' => 'The file must be an Ableton device preset (.adv) file.',
            'file.max' => 'The preset file must not exceed 5MB.',
            'file.min' => 'The preset file must be at least 1KB.',
            'title.required' => 'A title is required.',
            'title.max' => 'The title must not exceed 255 characters.',
            'description.max' => 'The description must not exceed 1000 characters.',
            'category.max' => 'The category must not exceed 50 characters.',
            'tags.max' => 'You can add a maximum of 10 tags.',
            'tags.*.max' => 'Each tag must not exceed 50 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'file' => 'preset file',
            'tags.*' => 'tag',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim and clean the title
        if ($this->has('title')) {
            $this->merge([
                'title' => trim($this->title),
            ]);
        }

        // Trim and clean the description
        if ($this->has('description')) {
            $this->merge([
                'description' => trim($this->description) ?: null,
            ]);
        }

        // Clean tags array
        if ($this->has('tags') && is_array($this->tags)) {
            $cleanTags = array_filter(
                array_map('trim', $this->tags),
                fn($tag) => !empty($tag)
            );
            $this->merge([
                'tags' => array_values($cleanTags),
            ]);
        }
    }
}