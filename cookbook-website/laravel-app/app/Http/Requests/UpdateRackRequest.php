<?php

namespace App\Http\Requests;

use App\Services\MarkdownService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'how_to_article' => 'nullable|string|max:100000', // 100KB limit
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:50',
            'is_public' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'title.max' => 'The rack title cannot exceed 255 characters.',
            'description.max' => 'The rack description cannot exceed 1000 characters.',
            'how_to_article.max' => 'The how-to article cannot exceed 100KB.',
            'tags.max' => 'You can add a maximum of 10 tags.',
            'tags.*.max' => 'Each tag cannot exceed 50 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->has('how_to_article') && !empty($this->how_to_article)) {
                $markdownService = app(MarkdownService::class);
                $issues = $markdownService->validateContent($this->how_to_article);
                
                foreach ($issues as $issue) {
                    $validator->errors()->add('how_to_article', $issue);
                }
            }
        });
    }
}