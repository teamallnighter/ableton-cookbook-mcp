<?php

namespace App\Enums;

/**
 * Comprehensive state machine for rack processing lifecycle
 * 
 * This enum defines all possible states a rack can be in during its processing journey,
 * providing clear transitions and enabling robust error handling and recovery.
 */
enum RackProcessingStatus: string
{
    // Initial states
    case UPLOADED = 'uploaded';
    case QUEUED = 'queued';
    
    // Processing states
    case ANALYZING = 'analyzing';
    case ANALYSIS_COMPLETE = 'analysis_complete';
    case PROCESSING_METADATA = 'processing_metadata';
    case READY_FOR_ANNOTATION = 'ready_for_annotation';
    
    // Success states
    case PENDING = 'pending';           // Ready for review/approval
    case APPROVED = 'approved';         // Published and live
    
    // Failure and recovery states
    case FAILED = 'failed';
    case RETRY_SCHEDULED = 'retry_scheduled';
    case PERMANENTLY_FAILED = 'permanently_failed';
    
    // Maintenance states
    case SUSPENDED = 'suspended';       // Temporarily disabled
    case ARCHIVED = 'archived';         // Soft deleted
    
    /**
     * Get human-readable status label
     */
    public function label(): string
    {
        return match($this) {
            self::UPLOADED => 'File Uploaded',
            self::QUEUED => 'Queued for Processing',
            self::ANALYZING => 'Analyzing Rack Structure',
            self::ANALYSIS_COMPLETE => 'Analysis Complete',
            self::PROCESSING_METADATA => 'Processing Metadata',
            self::READY_FOR_ANNOTATION => 'Ready for Annotation',
            self::PENDING => 'Pending Review',
            self::APPROVED => 'Published',
            self::FAILED => 'Processing Failed',
            self::RETRY_SCHEDULED => 'Retry Scheduled',
            self::PERMANENTLY_FAILED => 'Permanently Failed',
            self::SUSPENDED => 'Temporarily Suspended',
            self::ARCHIVED => 'Archived',
        };
    }
    
    /**
     * Get user-friendly description
     */
    public function description(): string
    {
        return match($this) {
            self::UPLOADED => 'Your rack file has been uploaded successfully.',
            self::QUEUED => 'Your rack is queued for processing and will be analyzed shortly.',
            self::ANALYZING => 'We are analyzing your rack structure and extracting device information.',
            self::ANALYSIS_COMPLETE => 'Analysis is complete! You can now add metadata and annotations.',
            self::PROCESSING_METADATA => 'Processing your metadata and preparing the rack for publication.',
            self::READY_FOR_ANNOTATION => 'Your rack is ready for device annotations and final touches.',
            self::PENDING => 'Your rack is ready and awaiting final review.',
            self::APPROVED => 'Your rack is published and available to the community!',
            self::FAILED => 'Processing failed. We will automatically retry or you can try uploading again.',
            self::RETRY_SCHEDULED => 'A retry has been scheduled. Your rack will be processed again shortly.',
            self::PERMANENTLY_FAILED => 'Processing failed permanently. Please check your file and try uploading again.',
            self::SUSPENDED => 'This rack has been temporarily suspended.',
            self::ARCHIVED => 'This rack has been archived.',
        };
    }
    
    /**
     * Determine if this status represents a processing state
     */
    public function isProcessing(): bool
    {
        return in_array($this, [
            self::QUEUED,
            self::ANALYZING,
            self::PROCESSING_METADATA,
        ]);
    }
    
    /**
     * Determine if this status represents a failure state
     */
    public function isFailure(): bool
    {
        return in_array($this, [
            self::FAILED,
            self::PERMANENTLY_FAILED,
        ]);
    }
    
    /**
     * Determine if this status can be retried
     */
    public function isRetryable(): bool
    {
        return in_array($this, [
            self::FAILED,
            self::RETRY_SCHEDULED,
        ]);
    }
    
