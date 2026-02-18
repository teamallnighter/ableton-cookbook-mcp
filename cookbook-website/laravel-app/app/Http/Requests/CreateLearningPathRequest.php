<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateLearningPathRequest extends FormRequest
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
            'description' => 'required|string|max:2000',
            'path_type' => 'required|string|in:skill_building,genre_mastery,production_workflow,sound_design,mixing_mastering,performance_setup,custom',
            'difficulty_level' => 'required|string|in:beginner,intermediate,advanced',
            'estimated_total_time' => 'nullable|numeric|min:0|max:500', // Max 500 hours
            'prerequisites' => 'nullable|array|max:15',
            'prerequisites.*' => 'string|max:255',
            'required_software' => 'nullable|array|max:10',
            'required_software.*' => 'string|max:255',
            'required_hardware' => 'nullable|array|max:10',
            'required_hardware.*' => 'string|max:255',
            'learning_objectives' => 'required|array|min:1|max:15',
            'learning_objectives.*' => 'string|max:500',
            'skills_taught' => 'nullable|array|max:20',
            'skills_taught.*' => 'string|max:255',
            'has_certificate' => 'boolean',
            'certificate_template' => 'nullable|string|max:100',
            'certificate_requirements' => 'nullable|array',
            'passing_score' => 'nullable|numeric|min:50|max:100',
            'is_free' => 'boolean',
            'path_price' => 'nullable|numeric|min:0|max:999.99',
            'track_time_spent' => 'boolean',
            'require_sequential_completion' => 'boolean',
            'allow_retakes' => 'boolean',
            'max_retakes' => 'nullable|integer|min:1|max:10',
            'cover_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120', // 5MB
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // If not free, price must be provided
            if (!$this->boolean('is_free') && empty($this->input('path_price'))) {
                $validator->errors()->add('path_price', 'Price is required for paid learning paths.');
            }

            // If has certificate, passing score is required
            if ($this->boolean('has_certificate') && empty($this->input('passing_score'))) {
                $validator->errors()->add('passing_score', 'Passing score is required for certified paths.');
            }

            // If max retakes is set, allow_retakes must be true
            if ($this->has('max_retakes') && !$this->boolean('allow_retakes')) {
                $validator->errors()->add('max_retakes', 'Maximum retakes can only be set when retakes are allowed.');
            }

            // Validate certificate requirements if certificate is enabled
            if ($this->boolean('has_certificate')) {
                $requirements = $this->input('certificate_requirements', []);
                if (empty($requirements)) {
                    $this->merge([
                        'certificate_requirements' => [
                            'complete_all_steps' => true,
                            'minimum_score' => $this->input('passing_score', 70),
                            'time_limit' => null,
                        ]
                    ]);
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
            'title.required' => 'Learning path title is required.',
            'title.max' => 'Learning path title cannot exceed 255 characters.',
            'description.required' => 'Learning path description is required.',
            'description.max' => 'Description cannot exceed 2000 characters.',
            'path_type.required' => 'Please select a path type.',
            'path_type.in' => 'Invalid path type selected.',
            'difficulty_level.required' => 'Please select a difficulty level.',
            'difficulty_level.in' => 'Invalid difficulty level selected.',
            'estimated_total_time.max' => 'Total time cannot exceed 500 hours.',
            'learning_objectives.required' => 'At least one learning objective is required.',
            'learning_objectives.min' => 'At least one learning objective is required.',
            'learning_objectives.max' => 'Maximum 15 learning objectives are allowed.',
            'passing_score.min' => 'Passing score must be at least 50%.',
            'passing_score.max' => 'Passing score cannot exceed 100%.',
            'path_price.max' => 'Path price cannot exceed $999.99.',
            'max_retakes.min' => 'Maximum retakes must be at least 1.',
            'max_retakes.max' => 'Maximum retakes cannot exceed 10.',
            'cover_image.image' => 'Cover image must be a valid image file.',
            'cover_image.mimes' => 'Cover image must be in JPEG, JPG, PNG, or WebP format.',
            'cover_image.max' => 'Cover image size cannot exceed 5MB.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'path_type' => 'path type',
            'difficulty_level' => 'difficulty level',
            'estimated_total_time' => 'estimated total time',
            'required_software' => 'required software',
            'required_hardware' => 'required hardware',
            'learning_objectives' => 'learning objectives',
            'skills_taught' => 'skills taught',
            'has_certificate' => 'certificate setting',
            'certificate_template' => 'certificate template',
            'certificate_requirements' => 'certificate requirements',
            'passing_score' => 'passing score',
            'path_price' => 'path price',
            'track_time_spent' => 'time tracking setting',
            'require_sequential_completion' => 'sequential completion setting',
            'allow_retakes' => 'retakes setting',
            'max_retakes' => 'maximum retakes',
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
            'has_certificate' => $this->boolean('has_certificate', false),
            'is_free' => $this->boolean('is_free', true), // Default to free
            'track_time_spent' => $this->boolean('track_time_spent', true),
            'require_sequential_completion' => $this->boolean('require_sequential_completion', false),
            'allow_retakes' => $this->boolean('allow_retakes', true),
        ]);

        // Clean up arrays
        if ($this->has('prerequisites')) {
            $this->merge([
                'prerequisites' => array_filter((array) $this->input('prerequisites'))
            ]);
        }

        if ($this->has('required_software')) {
            $this->merge([
                'required_software' => array_filter((array) $this->input('required_software'))
            ]);
        }

        if ($this->has('required_hardware')) {
            $this->merge([
                'required_hardware' => array_filter((array) $this->input('required_hardware'))
            ]);
        }

        if ($this->has('learning_objectives')) {
            $this->merge([
                'learning_objectives' => array_filter((array) $this->input('learning_objectives'))
            ]);
        }

        if ($this->has('skills_taught')) {
            $this->merge([
                'skills_taught' => array_filter((array) $this->input('skills_taught'))
            ]);
        }

        // Set default passing score for certified paths
        if ($this->boolean('has_certificate') && empty($this->input('passing_score'))) {
            $this->merge(['passing_score' => 70.0]);
        }
    }
}