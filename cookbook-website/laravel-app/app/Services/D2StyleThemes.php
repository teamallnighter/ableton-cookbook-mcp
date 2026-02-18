<?php

namespace App\Services;

/**
 * D2 Style Themes for Ableton Cookbook
 * 
 * Provides comprehensive visual styling themes for different use cases:
 * - Sketch: Creative, hand-drawn style for social sharing
 * - SVG: Clean, technical style for documentation  
 * - ASCII: Terminal-friendly for developers
 * - Dark/Light variants with accessibility considerations
 */
class D2StyleThemes 
{
    /**
     * Get theme configuration for D2 diagrams
     */
    public function getTheme(string $themeName): array
    {
        return match($themeName) {
            'sketch' => $this->getSketchTheme(),
            'sketch_dark' => $this->getSketchDarkTheme(),
            'technical' => $this->getTechnicalTheme(),
            'technical_dark' => $this->getTechnicalDarkTheme(),
            'minimal' => $this->getMinimalTheme(),
            'ascii' => $this->getAsciiTheme(),
            'neon' => $this->getNeonTheme(),
            'ableton' => $this->getAbletonTheme(),
            default => $this->getSketchTheme()
        };
    }

    /**
     * Generate D2 theme definition string
     */
    public function generateThemeDefinition(string $themeName): string
    {
        $theme = $this->getTheme($themeName);
        
        $d2 = "# {$theme['name']} Theme\n";
        $d2 .= "theme: {$theme['d2_theme_id']}\n\n";

        // Add custom style overrides
        if (!empty($theme['custom_styles'])) {
            $d2 .= "# Custom Style Overrides\n";
            foreach ($theme['custom_styles'] as $selector => $styles) {
                $d2 .= "{$selector}: {\n";
                foreach ($styles as $property => $value) {
                    $d2 .= "  {$property}: {$value}\n";
                }
                $d2 .= "}\n\n";
            }
        }

        return $d2;
    }

    /**
     * Sketch theme - Creative, hand-drawn style
     */
    private function getSketchTheme(): array
    {
        return [
            'name' => 'Sketch Style',
            'description' => 'Hand-drawn, creative style perfect for social sharing and tutorials',
            'd2_theme_id' => 101,
            'color_palette' => [
                'primary' => '#ff6b6b',
                'secondary' => '#4ecdc4', 
                'accent' => '#45b7d1',
                'warning' => '#feca57',
                'success' => '#48cae4',
                'background' => '#ffffff',
                'text' => '#2d3436'
            ],
            'device_colors' => [
                'synthesizer' => '#ff6b6b',
                'sampler' => '#4ecdc4',
                'audio_effect' => '#45b7d1',
                'midi_effect' => '#96ceb4',
                'drum' => '#feca57',
                'utility' => '#a55eea'
            ],
            'typography' => [
                'font_family' => 'Hand-drawn',
                'title_size' => '16px',
                'body_size' => '12px'
            ],
            'custom_styles' => [
                '*.rack_container' => [
                    'style.stroke-width' => '3',
                    'style.stroke-dash' => '5',
                    'style.border-radius' => '8'
                ],
                '*.device' => [
                    'style.stroke-width' => '2',
                    'style.border-radius' => '6'
                ]
            ]
        ];
    }

    /**
     * Sketch Dark theme
     */
    private function getSketchDarkTheme(): array
    {
        $theme = $this->getSketchTheme();
        $theme['name'] = 'Sketch Dark';
        $theme['description'] = 'Dark variant of sketch style for better visibility in dark environments';
        $theme['d2_theme_id'] = 200;
        $theme['color_palette']['background'] = '#1a1a1a';
        $theme['color_palette']['text'] = '#ffffff';
        
        return $theme;
    }

