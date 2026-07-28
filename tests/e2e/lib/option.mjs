// tests/e2e/lib/option.mjs
//
// Safe read/restore of site-global WordPress OPTIONS for the e2e suite — the
// option-shaped sibling of theme-mod.mjs's theme_mod helpers. Needed because
// the static-front-page switch (#37) is `show_on_front`/`page_on_front`,
// which are OPTIONS, not theme_mods — `wp theme mod get/set` cannot touch
// them, so theme-mod.mjs's helpers do not apply.
//
// Same two rules as theme-mod.mjs, because neither is specific to theme_mods:
//  1. NEVER swallow a read error. theme-mod.mjs's readThemeMod() treats an
//     EMPTY result as "legitimately unset", which is correct for a
//     theme_mod on a fresh site. show_on_front/page_on_front are core
//     WordPress options created on every install — there is no legitimate
//     "unset" state for them, so a failed read here is a real problem and
//     must fail loud rather than round-trip an empty string over whatever
//     is actually stored.
//  2. NEVER interpolate a database value into a shell command unvalidated —
//     validate against the option's own closed set (or an integer pattern,
//     see isInteger in theme-mod.mjs) before it can reach execSync.
import { wp } from './theme-mod.mjs';

/**
 * The stored value of a WordPress option.
 *
 * @param {string} name       Option name.
 * @param {(value: string) => boolean} isValid Guard for the stored value.
 */
export function readOption(name, isValid) {
  const value = wp(`option get ${name}`);

  if (!isValid(value)) {
    throw new Error(`Refusing to round-trip an unrecognised ${name}: ${JSON.stringify(value)}`);
  }

  return value;
}

/**
 * Put an option back exactly as readOption() found it.
 *
 * @param {string} name          Option name.
 * @param {string|null} previous Value from readOption(), or null if never read.
 */
export function restoreOption(name, previous) {
  // Never read means the prior state is unknown — touching it now would
  // destroy exactly what we failed to read (same rule as restoreThemeMod()).
  if (null === previous) {
    return;
  }

  wp(`option update ${name} ${previous}`);
}
