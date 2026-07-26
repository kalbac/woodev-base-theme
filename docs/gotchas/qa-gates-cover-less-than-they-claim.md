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

## s15 added a third shape: a gate written off in the docs, and a success message that meant nothing

Two more instances, both in one hour, both about **believing a sentence instead of a measurement**.

| Gate | What was believed | What was true |
|---|---|---|
| **Prettier, in CI** | `docs/CURRENT-STATE.md` had said for two sessions: "`npm run format` is red on 5 files no session has touched. **Not in the documented gate battery.**" So every session skipped it. | `.github/workflows/ci.yml` runs `npm run format` inside `js-qa`. It had been failing on PR #24 the whole time — and because the `e2e` job declares `needs: js-qa`, **CI had never once run the base e2e on that PR**. A red gate was hiding a gate that never ran. |
| **Prettier, locally** | `npx prettier --check opencode.json` printed `All matched files use Prettier code style!`, which was read as "the file is clean" and written into the docs as a measured fact. | The file had just been added to `.gitignore`, and **Prettier 3 reads `.gitignore` as well as `.prettierignore`**. It matched *zero* files. That message is emitted for an empty match set and is byte-identical to a real pass. The file was in fact red. |

The second one is the more dangerous, because it is a **success message produced by checking nothing** — the same failure shape as `codex-cli-dies-silently`, only in a tool nobody suspects. `prettier --check` on an ignored path exits 0 and congratulates you.

- **Confirm a path is actually being checked** before trusting a per-file result: `npx prettier --check <path> --ignore-path /dev/null`, or `git check-ignore -v <path>` to see which rule excluded it. On a directory run, compare the file count against what you expected to be scanned.
- **A docs note claiming a check "is not in the gate battery" is a claim about CI that only CI can settle.** Read `.github/workflows/ci.yml`. The note was written by someone who did not check either, and it propagated for two sessions.
- **A skipped CI job is not a passing one.** `gh pr checks` reported `e2e  skipping` next to three passes, which scans as "fine". Any job with `needs:` disappears silently when its dependency fails — check for `skipping` explicitly before calling a PR green.

## How to apply here

- **Check what a gate scanned, not just what it returned.** PHPCS prints `20 / 20`; if that number does not roughly match the files you touched plus their neighbours, the gate is not covering your work. `vendor/bin/phpcs --report=summary` lists them.
- **Run every gate after every task, not the ones that look relevant.** "This change is PHP-only" is how the ESLint failure reached CI. The full set is: `phpcs`, `phpstan`, `test:unit`, `test:integration`, `format`, `lint:js`, `test:js`, `build`.
- **A gate that is green on one platform and red on the other is a defect in the gate**, until proven otherwise. Both of the platform splits here were config bugs, not environment problems.
- **Ignore patterns need `**/` in ESLint flat config** — `vendor/**` is anchored, `**/vendor/**` is not. `.prettierignore` uses gitignore syntax and does not have this problem, which is why it never broke.

When a gate's scope is deliberately narrow, say so in the config. `phpcs.xml.dist` now carries its test-only relaxations with a written reason for each, so the next person can tell "excluded on purpose" from "never covered".

## Related

- [[wp-test-suite-removes-html5-support]] — the same session's other flavour of false confidence: a test that passes for a reason unrelated to what it claims
- [[codex-cli-dies-silently]] — a tool whose failure modes all exit 0
