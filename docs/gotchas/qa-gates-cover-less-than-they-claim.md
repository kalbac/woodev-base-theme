# QA gates cover less than their exit code implies

> Discovered s3 (19–20.07.2026) — three separate instances in one session, which is why this is a pattern and not a bug report. Three more in s15 (26–27.07.2026), which is why it is still open.

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

## The third s15 instance: a security guard that turned the suite off

Adding `defined( 'ABSPATH' ) || exit;` to all 45 shipped PHP files — a wp.org expectation, and correct — **silently disabled the entire unit suite**. The unit suite runs on Brain\Monkey *without* WordPress, so `ABSPATH` is undefined, and the first `require` of the theme autoloader hit that `exit`. PHPUnit died before printing a single character and returned **exit code 0**.

```
$ composer test:unit
$ echo $?
0
```

That is the whole output. A suite that never ran looks exactly like a suite that passed — and `exit 0` means every downstream check, including CI, would have agreed. It was caught only because the output was *empty* rather than *wrong*, which is luck, not process.

- **When a change makes a gate quieter, that is the signal.** The reflex is to be pleased; the correct reflex is to ask what stopped running. Compare the test COUNT against the previous run, not just the exit code — 208 tests to zero tests is invisible in `$?` and obvious in the count.
- **`exit` in shipped code is a landmine for every non-WordPress consumer** — unit suites, static analysers, scripts. The fix is one line in the test bootstrap (`defined( 'ABSPATH' ) || define( 'ABSPATH', … )`), and it is not faking anything: the constant's job is to assert "WordPress is loading this", which is as true of the suite as of a request.
- The matching guard test reads the file's **executable head** (comments stripped) rather than a fixed character window. The first version used 800 characters and reported a false defect in `woocommerce/content-product.php`, whose perfectly good guard sits behind a 22-line upstream docblock.

## Related

- [[wp-test-suite-removes-html5-support]] — the same session's other flavour of false confidence: a test that passes for a reason unrelated to what it claims
- [[codex-cli-dies-silently]] — a tool whose failure modes all exit 0
- [[wp-env-mounts-the-theme-live]] — another failure whose symptom points at the wrong file
