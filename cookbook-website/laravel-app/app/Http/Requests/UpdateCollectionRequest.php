<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCollectionRequest extends FormRequest
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
            'description' => 'nullable|string|max:2000',
            'how_to_article' => 'nullable|string|max:100000', // 100KB limit
            'difficulty_level' => 'sometimes|required|string|in:beginner,intermediate,advanced',
            'genre' => 'nullable|string|max:100',
            'mood' => 'nullable|string|max:100',
            'energy_level' => 'nullable|string|in:low,medium,high',
            'estimated_completion_time' => 'nullable|numeric|min:0|max:200',
            'required_packs' => 'nullable|array|max:20',
            'required_packs.*' => 'string|max:255',
            'required_plugins' => 'nullable|array|max:20',
            'required_plugins.*' => 'string|max:255',
            'min_ableton_version' => 'nullable|string|max:20',
            'is_free' => 'boolean',
            'collection_price' => 'nullable|numeric|min:0|max:999.99',
            'allow_individual_downloads' => 'boolean',
            'require_full_download' => 'boolean',
            'prerequisites' => 'nullable|array|max:10',
            'prerequisites.*' => 'string|max:255',
            'learning_objectives' => 'nullable|array|max:10',
            'learning_objectives.*' => 'string|max:500',
            'cover_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120', // 5MB
            'remove_cover_image' => 'boolean',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:50',
            // Auto-save fields
            'auto_save' => 'boolean',
            'last_auto_save_session' => 'nullable|string|max:100',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // If not free, price must be provided
            if ($this->has('is_free') && !$this->boolean('is_free') && empty($this->input('collection_price'))) {
                $validator->errors()->add('collection_price', 'Price is required for paid collections.');
            }

            // Validate how-to article size (additional check)
            if ($this->has('how_to_article')) {
                $article = $this->input('how_to_article');
                if (strlen($article) > 100000) {
                    $validator->errors()->add('how_to_article', 'How-to article cannot exceed 100,000 characters.');
                }
            }
        });
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Collection title is required.',
            'title.max' => 'Collection title cannot exceed 255 characters.',
            'difficulty_level.required' => 'Please select a difficulty level.',
            'difficulty_level.in' => 'Invalid difficulty level selected.',
            'how_to_article.max' => 'How-to article cannot exceed 100,000 characters.',
            'estimated_completion_time.numeric' => 'Completion time must be a number.',
            'estimated_completion_time.max' => 'Completion time cannot exceed 200 hours.',
            'required_packs.max' => 'Maximum 20 Ableton packs can be specified.',
            'required_plugins.max' => 'Maximum 20 plugins can be specified.',
            'collection_price.max' => 'Collection price cannot exceed $999.99.',
            'cover_image.image' => 'Cover image must be a valid image file.',
            'cover_image.mimes' => 'Cover image must be in JPEG, JPG, PNG, or WebP format.',
            'cover_image.max' => 'Cover image size cannot exceed 5MB.',
            'tags.max' => 'Maximum 10 tags are allowed.',
            'tags.*.max' => 'Each tag cannot exceed 50 characters.',
            'prerequisites.max' => 'Maximum 10 prerequisites are allowed.',
            'learning_objectives.max' => 'Maximum 10 learning objectives are allowed.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'difficulty_level' => 'difficulty level',
            'estimated_completion_time' => 'estimated completion time',
            'required_packs' => 'required Ableton packs',
            'required_plugins' => 'required plugins',
            'min_ableton_version' => 'minimum Ableton version',
            'collection_price' => 'collection price',
            'allow_individual_downloads' => 'individual downloads setting',
            'require_full_download' => 'full download requirement',
            'learning_objectives' => 'learning objectives',
            'cover_image' => 'cover image',
            'how_to_article' => 'how-to article',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean up arrays if present
        if ($this->has('required_packs')) {
            $this->merge([
                'required_packs' => array_filter((array) $this->input('required_packs'))
            ]);
        }

        if ($this->has('required_plugins')) {
            $this->merge([
                'required_plugins' => array_filter((array) $this->input('required_plugins'))
            ]);
        }

        if ($this->has('prerequisites')) {
            $this->merge([
                'prerequisites' => array_filter((array) $this->input('prerequisites'))
            ]);
        }

        if ($this->has('learning_objectives')) {
            $this->merge([
                'learning_objectives' => array_filter((array) $this->input('learning_objectives'))
            ]);
        }

        if ($this->has('tags')) {
            $this->merge([
                'tags' => array_filter((array) $this->input('tags'))
            ]);
        }

        // Handle auto-save session
        if ($this->boolean('auto_save')) {
            $this->merge([
                'last_auto_save' => now(),
                'last_auto_save_session' => $this->input('last_auto_save_session', session()->getId()),
            ]);
        }
    }

    /**
     * Get validated data with additional processing
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Handle how-to article updates
        if (isset($validated['how_to_article'])) {
            $validated['how_to_updated_at'] = now();
        }

        return $validated;
    }
}