    /**
     * Determine if this status represents a successful completion
     */
    public function isComplete(): bool
    {
        return in_array($this, [
            self::ANALYSIS_COMPLETE,
            self::READY_FOR_ANNOTATION,
            self::PENDING,
            self::APPROVED,
        ]);
    }
    
    /**
     * Get valid transition states from current status
     */
    public function validTransitions(): array
    {
        return match($this) {
            self::UPLOADED => [self::QUEUED, self::FAILED],
            self::QUEUED => [self::ANALYZING, self::FAILED],
            self::ANALYZING => [self::ANALYSIS_COMPLETE, self::FAILED, self::RETRY_SCHEDULED],
            self::ANALYSIS_COMPLETE => [self::PROCESSING_METADATA, self::READY_FOR_ANNOTATION, self::PENDING],
            self::PROCESSING_METADATA => [self::READY_FOR_ANNOTATION, self::PENDING, self::FAILED],
            self::READY_FOR_ANNOTATION => [self::PENDING, self::APPROVED],
            self::PENDING => [self::APPROVED, self::FAILED],
            self::APPROVED => [self::SUSPENDED, self::ARCHIVED],
            self::FAILED => [self::RETRY_SCHEDULED, self::PERMANENTLY_FAILED, self::QUEUED],
            self::RETRY_SCHEDULED => [self::QUEUED, self::PERMANENTLY_FAILED],
            self::PERMANENTLY_FAILED => [self::QUEUED], // Allow manual retry
            self::SUSPENDED => [self::APPROVED, self::ARCHIVED],
            self::ARCHIVED => [self::APPROVED], // Allow restoration
        };
    }
    
    /**
     * Check if transition to new status is valid
     */
    public function canTransitionTo(RackProcessingStatus $newStatus): bool
    {
        return in_array($newStatus, $this->validTransitions());
    }
    
    /**
     * Get CSS class for status styling
     */
    public function cssClass(): string
    {
        return match($this) {
            self::UPLOADED, self::QUEUED => 'status-info',
            self::ANALYZING, self::PROCESSING_METADATA => 'status-processing',
            self::ANALYSIS_COMPLETE, self::READY_FOR_ANNOTATION => 'status-ready',
            self::PENDING => 'status-pending',
            self::APPROVED => 'status-success',
            self::FAILED, self::PERMANENTLY_FAILED => 'status-error',
            self::RETRY_SCHEDULED => 'status-warning',
            self::SUSPENDED, self::ARCHIVED => 'status-muted',
        };
    }
    
    /**
     * Get icon for status display
     */
    public function icon(): string
    {
        return match($this) {
            self::UPLOADED => 'upload',
            self::QUEUED => 'clock',
            self::ANALYZING => 'cog-spin',
            self::ANALYSIS_COMPLETE => 'check-circle',
            self::PROCESSING_METADATA => 'edit',
            self::READY_FOR_ANNOTATION => 'tags',
            self::PENDING => 'eye',
            self::APPROVED => 'check-double',
            self::FAILED => 'exclamation-triangle',
            self::RETRY_SCHEDULED => 'redo',
            self::PERMANENTLY_FAILED => 'times-circle',
            self::SUSPENDED => 'pause-circle',
            self::ARCHIVED => 'archive',
        };
    }
    
    /**
     * Get progress percentage (0-100) for this status
     */
    public function progressPercentage(): int
    {
        return match($this) {
            self::UPLOADED => 5,
            self::QUEUED => 10,
            self::ANALYZING => 45,
            self::ANALYSIS_COMPLETE => 70,
            self::PROCESSING_METADATA => 85,
            self::READY_FOR_ANNOTATION => 90,
            self::PENDING => 95,
            self::APPROVED => 100,
            self::FAILED, self::PERMANENTLY_FAILED => 0,
            self::RETRY_SCHEDULED => 15,
            self::SUSPENDED, self::ARCHIVED => 100,
        };
    }
}