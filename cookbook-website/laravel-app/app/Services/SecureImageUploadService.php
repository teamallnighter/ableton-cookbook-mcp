<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Secure Image Upload Service
 * Implements comprehensive security validation for image uploads
 */
class SecureImageUploadService
{
    private array $allowedMimeTypes;
    private array $allowedExtensions;
    private array $dangerousPatterns;
    private int $maxFileSize;
    private int $maxDimensions;
    
    public function __construct()
    {
        $this->allowedMimeTypes = [
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'image/gif',
            'image/webp'
        ];
        
        $this->allowedExtensions = [
            'jpg', 'jpeg', 'png', 'gif', 'webp'
        ];
        
        $this->dangerousPatterns = [
            // PHP code injection
            '/<\?php/i',
            '/<\?=/i',
            '/<script/i',
            '/eval\s*\(/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/shell_exec\s*\(/i',
            '/passthru\s*\(/i',
            
            // HTML/JS injection in EXIF
            '/javascript:/i',
            '/vbscript:/i',
            '/on\w+\s*=/i',
            
            // File inclusion attempts
            '/include\s*\(/i',
            '/require\s*\(/i',
            '/file_get_contents\s*\(/i',
            '/fopen\s*\(/i',
            
            // SQL injection attempts
            '/union\s+select/i',
            '/drop\s+table/i',
            '/insert\s+into/i',
        ];
        
        $this->maxFileSize = 5120; // 5MB in KB
        $this->maxDimensions = 4000; // 4000px max width/height
    }
    
