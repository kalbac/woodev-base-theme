// tests/e2e/lib/option.mjs
//
// Safe read/restore of site-global WordPress OPTIONS for the e2e suite — the
// option-shaped sibling of theme-mod.mjs's theme_mod helpers. Needed because
// the static-front-page switch (#37) is `show_on_front`/`page_on_front`,
// which are OPTIONS, not theme_mods — `wp theme mod get/set` cannot touch
// them, so theme-mod.mjs's helpers do not apply.
//
// Same two rules as theme-mod.mjs, because neither is specific to theme_mods:
//  1. NEVER swallow a read error.
//  2. NEVER interpolate a database value into a shell command unvalidated —
//     validate against the option's own closed set (or an integer pattern,
//     see isInteger in theme-mod.mjs) before it can reach execSync.
//
// Rule 1 needs one distinction that the first version of this file got wrong.
// It read with `wp option get <name>` and treated ANY failure as fatal, on the
// stated grounds that `show_on_front`/`page_on_front` are core options present
// on every install so an absent one cannot be legitimate. That is not true:
// an option can simply not be there — `wp option get` exits 1 with "Could not
// get 'page_on_front' option. Does it exist?" — and s17 hit exactly that after
// a killed run's state was cleaned up by hand. The suite then failed on every
// subsequent run with a message that reads like a container fault, on a test
// whose assertions were fine.
//
// "Absent" and "unreadable" are therefore different answers, and the read must
// return the first while still throwing on the second. `wp option list
// --search=<name>` gives both in one call: an empty array for an option that
// is not there, a row for one that is, and no parsable JSON at all when the
// container failed.
//
// Not `wp eval` with a small PHP snippet, which was the obvious first try and
// does not survive the trip: `npx wp-env run cli` RE-SPLITS the command
// string, so quotes are stripped and `=>` arrives as `=`
// ("Too many positional arguments: json_encode( [ exists = !== get_option(…"),
// tests/e2e-woo/global-setup.mjs records the same hazard. Any helper here has
// to be expressible without quotes, spaces or shell metacharacters.
import { wp } from './theme-mod.mjs';

/**
 * The stored value of a WordPress option, and whether it exists at all.
 *
 * @param {string} name       Option name.
 * @param {(value: string) => boolean} isValid Guard for the stored value.
 * @returns {{ exists: boolean, value: string }}
 */
export function readOption(name, isValid) {
  // `wp option get` cannot answer this: an absent option and a failed call
  // are both exit 1 with a message on stderr.
  const raw = wp(`option list --search=${name} --format=json`);

  const line = raw.split('\n').find((candidate) => candidate.trim().startsWith('['));

  // No JSON at all means the container never ran our command — a real read
  // failure, and rule 1 says it must not be mistaken for "unset".
  if (undefined === line) {
    throw new Error(`Refusing to guess at ${name}: wp-cli returned ${JSON.stringify(raw)}`);
  }

  // `--search` is a SQL LIKE, so it can return neighbours of the name we
  // asked for. Match exactly.
  const row = JSON.parse(line).find((candidate) => candidate.option_name === name);

  if (undefined === row) {
    return { exists: false, value: '' };
  }

  const value = String(row.option_value);

  if (!isValid(value)) {
    throw new Error(`Refusing to round-trip an unrecognised ${name}: ${JSON.stringify(value)}`);
  }

  return { exists: true, value };
}

/**
 * Put an option back exactly as readOption() found it — including putting it
 * back to ABSENT, which `wp option update` cannot express.
 *
 * @param {string} name Option name.
 * @param {{ exists: boolean, value: string }|null} previous Value from readOption(), or null if never read.
 */
export function restoreOption(name, previous) {
  // Never read means the prior state is unknown — touching it now would
  // destroy exactly what we failed to read (same rule as restoreThemeMod()).
  if (null === previous) {
    return;
  }

  if (!previous.exists) {
    wp(`option delete ${name}`);

    return;
  }

  wp(`option update ${name} ${previous.value}`);
}
