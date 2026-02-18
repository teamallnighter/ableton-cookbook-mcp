<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

class MimeTypeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Register custom MIME type for .adg files
        Validator::extend('adg_file', function ($attribute, $value, $parameters, $validator) {
            if (!$value instanceof \Illuminate\Http\UploadedFile) {
                return false;
            }
            
            // Check file extension
            $extension = strtolower($value->getClientOriginalExtension());
            if ($extension !== 'adg') {
                return false;
            }
            
            // Check if it's a valid gzipped file by trying to read the first few bytes
            $content = file_get_contents($value->getPathname(), false, null, 0, 10);
            
            // ADG files are gzipped, so they should start with the gzip magic number
            return $content && (substr($content, 0, 2) === "\x1f\x8b");
        });
        
        // Add custom validation message
        Validator::replacer('adg_file', function ($message, $attribute, $rule, $parameters) {
            return 'The :attribute must be a valid Ableton rack file (.adg).';
        });
    }
}