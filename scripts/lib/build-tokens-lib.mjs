/**
 * Token generator: turns src/tokens/tokens.mjs into the three artefacts the
 * theme consumes — tokens.generated.css, theme.json and inc/generated/palettes.php.
 *
 * The interesting half is not the emitters, it is the CONTRAST GATE. The design
 * expresses colours as var()-driven oklch() expressions, so a palette is three
 * numbers and every surface derives. That is excellent for the Customizer and
 * terrible for naive verification: you cannot read a contrast ratio off
 * `oklch(52% 0.012 var(--n-h))`. So the gate resolves each palette numerically —
 * substituting --n-h, --accent-h, --accent-c through the same declarations the
 * browser will — and measures the resolved colours. Seven palettes times two
 * schemes, every text/surface pair the design relies on. Below AA the BUILD
 * FAILS. An inaccessible palette must not be shippable by accident.
 */

const titleCase = (slug) =>
  slug
    .split('-')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');

/* ==========================================================================
   Resolver — CSS custom properties to numbers
   ========================================================================== */

// One oklch component: an integer or decimal, optionally with a leading dot.
// Deliberately NOT `[\d.]+`, which admits nonsense like `0.5.6` — that parses to
// NaN and reaches the browser as an invalid colour, i.e. a declaration silently
// dropped at computed-value time. Scientific notation is valid CSS but rejected
// on purpose: nothing here generates it, and a lax pattern would read `6.3e-1`
// as 6.3 and pick a foreground off the wrong side of a contrast comparison.
const NUMBER = String.raw`\d*\.?\d+`;

const VAR_REF = String.raw`var\(--[\w-]+\)`;

// The only arithmetic the design uses: scale a custom property by a constant
// (`calc(var(--accent-c) * .95)`, the dark scheme's slightly desaturated accent).
// Anything else throws rather than being approximated. Note this must be spelled
// out rather than matched as `calc\([^)]*\)` — the inner var() carries its own
// closing paren, so the lazy version stops early and the whole colour reads as
// unparseable.
const CALC_BODY = String.raw`calc\(\s*${VAR_REF}\s*\*\s*${NUMBER}\s*\)`;

const OKLCH = new RegExp(
  String.raw`^oklch\(\s*(?<l>${NUMBER})(?<pct>%?)\s+(?<c>${NUMBER}|${VAR_REF}|${CALC_BODY})` +
    String.raw`\s+(?<h>${NUMBER}|${VAR_REF})\s*(?:/\s*(?<a>${NUMBER}|${VAR_REF})\s*)?\)$`,
);

const VAR = /^var\(--(?<name>[\w-]+)\)$/;

const CALC_SCALE = new RegExp(
  String.raw`^calc\(\s*var\(--(?<name>[\w-]+)\)\s*\*\s*(?<factor>${NUMBER})\s*\)$`,
);

const finite = (value, source) => {
  // The patterns bound the SHAPE, not the magnitude: 309 digits is still all
  // digits, and Number() turns it into Infinity. That would poison the colour
  // maths into NaN, and `NaN < MIN_CONTRAST` is false — so an unmeasurable
  // colour would sail through the gate as if it had passed.
  if (!Number.isFinite(value)) {
    throw new Error(`Not a finite number in token value: ${source}`);
  }

  return value;
};

const DECIMAL = new RegExp(`^${NUMBER}$`);

/**
 * A number in the spelling this generator accepts: plain decimal, nothing else.
 *
 * That is deliberately NARROWER than CSS, and the distinction matters:
 *
 * - `0x10`, `0b110`, `''` and `Infinity` are **invalid CSS**. `Number()` reads
 *   them as 16, 6, 0 and Infinity, so a token spelled that way would resolve to
 *   a plausible number here while the browser drops the declaration at
 *   computed-value time — the gate would be measuring a colour nobody sees.
 * - `1e3` **is valid CSS** (CSS Syntax §4 admits scientific notation). We
 *   reject it anyway, because a pattern permissive enough to accept it also
 *   reads `6.3e-1` as `6.3` if anything downstream ever parses loosely, and
 *   nothing in this design needs the notation. This is a project subset, not a
 *   claim about the platform — do not "fix" it by widening the pattern to match
 *   CSS unless something actually needs exponents.
 */
