<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * D2 Template Engine for Ableton Cookbook
 * 
 * Provides reusable D2 templates with educational tooltips and advanced styling
 */
class D2TemplateEngine 
{
    private const TEMPLATE_PATH = 'templates/d2';

    /**
     * Educational content for device types and techniques
     */
    private const EDUCATIONAL_CONTENT = [
        'drum_rack' => [
            'title' => 'Drum Rack Fundamentals',
            'description' => 'Drum Racks map MIDI notes to individual drum samples or synthesizers',
            'tips' => [
                'Use C1 (36) for kicks, D1 (38) for snares - standard GM mapping',
                'Layer multiple devices per pad for richer sounds',
                'Use velocity ranges to create dynamic performances',
                'Solo chains to isolate sounds during mixing'
            ],
            'techniques' => [
                'Parallel Compression' => 'Route drums to multiple chains for parallel processing',
                'Frequency Splitting' => 'Use EQ8 to split drums across frequency ranges',
                'Dynamic Layering' => 'Use velocity ranges to trigger different samples'
            ]
        ],
        'synthesizer' => [
            'title' => 'Synthesizer Design',
            'description' => 'Synthesizers generate sound using oscillators, filters, and envelopes',
            'tips' => [
                'Start with basic waveforms (sine, saw, square) and shape with filters',
                'Use LFOs for movement and modulation',
                'Layer oscillators for complex timbres',
                'Apply effects to create unique textures'
            ],
            'techniques' => [
                'FM Synthesis' => 'Frequency modulation creates complex harmonic content',
                'Wavetable Scanning' => 'Morph between different waveforms over time',
                'Ring Modulation' => 'Multiply signals for metallic, bell-like tones'
            ]
        ],
        'audio_effect' => [
            'title' => 'Audio Effects Processing',
            'description' => 'Audio effects modify and enhance the sound of instruments and samples',
            'tips' => [
                'EQ before compression for cleaner dynamics',
                'Use reverb sends to create cohesive spaces',
                'Parallel processing for maintaining punch',
                'Automate parameters for dynamic interest'
            ],
            'techniques' => [
                'Sidechain Compression' => 'Duck signals rhythmically for groove',
                'Mid/Side Processing' => 'Process stereo center and sides independently',
                'Multiband Dynamics' => 'Compress different frequency ranges separately'
            ]
        ],
        'midi_effect' => [
            'title' => 'MIDI Effects & Sequencing',
            'description' => 'MIDI effects modify note data before it reaches instruments',
            'tips' => [
                'Arpeggiators turn single notes into patterns',
                'Scale effects ensure notes stay in key',
                'Velocity effects add human-like dynamics',
                'Note Length affects articulation and feel'
            ],
            'techniques' => [
                'Generative Sequences' => 'Use Random and Scale for evolving patterns',
                'Rhythmic Manipulation' => 'Note Echo and Beat Repeat create polyrhythms',
                'Chord Progressions' => 'Chord device builds harmony from single notes'
            ]
        ],
        'performance' => [
            'complexity_score' => [
                'low' => 'Simple rack with few devices - great for CPU efficiency',
                'medium' => 'Moderate complexity - good balance of features and performance',
                'high' => 'Complex rack with many devices - may require powerful CPU'
            ],
            'cpu_usage' => [
                'low' => 'Minimal CPU impact - safe for large sessions',
                'medium' => 'Moderate CPU usage - monitor in complex arrangements',
                'high' => 'CPU intensive - consider freezing tracks when not editing'
            ],
            'optimization_tips' => [
                'Freeze tracks when not editing to reduce CPU load',
                'Use Simpler instead of Sampler for basic playback',
                'Limit reverb instances - use sends for efficiency',
                'Turn off unused devices or entire tracks'
            ]
        ]
    ];

    /**
     * Get educational tooltip content for a specific topic
     */
    public function getEducationalTooltip(string $topic, array $context = []): string
    {
        $content = self::EDUCATIONAL_CONTENT[$topic] ?? null;
        
        if (!$content) {
            return "No educational content available for {$topic}";
        }

        $tooltip = "## {$content['title']}\n\n";
        $tooltip .= "{$content['description']}\n\n";

        if (isset($content['tips'])) {
            $tooltip .= "### 💡 Pro Tips:\n";
            foreach ($content['tips'] as $tip) {
                $tooltip .= "• {$tip}\n";
            }
            $tooltip .= "\n";
        }

        if (isset($content['techniques'])) {
            $tooltip .= "### 🎛️ Advanced Techniques:\n";
            foreach ($content['techniques'] as $technique => $description) {
                $tooltip .= "**{$technique}**: {$description}\n";
            }
            $tooltip .= "\n";
        }

        // Add context-specific information
        if (isset($context['device_count'])) {
            $tooltip .= "### 📊 This Rack:\n";
            $tooltip .= "• Device Count: {$context['device_count']}\n";
        }

        if (isset($context['complexity_score'])) {
            $score = $context['complexity_score'];
            $level = $score > 75 ? 'high' : ($score > 50 ? 'medium' : 'low');
            $tooltip .= "• Complexity: {$score}/100 ({$level})\n";
            
            if (isset(self::EDUCATIONAL_CONTENT['performance']['complexity_score'][$level])) {
                $tooltip .= "• " . self::EDUCATIONAL_CONTENT['performance']['complexity_score'][$level] . "\n";
            }
        }

        return trim($tooltip);
    }

