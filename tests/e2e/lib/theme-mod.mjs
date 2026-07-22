// tests/e2e/lib/theme-mod.mjs
//
// Safe read/restore of site-global theme_mods for the e2e suite.
//
// Two rules learned the hard way in s5:
//  1. NEVER swallow a read error. A wp-cli failure dressed up as "unset" makes
//     the restore delete the very setting it exists to preserve.
//  2. NEVER interpolate a database value into a shell command unvalidated. The
//     value comes out of the DB; validate it against the setting's own closed
//     set (or an integer pattern) before it can reach execSync.
import { execSync } from 'node:child_process';

/** Run a wp-cli command in the cli container, return trimmed stdout. */
export function wp(command) {
  return execSync(`npx wp-env run cli wp ${command}`, {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  }).trim();
}

/**
 * The stored value of a theme_mod, or '' when genuinely unset.
 *
 * @param {string} name       theme_mod name.
 * @param {(value: string) => boolean} isValid Guard for the stored value.
 */
export function readThemeMod(name, isValid) {
  const [row] = JSON.parse(wp(`theme mod get ${name} --format=json`));
  const value = row?.value ?? '';

  if ('' !== value && !isValid(value)) {
    throw new Error(`Refusing to round-trip an unrecognised ${name}: ${JSON.stringify(value)}`);
  }

  return value;
}

/**
 * Put a theme_mod back exactly as readThemeMod() found it.
 *
 * @param {string} name     theme_mod name.
 * @param {string|boolean|null} previous Value from readThemeMod, or null if never read.
 */
export function restoreThemeMod(name, previous) {
  // Never read means the prior state is unknown — touching it now would destroy
  // exactly what we failed to read.
  if (null === previous) {
    return;
  }

  if ('' === previous) {
    wp(`theme mod remove ${name}`);
    return;
  }

  // `wp theme mod set` always writes a literal CLI string, unsanitized. A
  // boolean theme_mod (e.g. a checkbox saved through the real Customizer,
  // which stores the SANITIZED PHP bool rather than a '1'/'0' string) must be
  // normalised to the string form its own sanitize_callback actually accepts
  // as "on" — otherwise the shell would print the word "true"/"false"
  // verbatim, and a boolean sanitizer that only recognises '1' would read
  // the restored value back as false regardless of what it was before.
  const value = 'boolean' === typeof previous ? (previous ? '1' : '0') : previous;

  wp(`theme mod set ${name} ${value}`);
}

/** Guard for the integer settings. */
export const isInteger = (value) => /^\d+$/.test(value);

/** Guard for a checkbox theme_mod: either CLI string form or a real PHP bool
 * (get_theme_mod() returns whatever was actually stored — a Customizer save
 * stores the sanitized bool, a bare `wp theme mod set` stores the string). */
export const isToggleValue = (value) =>
  '1' === value || '0' === value || true === value || false === value;
