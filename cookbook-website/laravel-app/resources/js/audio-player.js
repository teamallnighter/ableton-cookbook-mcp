/**
 * Enhanced Audio Player with Waveform Visualization
 * Integrates with Ableton Cookbook markdown extensions
 */

class AudioPlayer {
    constructor(container) {
        this.container = container;
        this.audio = container.querySelector('.audio-player__element');
        this.canvas = container.querySelector('.audio-player__waveform');
        this.playButton = container.querySelector('.audio-player__play');
        this.progressBar = container.querySelector('.audio-player__progress');
        this.track = container.querySelector('.audio-player__track');
        this.thumb = container.querySelector('.audio-player__thumb');
        this.timeDisplay = container.querySelector('.audio-player__time');
        
        this.isPlaying = false;
        this.audioContext = null;
        this.analyser = null;
        this.dataArray = null;
        this.waveformData = null;
        this.animationId = null;
        
        this.init();
    }

    async init() {
        if (!this.audio || !this.canvas) {
            console.error('Audio player: Required elements not found');
            return;
        }

        this.setupEventListeners();
        await this.loadAudio();
        this.drawWaveform();
    }

    setupEventListeners() {
        // Play/pause button
        this.playButton?.addEventListener('click', () => {
            this.togglePlayPause();
        });

        // Audio events
        this.audio.addEventListener('loadedmetadata', () => {
            this.updateTimeDisplay();
        });

        this.audio.addEventListener('timeupdate', () => {
            this.updateProgress();
            this.updateTimeDisplay();
        });

        this.audio.addEventListener('ended', () => {
            this.isPlaying = false;
            this.updatePlayButton();
            this.stopVisualization();
            this.resetProgress();
        });

        this.audio.addEventListener('error', (e) => {
            console.error('Audio loading error:', e);
            this.showError('Failed to load audio file');
        });

        // Progress bar interaction
        this.progressBar?.addEventListener('click', (e) => {
            const rect = this.progressBar.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            const newTime = percent * this.audio.duration;
            
            if (!isNaN(newTime)) {
                this.audio.currentTime = newTime;
            }
        });

        // Keyboard controls
        this.container.addEventListener('keydown', (e) => {
            if (e.target === this.container || this.container.contains(e.target)) {
                switch (e.code) {
                    case 'Space':
                        e.preventDefault();
                        this.togglePlayPause();
                        break;
                    case 'ArrowLeft':
                        e.preventDefault();
                        this.seekRelative(-5);
                        break;
                    case 'ArrowRight':
                        e.preventDefault();
                        this.seekRelative(5);
                        break;
                }
            }
        });
    }

    async loadAudio() {
        try {
            // Create audio context for visualization
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const source = this.audioContext.createMediaElementSource(this.audio);
            
            this.analyser = this.audioContext.createAnalyser();
            this.analyser.fftSize = 256;
            this.analyser.smoothingTimeConstant = 0.8;
            
            source.connect(this.analyser);
            this.analyser.connect(this.audioContext.destination);
            
            this.dataArray = new Uint8Array(this.analyser.frequencyBinCount);
            
            // Load and decode audio for waveform visualization
            if (this.canvas) {
                await this.generateWaveform();
            }
            
        } catch (error) {
            console.warn('Audio context setup failed:', error);
            // Player will still work without visualization
        }
    }

    async generateWaveform() {
        try {
            // Fetch audio file and decode for waveform
            const response = await fetch(this.audio.src);
            const arrayBuffer = await response.arrayBuffer();
            const audioBuffer = await this.audioContext.decodeAudioData(arrayBuffer);
            
            // Extract waveform data
            const channelData = audioBuffer.getChannelData(0);
            const samples = 400; // Number of waveform bars
            const blockSize = Math.floor(channelData.length / samples);
            
            this.waveformData = [];
            
            for (let i = 0; i < samples; i++) {
                let sum = 0;
                for (let j = 0; j < blockSize; j++) {
                    sum += Math.abs(channelData[i * blockSize + j]);
                }
                this.waveformData.push(sum / blockSize);
            }
            
            // Normalize waveform data
            const max = Math.max(...this.waveformData);
            this.waveformData = this.waveformData.map(val => val / max);
            
        } catch (error) {
            console.warn('Waveform generation failed:', error);
            // Create fallback flat waveform
            this.waveformData = new Array(400).fill(0.5);
        }
    }

    drawWaveform() {
        if (!this.canvas || !this.waveformData) return;
        
        const ctx = this.canvas.getContext('2d');
        const width = this.canvas.width;
        const height = this.canvas.height;
        
        // Clear canvas
        ctx.clearRect(0, 0, width, height);
        
        const barWidth = width / this.waveformData.length;
        const progress = this.audio.duration ? (this.audio.currentTime / this.audio.duration) : 0;
        const progressX = progress * width;
        
        // Draw waveform bars
        this.waveformData.forEach((amplitude, i) => {
            const x = i * barWidth;
            const barHeight = amplitude * height * 0.8;
            const y = (height - barHeight) / 2;
            
            // Color bars based on playback progress
            ctx.fillStyle = x < progressX ? '#3b82f6' : '#e5e7eb';
            ctx.fillRect(x, y, barWidth - 1, barHeight);
        });
        
        // Draw progress line
        if (this.isPlaying) {
            ctx.strokeStyle = '#1d4ed8';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(progressX, 0);
            ctx.lineTo(progressX, height);
            ctx.stroke();
        }
    }

