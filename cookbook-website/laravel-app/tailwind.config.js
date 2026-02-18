import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Ableton Sans', 'AbletonSans-Regular', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Clean, simple design system
                'favorite-pink': '#FF1493',  // Hot pink for favorites
                'star-yellow': '#FFD700',    // Yellow for stars
                'vibrant': {
                    'blue': '#0066FF',       // For Live Suite
                    'green': '#00CC66',      // For Live Standard  
                    'orange': '#FF6600',     // For Live Intro
                    'purple': '#9966FF',     // For categories
                    'red': '#FF3366',        // For tags
                    'cyan': '#00CCFF',       // For other highlights
                },
            },
            spacing: {
                // 8px base unit system for consistent spacing
                '18': '4.5rem',   // 72px
                '22': '5.5rem',   // 88px
            },
            animation: {
                // Ableton-inspired subtle animations
                'fade-in': 'fadeIn 0.3s ease-in-out',
                'slide-up': 'slideUp 0.2s ease-out',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                slideUp: {
                    '0%': { transform: 'translateY(10px)', opacity: '0' },
                    '100%': { transform: 'translateY(0)', opacity: '1' },
                },
            },
        },
    },

    plugins: [forms, typography],
};
