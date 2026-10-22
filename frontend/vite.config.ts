import { fileURLToPath, URL } from 'node:url';

import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';

export default defineConfig({
    base: process.env.VITE_BASE_PATH ?? '/',
    plugins: [vue(), tailwindcss()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
    server: {
        port: 5174,
        strictPort: true,
        proxy: {
            '/api': {
                target: 'http://127.0.0.1:8000',
                changeOrigin: true,
            },
            '/images': {
                target: 'http://127.0.0.1:8000',
                changeOrigin: true,
            },
        },
    },
});
