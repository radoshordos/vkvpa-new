import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/vizualizace.js', 'resources/js/vizualizer.js', 'resources/js/porovnani.js', 'resources/js/dashboard.js', 'resources/js/statistiky.js', 'resources/js/trendy.js', 'resources/js/edi-generator.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