    drawVisualization() {
        if (!this.analyser || !this.canvas) return;
        
        this.analyser.getByteFrequencyData(this.dataArray);
        
        const ctx = this.canvas.getContext('2d');
        const width = this.canvas.width;
        const height = this.canvas.height;
        
        // Clear canvas
        ctx.clearRect(0, 0, width, height);
        
        // Draw frequency visualization over waveform
        const barWidth = width / this.dataArray.length;
        
        this.dataArray.forEach((frequency, i) => {
            const x = i * barWidth;
            const barHeight = (frequency / 255) * height * 0.3;
            const y = height - barHeight;
            
            // Create gradient effect
            const gradient = ctx.createLinearGradient(0, height, 0, y);
            gradient.addColorStop(0, 'rgba(59, 130, 246, 0.8)');
            gradient.addColorStop(1, 'rgba(147, 197, 253, 0.4)');
            
            ctx.fillStyle = gradient;
            ctx.fillRect(x, y, barWidth - 1, barHeight);
        });
        
        // Continue animation
        if (this.isPlaying) {
            this.animationId = requestAnimationFrame(() => this.drawVisualization());
        }
    }

    togglePlayPause() {
        if (this.isPlaying) {
            this.pause();
        } else {
            this.play();
        }
    }

    async play() {
        try {
            // Resume audio context if suspended
            if (this.audioContext?.state === 'suspended') {
                await this.audioContext.resume();
            }
            
            await this.audio.play();
            this.isPlaying = true;
            this.updatePlayButton();
            this.startVisualization();
            
        } catch (error) {
            console.error('Play failed:', error);
            this.showError('Playback failed');
        }
    }

    pause() {
        this.audio.pause();
        this.isPlaying = false;
        this.updatePlayButton();
        this.stopVisualization();
    }

    startVisualization() {
        if (this.analyser && this.canvas) {
            this.drawVisualization();
        } else {
            // Fallback to simple waveform progress update
            this.updateWaveformProgress();
        }
    }

    stopVisualization() {
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
            this.animationId = null;
        }
        this.drawWaveform();
    }

    updateWaveformProgress() {
        this.drawWaveform();
        
        if (this.isPlaying) {
            setTimeout(() => this.updateWaveformProgress(), 100);
        }
    }

    updateProgress() {
        if (!this.audio.duration) return;
        
        const progress = this.audio.currentTime / this.audio.duration;
        
        if (this.track) {
            this.track.style.width = `${progress * 100}%`;
        }
        
        if (this.thumb) {
            this.thumb.style.left = `${progress * 100}%`;
        }
    }

    updateTimeDisplay() {
        if (!this.timeDisplay) return;
        
        const current = this.formatTime(this.audio.currentTime || 0);
        const duration = this.formatTime(this.audio.duration || 0);
        
        this.timeDisplay.textContent = `${current} / ${duration}`;
    }

    updatePlayButton() {
        if (!this.playButton) return;
        
        this.playButton.textContent = this.isPlaying ? '⏸' : '▶';
        this.playButton.setAttribute('aria-label', this.isPlaying ? 'Pause' : 'Play');
    }

    seekRelative(seconds) {
        if (!this.audio.duration) return;
        
        const newTime = Math.max(0, Math.min(
            this.audio.duration,
            this.audio.currentTime + seconds
        ));
        
        this.audio.currentTime = newTime;
    }

    resetProgress() {
        this.audio.currentTime = 0;
        this.updateProgress();
        this.updateTimeDisplay();
        this.drawWaveform();
    }

    formatTime(seconds) {
        if (!seconds || isNaN(seconds)) return '0:00';
        
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    showError(message) {
        const errorElement = document.createElement('div');
        errorElement.className = 'audio-player-error text-red-500 text-sm p-2 bg-red-50 rounded';
        errorElement.textContent = message;
        
        this.container.appendChild(errorElement);
        
        // Auto-remove error after 5 seconds
        setTimeout(() => {
            if (errorElement.parentNode) {
                errorElement.parentNode.removeChild(errorElement);
            }
        }, 5000);
    }

    destroy() {
        // Stop any ongoing animation
        this.stopVisualization();
        
        // Close audio context
        if (this.audioContext && this.audioContext.state !== 'closed') {
            this.audioContext.close();
        }
        
        // Remove event listeners would be good but complex to track
        // In production, you'd want to track and remove all listeners
    }
}

// Auto-initialize audio players
document.addEventListener('DOMContentLoaded', function() {
    const audioPlayers = document.querySelectorAll('[data-audio-player]');
    
    audioPlayers.forEach(container => {
        try {
            new AudioPlayer(container);
        } catch (error) {
            console.error('Failed to initialize audio player:', error);
        }
    });
});

// Re-initialize when content is dynamically loaded
if (window.MutationObserver) {
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    const audioPlayers = node.querySelectorAll('[data-audio-player]');
                    audioPlayers.forEach(container => {
                        if (!container._audioPlayerInitialized) {
                            container._audioPlayerInitialized = true;
                            try {
                                new AudioPlayer(container);
                            } catch (error) {
                                console.error('Failed to initialize dynamic audio player:', error);
                            }
                        }
                    });
                }
            });
        });
    });
    
    observer.observe(document.body, { childList: true, subtree: true });
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AudioPlayer;
}

// Global export
window.AudioPlayer = AudioPlayer;