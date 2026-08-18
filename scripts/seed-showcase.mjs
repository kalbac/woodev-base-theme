/**
 * Populate the isolated Woo wp-env site with human-readable showcase content.
 *
 * This is deliberately separate from e2e fixtures. It makes a local demo useful
 * for visual review and the wp.org screenshot without giving tests ownership of
 * editorial copy. The PHP batch is mounted by .wp-env.woo.json and runs through
 * wp-env, so it never depends on a Docker-generated container name.
 *
 * Usage: npm run wp:woo:start && npm run seed:showcase
 */
import { execFileSync } from 'node:child_process';

const config = '.wp-env.woo.json';
const script = 'wp-content/woodev-scripts/seed-showcase.php';

const args = ['wp-env', 'run', 'cli', `--config=${config}`, 'wp', 'eval-file', script];
const options = { cwd: new URL('..', import.meta.url), stdio: 'inherit' };

if (process.platform === 'win32') {
  execFileSync('cmd.exe', ['/d', '/s', '/c', `npx ${args.join(' ')}`], options);
} else {
  execFileSync('npx', args, options);
}