    /**
     * Comprehensive image security validation
     */
    public function validateImageSecurity(UploadedFile $file, int $index = 0): array
    {
        $result = [
            'valid' => true,
            'error' => null,
            'warnings' => [],
            'security_score' => 100
        ];
        
        try {
            // Basic file validation
            if (!$this->validateBasicFile($file)) {
                return $this->createError('Invalid file or file upload failed');
            }
            
            // File size validation
            if (!$this->validateFileSize($file)) {
                return $this->createError("File too large. Maximum size: {$this->maxFileSize}KB");
            }
            
            // MIME type validation
            if (!$this->validateMimeType($file)) {
                return $this->createError('Invalid image type. Allowed: ' . implode(', ', $this->allowedExtensions));
            }
            
            // File extension validation
            if (!$this->validateExtension($file)) {
                return $this->createError('Invalid file extension');
            }
            
            // Image header validation
            $imageInfo = $this->validateImageHeaders($file);
            if (!$imageInfo['valid']) {
                return $this->createError($imageInfo['error']);
            }
            
            // Dimension validation
            if (!$this->validateDimensions($imageInfo['width'], $imageInfo['height'])) {
                return $this->createError("Image dimensions too large. Maximum: {$this->maxDimensions}px");
            }
            
            // Content scanning for malicious code
            $contentScan = $this->scanFileContent($file);
            if (!$contentScan['valid']) {
                return $this->createError($contentScan['error']);
            }
            
            // EXIF data validation
            $exifScan = $this->validateExifData($file);
            if (!$exifScan['valid']) {
                $result['warnings'][] = $exifScan['error'];
                $result['security_score'] -= 20;
            }
            
            // Filename validation
            if (!$this->validateFilename($file->getClientOriginalName())) {
                $result['warnings'][] = 'Suspicious filename detected';
                $result['security_score'] -= 10;
            }
            
            // File signature validation
            if (!$this->validateFileSignature($file)) {
                return $this->createError('File signature does not match extension');
            }
            
        } catch (\Exception $e) {
            Log::error('Image security validation failed', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->createError('Security validation failed');
        }
        
        return $result;
    }
    
    /**
     * Validate basic file properties
     */
    private function validateBasicFile(UploadedFile $file): bool
    {
        return $file->isValid() && 
               $file->getSize() > 0 && 
               !empty($file->getClientOriginalName());
    }
    
    /**
     * Validate file size
     */
    private function validateFileSize(UploadedFile $file): bool
    {
        return $file->getSize() <= ($this->maxFileSize * 1024);
    }
    
    /**
     * Validate MIME type
     */
    private function validateMimeType(UploadedFile $file): bool
    {
        $mimeType = $file->getMimeType();
        return in_array($mimeType, $this->allowedMimeTypes);
    }
    
    /**
     * Validate file extension
     */
    private function validateExtension(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        return in_array($extension, $this->allowedExtensions);
    }
    
    /**
     * Validate image headers and get dimensions
     */
    private function validateImageHeaders(UploadedFile $file): array
    {
        $imageInfo = @getimagesize($file->getRealPath());
        
        if (!$imageInfo) {
            return [
                'valid' => false,
                'error' => 'Invalid image file or corrupted headers'
            ];
        }
        
        return [
            'valid' => true,
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'type' => $imageInfo[2],
            'mime' => $imageInfo['mime']
        ];
    }
    
    /**
     * Validate image dimensions
     */
    private function validateDimensions(int $width, int $height): bool
    {
        return $width <= $this->maxDimensions && $height <= $this->maxDimensions;
    }
    
    /**
     * Scan file content for malicious patterns
     */
    private function scanFileContent(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());
        
        if ($content === false) {
            return [
                'valid' => false,
                'error' => 'Could not read file content'
            ];
        }
        
        // Check for dangerous patterns
        foreach ($this->dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                Log::warning('Malicious content detected in uploaded image', [
                    'filename' => $file->getClientOriginalName(),
                    'pattern' => $pattern,
                    'user_id' => auth()->id(),
                    'ip' => request()->ip()
                ]);
                
                return [
                    'valid' => false,
                    'error' => 'File contains potentially malicious content'
                ];
            }
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validate EXIF data for suspicious content
     */
    private function validateExifData(UploadedFile $file): array
    {
        if (!function_exists('exif_read_data')) {
            return ['valid' => true]; // Skip if EXIF extension not available
        }
        
        try {
            $exifData = @exif_read_data($file->getRealPath());
            
            if ($exifData) {
                // Check EXIF data for suspicious patterns
                $exifString = serialize($exifData);
                
                foreach ($this->dangerousPatterns as $pattern) {
                    if (preg_match($pattern, $exifString)) {
                        return [
                            'valid' => false,
                            'error' => 'Suspicious content found in image metadata'
                        ];
                    }
                }
            }
            
            return ['valid' => true];
            
        } catch (\Exception $e) {
            // EXIF reading failed, but this shouldn't block the upload
            return ['valid' => true];
        }
    }
    
    /**
     * Validate filename for suspicious patterns
     */
    private function validateFilename(string $filename): bool
    {
        // Check for directory traversal
        if (strpos($filename, '../') !== false || strpos($filename, '..\\') !== false) {
            return false;
        }
        
        // Check for null bytes
        if (strpos($filename, "\0") !== false) {
            return false;
        }
        
        // Check for suspicious extensions (double extensions)
        if (preg_match('/\.(php|phtml|php3|php4|php5|phar|pl|py|jsp|asp|sh)\./', $filename)) {
            return false;
        }
        
        // Check for overly long filename
        if (strlen($filename) > 255) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate file signature matches extension
     */
    private function validateFileSignature(UploadedFile $file): bool
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if (!$handle) {
            return false;
        }
        
        $signature = fread($handle, 10);
        fclose($handle);
        
        $extension = strtolower($file->getClientOriginalExtension());
        
        // Check magic numbers
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                return substr($signature, 0, 3) === "\xFF\xD8\xFF";
                
            case 'png':
                return substr($signature, 0, 8) === "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A";
                
            case 'gif':
                return substr($signature, 0, 6) === "GIF87a" || substr($signature, 0, 6) === "GIF89a";
                
            case 'webp':
                return substr($signature, 0, 4) === "RIFF" && substr($signature, 8, 4) === "WEBP";
                
            default:
                return true; // Unknown extension, allow through
        }
    }
    