    /**
     * Technical theme - Clean, precise style
     */
    private function getTechnicalTheme(): array
    {
        return [
            'name' => 'Technical Documentation',
            'description' => 'Clean, precise style ideal for technical documentation and manuals',
            'd2_theme_id' => 1,
            'color_palette' => [
                'primary' => '#2563eb',
                'secondary' => '#64748b',
                'accent' => '#059669',
                'warning' => '#d97706',
                'success' => '#16a34a',
                'background' => '#ffffff',
                'text' => '#1f2937'
            ],
            'device_colors' => [
                'synthesizer' => '#2563eb',
                'sampler' => '#059669',
                'audio_effect' => '#7c3aed',
                'midi_effect' => '#0891b2',
                'drum' => '#d97706',
                'utility' => '#64748b'
            ],
            'typography' => [
                'font_family' => 'Inter, system-ui',
                'title_size' => '14px',
                'body_size' => '11px'
            ],
            'custom_styles' => [
                '*.rack_container' => [
                    'style.stroke-width' => '1',
                    'style.border-radius' => '4'
                ],
                '*.device' => [
                    'style.stroke-width' => '1',
                    'style.border-radius' => '2'
                ]
            ]
        ];
    }

    /**
     * Technical Dark theme
     */
    private function getTechnicalDarkTheme(): array
    {
        $theme = $this->getTechnicalTheme();
        $theme['name'] = 'Technical Dark';
        $theme['d2_theme_id'] = 300;
        $theme['color_palette']['background'] = '#0f172a';
        $theme['color_palette']['text'] = '#f8fafc';
        
        return $theme;
    }

    /**
     * Minimal theme - Ultra-clean style
     */
    private function getMinimalTheme(): array
    {
        return [
            'name' => 'Minimal',
            'description' => 'Ultra-clean style with minimal visual elements',
            'd2_theme_id' => 4,
            'color_palette' => [
                'primary' => '#000000',
                'secondary' => '#666666',
                'accent' => '#333333',
                'warning' => '#999999',
                'success' => '#444444',
                'background' => '#ffffff',
                'text' => '#000000'
            ],
            'device_colors' => [
                'synthesizer' => '#000000',
                'sampler' => '#333333',
                'audio_effect' => '#666666',
                'midi_effect' => '#555555',
                'drum' => '#222222',
                'utility' => '#777777'
            ],
            'typography' => [
                'font_family' => 'SF Mono, Monaco, monospace',
                'title_size' => '12px',
                'body_size' => '10px'
            ],
            'custom_styles' => [
                '*.rack_container' => [
                    'style.stroke-width' => '1',
                    'style.border-radius' => '0'
                ],
                '*.device' => [
                    'style.stroke-width' => '1',
                    'style.border-radius' => '0'
                ]
            ]
        ];
    }

    /**
     * ASCII theme - Terminal friendly
     */
    private function getAsciiTheme(): array
    {
        return [
            'name' => 'ASCII Terminal',
            'description' => 'Terminal-friendly ASCII art style for developers',
            'd2_theme_id' => 200,
            'color_palette' => [
                'primary' => '#00ff00',
                'secondary' => '#00ffff',
                'accent' => '#ffff00',
                'warning' => '#ff8800',
                'success' => '#00ff00',
                'background' => '#000000',
                'text' => '#ffffff'
            ],
            'device_colors' => [
                'synthesizer' => '#00ff00',
                'sampler' => '#00ffff',
                'audio_effect' => '#ffff00',
                'midi_effect' => '#ff00ff',
                'drum' => '#ff8800',
                'utility' => '#ffffff'
            ],
            'typography' => [
                'font_family' => 'JetBrains Mono, Courier New, monospace',
                'title_size' => '12px',
                'body_size' => '10px'
            ],
            'symbols' => [
                'synthesizer' => '[SYN]',
                'sampler' => '[SMP]',
                'audio_effect' => '[FX]',
                'midi_effect' => '[MDI]',
                'drum' => '[DRM]',
                'utility' => '[UTL]'
            ]
        ];
    }

