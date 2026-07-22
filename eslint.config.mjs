// eslint.config.mjs
import js from '@eslint/js';

export default [
  js.configs.recommended,
  {
    files: [
      'src/js/**/*.js',
      'scripts/**/*.mjs',
      'tests/js/**/*.mjs',
      'tests/e2e/**/*.mjs',
      'tests/e2e-dev/**/*.mjs',
    ],
    languageOptions: {
      ecmaVersion: 2024,
      sourceType: 'module',
      globals: {
        window: 'readonly',
        document: 'readonly',
        console: 'readonly',
        fetch: 'readonly',
        process: 'readonly',
        getComputedStyle: 'readonly',
        localStorage: 'readonly',
      },
    },
  },
  {
    // Root-level *.mjs config files (vite.config.mjs, playwright.config.mjs,
    // playwright.dev.config.mjs, vitest.config.mjs) run under Node, not a
    // browser — they must NOT get window/document/localStorage/etc. as
    // globals. They previously matched the block above via '*.config.mjs',
    // which meant a stray `document.body` in vite.config.mjs no longer
    // tripped no-undef. `*.config.mjs` (no `**/` prefix) is anchored to this
    // file's directory, so this intentionally does not match config files
    // nested under scripts/ or tests/.
    files: ['*.config.mjs'],
    languageOptions: {
      ecmaVersion: 2024,
      sourceType: 'module',
      globals: {
        process: 'readonly',
      },
    },
  },
  {
    // `**/` prefixes are load-bearing: flat-config ignore patterns are anchored
    // to the config file's directory, so a bare `vendor/**` misses the nested
    // tests/integration/vendor/ that the integration harness installs — ESLint
    // then walks into php-code-coverage's bundled jQuery and reports 831 errors.
    // Invisible in CI, which never installs that tree; only the developer sees it.
    // (.prettierignore needs no such fix — it uses gitignore syntax, where a
    // trailing-slash pattern already matches at any depth.)
    ignores: ['woodev-base-theme/assets/dist/**', '**/vendor/**', '**/node_modules/**'],
  },
];
