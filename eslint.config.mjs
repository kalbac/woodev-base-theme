// eslint.config.mjs
import js from '@eslint/js';

export default [
  js.configs.recommended,
  {
    files: ['src/js/**/*.js', 'scripts/**/*.mjs', 'tests/js/**/*.mjs', 'tests/e2e/**/*.mjs'],
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
      },
    },
  },
  {
    ignores: ['woodev-base-theme/assets/dist/**', 'vendor/**', 'node_modules/**'],
  },
];
