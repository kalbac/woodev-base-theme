// src/js/app.js — theme JS entry.
// Basecoat drives its own components (auto-initializes on import);
// Alpine owns theme-level behavior only (AGENTS.md).

// `basecoat-css/all`, not `basecoat-css`: the bare specifier resolves to the
// package's "." export, which is CSS. `basecoat-css/basecoat` is the registry
// alone and registers zero components. Only `/all` self-registers them.
// See docs/gotchas/basecoat-js-entry-is-a-subpath-export.md
import 'basecoat-css/all';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
