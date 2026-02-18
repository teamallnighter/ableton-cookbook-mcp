<?php

namespace App\Http\Controllers;

use App\Models\Rack;
use App\Services\VirusScanningService;
use App\Services\SecureImageUploadService;
use App\Jobs\VirusScanFileJob;
use App\Enums\ScanStatus;
use App\Enums\ThreatLevel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
// Note: Using built-in PHP image functions instead of Intervention Image

class HowToImageController extends Controller
{
    private VirusScanningService $virusScanner;
    private SecureImageUploadService $imageUploadService;
    
    public function __construct(
        VirusScanningService $virusScanner,
        SecureImageUploadService $imageUploadService
    ) {
        $this->virusScanner = $virusScanner;
        $this->imageUploadService = $imageUploadService;
    }
    /**
     * Upload images for how-to articles with comprehensive security scanning
     */
    public function upload(Request $request, Rack $rack): JsonResponse
    {
        Log::info('Starting secure image upload', [
            'rack_id' => $rack->id,
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
            'files_count' => count($request->file('images', []))
        ]);
        
        // Enhanced validation with security checks
        $validator = Validator::make($request->all(), [
            'images' => 'required|array|max:5',
            'images.*' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120', // 5MB max per image
        ]);

        if ($validator->fails()) {
            Log::warning('Image upload validation failed', [
                'rack_id' => $rack->id,
                'errors' => $validator->errors()->toArray(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user can upload images for this rack
        if (!$this->canUploadImages($request, $rack)) {
            Log::warning('Unauthorized image upload attempt', [
                'rack_id' => $rack->id,
                'user_id' => auth()->id(),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to upload images for this rack'
            ], 403);
        }

        try {
            $uploadedImages = [];
            $securityViolations = [];
            $images = $request->file('images');

            foreach ($images as $index => $image) {
                // Perform immediate security pre-scan
                $preScanResult = $this->performPreScan($image);
                
                if (!$preScanResult['is_safe']) {
                    $securityViolations[] = [
                        'file_index' => $index,
                        'filename' => $image->getClientOriginalName(),
                        'violations' => $preScanResult['violations']
                    ];
                    
                    Log::warning('Image upload blocked by pre-scan', [
                        'filename' => $image->getClientOriginalName(),
                        'violations' => $preScanResult['violations'],
                        'rack_id' => $rack->id
                    ]);
                    
                    continue; // Skip this file
                }
                
                $uploadResult = $this->processAndStoreImageSecurely($image, $rack);
                if ($uploadResult) {
                    $uploadedImages[] = $uploadResult;
                    
                    // Schedule comprehensive virus scan for each uploaded image
                    $this->scheduleVirusScan($uploadResult['file_path'], 'image_upload', [
                        'rack_id' => $rack->id,
                        'original_filename' => $image->getClientOriginalName(),
                        'upload_result' => $uploadResult
                    ]);
                }
            }
            
            $response = [
                'success' => true,
                'message' => 'Images uploaded successfully',
                'images' => $uploadedImages
            ];
            
            if (!empty($securityViolations)) {
                $response['security_warnings'] = [
                    'message' => 'Some files were blocked due to security concerns',
                    'blocked_files' => count($securityViolations),
                    'violations' => $securityViolations
                ];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Secure image upload failed', [
                'rack_id' => $rack->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Image upload failed due to security validation. Please try again.',
                'error_code' => 'UPLOAD_SECURITY_ERROR'
            ], 500);
        }
    }

    /**
     * Get preview of markdown content with images
     */
    public function preview(Request $request, Rack $rack): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'markdown' => 'required|string|max:50000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $markdownService = app(\App\Services\MarkdownService::class);
            $html = $markdownService->parseToHtml($request->input('markdown'));

            return response()->json([
                'success' => true,
                'html' => $html
            ]);

        } catch (\Exception $e) {
            \Log::error('Markdown preview failed', [
                'rack_id' => $rack->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Preview generation failed'
            ], 500);
        }
    }

    /**
     * Delete an uploaded image
     */
    public function delete(Request $request, Rack $rack, string $filename): JsonResponse
    {
        if (!$this->canUploadImages($request, $rack)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            $path = "how-to-images/{$rack->id}/{$filename}";
            
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                
                // Also delete thumbnail if it exists
                $thumbnailPath = "how-to-images/{$rack->id}/thumbs/{$filename}";
                if (Storage::disk('public')->exists($thumbnailPath)) {
                    Storage::disk('public')->delete($thumbnailPath);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Image deleted successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Image not found'
            ], 404);

        } catch (\Exception $e) {
            \Log::error('How-to image deletion failed', [
                'rack_id' => $rack->id,
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Image deletion failed'
            ], 500);
        }
    }

