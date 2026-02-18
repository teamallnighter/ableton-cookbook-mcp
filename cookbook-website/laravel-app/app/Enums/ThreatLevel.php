<?php

namespace App\Enums;

/**
 * Threat level enumeration for security assessments
 * 
 * Defines severity levels for detected threats and security violations
 */
enum ThreatLevel: string
{
    case NONE = 'none';
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';
    case UNKNOWN = 'unknown';
    
    /**
     * Get human-readable label for the threat level
     */
    public function label(): string
    {
        return match ($this) {
            self::NONE => 'No Threat',
            self::LOW => 'Low Risk',
            self::MEDIUM => 'Medium Risk',
            self::HIGH => 'High Risk',
            self::CRITICAL => 'Critical Threat',
            self::UNKNOWN => 'Unknown Risk',
        };
    }
    
    /**
     * Get description for the threat level
     */
    public function description(): string
    {
        return match ($this) {
            self::NONE => 'No security threats detected',
            self::LOW => 'Minor security concerns that should be monitored',
            self::MEDIUM => 'Moderate security risk that requires attention',
            self::HIGH => 'Serious security threat requiring immediate action',
            self::CRITICAL => 'Severe security threat requiring emergency response',
            self::UNKNOWN => 'Security risk level could not be determined',
        };
    }
    
    /**
     * Get numeric severity score (0-100)
     */
    public function severityScore(): int
    {
        return match ($this) {
            self::NONE => 0,
            self::LOW => 20,
            self::MEDIUM => 40,
            self::HIGH => 70,
            self::CRITICAL => 100,
            self::UNKNOWN => 50,
        };
    }
    
    /**
     * Get recommended action for the threat level
     */
    public function recommendedAction(): string
    {
        return match ($this) {
            self::NONE => 'Continue normal operations',
            self::LOW => 'Monitor and log for patterns',
            self::MEDIUM => 'Investigate and apply security measures',
            self::HIGH => 'Take immediate protective action',
            self::CRITICAL => 'Emergency response - isolate and escalate',
            self::UNKNOWN => 'Investigate to determine appropriate action',
        };
    }
    
    /**
     * Check if the threat level requires immediate action
     */
    public function requiresImmediateAction(): bool
    {
        return in_array($this, [
            self::HIGH,
            self::CRITICAL,
        ]);
    }
    
    /**
     * Check if the threat level allows normal processing
     */
    public function allowsProcessing(): bool
    {
        return in_array($this, [
            self::NONE,
            self::LOW,
        ]);
    }
    
    /**
     * Check if the threat level should block file processing
     */
    public function blocksProcessing(): bool
    {
        return in_array($this, [
            self::MEDIUM,
            self::HIGH,
            self::CRITICAL,
        ]);
    }
    
    /**
     * Check if the threat level requires quarantine
     */
    public function requiresQuarantine(): bool
    {
        return in_array($this, [
            self::HIGH,
            self::CRITICAL,
        ]);
    }
    
    /**
     * Check if the threat level requires security team notification
     */
    public function requiresNotification(): bool
    {
        return in_array($this, [
            self::MEDIUM,
            self::HIGH,
            self::CRITICAL,
        ]);
    }
    
    /**
     * Get CSS class for threat level display
     */
    public function getCssClass(): string
    {
        return match ($this) {
            self::NONE => 'text-green-600 bg-green-50',
            self::LOW => 'text-yellow-600 bg-yellow-50',
            self::MEDIUM => 'text-orange-600 bg-orange-50',
            self::HIGH => 'text-red-600 bg-red-50',
            self::CRITICAL => 'text-red-800 bg-red-100 font-bold',
            self::UNKNOWN => 'text-gray-600 bg-gray-50',
        };
    }
    
    /**
     * Get icon for threat level display
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::NONE => 'shield-check',
            self::LOW => 'information-circle',
            self::MEDIUM => 'exclamation',
            self::HIGH => 'exclamation-triangle',
            self::CRITICAL => 'x-circle',
            self::UNKNOWN => 'question-mark-circle',
        };
    }
    
    /**
     * Get color for threat level visualization
     */
    public function getColor(): string
    {
        return match ($this) {
            self::NONE => '#10B981',     // green
            self::LOW => '#F59E0B',      // yellow
            self::MEDIUM => '#F97316',   // orange
            self::HIGH => '#EF4444',     // red
            self::CRITICAL => '#DC2626', // dark red
            self::UNKNOWN => '#6B7280',  // gray
        };
    }
    
    /**
     * Compare threat levels - returns true if this level is higher than the other
     */
    public function isHigherThan(ThreatLevel $other): bool
    {
        return $this->severityScore() > $other->severityScore();
    }
    
    /**
     * Compare threat levels - returns true if this level is lower than the other
     */
    public function isLowerThan(ThreatLevel $other): bool
    {
        return $this->severityScore() < $other->severityScore();
    }
    
    /**
     * Get the highest threat level from an array of levels
     */
    public static function getHighest(array $levels): self
    {
        if (empty($levels)) {
            return self::NONE;
        }
        
        $highest = self::NONE;
        
        foreach ($levels as $level) {
            if ($level instanceof self && $level->isHigherThan($highest)) {
                $highest = $level;
            }
        }
        
        return $highest;
    }
    
    /**
     * Create threat level from severity score
     */
    public static function fromSeverityScore(int $score): self
    {
        return match (true) {
            $score >= 90 => self::CRITICAL,
            $score >= 70 => self::HIGH,
            $score >= 40 => self::MEDIUM,
            $score >= 20 => self::LOW,
            $score > 0 => self::LOW,
            default => self::NONE,
        };
    }
}