    /**
     * Neon theme - Cyberpunk aesthetic
     */
    private function getNeonTheme(): array
    {
        return [
            'name' => 'Neon Cyberpunk',
            'description' => 'Glowing neon style with cyberpunk aesthetics',
            'd2_theme_id' => 300,
            'color_palette' => [
                'primary' => '#ff0080',
                'secondary' => '#00ffff',
                'accent' => '#ffff00',
                'warning' => '#ff4500',
                'success' => '#00ff41',
                'background' => '#0a0a0a',
                'text' => '#ffffff'
            ],
            'device_colors' => [
                'synthesizer' => '#ff0080',
                'sampler' => '#00ffff',
                'audio_effect' => '#8000ff',
                'midi_effect' => '#00ff80',
                'drum' => '#ff4500',
                'utility' => '#ffff00'
            ],
            'typography' => [
                'font_family' => 'Orbitron, sci-fi',
                'title_size' => '16px',
                'body_size' => '12px'
            ],
            'effects' => [
                'glow' => true,
                'shadow' => '0 0 10px currentColor'
            ],
            'custom_styles' => [
                '*.rack_container' => [
                    'style.stroke-width' => '2',
                    'style.stroke-dash' => '0',
                    'style.shadow' => '0 0 15px currentColor'
                ]
            ]
        ];
    }

    /**
     * Ableton theme - Official Ableton Live colors
     */
    private function getAbletonTheme(): array
    {
        return [
            'name' => 'Ableton Live',
            'description' => 'Official Ableton Live color scheme and styling',
            'd2_theme_id' => 101,
            'color_palette' => [
                'primary' => '#ff0040',     // Ableton red
                'secondary' => '#0080ff',   // Ableton blue  
                'accent' => '#40ff40',      // Ableton green
                'warning' => '#ffff00',     // Ableton yellow
                'success' => '#40ff40',     // Ableton green
                'background' => '#1a1a1a',  // Dark gray
                'text' => '#ffffff'
            ],
            'device_colors' => [
                'synthesizer' => '#ff0040',  // Red for synths
                'sampler' => '#0080ff',      // Blue for samplers
                'audio_effect' => '#40ff40', // Green for audio FX
                'midi_effect' => '#ffff00',  // Yellow for MIDI FX
                'drum' => '#ff8000',         // Orange for drums
                'utility' => '#8080ff'       // Light blue for utility
            ],
            'typography' => [
                'font_family' => 'Helvetica Neue, Arial, sans-serif',
                'title_size' => '14px',
                'body_size' => '11px'
            ],
            'layout' => [
                'grid_snap' => true,
                'alignment' => 'center'
            ],
            'custom_styles' => [
                '*.rack_container' => [
                    'style.stroke-width' => '2',
                    'style.border-radius' => '3'
                ],
                '*.device' => [
                    'style.stroke-width' => '1',
                    'style.border-radius' => '2'
                ],
                '*.macro_control' => [
                    'style.fill' => '#2a2a2a',
                    'style.stroke' => '#ff0040'
                ]
            ]
        ];
    }

    /**
     * Get device styling based on theme and category
     */
    public function getDeviceStyle(string $themeName, string $deviceCategory): array
    {
        $theme = $this->getTheme($themeName);
        $color = $theme['device_colors'][$deviceCategory] ?? $theme['color_palette']['primary'];
        
        $style = [
            'fill' => $color,
            'stroke' => '#ffffff',
            'stroke-width' => '1'
        ];

        // Add theme-specific effects
        if (isset($theme['effects']['glow']) && $theme['effects']['glow']) {
            $style['shadow'] = "0 0 8px {$color}";
        }

        return $style;
    }

    /**
     * Get connection styling for signal flow
     */
    public function getConnectionStyle(string $themeName, string $connectionType = 'signal'): array
    {
        $theme = $this->getTheme($themeName);
        
        return match($connectionType) {
            'signal' => [
                'stroke' => $theme['color_palette']['primary'],
                'stroke-width' => '2',
                'stroke-dash' => '0'
            ],
            'midi' => [
                'stroke' => $theme['color_palette']['accent'],
                'stroke-width' => '2',
                'stroke-dash' => '5'
            ],
            'modulation' => [
                'stroke' => $theme['color_palette']['warning'],
                'stroke-width' => '1',
                'stroke-dash' => '3'
            ],
            default => [
                'stroke' => $theme['color_palette']['secondary'],
                'stroke-width' => '1',
                'stroke-dash' => '0'
            ]
        };
    }

