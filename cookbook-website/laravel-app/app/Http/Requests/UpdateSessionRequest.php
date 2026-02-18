<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSessionRequest extends FormRequest
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
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'nullable|string|in:production,template,remix,stems,loop_pack,sample_pack,tutorial',
            'genre' => 'nullable|string|max:50',
            'how_to_article' => 'nullable|string|max:100000', // 100KB markdown content
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
            'title.required' => 'A title is required.',
            'title.max' => 'The title must not exceed 255 characters.',
            'description.max' => 'The description must not exceed 1000 characters.',
            'category.in' => 'The selected category is invalid.',
            'genre.max' => 'The genre must not exceed 50 characters.',
            'how_to_article.max' => 'The how-to article must not exceed 100,000 characters.',
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
            'how_to_article' => 'how-to article',
            'tags.*' => 'tag',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim and clean fields
        if ($this->has('title')) {
            $this->merge([
                'title' => trim($this->title),
            ]);
        }

        if ($this->has('description')) {
            $this->merge([
                'description' => trim($this->description) ?: null,
            ]);
        }

        if ($this->has('category')) {
            $this->merge([
                'category' => trim($this->category) ?: null,
            ]);
        }

        if ($this->has('genre')) {
            $this->merge([
                'genre' => trim($this->genre) ?: null,
            ]);
        }

        if ($this->has('how_to_article')) {
            $this->merge([
                'how_to_article' => trim($this->how_to_article) ?: null,
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