const decimal = (text, source) => {
  const trimmed = text.trim();

  if (!DECIMAL.test(trimmed)) {
    throw new Error(`Not a plain decimal number in token value: ${source}`);
  }

  return finite(Number(trimmed), source);
};

// oklch() lightness is 0…1 (or 0%…100%). Chroma may legitimately exceed the
// sRGB gamut — that is what the gamut mapping below is for — but lightness
// cannot: a browser clamps it, so measuring an unclamped 101% would compute a
// contrast ratio nothing on screen has. Refuse rather than clamp: out-of-range
// lightness in OUR OWN token source is a typo, and silently correcting a typo
// is how a design ships a colour nobody chose.
const lightness = (value, source) => {
  if (0 > value || 1 < value) {
    throw new Error(`oklch lightness out of the 0…1 range: ${source}`);
  }

  return value;
};

/**
 * A scalar custom property (--n-h, --accent-c, --focus-ring-alpha …) as a number.
 */
function resolveScalar(value, vars, seen = []) {
  const asVar = VAR.exec(value.trim());

  if (null !== asVar) {
    const { name } = asVar.groups;

    if (seen.includes(name)) {
      throw new Error(`Custom property cycle: ${[...seen, name].join(' -> ')}`);
    }

    if (!(name in vars)) {
      throw new Error(`Unknown custom property --${name}`);
    }

    return resolveScalar(vars[name], vars, [...seen, name]);
  }

  const asCalc = CALC_SCALE.exec(value.trim());

  if (null !== asCalc) {
    const { name, factor } = asCalc.groups;

    // Both operands are finite, and their PRODUCT still need not be: 1e308 * 10
    // is Infinity. An unchecked Infinity here reaches formatColor() and lands
    // in theme.json as `oklch(50% Infinity 0)` — a colour the editor cannot
    // parse, emitted by a build that reported success.
    return finite(resolveScalar(`var(--${name})`, vars, seen) * decimal(factor, value), value);
  }

  return decimal(value, value);
}

/**
 * A colour token as { l (0…1), c, h, a } — following var() references through
 * the given custom-property map exactly as the browser would.
 *
 * Throws on anything it does not understand. That strictness is the feature: a
 * resolver that shrugged and returned a default would hand the contrast gate a
 * colour nobody ships.
 */
export function resolveColor(value, vars, seen = []) {
  const trimmed = value.trim();
  const asVar = VAR.exec(trimmed);

  if (null !== asVar) {
    const { name } = asVar.groups;

    if (seen.includes(name)) {
      throw new Error(`Custom property cycle: ${[...seen, name].join(' -> ')}`);
    }

    if (!(name in vars)) {
      throw new Error(`Unknown custom property --${name}`);
    }

    return resolveColor(vars[name], vars, [...seen, name]);
  }

  const match = OKLCH.exec(trimmed);

  if (null === match) {
    throw new Error(`Cannot resolve to an oklch colour: ${value}`);
  }

  const { l, pct, c, h, a } = match.groups;

  return {
    l: lightness('%' === pct ? decimal(l, value) / 100 : decimal(l, value), value),
    c: resolveScalar(c, vars, seen),
    h: resolveScalar(h, vars, seen),
    a: undefined === a ? 1 : resolveScalar(a, vars, seen),
  };
}

/**
 * The custom-property map a browser would compute for one palette in one scheme:
 * base scalars, the palette's three overrides, the neutral scale, then the
 * scheme's semantic colours. Later keys win, which mirrors the cascade our
 * generated CSS produces (:root, then .dark).
 */
export function varsFor(tokens, paletteSlug, scheme) {
  const palette = tokens.palettes[paletteSlug];

  if (undefined === palette) {
    throw new Error(`Unknown palette: ${paletteSlug}`);
  }

  return {
    ...tokens.base,
    ...palette,
    ...tokens.neutrals,
    ...tokens.colors.light,
    ...('dark' === scheme ? tokens.colors.dark : {}),
  };
}

/* ==========================================================================
   Contrast — WCAG, measured pessimistically
   ========================================================================== */

// WCAG 2.1 AA for normal-size text.
const MIN_CONTRAST = 4.5;

// Channel tolerance for "inside sRGB" — floating-point slack, not a real gamut
// allowance.
const GAMUT_EPSILON = 0.0001;

