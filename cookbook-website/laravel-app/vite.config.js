import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css', 
                'resources/css/drum-rack.css',
                'resources/js/app.js',
                'resources/js/drum-rack-interactions.js',
                'resources/js/markdown-editor.js'
            ],
            refresh: true,
        }),
    ],
});
