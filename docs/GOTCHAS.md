# Gotchas Index — Woodev Base

> 8 entries. Each gotcha is a separate file in `docs/gotchas/`.

| Gotcha | Area | Summary |
|---|---|---|
| [tailwind-v4-layer-precedence](gotchas/tailwind-v4-layer-precedence.md) | CSS | `@layer components` loses to utilities; un-layered CSS beats all layers — plan overrides accordingly |
| [basecoat-style-packs-standalone](gotchas/basecoat-style-packs-standalone.md) | CSS/Basecoat | Style packs can't be combined — one standalone bundle per pack, enqueue only the chosen one; dark mode is `.dark` class; version pinned exact |
| [basecoat-js-entry-is-a-subpath-export](gotchas/basecoat-js-entry-is-a-subpath-export.md) | JS/Basecoat | `import 'basecoat-css'` silently imports CSS, not JS; `/basecoat` registers 0 components — only `/all` auto-inits. npm CSS is source, needs Tailwind |
| [basecoat-tokens-are-un-layered](gotchas/basecoat-tokens-are-un-layered.md) | CSS/Basecoat | `layer(components)` on the Basecoat import won't build (`@custom-variant cannot be nested`) and isn't needed — Basecoat self-layers. Its `:root` tokens are un-layered, so ours must be too, imported after it |
| [vite-css-entry-is-not-imported-by-the-js-entry](gotchas/vite-css-entry-is-not-imported-by-the-js-entry.md) | Build/Assets | The two Rollup inputs are independent graphs, so dev mode must ask the dev server for the CSS entry by name — and Vite serves it as a JS module (`text/javascript`), so it's a script module, not a stylesheet |
| [wp-json-file-decode-warns-on-missing-file](gotchas/wp-json-file-decode-warns-on-missing-file.md) | PHP/WP core | It calls `wp_trigger_error()` before returning null — an absent manifest emits a PHP notice on every request. Two holes, so two halves: `is_file() && is_readable()` — core hands the path to `file_get_contents()` unchecked. Returning null is no proof an API stayed quiet |
| [wp-env-config-constants-persist](gotchas/wp-env-config-constants-persist.md) | Tooling/wp-env | `config` constants are appended to wp-config.php of **both** environments and never removed — not even by `--update`. Plus: `wp eval` probes self-match wp-env's echo, and `display_errors=stderr` hides notices from the HTML |
| [codex-cli-dies-silently](gotchas/codex-cli-dies-silently.md) | Tooling/Codex | The mandatory critic gate fails in four ways and every one exits 0 — MCP loads despite `-c mcp_servers={}`, argv >32KB, background runs no-op. Working recipe: clean `CODEX_HOME`, foreground, prompt <15KB |
