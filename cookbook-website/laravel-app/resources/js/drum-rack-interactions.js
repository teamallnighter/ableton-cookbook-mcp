/**
 * Drum Rack Interactive Features
 * Enhanced interactions for drum rack visualization
 */

// MIDI note to drum type mapping for enhanced pad display
const DRUM_PAD_MAPPING = {
    36: { name: "C1", type: "Kick", color: "#ef4444" },
    37: { name: "C#1", type: "Side Stick", color: "#f59e0b" },
    38: { name: "D1", type: "Snare", color: "#10b981" },
    39: { name: "D#1", type: "Hand Clap", color: "#f59e0b" },
    40: { name: "E1", type: "Snare Alt", color: "#10b981" },
    41: { name: "F1", type: "Low Tom", color: "#3b82f6" },
    42: { name: "F#1", type: "Hi-Hat Closed", color: "#8b5cf6" },
    43: { name: "G1", type: "Low Tom", color: "#3b82f6" },
    44: { name: "G#1", type: "Hi-Hat Pedal", color: "#8b5cf6" },
    45: { name: "A1", type: "Mid Tom", color: "#3b82f6" },
    46: { name: "A#1", type: "Hi-Hat Open", color: "#8b5cf6" },
    47: { name: "B1", type: "High Tom", color: "#3b82f6" },
    48: { name: "C2", type: "Crash", color: "#f59e0b" },
    49: { name: "C#2", type: "Crash", color: "#f59e0b" },
    50: { name: "D2", type: "High Tom", color: "#3b82f6" },
    51: { name: "D#2", type: "Ride", color: "#f59e0b" }
};

// Initialize drum rack interactions
document.addEventListener('DOMContentLoaded', function() {
    initializeDrumRackFeatures();
});

function initializeDrumRackFeatures() {
    // Add keyboard navigation for drum pads
    addKeyboardNavigation();
    
    // Add audio preview support (if available)
    addAudioPreviewSupport();
    
    // Add pad info tooltips
    enhancePadTooltips();
    
    // Add performance monitoring
    addPerformanceVisualization();
    
    // Add accessibility features
    addAccessibilityEnhancements();
}

/**
 * Add keyboard navigation for drum pad grid
 */
function addKeyboardNavigation() {
    const drumPads = document.querySelectorAll('.drum-pad');
    if (!drumPads.length) return;

    drumPads.forEach((pad, index) => {
        pad.setAttribute('tabindex', '0');
        pad.setAttribute('role', 'button');
        
        pad.addEventListener('keydown', function(e) {
            switch(e.key) {
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    pad.click();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    navigateToNextPad(index, 1);
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    navigateToNextPad(index, -1);
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    navigateToNextPad(index, 4);
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    navigateToNextPad(index, -4);
                    break;
            }
        });
    });

    function navigateToNextPad(currentIndex, offset) {
        const nextIndex = currentIndex + offset;
        if (nextIndex >= 0 && nextIndex < drumPads.length) {
            drumPads[nextIndex].focus();
        }
    }
}

/**
 * Add audio preview support for drum pads (if audio files are available)
 */
function addAudioPreviewSupport() {
    const drumPads = document.querySelectorAll('.drum-pad');
    
    drumPads.forEach(pad => {
        const midiNote = pad.dataset.midiNote;
        if (midiNote && DRUM_PAD_MAPPING[midiNote]) {
            pad.addEventListener('mouseenter', function() {
                preloadAudioSample(midiNote);
            });
            
            pad.addEventListener('click', function() {
                playAudioSample(midiNote);
            });
        }
    });
}

/**
 * Preload audio sample for smoother playback
 */
function preloadAudioSample(midiNote) {
    // This would connect to actual audio samples if available
    // For now, we'll just add visual feedback
    const pad = document.querySelector(`[data-midi-note="${midiNote}"]`);
    if (pad) {
        pad.classList.add('hover-preview');
    }
}

/**
 * Play audio sample for drum pad
 */
function playAudioSample(midiNote) {
    // Visual feedback for now - could connect to Web Audio API
    const pad = document.querySelector(`[data-midi-note="${midiNote}"]`);
    if (pad) {
        pad.classList.add('pad-triggered');
        setTimeout(() => {
            pad.classList.remove('pad-triggered');
        }, 200);
        
        // Add ripple effect
        addRippleEffect(pad);
    }
    
    // Here you would actually trigger audio playback
    console.log(`Playing drum sample for MIDI note ${midiNote}`);
}

/**
 * Add ripple effect to drum pads when clicked
 */
function addRippleEffect(element) {
    const ripple = document.createElement('div');
    ripple.className = 'ripple-effect';
    ripple.style.cssText = `
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        transform: scale(0);
        animation: ripple 0.6s linear;
        pointer-events: none;
    `;
    
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = '50%';
    ripple.style.top = '50%';
    ripple.style.transform = 'translate(-50%, -50%) scale(0)';
    
    element.appendChild(ripple);
    
    setTimeout(() => {
        ripple.remove();
    }, 600);
}

/**
 * Enhance pad tooltips with detailed information
 */
function enhancePadTooltips() {
    const drumPads = document.querySelectorAll('.drum-pad');
    
    drumPads.forEach(pad => {
        const midiNote = pad.dataset.midiNote;
        const padInfo = DRUM_PAD_MAPPING[midiNote];
        
        if (padInfo) {
            pad.setAttribute('aria-label', `${padInfo.name} - ${padInfo.type}`);
            
            // Enhanced tooltip on hover
            pad.addEventListener('mouseenter', function(e) {
                showEnhancedTooltip(e, padInfo, pad);
            });
            
            pad.addEventListener('mouseleave', function() {
                hideEnhancedTooltip();
            });
        }
    });
}

