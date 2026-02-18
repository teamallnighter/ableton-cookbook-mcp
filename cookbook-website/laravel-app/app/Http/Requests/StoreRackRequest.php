<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB max
                'mimetypes:application/octet-stream',
                function ($attribute, $value, $fail) {
                    // Check file extension
                    $extension = strtolower($value->getClientOriginalExtension());
                    if ($extension !== 'adg') {
                        $fail('Please upload a valid Ableton Device Group (.adg) file.');
                        return;
                    }
                    
                    // Additional file signature verification
                    $handle = fopen($value->getPathname(), 'rb');
                    if (!$handle) {
                        $fail('Unable to read the uploaded file.');
                        return;
                    }
                    
                    $header = fread($handle, 4);
                    fclose($handle);
                    
                    // Check for gzip header (ADG files are gzipped XML)
                    if (strlen($header) < 2 || substr($header, 0, 2) !== "\x1f\x8b") {
                        $fail('The uploaded file does not appear to be a valid ADG file.');
                    }
                }
            ],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:50',
            'is_public' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'The rack file cannot be larger than 10MB.',
            'file.mimes' => 'Please upload a valid Ableton Device Group (.adg) file.',
            'file.mimetypes' => 'Please upload a valid Ableton Device Group (.adg) file.',
            'title.required' => 'A rack title is required.',
            'title.max' => 'The rack title cannot exceed 255 characters.',
            'description.max' => 'The rack description cannot exceed 1000 characters.',
            'tags.max' => 'You can add a maximum of 10 tags.',
            'tags.*.max' => 'Each tag cannot exceed 50 characters.',
        ];
    }
}