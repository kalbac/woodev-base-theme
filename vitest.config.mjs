// vitest.config.mjs
import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    // Vitest owns tests/js only. Without an explicit include it also picks up
    // tests/e2e/*.spec.mjs, whose `@playwright/test` import then fails inside the
    // Vitest runner ("calling test() from an async test.describe() block").
    // Playwright owns tests/e2e via playwright.config.mjs.
    include: ['tests/js/**/*.test.mjs'],
  },
});