    /**
     * Securely process and store image
     */
    public function processAndStoreSecurely(UploadedFile $file, string $directory): array
    {
        // Generate secure filename
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($file->getClientOriginalExtension());
        $secureFilename = $this->generateSecureFilename($originalName, $extension);
        
        // Create full directory path
        $fullDirectory = $directory;
        $thumbnailDirectory = $directory . '/thumbs';
        
        Storage::disk('public')->makeDirectory($fullDirectory);
        Storage::disk('public')->makeDirectory($thumbnailDirectory);
        
        // Process image securely
        $processedImage = $this->processImageSecurely($file, $extension);
        if (!$processedImage) {
            throw new \Exception('Image processing failed');
        }
        
        // Save main image
        $imagePath = $fullDirectory . '/' . $secureFilename;
        $fullImagePath = Storage::disk('public')->path($imagePath);
        
        if (!$this->saveProcessedImage($processedImage, $fullImagePath, $extension)) {
            throw new \Exception('Failed to save processed image');
        }
        
        // Create secure thumbnail
        $thumbnail = $this->createSecureThumbnail($processedImage, 300, 300);
        $thumbnailPath = $thumbnailDirectory . '/' . $secureFilename;
        $fullThumbnailPath = Storage::disk('public')->path($thumbnailPath);
        
        $this->saveProcessedImage($thumbnail, $fullThumbnailPath, $extension);
        
        // Clean up memory
        if (is_resource($processedImage)) {
            imagedestroy($processedImage);
        }
        if (is_resource($thumbnail)) {
            imagedestroy($thumbnail);
        }
        
        // Get final file info
        $fileSize = filesize($fullImagePath);
        $imageInfo = getimagesize($fullImagePath);
        
        return [
            'filename' => $secureFilename,
            'url' => Storage::url($imagePath),
            'thumbnail_url' => Storage::url($thumbnailPath),
            'alt' => $originalName,
            'size' => $fileSize,
            'width' => $imageInfo[0],
            'height' => $imageInfo[1]
        ];
    }
    
    /**
     * Generate secure filename
     */
    private function generateSecureFilename(string $originalName, string $extension): string
    {
        // Sanitize original name
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', $originalName);
        $safeName = substr($safeName, 0, 50); // Limit length
        
        if (empty($safeName)) {
            $safeName = 'image';
        }
        
        // Add timestamp and random string for uniqueness
        $timestamp = date('YmdHis');
        $random = Str::random(8);
        
        return "{$safeName}_{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * Process image securely by recreating it
     */
    private function processImageSecurely(UploadedFile $file, string $extension)
    {
        $sourceImage = null;
        
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $sourceImage = @imagecreatefromjpeg($file->getRealPath());
                break;
            case 'png':
                $sourceImage = @imagecreatefrompng($file->getRealPath());
                break;
            case 'gif':
                $sourceImage = @imagecreatefromgif($file->getRealPath());
                break;
            case 'webp':
                $sourceImage = @imagecreatefromwebp($file->getRealPath());
                break;
        }
        
        if (!$sourceImage) {
            return false;
        }
        
        // Get dimensions
        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);
        
        // Create new clean image
        $cleanImage = imagecreatetruecolor($width, $height);
        
        // Preserve transparency for PNG and GIF
        if ($extension === 'png' || $extension === 'gif') {
            imagealphablending($cleanImage, false);
            imagesavealpha($cleanImage, true);
            $transparent = imagecolorallocatealpha($cleanImage, 255, 255, 255, 127);
            imagefilledrectangle($cleanImage, 0, 0, $width, $height, $transparent);
        }
        
        // Copy image data (this strips EXIF and other metadata)
        imagecopyresampled($cleanImage, $sourceImage, 0, 0, 0, 0, $width, $height, $width, $height);
        
        imagedestroy($sourceImage);
        
        return $cleanImage;
    }
    
    /**
     * Create secure thumbnail
     */
    private function createSecureThumbnail($sourceImage, int $maxWidth, int $maxHeight)
    {
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        
        // Calculate proportional dimensions
        $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
        $newWidth = intval($sourceWidth * $ratio);
        $newHeight = intval($sourceHeight * $ratio);
        
        // Create thumbnail
        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
        
        imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
        
        return $thumbnail;
    }
    
    /**
     * Save processed image
     */
    private function saveProcessedImage($image, string $path, string $extension): bool
    {
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                return imagejpeg($image, $path, 85);
            case 'png':
                return imagepng($image, $path, 2);
            case 'gif':
                return imagegif($image, $path);
            case 'webp':
                return imagewebp($image, $path, 85);
        }
        
        return false;
    }
    
    /**
     * Create error response
     */
    private function createError(string $message): array
    {
        return [
            'valid' => false,
            'error' => $message,
            'warnings' => [],
            'security_score' => 0
        ];
    }
}