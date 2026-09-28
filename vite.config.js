import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

const hmrHost = process.env.VITE_HMR_HOST;

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        ...(hmrHost
            ? {
                  hmr: {
                      host: hmrHost,
                  },
              }
            : {}),
    },
});
