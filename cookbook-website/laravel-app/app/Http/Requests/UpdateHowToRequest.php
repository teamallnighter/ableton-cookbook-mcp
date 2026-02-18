<?php

namespace App\Http\Requests;

use App\Services\MarkdownService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHowToRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'how_to_article' => 'required|string|max:100000', // 100KB limit
        ];
    }

    public function messages(): array
    {
        return [
            'how_to_article.required' => 'How-to article content is required.',
            'how_to_article.max' => 'The how-to article cannot exceed 100KB.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!empty($this->how_to_article)) {
                $markdownService = app(MarkdownService::class);
                $issues = $markdownService->validateContent($this->how_to_article);
                
                foreach ($issues as $issue) {
                    $validator->errors()->add('how_to_article', $issue);
                }
            }
        });
    }
}