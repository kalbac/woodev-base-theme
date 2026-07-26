# wp-env mounts the theme live, so nobody may edit it while an e2e suite runs

**Area:** Tooling / wp-env · **Found:** s14 (26.07.2026)

## What happens

`npm run e2e:woo` died five minutes into seeding with:

```
PHP Parse error: syntax error, unexpected token "const", expecting "="
  in /var/www/html/wp-content/themes/woodev-base-theme/inc/Woo/BlockAssets.php on line 53
Error: There has been a critical error on this website.
✖ Command failed with exit code 1
```

`BlockAssets.php` was fine minutes later — `php -l` clean. The file was simply caught
half-written: a parallel worker was in the middle of creating that class while the suite
ran. wp-env bind-mounts the theme directory into the container, so the container sees every
intermediate save instantly. A partially written PHP file is a **fatal on every request**,
which takes down the wp-cli calls the global setup depends on.

The failure points at the seeding code (`global-setup.mjs:64`, inside `execSync`), which is
where the exception surfaces, not where the cause is. Reading only the stack trace sends you
to debug the wrong file.

## What to do

- **Separate theme edits from suite runs in TIME, not by file.** "Different files, no
  conflict" is not enough — it is the same mounted directory and the same PHP process. Two
  agents can safely write CSS and tests in parallel; neither may do so while an e2e or
  integration run is in flight.
- When running workers in parallel, give exactly one of them Docker and tell the others they
  own no environment.
- If a suite fails with a PHP parse error in a file you are actively editing, do not debug
  the file — re-run once the tree is quiet. Check `php -l` first to tell a real syntax error
  from a caught-mid-save one.
- The same applies to `npm run build`: it rewrites `assets/dist` under the running site.
  Building between suites is fine; building during one is not.

## Related

- [[wp-env-installs-themes-without-activating-them]] — the other wp-env trap in the setup path
- [[wp-env-config-constants-persist]]