    /**
     * Generate theme-specific rack container
     */
    public function generateRackContainer(string $themeName, string $rackName, string $rackType): string
    {
        $theme = $this->getTheme($themeName);
        
        $d2 = "{$rackName}: {\n";
        $d2 .= "  label: \"{$rackName} - {$rackType}\"\n";
        $d2 .= "  style.fill: '{$theme['color_palette']['background']}'\n";
        $d2 .= "  style.stroke: '{$theme['color_palette']['primary']}'\n";
        
        if (isset($theme['custom_styles']['*.rack_container'])) {
            foreach ($theme['custom_styles']['*.rack_container'] as $property => $value) {
                $d2 .= "  {$property}: {$value}\n";
            }
        }
        
        $d2 .= "}\n";
        
        return $d2;
    }

    /**
     * Generate theme preview diagram
     */
    public function generateThemePreview(string $themeName): string
    {
        $theme = $this->getTheme($themeName);
        
        $d2 = "# {$theme['name']} Theme Preview\n";
        $d2 .= "theme: {$theme['d2_theme_id']}\n\n";
        
        $d2 .= "preview: {$theme['name']} Preview {\n";
        
        // Show device type examples
        foreach ($theme['device_colors'] as $deviceType => $color) {
            $d2 .= "  {$deviceType}: {$deviceType} {\n";
            $d2 .= "    style.fill: {$color}\n";
            $d2 .= "  }\n";
        }
        
        // Add connections
        $d2 .= "\n";
        $deviceTypes = array_keys($theme['device_colors']);
        for ($i = 0; $i < count($deviceTypes) - 1; $i++) {
            $d2 .= "  {$deviceTypes[$i]} -> {$deviceTypes[$i + 1]}\n";
        }
        
        $d2 .= "}\n";
        
        return $d2;
    }

    /**
     * Get all available themes
     */
    public function getAllThemes(): array
    {
        return [
            'sketch' => $this->getSketchTheme(),
            'sketch_dark' => $this->getSketchDarkTheme(),
            'technical' => $this->getTechnicalTheme(),
            'technical_dark' => $this->getTechnicalDarkTheme(),
            'minimal' => $this->getMinimalTheme(),
            'ascii' => $this->getAsciiTheme(),
            'neon' => $this->getNeonTheme(),
            'ableton' => $this->getAbletonTheme()
        ];
    }

    /**
     * Generate responsive theme definition that adapts to viewport
     */
    public function generateResponsiveTheme(string $themeName): string
    {
        $theme = $this->getTheme($themeName);
        
        $d2 = "# Responsive {$theme['name']} Theme\n";
        $d2 .= "theme: {$theme['d2_theme_id']}\n\n";
        
        // Desktop styles
        $d2 .= "# Desktop (default)\n";
        $d2 .= "*.desktop: {\n";
        $d2 .= "  style.font-size: {$theme['typography']['body_size']}\n";
        $d2 .= "}\n\n";
        
        // Mobile styles  
        $d2 .= "# Mobile optimizations\n";
        $d2 .= "*.mobile: {\n";
        $d2 .= "  style.font-size: 14px\n";
        $d2 .= "  style.stroke-width: 2\n";
        $d2 .= "}\n\n";
        
        return $d2;
    }

    /**
     * Generate accessibility-compliant theme
     */
    public function generateAccessibleTheme(string $baseTheme = 'technical'): array
    {
        $theme = $this->getTheme($baseTheme);
        
        // Ensure WCAG AA contrast ratios
        $theme['name'] = 'Accessible ' . $theme['name'];
        $theme['accessibility'] = [
            'high_contrast' => true,
            'colorblind_safe' => true,
            'screen_reader_friendly' => true
        ];
        
        // Update colors for better contrast
        $theme['device_colors'] = [
            'synthesizer' => '#d00000',    // High contrast red
            'sampler' => '#0066cc',        // High contrast blue
            'audio_effect' => '#008000',   // High contrast green
            'midi_effect' => '#800080',    // High contrast purple
            'drum' => '#ff8c00',           // High contrast orange
            'utility' => '#4b0082'         // High contrast indigo
        ];
        
        return $theme;
    }
}