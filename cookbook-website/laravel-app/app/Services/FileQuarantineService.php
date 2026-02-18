<?php

namespace App\Services;

use App\Enums\ThreatLevel;
use App\Enums\ScanStatus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Exception;

/**
 * File Quarantine Service
 * 
 * Manages secure isolation and handling of infected files including:
 * - Safe file quarantine with metadata preservation
 * - Quarantine inventory management
 * - Secure file disposal procedures
 * - Quarantine analytics and reporting
 * - Recovery procedures for false positives
 */
class FileQuarantineService
{
    private const QUARANTINE_DISK = 'quarantine';
    private const QUARANTINE_PATH = 'quarantine';
    private const METADATA_EXTENSION = '.json';
    private const MAX_QUARANTINE_SIZE = 1073741824; // 1GB
    private const QUARANTINE_RETENTION_DAYS = 30;
    
    /**
     * Quarantine an infected file with comprehensive metadata
     */
    public function quarantineFile(
        string $filePath, 
        array $threats, 
        ThreatLevel $threatLevel,
        array $metadata = []
    ): array {
        try {
            Log::info('Starting file quarantine process', [
                'file_path' => basename($filePath),
                'threat_level' => $threatLevel->value,
                'threat_count' => count($threats)
            ]);
            
            // Generate quarantine ID
            $quarantineId = $this->generateQuarantineId($filePath);
            
            // Prepare quarantine metadata
            $quarantineMetadata = $this->prepareQuarantineMetadata(
                $filePath, 
                $threats, 
                $threatLevel, 
                $metadata,
                $quarantineId
            );
            
            // Check quarantine storage capacity
            if (!$this->checkQuarantineCapacity(filesize($filePath))) {
                Log::warning('Quarantine storage capacity exceeded', [
                    'file_size' => filesize($filePath),
                    'available_capacity' => $this->getAvailableQuarantineCapacity()
                ]);
                
                // Clean old quarantine files to make space
                $this->cleanOldQuarantineFiles();
                
                // Re-check capacity
                if (!$this->checkQuarantineCapacity(filesize($filePath))) {
                    throw new Exception('Insufficient quarantine storage capacity');
                }
            }
            
            // Move file to quarantine
            $quarantineResult = $this->moveToQuarantine($filePath, $quarantineId);
            
            if (!$quarantineResult['success']) {
                throw new Exception('Failed to move file to quarantine: ' . $quarantineResult['error']);
            }
            
            // Store quarantine metadata
            $this->storeQuarantineMetadata($quarantineId, $quarantineMetadata);
            
            // Update quarantine inventory
            $this->updateQuarantineInventory($quarantineId, $quarantineMetadata);
            
            // Send quarantine notifications
            $this->sendQuarantineNotification($quarantineMetadata);
            
            Log::info('File successfully quarantined', [
                'quarantine_id' => $quarantineId,
                'original_path' => basename($filePath),
                'threat_level' => $threatLevel->value
            ]);
            
            return [
                'success' => true,
                'quarantine_id' => $quarantineId,
                'quarantine_path' => $quarantineResult['quarantine_path'],
                'metadata' => $quarantineMetadata
            ];
            
        } catch (Exception $e) {
            Log::error('File quarantine failed', [
                'file_path' => basename($filePath),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * List quarantined files with filtering and pagination
     */
    public function listQuarantinedFiles(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        try {
            $inventory = $this->getQuarantineInventory();
            
            // Apply filters
            if (!empty($filters)) {
                $inventory = $this->filterQuarantineInventory($inventory, $filters);
            }
            
            // Sort by quarantine date (newest first)
            usort($inventory, function($a, $b) {
                return strtotime($b['quarantined_at']) - strtotime($a['quarantined_at']);
            });
            
            // Apply pagination
            $total = count($inventory);
            $paginatedItems = array_slice($inventory, $offset, $limit);
            
            return [
                'success' => true,
                'items' => $paginatedItems,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ];
            
        } catch (Exception $e) {
            Log::error('Failed to list quarantined files', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'items' => [],
                'total' => 0
            ];
        }
    }
    
    /**
     * Get detailed information about a quarantined file
     */
    public function getQuarantineDetails(string $quarantineId): array
    {
        try {
            $metadataPath = $this->getQuarantineMetadataPath($quarantineId);
            
            if (!Storage::exists($metadataPath)) {
                throw new Exception('Quarantine metadata not found');
            }
            
            $metadata = json_decode(Storage::get($metadataPath), true);
            
            if (!$metadata) {
                throw new Exception('Failed to decode quarantine metadata');
            }
            
            // Add current analysis
            $metadata['current_analysis'] = $this->analyzeQuarantinedFile($quarantineId);
            
            return [
                'success' => true,
                'quarantine_id' => $quarantineId,
                'metadata' => $metadata
            ];
            
        } catch (Exception $e) {
            Log::error('Failed to get quarantine details', [
                'quarantine_id' => $quarantineId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Restore a quarantined file (for false positives)
     */
    public function restoreQuarantinedFile(string $quarantineId, string $destinationPath, string $reason = ''): array
    {
        try {
            Log::warning('Attempting to restore quarantined file', [
                'quarantine_id' => $quarantineId,
                'destination' => basename($destinationPath),
                'reason' => $reason
            ]);
            
            $quarantinePath = $this->getQuarantinedFilePath($quarantineId);
            
            if (!Storage::exists($quarantinePath)) {
                throw new Exception('Quarantined file not found');
            }
            
            // Verify destination directory exists and is writable
            $destinationDir = dirname($destinationPath);
            if (!is_dir($destinationDir) || !is_writable($destinationDir)) {
                throw new Exception('Destination directory not accessible');
            }
            
            // Move file back
            $restored = Storage::move($quarantinePath, $destinationPath);
            
            if (!$restored) {
                throw new Exception('Failed to restore file from quarantine');
            }
            
            // Update quarantine metadata
            $this->markAsRestored($quarantineId, $destinationPath, $reason);
            
            // Log restoration
            Log::warning('Quarantined file restored', [
                'quarantine_id' => $quarantineId,
                'destination' => basename($destinationPath),
                'reason' => $reason,
                'restored_at' => now()->toISOString()
            ]);
            
            return [
                'success' => true,
                'restored_to' => $destinationPath,
                'quarantine_id' => $quarantineId
            ];
            
        } catch (Exception $e) {
            Log::error('File restoration from quarantine failed', [
                'quarantine_id' => $quarantineId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Permanently delete a quarantined file
     */
    public function deleteQuarantinedFile(string $quarantineId, string $reason = 'Manual deletion'): array
    {
        try {
            Log::info('Deleting quarantined file', [
                'quarantine_id' => $quarantineId,
                'reason' => $reason
            ]);
            
            $quarantinePath = $this->getQuarantinedFilePath($quarantineId);
            $metadataPath = $this->getQuarantineMetadataPath($quarantineId);
            
            // Delete the quarantined file
            if (Storage::exists($quarantinePath)) {
                Storage::delete($quarantinePath);
            }
            
            // Mark metadata as deleted but keep for audit trail
            if (Storage::exists($metadataPath)) {
                $metadata = json_decode(Storage::get($metadataPath), true);
                $metadata['deleted_at'] = now()->toISOString();
                $metadata['deletion_reason'] = $reason;
                $metadata['status'] = 'deleted';
                
                Storage::put($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
            }
            
            // Remove from active inventory
            $this->removeFromQuarantineInventory($quarantineId);
            
            Log::info('Quarantined file deleted', [
                'quarantine_id' => $quarantineId,
                'reason' => $reason
            ]);
            
            return [
                'success' => true,
                'quarantine_id' => $quarantineId,
                'deleted_at' => now()->toISOString()
            ];
            
        } catch (Exception $e) {
            Log::error('Failed to delete quarantined file', [
                'quarantine_id' => $quarantineId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get quarantine statistics
     */
    public function getQuarantineStatistics(): array
    {
        try {
            $inventory = $this->getQuarantineInventory();
            
            $stats = [
                'total_files' => count($inventory),
                'total_size' => 0,
                'threat_levels' => [],
                'threat_types' => [],
                'quarantine_dates' => [],
                'oldest_file' => null,
                'newest_file' => null,
                'storage_usage' => $this->getQuarantineStorageUsage()
            ];
            
            if (empty($inventory)) {
                return $stats;
            }
            
            $dates = [];
            
            foreach ($inventory as $item) {
                // Size calculation
                $stats['total_size'] += $item['file_size'] ?? 0;
                
                // Threat level distribution
                $threatLevel = $item['threat_level'] ?? 'unknown';
                $stats['threat_levels'][$threatLevel] = ($stats['threat_levels'][$threatLevel] ?? 0) + 1;
                
                // Threat type distribution
                foreach ($item['threats'] ?? [] as $threat) {
                    $threatType = $threat['type'] ?? 'unknown';
                    $stats['threat_types'][$threatType] = ($stats['threat_types'][$threatType] ?? 0) + 1;
                }
                
                // Date tracking
                if (isset($item['quarantined_at'])) {
                    $dates[] = $item['quarantined_at'];
                    $dateKey = date('Y-m-d', strtotime($item['quarantined_at']));
                    $stats['quarantine_dates'][$dateKey] = ($stats['quarantine_dates'][$dateKey] ?? 0) + 1;
                }
            }
            
            // Find oldest and newest files
            if (!empty($dates)) {
                sort($dates);
                $stats['oldest_file'] = reset($dates);
                $stats['newest_file'] = end($dates);
            }
            
            return $stats;
            
        } catch (Exception $e) {
            Log::error('Failed to get quarantine statistics', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'total_files' => 0,
                'total_size' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Clean up old quarantine files
     */
    public function cleanOldQuarantineFiles(int $retentionDays = null): array
    {
        $retentionDays = $retentionDays ?? self::QUARANTINE_RETENTION_DAYS;
        $cutoffDate = now()->subDays($retentionDays);
        
        try {
            Log::info('Starting quarantine cleanup', [
                'retention_days' => $retentionDays,
                'cutoff_date' => $cutoffDate->toISOString()
            ]);
            
            $inventory = $this->getQuarantineInventory();
            $deleted = [];
            $errors = [];
            
            foreach ($inventory as $item) {
                if (!isset($item['quarantined_at'])) {
                    continue;
                }
                
                $quarantineDate = strtotime($item['quarantined_at']);
                
                if ($quarantineDate < $cutoffDate->timestamp) {
                    $result = $this->deleteQuarantinedFile(
                        $item['quarantine_id'], 
                        "Automatic cleanup - retention period exceeded ({$retentionDays} days)"
                    );
                    
                    if ($result['success']) {
                        $deleted[] = $item['quarantine_id'];
                    } else {
                        $errors[] = [
                            'quarantine_id' => $item['quarantine_id'],
                            'error' => $result['error']
                        ];
                    }
                }
            }
            
            Log::info('Quarantine cleanup completed', [
                'deleted_count' => count($deleted),
                'error_count' => count($errors),
                'retention_days' => $retentionDays
            ]);
            
            return [
                'success' => true,
                'deleted_count' => count($deleted),
                'deleted_files' => $deleted,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            Log::error('Quarantine cleanup failed', [
                'error' => $e->getMessage(),
                'retention_days' => $retentionDays
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'deleted_count' => 0
            ];
        }
    }
    
    /**
     * Generate unique quarantine ID
     */
    private function generateQuarantineId(string $filePath): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        $hash = substr(hash('sha256', $filePath . microtime(true)), 0, 8);
        
        return "QUAR_{$timestamp}_{$hash}";
    }
    
    /**
     * Prepare comprehensive quarantine metadata
     */
    private function prepareQuarantineMetadata(
        string $filePath, 
        array $threats, 
        ThreatLevel $threatLevel, 
        array $metadata,
        string $quarantineId
    ): array {
        $fileInfo = $this->getFileInfo($filePath);
        
        return [
            'quarantine_id' => $quarantineId,
            'quarantined_at' => now()->toISOString(),
            'original_path' => $filePath,
            'original_filename' => basename($filePath),
            'file_size' => $fileInfo['size'],
            'file_hash' => $fileInfo['hash'],
            'file_type' => $fileInfo['type'],
            'threats' => $threats,
            'threat_level' => $threatLevel->value,
            'threat_count' => count($threats),
            'scan_metadata' => $metadata,
            'quarantine_reason' => $this->generateQuarantineReason($threats, $threatLevel),
            'status' => 'quarantined',
            'user_id' => $metadata['user_id'] ?? null,
            'ip_address' => $metadata['ip_address'] ?? null,
            'user_agent' => $metadata['user_agent'] ?? null,
            'context' => $metadata['context'] ?? 'unknown',
            'system_info' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_hostname' => gethostname(),
                'quarantine_version' => '1.0.0'
            ]
        ];
    }
    
    /**
     * Move file to quarantine storage
     */
    private function moveToQuarantine(string $filePath, string $quarantineId): array
    {
        try {
            // Prepare quarantine paths
            $quarantinePath = self::QUARANTINE_PATH . '/' . $quarantineId;
            
            // Ensure quarantine directory exists
            Storage::makeDirectory(dirname($quarantinePath));
            
            // Move file to quarantine
            $moved = Storage::put($quarantinePath, file_get_contents($filePath));
            
            if (!$moved) {
                throw new Exception('Failed to move file to quarantine storage');
            }
            
            // Remove original file
            unlink($filePath);
            
            return [
                'success' => true,
                'quarantine_path' => $quarantinePath
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Store quarantine metadata
     */
    private function storeQuarantineMetadata(string $quarantineId, array $metadata): void
    {
        $metadataPath = $this->getQuarantineMetadataPath($quarantineId);
        Storage::put($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
    }
    
    /**
     * Update quarantine inventory
     */
    private function updateQuarantineInventory(string $quarantineId, array $metadata): void
    {
        $inventory = $this->getQuarantineInventory();
        
        $inventory[] = [
            'quarantine_id' => $quarantineId,
            'quarantined_at' => $metadata['quarantined_at'],
            'original_filename' => $metadata['original_filename'],
            'file_size' => $metadata['file_size'],
            'threat_level' => $metadata['threat_level'],
            'threat_count' => $metadata['threat_count'],
            'threats' => $metadata['threats'],
            'context' => $metadata['context'],
            'user_id' => $metadata['user_id']
        ];
        
        $this->saveQuarantineInventory($inventory);
    }
    
    /**
     * Get file information
     */
    private function getFileInfo(string $filePath): array
    {
        return [
            'size' => filesize($filePath) ?: 0,
            'hash' => hash_file('sha256', $filePath),
            'type' => mime_content_type($filePath) ?: 'application/octet-stream'
        ];
    }
    
    /**
     * Generate quarantine reason message
     */
    private function generateQuarantineReason(array $threats, ThreatLevel $threatLevel): string
    {
        if (empty($threats)) {
            return 'File quarantined due to security policy';
        }
        
        $threatCount = count($threats);
        
        if ($threatCount === 1) {
            return 'File quarantined due to detected threat: ' . $threats[0]['threat_name'];
        }
        
        $threatNames = array_slice(array_column($threats, 'threat_name'), 0, 3);
        $reason = 'File quarantined due to multiple threats (' . $threatCount . ' total): ' . implode(', ', $threatNames);
        
        if ($threatCount > 3) {
            $reason .= ' and ' . ($threatCount - 3) . ' more';
        }
        
        return $reason;
    }
    
    /**
     * Get quarantine inventory
     */
    private function getQuarantineInventory(): array
    {
        $inventoryPath = 'quarantine_inventory.json';
        
        if (!Storage::exists($inventoryPath)) {
            return [];
        }
        
        $inventory = json_decode(Storage::get($inventoryPath), true);
        
        return $inventory ?: [];
    }
    
    /**
     * Save quarantine inventory
     */
    private function saveQuarantineInventory(array $inventory): void
    {
        $inventoryPath = 'quarantine_inventory.json';
        Storage::put($inventoryPath, json_encode($inventory, JSON_PRETTY_PRINT));
    }
    
    /**
     * Additional helper methods...
     */
    private function checkQuarantineCapacity(int $fileSize): bool
    {
        $currentUsage = $this->getQuarantineStorageUsage();
        return ($currentUsage + $fileSize) <= self::MAX_QUARANTINE_SIZE;
    }
    
    private function getAvailableQuarantineCapacity(): int
    {
        return max(0, self::MAX_QUARANTINE_SIZE - $this->getQuarantineStorageUsage());
    }
    
    private function getQuarantineStorageUsage(): int
    {
        // Implementation would calculate total storage used by quarantine
        return 0; // Placeholder
    }
    
    private function getQuarantinedFilePath(string $quarantineId): string
    {
        return self::QUARANTINE_PATH . '/' . $quarantineId;
    }
    
    private function getQuarantineMetadataPath(string $quarantineId): string
    {
        return self::QUARANTINE_PATH . '/' . $quarantineId . self::METADATA_EXTENSION;
    }
    
    private function filterQuarantineInventory(array $inventory, array $filters): array
    {
        return array_filter($inventory, function($item) use ($filters) {
            foreach ($filters as $key => $value) {
                if (!isset($item[$key]) || $item[$key] !== $value) {
                    return false;
                }
            }
            return true;
        });
    }
    
    private function analyzeQuarantinedFile(string $quarantineId): array
    {
        return [
            'last_analyzed' => now()->toISOString(),
            'analysis_status' => 'quarantined',
            'recommendation' => 'Keep quarantined - contains confirmed threats'
        ];
    }
    
    private function markAsRestored(string $quarantineId, string $destinationPath, string $reason): void
    {
        $metadataPath = $this->getQuarantineMetadataPath($quarantineId);
        
        if (Storage::exists($metadataPath)) {
            $metadata = json_decode(Storage::get($metadataPath), true);
            $metadata['restored_at'] = now()->toISOString();
            $metadata['restored_to'] = $destinationPath;
            $metadata['restoration_reason'] = $reason;
            $metadata['status'] = 'restored';
            
            Storage::put($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
        }
    }
    
    private function removeFromQuarantineInventory(string $quarantineId): void
    {
        $inventory = $this->getQuarantineInventory();
        $inventory = array_filter($inventory, function($item) use ($quarantineId) {
            return $item['quarantine_id'] !== $quarantineId;
        });
        
        $this->saveQuarantineInventory(array_values($inventory));
    }
    
    private function sendQuarantineNotification(array $metadata): void
    {
        // Implementation would send notifications to security team
        Log::info('Quarantine notification would be sent', [
            'quarantine_id' => $metadata['quarantine_id'],
            'threat_level' => $metadata['threat_level']
        ]);
    }
}