    /**
     * Generate device-specific tooltip with educational content
     */
    public function generateDeviceTooltip(array $device, array $context = []): string
    {
        $deviceName = $device['name'] ?? 'Unknown Device';
        $deviceType = $device['type'] ?? 'unknown';
        $isOn = $device['is_on'] ?? true;

        $tooltip = "# {$deviceName}\n\n";
        $tooltip .= "**Type**: {$deviceType}\n";
        $tooltip .= "**Status**: " . ($isOn ? "🟢 On" : "🔴 Off") . "\n";

        if (isset($device['preset_name'])) {
            $tooltip .= "**Preset**: {$device['preset_name']}\n";
        }

        $tooltip .= "\n";

        // Add device-specific educational content
        $category = $this->categorizeDevice($device);
        if (isset(self::EDUCATIONAL_CONTENT[$category])) {
            $educationalContent = self::EDUCATIONAL_CONTENT[$category];
            
            $tooltip .= "## About {$educationalContent['title']}\n";
            $tooltip .= "{$educationalContent['description']}\n\n";

            // Add one random tip for this device type
            if (isset($educationalContent['tips'])) {
                $randomTip = $educationalContent['tips'][array_rand($educationalContent['tips'])];
                $tooltip .= "### 💡 Quick Tip:\n{$randomTip}\n\n";
            }
        }

        // Add nested chains information
        if (!empty($device['chains'])) {
            $tooltip .= "### 🔗 Contains Chains:\n";
            foreach ($device['chains'] as $index => $chain) {
                $chainName = $chain['name'] ?? "Chain " . ($index + 1);
                $deviceCount = count($chain['devices'] ?? []);
                $tooltip .= "• **{$chainName}**: {$deviceCount} devices\n";
            }
            $tooltip .= "\n";
        }

        // Add performance considerations
        if ($category === 'audio_effect' && isset(self::EDUCATIONAL_CONTENT['performance']['optimization_tips'])) {
            $optimizationTips = self::EDUCATIONAL_CONTENT['performance']['optimization_tips'];
            $randomOptTip = $optimizationTips[array_rand($optimizationTips)];
            $tooltip .= "### ⚡ Performance Tip:\n{$randomOptTip}\n";
        }

        return trim($tooltip);
    }

    /**
     * Generate macro control tooltip with automation suggestions
     */
    public function generateMacroTooltip(array $macro): string
    {
        $macroName = $macro['name'] ?? 'Macro Control';
        $macroValue = round($macro['value'] ?? 0, 2);
        $macroIndex = $macro['index'] ?? 0;

        $tooltip = "# {$macroName}\n\n";
        $tooltip .= "**Current Value**: {$macroValue} (0.0 - 1.0)\n";
        $tooltip .= "**Macro Index**: " . ($macroIndex + 1) . "\n\n";

        $tooltip .= "## 🎛️ Macro Control Tips:\n";
        $tooltip .= "• Map multiple parameters to one macro for complex modulation\n";
        $tooltip .= "• Use Min/Max ranges to limit parameter movement\n";
        $tooltip .= "• Automate macros for evolving textures\n";
        $tooltip .= "• Name macros descriptively for easy recall\n\n";

        $tooltip .= "## 🎪 Creative Ideas:\n";
        
        $creativeIdeas = [
            "Map filter cutoff and resonance for sweeping effects",
            "Control reverb send and feedback for dynamic spaces",
            "Link oscillator pitch and filter for classic synth movements",
            "Map multiple delay times for rhythmic patterns",
            "Control amplitude and panning for movement effects"
        ];
        
        $randomIdea = $creativeIdeas[array_rand($creativeIdeas)];
        $tooltip .= "• {$randomIdea}\n";

        // Add value-specific suggestions
        if ($macroValue > 0.8) {
            $tooltip .= "\n**🔥 High Value Detected**: This macro is nearly maxed out - consider automation for more dynamic control";
        } elseif ($macroValue < 0.2) {
            $tooltip .= "\n**💫 Low Value Detected**: Plenty of headroom available for dramatic changes";
        } else {
            $tooltip .= "\n**⚖️ Balanced Value**: Good starting point for both subtle and dramatic changes";
        }

        return $tooltip;
    }

