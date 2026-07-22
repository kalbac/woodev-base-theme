// src/js/app.js — theme JS entry.
// Basecoat drives its own components (auto-initializes on import);
// Alpine owns theme-level behavior only (AGENTS.md).

// `basecoat-css/all`, not `basecoat-css`: the bare specifier resolves to the
// package's "." export, which is CSS. `basecoat-css/basecoat` is the registry
// alone and registers zero components. Only `/all` self-registers them.
// See docs/gotchas/basecoat-js-entry-is-a-subpath-export.md
import 'basecoat-css/all';
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

// @alpinejs/focus provides `x-trap`, used by the mobile nav drawer to keep
// keyboard focus inside the open menu and restore it to the toggle on close.
// Must be registered BEFORE Alpine.start().
Alpine.plugin(focus);

// Colour-scheme switcher (M1-05, spec §6). Named component, not inline
// x-data: it owns a matchMedia listener with a real teardown path, which is
// unwieldy to express as one attribute string. Registered BEFORE
// Alpine.start(), same as the focus plugin above.
//
// `labels` is the two translated accessible-name strings, injected from PHP
// (template-parts/header/scheme-toggle.php) as the x-data() call argument —
// they cannot live here because a .js file has no text domain for the .pot
// scanner to find.
Alpine.data('wtbSchemeToggle', (labels) => ({
  dark: false,
  followSystem: false,
  mql: null,
  _onSystemChange: null,

  init() {
    const root = document.documentElement;
    const hasExplicitClass = root.classList.contains('light') || root.classList.contains('dark');

    this.mql = window.matchMedia('(prefers-color-scheme: dark)');
    this.followSystem = !hasExplicitClass;
    this.dark = hasExplicitClass ? root.classList.contains('dark') : this.mql.matches;

    if (this.followSystem) {
      this._startFollowingSystem();
    }

    this.$el.classList.add('wtb-scheme-toggle--enhanced');
  },

  // While the resolved scheme is `system` (no explicit class on <html>), the
  // button keeps following the OS live rather than freezing at whatever the
  // OS reported on load.
  _startFollowingSystem() {
    this._onSystemChange = (event) => {
      this.dark = event.matches;
    };
    this.mql.addEventListener('change', this._onSystemChange);
  },

  _stopFollowingSystem() {
    if (this._onSystemChange) {
      this.mql.removeEventListener('change', this._onSystemChange);
      this._onSystemChange = null;
    }
    this.followSystem = false;
  },

  toggle() {
    this._stopFollowingSystem();

    this.dark = !this.dark;
    const scheme = this.dark ? 'dark' : 'light';
    const root = document.documentElement;

    root.classList.remove('light', 'dark');
    root.classList.add(scheme);

    // Throws, rather than returning null, in Safari private mode and
    // whenever storage/cookies are blocked — mirrors Scheme::build_head_script().
    // An uncaught exception here would abort the click handler and leave the
    // class flip above applied but the choice unremembered on reload.
    try {
      localStorage.setItem('wtb-scheme', scheme);
    } catch {
      // No persistence available; the class flip for this page view still
      // stands.
    }
  },

  get label() {
    return this.dark ? labels.toLight : labels.toDark;
  },
}));

window.Alpine = Alpine;
Alpine.start();
