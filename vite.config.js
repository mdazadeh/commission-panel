import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

// No external font/CDN providers: everything is bundled so the app works on the national internet.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    // CSS asset URLs (fonts) must be relative: the app is served from a sub-folder (/commission).
    experimental: {
        renderBuiltUrl(filename, { hostType }) {
            return hostType === 'css' ? { relative: true } : undefined;
        },
    },
    resolve: {
        alias: { '@': '/resources/js' },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
