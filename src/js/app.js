// src/js/app.js — theme JS entry.
// Basecoat drives its own components (auto-initializes on import);
// Alpine owns theme-level behavior only (AGENTS.md).
//
// DEVIATION FROM PLAN: the plan text said `import 'basecoat-css';` but that
// bare specifier resolves to the package's "." export, which is a CSS file
// (./dist/basecoat.css) per basecoat-css's package.json `exports` map — it
// would import CSS from a JS module (and duplicate CSS already pulled in via
// app.css), not register any component behavior. The JS entry points are
// separate subpath exports. `basecoat-css/basecoat` (./dist/js/basecoat.js)
// only ships the core registry/observer/theme-toggle API with zero
// components registered. `basecoat-css/all` (./dist/js/all.js) contains that
// same core PLUS every component's self-registration code, and still ends
// with the `DOMContentLoaded` listener that calls `initAllComponents()` and
// starts the mutation observer. So `basecoat-css/all` is the import that
// actually auto-initializes Basecoat's components.
import 'basecoat-css/all';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
