// vite.config.mjs
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { PACKS } from './scripts/lib/packs-lib.mjs';

export default defineConfig({
  plugins: [tailwindcss()],
  build: {
    outDir: 'woodev-base-theme/assets/dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        app: 'src/js/app.js',
        // One standalone CSS bundle per Basecoat style pack; Assets.php enqueues
        // only the one the style_preset theme_mod selects (default vega).
        ...Object.fromEntries(PACKS.map((pack) => [`style-${pack}`, `src/css/packs/${pack}.css`])),
      },
    },
  },
  server: {
    port: 5173,
    strictPort: true,
    // Only the local wp-env origins may pull dev-server assets. `cors: true`
    // would reflect any origin, letting any site a developer visits read this
    // server's source over CORS while it runs.
    cors: { origin: ['http://localhost:8888', 'http://localhost:8889'] },
  },
});