function showEnhancedTooltip(event, padInfo, pad) {
    const tooltip = document.createElement('div');
    tooltip.className = 'enhanced-drum-tooltip';
    tooltip.innerHTML = `
        <div class="tooltip-header">${padInfo.name}</div>
        <div class="tooltip-type">${padInfo.type}</div>
        <div class="tooltip-note">MIDI ${event.target.dataset.midiNote}</div>
    `;
    
    tooltip.style.cssText = `
        position: fixed;
        background: #1f2937;
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 12px;
        pointer-events: none;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        transform: translateY(-100%);
    `;
    
    document.body.appendChild(tooltip);
    
    const rect = pad.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - 10 + 'px';
    
    tooltip.setAttribute('data-tooltip', 'active');
}

function hideEnhancedTooltip() {
    const tooltip = document.querySelector('[data-tooltip="active"]');
    if (tooltip) {
        tooltip.remove();
    }
}

/**
 * Add performance visualization animations
 */
function addPerformanceVisualization() {
    const complexityBars = document.querySelectorAll('.complexity-bar');
    
    // Animate complexity bars on scroll into view
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const bar = entry.target;
                const targetWidth = bar.dataset.width || '0%';
                bar.style.width = '0%';
                
                setTimeout(() => {
                    bar.style.width = targetWidth;
                }, 100);
            }
        });
    });
    
    complexityBars.forEach(bar => {
        observer.observe(bar);
    });
    
    // Animate statistics counters
    animateCounters();
}

/**
 * Animate number counters in statistics
 */
function animateCounters() {
    const counters = document.querySelectorAll('.statistic-value, .metric-value');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const counter = entry.target;
                const target = parseInt(counter.textContent);
                let current = 0;
                const increment = target / 30; // 30 steps
                const timer = setInterval(() => {
                    current += increment;
                    counter.textContent = Math.floor(current);
                    
                    if (current >= target) {
                        counter.textContent = target;
                        clearInterval(timer);
                    }
                }, 50);
                
                observer.unobserve(counter);
            }
        });
    });
    
    counters.forEach(counter => {
        observer.observe(counter);
    });
}

/**
 * Add accessibility enhancements
 */
function addAccessibilityEnhancements() {
    // Add ARIA labels for screen readers
    const drumRackVisualizer = document.querySelector('.drum-rack-visualizer');
    if (drumRackVisualizer) {
        drumRackVisualizer.setAttribute('role', 'application');
        drumRackVisualizer.setAttribute('aria-label', 'Drum Rack Visualizer');
    }
    
    // Add live region for pad selection announcements
    const liveRegion = document.createElement('div');
    liveRegion.setAttribute('aria-live', 'polite');
    liveRegion.setAttribute('aria-atomic', 'true');
    liveRegion.style.cssText = 'position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden;';
    liveRegion.id = 'drum-pad-announcements';
    document.body.appendChild(liveRegion);
    
    // Announce pad selections
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('drum-pad')) {
            const midiNote = e.target.dataset.midiNote;
            const padInfo = DRUM_PAD_MAPPING[midiNote];
            
            if (padInfo) {
                const announcement = `Selected ${padInfo.type} on ${padInfo.name}`;
                liveRegion.textContent = announcement;
            }
        }
    });
    
    // Add focus management for modal-like pad details
    addFocusManagement();
}

/**
 * Add focus management for pad details
 */
function addFocusManagement() {
    const padDetails = document.querySelectorAll('[x-show*="selectedPad"]');
    
    padDetails.forEach(detail => {
        detail.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Close pad details and return focus to pad
                const event = new CustomEvent('close-pad-details');
                detail.dispatchEvent(event);
            }
        });
    });
}

/**
 * Add CSS animations for drum rack elements
 */
function addDynamicStyles() {
    const style = document.createElement('style');
    style.textContent = `
        .pad-triggered {
            animation: padTrigger 0.2s ease-out;
        }
        
        @keyframes padTrigger {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        @keyframes ripple {
            to {
                transform: translate(-50%, -50%) scale(4);
                opacity: 0;
            }
        }
        
        .hover-preview {
            box-shadow: 0 0 20px rgba(249, 115, 22, 0.5);
        }
        
        .enhanced-drum-tooltip .tooltip-header {
            font-weight: bold;
            margin-bottom: 2px;
        }
        
        .enhanced-drum-tooltip .tooltip-type {
            color: #d1d5db;
            font-size: 11px;
        }
        
        .enhanced-drum-tooltip .tooltip-note {
            color: #9ca3af;
            font-size: 10px;
            margin-top: 2px;
        }
        
        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .drum-pad {
                border: 2px solid;
            }
            
            .enhanced-drum-tooltip {
                border: 1px solid white;
            }
        }
        
        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            .drum-pad, .complexity-bar, .statistic-value {
                transition: none;
                animation: none;
            }
        }
    `;
    
    document.head.appendChild(style);
}

// Initialize dynamic styles
addDynamicStyles();

// Export functions for external use
window.DrumRackInteractions = {
    playAudioSample,
    addRippleEffect,
    showEnhancedTooltip,
    hideEnhancedTooltip
};