/**
 * Linear sRGB channels for an oklch colour, without gamut correction.
 */
function linearChannels({ l, c, h }) {
  const radians = (h * Math.PI) / 180;
  const a = c * Math.cos(radians);
  const b = c * Math.sin(radians);

  const long = (l + 0.3963377774 * a + 0.2158037573 * b) ** 3;
  const medium = (l - 0.1055613458 * a - 0.0638541728 * b) ** 3;
  const short = (l - 0.0894841775 * a - 1.291485548 * b) ** 3;

  return [
    4.0767416621 * long - 3.3077115913 * medium + 0.2309699292 * short,
    -1.2684380046 * long + 2.6097574011 * medium - 0.3413193965 * short,
    -0.0041960863 * long - 0.7034186147 * medium + 1.707614701 * short,
  ];
}

const insideSrgb = (channels) =>
  channels.every((channel) => channel >= -GAMUT_EPSILON && channel <= 1 + GAMUT_EPSILON);

/**
 * WCAG relative luminance, chroma-reduction half.
 *
 * Out-of-gamut handling is load-bearing: this design's accents reach past sRGB,
 * and how they are brought back in changes the answer by a quarter of a ratio
 * point. There is no single right answer to reproduce — CSS Color 4 §14 lets a
 * UA pick among several algorithms. So do not pretend to know: contrastRatio()
 * measures BOTH this and the naive clamp, and keeps the WORSE of the two.
 *
 * This half holds lightness and hue and bisects chroma to the gamut boundary.
 */
