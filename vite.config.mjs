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
        // The theme's one visual identity (ADR-008); Assets.php enqueues it
        // unconditionally, no per-pack resolution.
        style: 'src/css/app.css',
        // Storefront bundle; Woo\Assets.php enqueues it only on Woo contexts.
        woo: 'src/css/woo.css',
      },
    },
  },
  server: {
    port: 5173,
    strictPort: true,
    // Only the local wp-env origins may pull dev-server assets. `cors: true`
    // would reflect any origin, letting any site a developer visits read this
    // server's source over CORS while it runs.
    // 8892 is the dev-mode e2e environment (.wp-env.dev-mode.json).
    cors: { origin: ['http://localhost:8888', 'http://localhost:8889', 'http://localhost:8892'] },
  },
});
