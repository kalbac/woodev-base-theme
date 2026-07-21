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

  it('rejects an unknown pack', () => {
    expect(() => packEntryCss('bogus')).toThrow(/Unknown Basecoat style pack/);
  });
});
