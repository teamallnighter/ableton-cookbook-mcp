<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddBundleItemRequest extends FormRequest
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
            'item_type' => 'required|string|in:rack,preset,session',
            'item_id' => 'required|integer|min:1',
            'position' => 'nullable|integer|min:0|max:1000',
            'section' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'usage_instructions' => 'nullable|string|max:1000',
            'is_required' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'item_type.required' => 'An item type is required.',
            'item_type.in' => 'The item type must be rack, preset, or session.',
            'item_id.required' => 'An item ID is required.',
            'item_id.integer' => 'The item ID must be a valid number.',
            'item_id.min' => 'The item ID must be at least 1.',
            'position.integer' => 'The position must be a valid number.',
            'position.min' => 'The position must be at least 0.',
            'position.max' => 'The position must not exceed 1000.',
            'section.max' => 'The section name must not exceed 100 characters.',
            'notes.max' => 'The notes must not exceed 500 characters.',
            'usage_instructions.max' => 'The usage instructions must not exceed 1000 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'item_type' => 'item type',
            'item_id' => 'item ID',
            'usage_instructions' => 'usage instructions',
            'is_required' => 'required status',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim and clean string fields
        if ($this->has('section')) {
            $this->merge([
                'section' => trim($this->section) ?: null,
            ]);
        }

        if ($this->has('notes')) {
            $this->merge([
                'notes' => trim($this->notes) ?: null,
            ]);
        }

        if ($this->has('usage_instructions')) {
            $this->merge([
                'usage_instructions' => trim($this->usage_instructions) ?: null,
            ]);
        }

        // Handle boolean flag
        if ($this->has('is_required')) {
            $this->merge([
                'is_required' => filter_var($this->is_required, FILTER_VALIDATE_BOOLEAN)
            ]);
        }

        // Set default position if not provided
        if (!$this->has('position') || $this->position === null) {
            $this->merge(['position' => 0]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that the item exists and is accessible
            $itemType = $this->item_type;
            $itemId = $this->item_id;

            if ($itemType && $itemId) {
                $modelClass = $this->getModelClass($itemType);
                
                if (!$modelClass) {
                    $validator->errors()->add('item_type', 'Invalid item type specified.');
                    return;
                }

                $item = $modelClass::find($itemId);
                
                if (!$item) {
                    $validator->errors()->add('item_id', 'The specified item does not exist.');
                    return;
                }

                // Check if the item is published and public, or if the user owns it
                $user = auth()->user();
                if (!$item->is_public && $item->user_id !== $user->id) {
                    $validator->errors()->add('item_id', 'You can only add your own private items to bundles.');
                }
            }
        });
    }

    /**
     * Get the model class for the given item type.
     */
    private function getModelClass(string $itemType): ?string
    {
        return match($itemType) {
            'rack' => \App\Models\Rack::class,
            'preset' => \App\Models\Preset::class,
            'session' => \App\Models\Session::class,
            default => null
        };
    }
}