# wp-env `config` constants are written once and never removed

> Discovered s3 (17.07.2026) while verifying dev mode by hand for the PR #1 triage. The first probe of this was a false positive (see below) — the conclusion below is the re-verified one.

## The trap

`.wp-env.json` / `.wp-env.override.json` `config` keys look declarative, as if wp-env reconciles wp-config.php with the file on every start. It does not — it **appends** constants it doesn't find and never removes ones you dropped from the config. Measured by grepping wp-config.php inside the containers:

| Step | dev | tests |
|---|---|---|
| baseline, no override | 0 | 0 |
| `wp-env start` with `{"config":{"WOODEV_BASE_DEV":true}}` | 1 | 1 |
| delete the override file, `wp-env start` | **1** | **1** |
| `wp-env start --update` | **1** | **1** |

Two things to take from it:

1. **The constant outlives its config file.** Removing the override and restarting leaves dev mode silently on; `--update` does not help. Remove it explicitly: `wp-env run cli wp config delete WOODEV_BASE_DEV` — **and again for `tests-cli`**, they are separate WordPress installs with separate wp-config.php files. (`wp-env destroy` also clears it, at the cost of the database.)
2. **`config` lands in both environments.** Anything you set for the dev site is also set for the test site. Relevant to M1's integration harness: dev-only constants must not leak into the environment PHPUnit boots against.

## Beware: probing this with `wp eval` self-matches

The obvious probe is a lie detector's nightmare:

```sh
wp-env run cli wp eval 'echo defined("X") ? "DEFINED" : "absent";' | grep -oE 'DEFINED|absent' | head -1
```

wp-env echoes `ℹ Starting 'wp eval echo defined("X") ? "DEFINED" : "absent";' on the cli container.` **before** the output — so the grep matches the words inside the echoed command and reports `DEFINED` no matter what. It reported the constant present in an environment where the front page proved it absent. Probe strings must not appear in the command that produces them, or read wp-config.php directly.

Two more wp-env footguns from the same session:

- **Git Bash mangles container paths.** `docker exec … grep /var/www/html/wp-config.php` becomes `C:/Program Files/Git/var/www/html/wp-config.php`. Export `MSYS_NO_PATHCONV=1`.
- **`display_errors=stderr`**, so PHP notices never reach the HTML — they only exist in `docker logs <container>`. A page can look clean and be emitting notices on every request; see [[wp-json-file-decode-warns-on-missing-file]].

## How to apply here

Recipe for checking dev mode by hand (the path with no e2e coverage, per [[vite-css-entry-is-not-imported-by-the-js-entry]]):

```sh
echo '{ "config": { "WOODEV_BASE_DEV": true } }' > .wp-env.override.json   # gitignored
npx wp-env start && npm run dev
# ... verify, then:
rm .wp-env.override.json
npx wp-env run cli wp config delete WOODEV_BASE_DEV
npx wp-env run tests-cli wp config delete WOODEV_BASE_DEV
```

## Related

- [[vite-css-entry-is-not-imported-by-the-js-entry]] — what the recipe above is for
- [[wp-json-file-decode-warns-on-missing-file]] — why the container log matters
