<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBundleRequest extends FormRequest
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
            'bundle_type' => 'sometimes|string|in:production,template,sample_pack,tutorial,remix_stems',
            'genre' => 'nullable|string|max:50',
            'difficulty_level' => 'nullable|string|in:beginner,intermediate,advanced',
            'category' => 'nullable|string|max:50',
            'how_to_article' => 'nullable|string|max:100000', // 100KB markdown content
            'is_free' => 'boolean',
            'bundle_price' => 'nullable|numeric|min:0|max:999.99',
            'allow_individual_downloads' => 'boolean',
            'require_full_download' => 'boolean',
            'estimated_completion_time' => 'nullable|numeric|min:0|max:100', // Hours
            'required_packs' => 'nullable|array|max:20',
            'required_packs.*' => 'string|max:100',
            'required_plugins' => 'nullable|array|max:50',
            'required_plugins.*' => 'string|max:100',
            'min_ableton_version' => 'nullable|string|max:20',
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
            'bundle_type.in' => 'The selected bundle type is invalid.',
            'difficulty_level.in' => 'The selected difficulty level is invalid.',
            'genre.max' => 'The genre must not exceed 50 characters.',
            'category.max' => 'The category must not exceed 50 characters.',
            'how_to_article.max' => 'The how-to article must not exceed 100,000 characters.',
            'bundle_price.numeric' => 'The price must be a valid number.',
            'bundle_price.min' => 'The price must be at least $0.00.',
            'bundle_price.max' => 'The price must not exceed $999.99.',
            'estimated_completion_time.numeric' => 'The estimated completion time must be a valid number.',
            'estimated_completion_time.min' => 'The estimated completion time must be at least 0 hours.',
            'estimated_completion_time.max' => 'The estimated completion time must not exceed 100 hours.',
            'required_packs.max' => 'You can specify a maximum of 20 required packs.',
            'required_packs.*.max' => 'Each required pack name must not exceed 100 characters.',
            'required_plugins.max' => 'You can specify a maximum of 50 required plugins.',
            'required_plugins.*.max' => 'Each required plugin name must not exceed 100 characters.',
            'min_ableton_version.max' => 'The minimum Ableton version must not exceed 20 characters.',
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
            'bundle_type' => 'bundle type',
            'difficulty_level' => 'difficulty level',
            'bundle_price' => 'price',
            'how_to_article' => 'how-to article',
            'allow_individual_downloads' => 'allow individual downloads',
            'require_full_download' => 'require full download',
            'estimated_completion_time' => 'estimated completion time',
            'min_ableton_version' => 'minimum Ableton version',
            'required_packs.*' => 'required pack',
            'required_plugins.*' => 'required plugin',
            'tags.*' => 'tag',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim and clean basic fields
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

        if ($this->has('genre')) {
            $this->merge([
                'genre' => trim($this->genre) ?: null,
            ]);
        }

        if ($this->has('category')) {
            $this->merge([
                'category' => trim($this->category) ?: null,
            ]);
        }

        if ($this->has('how_to_article')) {
            $this->merge([
                'how_to_article' => trim($this->how_to_article) ?: null,
            ]);
        }

        // Handle pricing logic
        if ($this->has('is_free')) {
            $isFree = filter_var($this->is_free, FILTER_VALIDATE_BOOLEAN);
            $this->merge(['is_free' => $isFree]);

            // If it's free, clear the price
            if ($isFree) {
                $this->merge(['bundle_price' => null]);
            }
        }

        // Handle boolean flags
        if ($this->has('allow_individual_downloads')) {
            $this->merge([
                'allow_individual_downloads' => filter_var($this->allow_individual_downloads, FILTER_VALIDATE_BOOLEAN)
            ]);
        }

        if ($this->has('require_full_download')) {
            $this->merge([
                'require_full_download' => filter_var($this->require_full_download, FILTER_VALIDATE_BOOLEAN)
            ]);
        }

        // Clean arrays
        if ($this->has('required_packs') && is_array($this->required_packs)) {
            $cleanPacks = array_filter(
                array_map('trim', $this->required_packs),
                fn($pack) => !empty($pack)
            );
            $this->merge([
                'required_packs' => array_values($cleanPacks),
            ]);
        }

        if ($this->has('required_plugins') && is_array($this->required_plugins)) {
            $cleanPlugins = array_filter(
                array_map('trim', $this->required_plugins),
                fn($plugin) => !empty($plugin)
            );
            $this->merge([
                'required_plugins' => array_values($cleanPlugins),
            ]);
        }

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

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // If not free, price should be required and greater than 0
            if ($this->has('is_free') && !$this->is_free && (!$this->bundle_price || $this->bundle_price <= 0)) {
                $validator->errors()->add('bundle_price', 'A price is required for paid bundles.');
            }

            // Individual downloads and full download requirements are mutually exclusive
            if ($this->allow_individual_downloads && $this->require_full_download) {
                $validator->errors()->add('require_full_download', 'Cannot require full download when individual downloads are allowed.');
            }
        });
    }
}