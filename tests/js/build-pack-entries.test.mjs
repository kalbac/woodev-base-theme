import { describe, expect, it } from 'vitest';
import { DEFAULT_PACK, PACKS, packEntryCss } from '../../scripts/lib/packs-lib.mjs';

describe('style-pack entries', () => {
  it('lists the 8 Basecoat packs with vega as default', () => {
    expect(PACKS).toEqual(['vega', 'nova', 'maia', 'lyra', 'mira', 'luma', 'sera', 'rhea']);
    expect(DEFAULT_PACK).toBe('vega');
    expect(PACKS).toContain(DEFAULT_PACK);
  });

  it('imports exactly one pack per entry, the requested one', () => {
    const packImports = [...packEntryCss('nova').matchAll(/@import 'basecoat-css\/(\w+)'/g)].map(
      (m) => m[1],
    );
    expect(packImports).toEqual(['nova']);
  });

  // The whole cascade hinges on this order (basecoat-tokens-are-un-layered.md):
  // the pack must be imported BEFORE our tokens, or Basecoat's un-layered :root
  // silently overrides every design token.
  it('imports the pack before the design tokens', () => {
    const css = packEntryCss('vega');
    const packAt = css.indexOf("@import 'basecoat-css/vega'");
    const tokensAt = css.indexOf("@import '../tokens.generated.css'");
    expect(packAt).toBeGreaterThan(-1);
    expect(tokensAt).toBeGreaterThan(packAt);
  });

  // The template is the single source for all 8 bundles, so a silent edit to it
  // breaks every pack at once. Pin the WHOLE contract, not just the pack/tokens
  // pair: the layer declaration, the @source glob, the exact import sequence and
  // the relative depth of each path (the entries live one level deeper than the
  // files they import).
  it('pins the full import contract of a generated entry', () => {
    const css = packEntryCss('vega');

    const order = [
      "@import 'tailwindcss';",
      "@import 'basecoat-css/vega';",
      "@import '../tokens.generated.css';",
      "@import '../adapter/index.css' layer(adapter);",
      "@import '../states.css';",
    ];
    const positions = order.map((line) => css.indexOf(line));
    expect(positions.filter((at) => at === -1)).toEqual([]);
    expect(positions).toEqual([...positions].sort((a, b) => a - b));

    expect(css).toContain('@source "../../../woodev-base-theme/**/*.php";');
    expect(css).toContain('@layer theme, base, components, adapter, utilities;');
  });

  // Wrapping the Basecoat import in layer() is a hard build failure (it nests
  // Basecoat's top-level @custom-variant, which Tailwind v4 rejects), and
  // layering our tokens or state overrides would make them lose to un-layered
  // CSS. Only the adapter import may carry a layer().
  it('wraps only the adapter import in a layer()', () => {
    const layered = [
      ...packEntryCss('vega').matchAll(/@import\s+'([^']+)'\s+layer\(([^)]+)\)/g),
    ].map((m) => [m[1], m[2]]);

    expect(layered).toEqual([['../adapter/index.css', 'adapter']]);
  });

  it('rejects an unknown pack', () => {
    expect(() => packEntryCss('bogus')).toThrow(/Unknown Basecoat style pack/);
  });
});
