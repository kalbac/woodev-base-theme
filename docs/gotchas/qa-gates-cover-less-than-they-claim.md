# QA gates cover less than their exit code implies

> Discovered s3 (19–20.07.2026) — three separate instances in one session, which is why this is a pattern and not a bug report.

## The trap

`composer phpcs` exits 0. That means "the files this gate looked at had no violations". It does not mean "your work is clean", and the difference is invisible from the exit code. All three of these were live in this repo at once:

| Gate | What it actually covered | How it was found |
|---|---|---|
| **PHPCS** | `phpcs.xml.dist` declared `<file>woodev-base-theme</file>` and nothing else. **No test file had ever been linted** — 10 files scanned, while `composer phpcs` was being quoted as evidence a PR was clean. | A worker checked the scanned-file count instead of the exit code. |
| **ESLint** | Flat-config `ignores` are anchored to the config's directory, so `vendor/**` matched only the root `vendor/`. Once the integration harness added `tests/integration/vendor/`, ESLint walked into php-code-coverage's bundled jQuery: **831 errors locally, invisible in CI** (which never installs that tree). | Running the JS gates on a change that "was PHP-only". |
| **PHPCS again** | With `core.autocrlf=true` and no `.gitattributes`, every file checks out CRLF on Windows and **all 8 files failed on EOL alone**, before a single sniff ran. Green in CI (Linux, LF). | Reading past the first screen of output — `tail -15` had shown only the last file, making it look like one file's problem. |

Two shapes recur: **the gate's scope is narrower than the work**, and **the gate's result differs per platform**. The second is the nastier one, because CI green plus developer red reads as "the developer's machine is broken" rather than "the gate is misconfigured".

## How to apply here

- **Check what a gate scanned, not just what it returned.** PHPCS prints `20 / 20`; if that number does not roughly match the files you touched plus their neighbours, the gate is not covering your work. `vendor/bin/phpcs --report=summary` lists them.
- **Run every gate after every task, not the ones that look relevant.** "This change is PHP-only" is how the ESLint failure reached CI. The full set is: `phpcs`, `phpstan`, `test:unit`, `test:integration`, `format`, `lint:js`, `test:js`, `build`.
- **A gate that is green on one platform and red on the other is a defect in the gate**, until proven otherwise. Both of the platform splits here were config bugs, not environment problems.
- **Ignore patterns need `**/` in ESLint flat config** — `vendor/**` is anchored, `**/vendor/**` is not. `.prettierignore` uses gitignore syntax and does not have this problem, which is why it never broke.

When a gate's scope is deliberately narrow, say so in the config. `phpcs.xml.dist` now carries its test-only relaxations with a written reason for each, so the next person can tell "excluded on purpose" from "never covered".

## Related

- [[wp-test-suite-removes-html5-support]] — the same session's other flavour of false confidence: a test that passes for a reason unrelated to what it claims
- [[codex-cli-dies-silently]] — a tool whose failure modes all exit 0
