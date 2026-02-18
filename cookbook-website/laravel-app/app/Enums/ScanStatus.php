<?php

namespace App\Enums;

/**
 * Virus scan status enumeration
 * 
 * Defines the possible states of a virus scan operation
 */
enum ScanStatus: string
{
    case PENDING = 'pending';
    case SCANNING = 'scanning';
    case CLEAN = 'clean';
    case INFECTED = 'infected';
    case QUARANTINED = 'quarantined';
    case ERROR = 'error';
    case FAILED = 'failed';
    case TIMEOUT = 'timeout';
    case CANCELLED = 'cancelled';
    
    /**
     * Get human-readable label for the status
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Scan',
            self::SCANNING => 'Scanning in Progress',
            self::CLEAN => 'Clean - No Threats Detected',
            self::INFECTED => 'Infected - Threats Found',
            self::QUARANTINED => 'Quarantined - File Isolated',
            self::ERROR => 'Scan Error',
            self::FAILED => 'Scan Failed',
            self::TIMEOUT => 'Scan Timeout',
            self::CANCELLED => 'Scan Cancelled',
        };
    }
    
    /**
     * Get description for the status
     */
    public function description(): string
    {
        return match ($this) {
            self::PENDING => 'File is queued for virus scanning',
            self::SCANNING => 'File is currently being scanned for viruses and malware',
            self::CLEAN => 'File has been scanned and no threats were detected',
            self::INFECTED => 'File contains viruses or malware and cannot be processed',
            self::QUARANTINED => 'File has been moved to quarantine due to detected threats',
            self::ERROR => 'An error occurred during the scanning process',
            self::FAILED => 'Scan could not be completed due to technical issues',
            self::TIMEOUT => 'Scan was terminated due to timeout',
            self::CANCELLED => 'Scan was cancelled by user or system',
        };
    }
    
    /**
     * Check if the status indicates a completed scan
     */
    public function isComplete(): bool
    {
        return in_array($this, [
            self::CLEAN,
            self::INFECTED,
            self::QUARANTINED,
            self::ERROR,
            self::FAILED,
            self::TIMEOUT,
            self::CANCELLED,
        ]);
    }
    
    /**
     * Check if the status indicates a successful scan
     */
    public function isSuccessful(): bool
    {
        return $this === self::CLEAN;
    }
    
    /**
     * Check if the status indicates a threat was found
     */
    public function hasThreat(): bool
    {
        return in_array($this, [
            self::INFECTED,
            self::QUARANTINED,
        ]);
    }
    
    /**
     * Check if the file is safe to process
     */
    public function isSafe(): bool
    {
        return $this === self::CLEAN;
    }
    
    /**
     * Get CSS class for status display
     */
    public function getCssClass(): string
    {
        return match ($this) {
            self::PENDING => 'text-yellow-600 bg-yellow-50',
            self::SCANNING => 'text-blue-600 bg-blue-50 animate-pulse',
            self::CLEAN => 'text-green-600 bg-green-50',
            self::INFECTED, self::QUARANTINED => 'text-red-600 bg-red-50',
            self::ERROR, self::FAILED => 'text-red-600 bg-red-50',
            self::TIMEOUT, self::CANCELLED => 'text-gray-600 bg-gray-50',
        };
    }
    
    /**
     * Get icon for status display
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'clock',
            self::SCANNING => 'refresh',
            self::CLEAN => 'check-circle',
            self::INFECTED, self::QUARANTINED => 'exclamation-triangle',
            self::ERROR, self::FAILED => 'x-circle',
            self::TIMEOUT => 'clock',
            self::CANCELLED => 'x',
        };
    }
}