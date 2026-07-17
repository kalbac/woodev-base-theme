// vite.config.mjs
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [tailwindcss()],
  build: {
    outDir: 'woodev-base-theme/assets/dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        app: 'src/js/app.js',
        style: 'src/css/app.css',
      },
    },
  },
  server: {
    port: 5173,
    strictPort: true,
    cors: true,
  },
});