    /**
     * Generate performance analysis tooltip
     */
    public function generatePerformanceTooltip(array $metrics): string
    {
        $complexity = $metrics['complexity_score'] ?? 50;
        $cpuUsage = $metrics['cpu_usage'] ?? 'medium';
        $deviceCount = $metrics['device_count'] ?? 0;

        $tooltip = "# 📊 Performance Analysis\n\n";
        
        $tooltip .= "**Complexity Score**: {$complexity}/100\n";
        $tooltip .= "**CPU Usage Level**: {$cpuUsage}\n";
        if ($deviceCount > 0) {
            $tooltip .= "**Total Devices**: {$deviceCount}\n";
        }
        $tooltip .= "\n";

        // Complexity analysis
        if ($complexity > 75) {
            $tooltip .= "## 🔥 High Complexity Rack\n";
            $tooltip .= "This rack is quite complex with many devices and signal routing.\n\n";
            $tooltip .= "### Optimization Strategies:\n";
            $tooltip .= "• Consider freezing this track when not editing\n";
            $tooltip .= "• Use groups to organize and potentially bounce sections\n";
            $tooltip .= "• Monitor CPU usage in large sessions\n";
        } elseif ($complexity > 50) {
            $tooltip .= "## ⚖️ Medium Complexity Rack\n";
            $tooltip .= "Well-balanced rack with good functionality without being too heavy.\n\n";
            $tooltip .= "### Usage Notes:\n";
            $tooltip .= "• Should perform well in most sessions\n";
            $tooltip .= "• Good candidate for templates and reuse\n";
        } else {
            $tooltip .= "## 💫 Simple & Efficient Rack\n";
            $tooltip .= "Lightweight rack that's easy on CPU resources.\n\n";
            $tooltip .= "### Benefits:\n";
            $tooltip .= "• Minimal CPU impact - great for large arrangements\n";
            $tooltip .= "• Quick loading and responsive performance\n";
            $tooltip .= "• Perfect for live performance setups\n";
        }

        $tooltip .= "\n### 📚 Learn More:\n";
        $tooltip .= "• CPU optimization techniques\n";
        $tooltip .= "• Track freezing workflows\n";
        $tooltip .= "• Efficient routing strategies\n";

        return $tooltip;
    }

    /**
     * Store D2 template for reuse
     */
    public function storeTemplate(string $name, string $d2Content, array $metadata = []): bool
    {
        $templateData = [
            'name' => $name,
            'content' => $d2Content,
            'metadata' => $metadata,
            'created_at' => now()->toISOString(),
        ];

        $filePath = self::TEMPLATE_PATH . "/{$name}.json";
        
        return Storage::disk('local')->put($filePath, json_encode($templateData, JSON_PRETTY_PRINT));
    }

    /**
     * Load D2 template by name
     */
    public function loadTemplate(string $name): ?array
    {
        $filePath = self::TEMPLATE_PATH . "/{$name}.json";
        
        if (!Storage::disk('local')->exists($filePath)) {
            return null;
        }

        $content = Storage::disk('local')->get($filePath);
        return json_decode($content, true);
    }