    /**
     * Perform immediate pre-scan security check
     */
    private function performPreScan($imageFile): array
    {
        $violations = [];
        $filename = $imageFile->getClientOriginalName();
        
        try {
            // Check file size
            $maxSize = config('security.image_upload.max_file_size', 5120) * 1024;
            if ($imageFile->getSize() > $maxSize) {
                $violations[] = 'File size exceeds security limit';
            }
            
            // Check for dangerous filename patterns
            if (preg_match('/\.(php|asp|jsp|js|html|htm)$/i', $filename)) {
                $violations[] = 'Dangerous file extension detected';
            }
            
            // Check for double extensions
            if (preg_match('/\..+\./i', $filename)) {
                $violations[] = 'Multiple file extensions detected';
            }
            
            // Quick content scan for embedded scripts
            $content = file_get_contents($imageFile->getRealPath());
            if ($content && preg_match('/<\?php|<script|javascript:/i', substr($content, 0, 2048))) {
                $violations[] = 'Suspicious content detected in image';
            }
            
        } catch (\Exception $e) {
            $violations[] = 'Pre-scan validation failed';
            Log::warning('Image pre-scan failed', [
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
        }
        
        return [
            'is_safe' => empty($violations),
            'violations' => $violations
        ];
    }
    
    /**
     * Schedule comprehensive virus scan for uploaded file
     */
    private function scheduleVirusScan(string $filePath, string $context, array $metadata = []): void
    {
        try {
            VirusScanFileJob::dispatch(
                $filePath,
                $context,
                auth()->id(),
                $metadata
            )->delay(now()->addSeconds(10)); // Small delay to ensure file is stored
            
            Log::info('Virus scan scheduled for uploaded image', [
                'file_path' => basename($filePath),
                'context' => $context,
                'user_id' => auth()->id()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to schedule virus scan', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Process and store uploaded image securely with optimization using built-in PHP functions
     */
    private function processAndStoreImageSecurely($imageFile, Rack $rack): ?array
    {
        try {
            // Generate secure unique filename
            $originalName = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = strtolower($imageFile->getClientOriginalExtension());
            
            // Additional security: sanitize original name and limit length
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', $originalName);
            $safeName = substr($safeName, 0, 50); // Limit length
            if (empty($safeName)) {
                $safeName = 'image';
            }
            
            $filename = $safeName . '_' . time() . '_' . Str::random(8) . '.' . $extension;

            // Create directory structure
            $directory = "how-to-images/{$rack->id}";
            $thumbnailDirectory = "how-to-images/{$rack->id}/thumbs";

            Storage::disk('public')->makeDirectory($directory);
            Storage::disk('public')->makeDirectory($thumbnailDirectory);

            // Get image information
            $imageInfo = getimagesize($imageFile->getRealPath());
            if (!$imageInfo) {
                throw new \Exception('Invalid image file');
            }

            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];
            $mimeType = $imageInfo['mime'];

            // Create image resource based on type
            switch ($mimeType) {
                case 'image/jpeg':
                    $sourceImage = imagecreatefromjpeg($imageFile->getRealPath());
                    break;
                case 'image/png':
                    $sourceImage = imagecreatefrompng($imageFile->getRealPath());
                    break;
                case 'image/gif':
                    $sourceImage = imagecreatefromgif($imageFile->getRealPath());
                    break;
                case 'image/webp':
                    $sourceImage = imagecreatefromwebp($imageFile->getRealPath());
                    break;
                default:
                    throw new \Exception('Unsupported image type');
            }

            if (!$sourceImage) {
                throw new \Exception('Failed to create image resource');
            }

            // Calculate optimized dimensions (max 1200px width)
            $maxWidth = 1200;
            if ($originalWidth > $maxWidth) {
                $ratio = $maxWidth / $originalWidth;
                $newWidth = $maxWidth;
                $newHeight = intval($originalHeight * $ratio);
            } else {
                $newWidth = $originalWidth;
                $newHeight = $originalHeight;
            }

            // Create optimized image
            $optimizedImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Handle transparency for PNG and GIF
            if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                imagealphablending($optimizedImage, false);
                imagesavealpha($optimizedImage, true);
                $transparent = imagecolorallocatealpha($optimizedImage, 255, 255, 255, 127);
                imagefilledrectangle($optimizedImage, 0, 0, $newWidth, $newHeight, $transparent);
            }

            imagecopyresampled($optimizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

            // Save optimized image
            $imagePath = $directory . '/' . $filename;
            $fullImagePath = Storage::disk('public')->path($imagePath);
            
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    imagejpeg($optimizedImage, $fullImagePath, 85);
                    break;
                case 'png':
                    imagepng($optimizedImage, $fullImagePath, 2);
                    break;
                case 'gif':
                    imagegif($optimizedImage, $fullImagePath);
                    break;
                case 'webp':
                    imagewebp($optimizedImage, $fullImagePath, 85);
                    break;
            }

            // Create thumbnail (max 300px width)
            $thumbMaxWidth = 300;
            if ($newWidth > $thumbMaxWidth) {
                $thumbRatio = $thumbMaxWidth / $newWidth;
                $thumbWidth = $thumbMaxWidth;
                $thumbHeight = intval($newHeight * $thumbRatio);
            } else {
                $thumbWidth = $newWidth;
                $thumbHeight = $newHeight;
            }

            $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
            
            // Handle transparency for PNG and GIF
            if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
                $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
                imagefilledrectangle($thumbnail, 0, 0, $thumbWidth, $thumbHeight, $transparent);
            }

            imagecopyresampled($thumbnail, $optimizedImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $newWidth, $newHeight);

            // Save thumbnail
            $thumbnailPath = $thumbnailDirectory . '/' . $filename;
            $fullThumbnailPath = Storage::disk('public')->path($thumbnailPath);
            
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    imagejpeg($thumbnail, $fullThumbnailPath, 80);
                    break;
                case 'png':
                    imagepng($thumbnail, $fullThumbnailPath, 3);
                    break;
                case 'gif':
                    imagegif($thumbnail, $fullThumbnailPath);
                    break;
                case 'webp':
                    imagewebp($thumbnail, $fullThumbnailPath, 80);
                    break;
            }

            // Clean up memory
            imagedestroy($sourceImage);
            imagedestroy($optimizedImage);
            imagedestroy($thumbnail);

            // Get file size
            $fileSize = filesize($fullImagePath);

            return [
                'filename' => $filename,
                'url' => Storage::url($imagePath),
                'thumbnail_url' => Storage::url($thumbnailPath),
                'alt' => $originalName,
                'size' => $fileSize,
                'width' => $newWidth,
                'height' => $newHeight,
                'original_width' => $originalWidth,
                'original_height' => $originalHeight,
                'file_path' => $fullImagePath, // For virus scanning
                'security_status' => 'pending_scan',
                'uploaded_at' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Secure image processing failed', [
                'rack_id' => $rack->id,
                'original_name' => $imageFile->getClientOriginalName(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return null;
        }
    }

    /**
     * Check if the current user can upload images for this rack
     */
    private function canUploadImages(Request $request, Rack $rack): bool
    {
        // For now, allow anyone to upload images for their own racks
        // In production, you might want to add more sophisticated authorization
        
        // Check if user is authenticated
        if (!auth()->check()) {
            return false;
        }

        // Check if user owns the rack or is admin
        $user = auth()->user();
        
        // If rack has a user_id, check ownership
        if ($rack->user_id && $rack->user_id !== $user->id) {
            // Check if user is admin (assuming you have an is_admin field)
            return $user->is_admin ?? false;
        }

        // Allow if no specific owner or user owns the rack
        return true;
    }

    /**
     * Get list of uploaded images for a rack
     */
    public function index(Request $request, Rack $rack): JsonResponse
    {
        if (!$this->canUploadImages($request, $rack)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            $directory = "how-to-images/{$rack->id}";
            $files = Storage::disk('public')->files($directory);

            $images = [];
            foreach ($files as $file) {
                $filename = basename($file);
                $thumbnailPath = "how-to-images/{$rack->id}/thumbs/{$filename}";
                
                $images[] = [
                    'filename' => $filename,
                    'url' => Storage::url($file),
                    'thumbnail_url' => Storage::disk('public')->exists($thumbnailPath) 
                        ? Storage::url($thumbnailPath) 
                        : Storage::url($file),
                    'size' => Storage::disk('public')->size($file),
                    'last_modified' => Storage::disk('public')->lastModified($file)
                ];
            }

            return response()->json([
                'success' => true,
                'images' => $images
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load images'
            ], 500);
        }
    }

    /**
     * Batch delete images
     */
    public function batchDelete(Request $request, Rack $rack): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'filenames' => 'required|array',
            'filenames.*' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        if (!$this->canUploadImages($request, $rack)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            $deleted = [];
            $failed = [];

            foreach ($request->input('filenames') as $filename) {
                $path = "how-to-images/{$rack->id}/{$filename}";
                
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                    
                    // Also delete thumbnail
                    $thumbnailPath = "how-to-images/{$rack->id}/thumbs/{$filename}";
                    if (Storage::disk('public')->exists($thumbnailPath)) {
                        Storage::disk('public')->delete($thumbnailPath);
                    }
                    
                    $deleted[] = $filename;
                } else {
                    $failed[] = $filename;
                }
            }

            return response()->json([
                'success' => true,
                'message' => count($deleted) . ' images deleted successfully',
                'deleted' => $deleted,
                'failed' => $failed
            ]);

        } catch (\Exception $e) {
            \Log::error('Batch image deletion failed', [
                'rack_id' => $rack->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Batch deletion failed'
            ], 500);
        }
    }
}