function mappedLuminance({ l, c, h }) {
  let channels = linearChannels({ l, c, h });

  if (!insideSrgb(channels)) {
    let low = 0;
    // Cap the bracket: no sRGB colour has chroma anywhere near 1, and a
    // parseable-but-absurd chroma would leave 32 halvings still astronomically
    // above the boundary, so `low` would stay 0 and the colour would be measured
    // as a neutral grey — passing the gate on a luminance it never had.
    let high = Math.min(c, 1);

    for (let step = 0; step < 32; step += 1) {
      const middle = (low + high) / 2;

      if (insideSrgb(linearChannels({ l, c: middle, h }))) {
        low = middle;
      } else {
        high = middle;
      }
    }

    channels = linearChannels({ l, c: low, h });
  }

  const [red, green, blue] = channels.map((channel) => Math.min(1, Math.max(0, channel)));

  return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

/**
 * WCAG relative luminance with out-of-gamut channels simply clamped.
 *
 * The naive reading. Kept precisely because it disagrees with the chroma
 * reduction above, and the disagreement is the uncertainty we refuse to gamble on.
 */
function clampedLuminance(color) {
  const [red, green, blue] = linearChannels(color).map((channel) =>
    Math.min(1, Math.max(0, channel)),
  );

  return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

const ratioFrom = (luminance, one, other) => {
  const [lighter, darker] = [luminance(one), luminance(other)].sort((x, y) => y - x);

  return (lighter + 0.05) / (darker + 0.05);
};

/**
 * WCAG contrast ratio between two resolved oklch colours, 1…21 — the PESSIMISTIC
 * of the two out-of-gamut readings.
 *
 * Translucent colours are REFUSED rather than measured: a ratio against a colour
 * whose real appearance depends on what is behind it is a number that means
 * nothing. Compositing would be the honest fix; until something needs it,
 * throwing keeps the gate from quietly reporting fiction.
 */
export function contrastRatio(one, other) {
  for (const color of [one, other]) {
    if (1 !== color.a) {
      throw new Error(`Refusing to measure contrast against a translucent colour (alpha ${color.a})`);
    }
  }

  return Math.min(ratioFrom(mappedLuminance, one, other), ratioFrom(clampedLuminance, one, other));
}

/**
 * Every text-on-surface pair the design depends on, as [textToken, surfaceToken].
 *
 * The design's own comments assert some of these (--muted-foreground clearing
 * 4.5:1 on three different surfaces, the status "-text" steps existing precisely
 * because the fill colours cannot carry text). Comments are not evidence — this
 * table turns each claim into a build-time fact.
 */
const CONTRAST_PAIRS = [
  ['primary-foreground', 'primary'],
  ['foreground', 'background'],
  ['foreground', 'card'],
  // --card-foreground is a SEPARATE token that merely happens to equal
  // --foreground today. Measuring only the pair that is currently a duplicate
  // means the day someone tints card text, nothing checks it.
  ['card-foreground', 'card'],
  ['foreground', 'muted'],
  ['muted-foreground', 'muted'],
  ['foreground', 'surface-2'],
  ['foreground', 'surface-3'],
  ['muted-foreground', 'background'],
  ['muted-foreground', 'card'],
  ['muted-foreground', 'surface-2'],
  ['secondary-foreground', 'secondary'],
  ['accent-foreground', 'accent'],
  ['popover-foreground', 'popover'],
  ['destructive-foreground', 'destructive'],
  ['success-foreground', 'success'],
  ['warning-foreground', 'warning'],
  ['sale-foreground', 'sale'],
  ['warning-text', 'background'],
  ['warning-text', 'card'],
  ['success-text', 'background'],
  ['success-text', 'card'],
  ['sale', 'background'],
  ['sale', 'card'],
  ['destructive', 'background'],
  ['footer-fg', 'footer-bg'],
  ['footer-muted', 'footer-bg'],
];

/**
 * Measure every pair, in every palette, in both schemes. Returns the failures
 * rather than throwing, so callers can report all of them at once — one throw
 * per build run beats seven rebuild-and-discover-the-next-one cycles.
 */
export function contrastFailures(tokens) {
  const failures = [];
  let measured = 0;

  for (const paletteSlug of Object.keys(tokens.palettes)) {
    for (const scheme of ['light', 'dark']) {
      const vars = varsFor(tokens, paletteSlug, scheme);

      for (const [textToken, surfaceToken] of CONTRAST_PAIRS) {
        // A scheme may legitimately not redefine a token (dark inherits
        // --warning / --warning-foreground from :root, for instance). The merged
        // map already models that; a genuinely missing token is a bug and
        // resolveColor throws for it.
        const ratio = contrastRatio(
          resolveColor(vars[textToken], vars),
          resolveColor(vars[surfaceToken], vars),
        );

        measured += 1;

        if (!Number.isFinite(ratio) || ratio < MIN_CONTRAST) {
          failures.push(
            `${paletteSlug}/${scheme}: --${textToken} on --${surfaceToken} is ` +
              `${ratio.toFixed(2)}:1, below AA (${MIN_CONTRAST}:1)`,
          );
        }
      }
    }
  }

  // A gate that measured nothing also reports nothing, and "no failures" reads
  // identically either way. An empty `palettes` map — a bad merge, a refactor
  // that renames the key, a generator run against a half-written source — would
  // otherwise sail through as a pass. The expected count is exact because both
  // dimensions are known: every palette is measured in both schemes.
  const expected = Object.keys(tokens.palettes).length * 2 * CONTRAST_PAIRS.length;

  if (0 === measured || measured !== expected) {
    throw new Error(
      `The contrast gate measured ${measured} pairs, expected ${expected}. ` +
        'A gate that measures nothing reports success — refusing to pretend it ran.',
    );
  }

  return failures;
}

export function assertAccessiblePalettes(tokens) {
  const failures = contrastFailures(tokens);

  if (0 < failures.length) {
    throw new Error(
      `${failures.length} palette colour pair(s) fail WCAG AA:\n  ${failures.join('\n  ')}\n` +
        'Fix the values in src/tokens/tokens.mjs — an inaccessible palette must not ship.',
    );
  }
}

/* ==========================================================================
   Emitters
   ========================================================================== */

const trimNumber = (value, decimals) =>
  Number(value.toFixed(decimals))
    .toString()
    .replace(/^0\./, '.');

/**
 * A resolved colour back as a concrete, var()-free oklch() literal — for
 * consumers that cannot follow custom properties, i.e. theme.json.
 */
export const formatColor = ({ l, c, h, a }) => {
  const body = `oklch(${trimNumber(l * 100, 3)}% ${trimNumber(c, 4)} ${trimNumber(h, 3)}`;

  return 1 === a ? `${body})` : `${body} / ${trimNumber(a, 4)})`;
};

const declarations = (entries, indent) =>
  Object.entries(entries)
    .map(([name, value]) => `${indent}--${name}: ${value};`)
    .join('\n');

/**
 * The block both dark selectors share. Kept as one string so the two can never
 * drift — an earlier generation of this file emitted them from the same map for
 * exactly that reason.
 */
const darkBlock = (tokens, indent) =>
  `${declarations(tokens.colors.dark, indent)}\n${declarations(tokens.shadows.dark, indent)}`;

export function buildTokensCss(tokens) {
  assertAccessiblePalettes(tokens);

  const root = [
    '  /* The three values a palette preset writes. Everything below derives. */',
    declarations(tokens.base, '  '),
    '',
    '  /* Neutral scale — raw material. Semantic tokens point at these; components never do. */',
    declarations(tokens.neutrals, '  '),
    '',
    declarations(tokens.colors.light, '  '),
    '',
    declarations(tokens.shadows.light, '  '),
    '',
    declarations(tokens.radius, '  '),
    '',
    declarations(tokens.spacing, '  '),
    '',
    declarations(tokens.fontRoles, '  '),
    declarations(tokens.aliases, '  '),
    '',
    declarations(tokens.typeScale, '  '),
    declarations(tokens.layout, '  '),
    '',
    declarations(tokens.motion, '  '),
    '',
    declarations(tokens.misc, '  '),
  ].join('\n');

  return `/* AUTO-GENERATED from src/tokens/tokens.mjs — do not edit. Run \`npm run tokens\`. */

/* Deliberately UN-LAYERED: Basecoat declares its own :root/.dark token defaults
 * un-layered too, and un-layered CSS beats every layer. Wrapped in @layer theme
 * these would lose to Basecoat and the Customizer could never move them.
 * Import order in app.css (after Basecoat) is what makes ours win.
 * See docs/gotchas/basecoat-tokens-are-un-layered.md */
:root {
${root}
}

.dark {
${darkBlock(tokens, '  ')}
}

@media (prefers-color-scheme: dark) {
  /* JS-disabled \`system\` visitors only. An explicit admin default or a stored
   * visitor choice puts .light/.dark on <html>, and either one excludes this
   * block — so it never fights a decision that has already been made.
   *
   * The :not() pair MUST stay wrapped in :where(). A bare
   * \`:root:not(.light):not(.dark)\` is specificity (0,3,0), because :not()
   * contributes its argument's specificity — which would outrank BOTH the
   * Customizer's inline \`:root{--accent-h:…}\` and a site owner's Additional
   * CSS, silently killing the palette choice for the commonest configuration
   * there is (default \`system\` + a dark OS). :where() contributes zero, so
   * this lands at (0,1,0): equal to Basecoat's own :root, which it beats on
   * source order, and equal to the overrides that come later and beat it. */
  :root:where(:not(.light):not(.dark)) {
${darkBlock(tokens, '    ')}
  }
}
`;
}

/**
 * The editor palette. Generated from the DEFAULT palette resolved to literals,
 * because theme.json is static JSON that cannot follow a custom property.
 *
 * Spec §5 listed "the block-editor palette does not follow the runtime pack
 * choice" as a known v1 limitation of the eight-pack world. With one identity
 * the limitation shrinks to "does not follow a non-default palette or a custom
 * accent" — still real, still documented, but no longer eight-ways wrong.
 */
const EDITOR_PALETTE = [
  'background',
  'foreground',
  'primary',
  'primary-foreground',
  'secondary',
  'secondary-foreground',
  'muted',
  'muted-foreground',
  'accent',
  'accent-foreground',
  'border',
  'destructive',
  'success',
  'warning',
  'sale',
];

export function buildThemeJson(tokens) {
  const vars = varsFor(tokens, 'warm-clay', 'light');

  return {
    $schema: 'https://schemas.wp.org/trunk/theme.json',
    version: 3,
    settings: {
      color: {
        palette: EDITOR_PALETTE.map((slug) => ({
          slug,
          name: titleCase(slug),
          color: formatColor(resolveColor(vars[slug], vars)),
        })),
      },
      typography: {
        fontFamilies: Object.entries(tokens.fontRoles).map(([role, fontFamily]) => ({
          slug: role.replace(/^font-/, ''),
          name: titleCase(role.replace(/^font-/, '')),
          fontFamily,
        })),
      },
    },
  };
}

// A slug becomes a single-quoted PHP array key. Quoting protects dashes and the
// like, but not a quote or a backslash — those would close the string and hand
// PHP a parse error, i.e. a fatal on every request once the file is required.
// Slugs come from our own token source, so this is a build-time assertion about
// what may enter the generated file, not a runtime defence.
const PALETTE_SLUG = /^[a-z][a-z0-9-]*$/;

// A palette value is interpolated into a single-quoted PHP string and from there
// into a <style> block. Only bare numbers may make that trip.
const PALETTE_VALUE = /^\d*\.?\d+$/;

// A palette IS these three properties — that claim is the whole architecture
// (ADR-008), and the PHP consumer reads exactly these keys. Validating only the
// key's SHAPE lets a fourth, innocently-named key through: it would be emitted
// into palettes.php, silently ignored by the consumer, and read by the next
// person as a supported knob. An allowlist makes the claim enforceable rather
// than aspirational.
const PALETTE_PROPERTIES = ['n-h', 'accent-h', 'accent-c'];

// PHPCS's WordPress.Arrays.MultipleStatementAlignment sniff requires every `=>`
// in a contiguous block of array items to land in the same column, padded to the
// widest key in that block. Computing it keeps the generated file PHPCS-clean
// without hand-editing it or reaching for phpcs:ignore.
const quoteKey = (key) => `'${key}'`;

const widestKey = (keys) => Math.max(...keys.map((key) => quoteKey(key).length));

const alignedArrow = (key, width) => {
  const label = quoteKey(key);

  return `${label}${' '.repeat(width - label.length + 1)}=>`;
};

/**
 * The seven palettes as a committed PHP file: one source, two consumers (the CSS
 * generator and the Customizer's sanitize/resolve pair).
 */
export function buildPalettesPhp(tokens) {
  const slugWidth = widestKey(Object.keys(tokens.palettes));

  const entries = Object.entries(tokens.palettes)
    .map(([slug, properties]) => {
      if (!PALETTE_SLUG.test(slug)) {
        throw new Error(`Refusing to emit a palette slug that is not a plain identifier: ${slug}`);
      }

      const names = Object.keys(properties);

      if (
        names.length !== PALETTE_PROPERTIES.length ||
        !PALETTE_PROPERTIES.every((expected) => names.includes(expected))
      ) {
        throw new Error(
          `Palette '${slug}' must define exactly ${PALETTE_PROPERTIES.join(', ')} — got ${names.join(', ')}`,
        );
      }

      // Per BLOCK, not per file: the sniff aligns each contiguous run of array
      // items on the widest key IN THAT RUN. Padding to a wider key from
      // elsewhere is itself a violation, not a harmless surplus.
      const propertyWidth = widestKey(names);

      const body = Object.entries(properties)
        .map(([property, value]) => {
          // No shape check on the key here: the allowlist above already fixes
          // the key set exactly, so a hostile key can never reach this line and
          // a pattern test would be unreachable code that reads like a guard.
          // The allowlist IS the injection guard for keys — the key and the
          // value are interpolated into single-quoted PHP by the same
          // expression, and both halves have to be pinned or neither is.
          if (!PALETTE_VALUE.test(value)) {
            throw new Error(`Refusing to emit a non-numeric palette value: ${property} = ${value}`);
          }

          return `\t\t${alignedArrow(property, propertyWidth)} '${value}',`;
        })
        .join('\n');

      return `\t${alignedArrow(slug, slugWidth)} [\n${body}\n\t],`;
    })
    .join('\n');

  return `<?php
/**
 * AUTO-GENERATED from src/tokens/tokens.mjs — do not edit. Run \`npm run tokens\`.
 *
 * The seven shipped colour palettes (ADR-008). A palette is three custom
 * properties: the neutral temperature and the accent's hue and chroma. Every
 * surface, border, wash and glow in the theme derives from them, which is what
 * makes "pick a palette" a three-declaration change.
 *
 * \`warm-clay\` equals the :root defaults, so selecting it emits no override.
 * Labels are NOT here — they are translatable strings and live in PHP source.
 *
 * @package Woodev\\Theme\\Base
 */

declare(strict_types=1);

return [
${entries}
];
`;
}