    /**
     * Get all available templates
     */
    public function getAvailableTemplates(): array
    {
        if (!Storage::disk('local')->exists(self::TEMPLATE_PATH)) {
            return [];
        }

        $files = Storage::disk('local')->files(self::TEMPLATE_PATH);
        $templates = [];

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $content = Storage::disk('local')->get($file);
                $template = json_decode($content, true);
                
                if ($template) {
                    $templates[] = [
                        'name' => $template['name'],
                        'metadata' => $template['metadata'] ?? [],
                        'created_at' => $template['created_at'] ?? null,
                    ];
                }
            }
        }

        return $templates;
    }

    /**
     * Generate drum pad tooltip with MIDI note information
     */
    public function generateDrumPadTooltip(int $midiNote, ?array $device = null): string
    {
        $noteName = D2DiagramService::MIDI_NOTE_MAP[$midiNote] ?? "Note {$midiNote}";
        
        $tooltip = "# 🥁 {$noteName}\n\n";
        $tooltip .= "**MIDI Note**: {$midiNote}\n";
        
        if ($device) {
            $deviceName = $device['name'] ?? 'Empty Pad';
            $tooltip .= "**Device**: {$deviceName}\n";
            
            if (!empty($device['devices'])) {
                $tooltip .= "**Chain Length**: " . count($device['devices']) . " devices\n";
            }
        } else {
            $tooltip .= "**Status**: Empty Pad\n";
        }
        
        $tooltip .= "\n## 🎵 MIDI Note Info:\n";
        
        // Add contextual information based on MIDI note
        $drumMapping = [
            36 => "Standard kick drum position - fundamental low end",
            38 => "Snare drum - main backbeat element",
            42 => "Closed hi-hat - tight rhythmic element", 
            46 => "Open hi-hat - sustained high-frequency accent",
            49 => "Crash cymbal - dramatic accent and transitions"
        ];
        
        if (isset($drumMapping[$midiNote])) {
            $tooltip .= "• {$drumMapping[$midiNote]}\n";
        }
        
        $tooltip .= "\n## 🎛️ Drum Rack Tips:\n";
        $tooltip .= "• Layer multiple devices for richer drum sounds\n";
        $tooltip .= "• Use velocity ranges for dynamic performance\n";
        $tooltip .= "• Route to different mixer chains for parallel processing\n";
        
        if (!$device) {
            $tooltip .= "\n**💡 This pad is empty** - drag a drum device here to fill it!";
        }
        
        return $tooltip;
    }

    /**
     * Generate key/velocity range tooltip for instrument chains
     */
    public function generateRangeTooltip(array $chain): string
    {
        $chainName = $chain['name'] ?? 'Chain';
        $tooltip = "# 🎹 {$chainName}\n\n";

        if (!empty($chain['annotations']['key_range'])) {
            $keyRange = $chain['annotations']['key_range'];
            $tooltip .= "**Key Range**: {$keyRange['low_key']} - {$keyRange['high_key']}\n";
            
            // Convert MIDI numbers to note names
            $lowNote = $this->midiToNoteName($keyRange['low_key']);
            $highNote = $this->midiToNoteName($keyRange['high_key']);
            $tooltip .= "**Notes**: {$lowNote} - {$highNote}\n";
        }

        if (!empty($chain['annotations']['velocity_range'])) {
            $velRange = $chain['annotations']['velocity_range'];
            $tooltip .= "**Velocity Range**: {$velRange['low_vel']} - {$velRange['high_vel']}\n";
            
            $velDescription = $this->getVelocityDescription($velRange);
            $tooltip .= "**Playing Style**: {$velDescription}\n";
        }

        $tooltip .= "\n## 🎛️ Chain Splitting Tips:\n";
        $tooltip .= "• Use key splits for multi-sampled instruments\n";
        $tooltip .= "• Velocity splits add dynamic expression\n";
        $tooltip .= "• Overlap ranges for smooth transitions\n";
        $tooltip .= "• Solo chains to isolate specific ranges\n";

        if (!empty($chain['devices'])) {
            $tooltip .= "\n## 🔗 Chain Contents:\n";
            foreach ($chain['devices'] as $device) {
                $deviceName = $device['name'] ?? 'Device';
                $isOn = $device['is_on'] ?? true;
                $status = $isOn ? "🟢" : "🔴";
                $tooltip .= "• {$status} {$deviceName}\n";
            }
        }

        return $tooltip;
    }

    /**
     * Convert MIDI note number to note name
     */
    private function midiToNoteName(int $midiNote): string
    {
        $notes = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];
        $octave = floor($midiNote / 12) - 1;
        $noteIndex = $midiNote % 12;
        return $notes[$noteIndex] . $octave;
    }

    /**
     * Get velocity range description
     */
    private function getVelocityDescription(array $velRange): string
    {
        $low = $velRange['low_vel'];
        $high = $velRange['high_vel'];
        
        if ($high <= 40) {
            return "Very soft playing (pianissimo)";
        } elseif ($high <= 70) {
            return "Soft to medium playing (piano to mezzo-forte)";
        } elseif ($high <= 100) {
            return "Medium to loud playing (mezzo-forte to forte)";
        } else {
            return "Loud to very loud playing (forte to fortissimo)";
        }
    }

    /**
     * Categorize device (duplicate from D2DiagramService for tooltip generation)
     */
    private function categorizeDevice(array $device): string
    {
        $deviceType = strtolower($device['type'] ?? '');
        $deviceName = strtolower($device['name'] ?? '');

        if (str_contains($deviceName, 'kick') || str_contains($deviceName, 'snare') || 
            str_contains($deviceName, 'hat') || str_contains($deviceName, 'cymbal')) {
            return 'drum';
        }

        if (str_contains($deviceType, 'analog') || str_contains($deviceType, 'operator') || 
            str_contains($deviceType, 'wavetable')) {
            return 'synthesizer';
        }

        if (str_contains($deviceType, 'simpler') || str_contains($deviceType, 'sampler') || 
            str_contains($deviceType, 'impulse')) {
            return 'sampler';
        }

        if (str_contains($deviceType, 'midi') || str_contains($deviceType, 'arpeggiator')) {
            return 'midi_effect';
        }

        if (str_contains($deviceType, 'reverb') || str_contains($deviceType, 'delay') || 
            str_contains($deviceType, 'compressor') || str_contains($deviceType, 'eq')) {
            return 'audio_effect';
        }

        return 'utility';
    }
}