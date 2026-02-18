<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCollectionRequest extends FormRequest
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
            'description' => 'nullable|string|max:2000',
            'collection_type' => 'required|string|in:genre_cookbook,technique_masterclass,artist_series,quick_start_pack,preset_library,custom',
            'difficulty_level' => 'required|string|in:beginner,intermediate,advanced',
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
            'is_learning_path' => 'boolean',
            'prerequisites' => 'nullable|array|max:10',
            'prerequisites.*' => 'string|max:255',
            'has_certificate' => 'boolean',
            'learning_objectives' => 'nullable|array|max:10',
            'learning_objectives.*' => 'string|max:500',
            'cover_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120', // 5MB
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:50',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // If not free, price must be provided
            if (!$this->boolean('is_free') && empty($this->input('collection_price'))) {
                $validator->errors()->add('collection_price', 'Price is required for paid collections.');
            }

            // If learning path with certificate, must have learning objectives
            if ($this->boolean('is_learning_path') && $this->boolean('has_certificate')) {
                if (empty($this->input('learning_objectives'))) {
                    $validator->errors()->add('learning_objectives', 'Learning objectives are required for certified learning paths.');
                }
            }

            // Certificate requires learning path
            if ($this->boolean('has_certificate') && !$this->boolean('is_learning_path')) {
                $validator->errors()->add('has_certificate', 'Certificates are only available for learning paths.');
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
            'collection_type.required' => 'Please select a collection type.',
            'collection_type.in' => 'Invalid collection type selected.',
            'difficulty_level.required' => 'Please select a difficulty level.',
            'difficulty_level.in' => 'Invalid difficulty level selected.',
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
            'collection_type' => 'collection type',
            'difficulty_level' => 'difficulty level',
            'estimated_completion_time' => 'estimated completion time',
            'required_packs' => 'required Ableton packs',
            'required_plugins' => 'required plugins',
            'min_ableton_version' => 'minimum Ableton version',
            'collection_price' => 'collection price',
            'allow_individual_downloads' => 'individual downloads setting',
            'require_full_download' => 'full download requirement',
            'is_learning_path' => 'learning path setting',
            'has_certificate' => 'certificate setting',
            'learning_objectives' => 'learning objectives',
            'cover_image' => 'cover image',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure booleans are properly formatted
        $this->merge([
            'is_free' => $this->boolean('is_free', true), // Default to free
            'allow_individual_downloads' => $this->boolean('allow_individual_downloads', true),
            'require_full_download' => $this->boolean('require_full_download', false),
            'is_learning_path' => $this->boolean('is_learning_path', false),
            'has_certificate' => $this->boolean('has_certificate', false),
        ]);

        // Clean up arrays
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
    }
}