// src/tokens/tokens.mjs
/**
 * Single source of truth for design tokens.
 * NEVER edit theme.json, tokens.generated.css or inc/generated/palettes.php by
 * hand — run `npm run tokens`.
 *
 * The values below are transcribed VERBATIM from the approved design's token
 * block (docs/design/v2-mockup/tokens.css, itself exported from the artifact
 * HTML). Keeping them as CSS strings rather than as a parsed data model is
 * deliberate: what ships is character-for-character what was approved, and the
 * contrast gate reads the same strings through a strict resolver that throws on
 * anything it cannot parse. A data model would have let a transcription slip
 * change the design; a lax resolver would have let an unmeasurable colour skip
 * the gate.
 *
 * The architecture in one paragraph: a neutral TEMPERATURE (--n-h) generates the
 * whole neutral scale, an accent is two numbers (--accent-h / --accent-c), and a
 * palette preset is exactly those three values. Everything else — surfaces,
 * borders, washes, glows, the footer, elevation — derives. That is what makes
 * "pick a palette" a three-property change instead of a re-skin.
 *
 * See docs/adr/ADR-008-single-visual-identity.md.
 */

/**
 * The neutral scale. Raw material: semantic tokens point AT these, components
 * never do. Hue comes from --n-h, so a palette change re-tints every surface.
 */
const neutrals = {
  'n-0': 'oklch(99%   0.002 var(--n-h))',
  'n-50': 'oklch(97.2% 0.003 var(--n-h))',
  'n-100': 'oklch(94.6% 0.004 var(--n-h))',
  'n-150': 'oklch(91%   0.005 var(--n-h))',
  'n-200': 'oklch(87%   0.006 var(--n-h))',
  'n-300': 'oklch(78%   0.008 var(--n-h))',
  'n-400': 'oklch(65%   0.010 var(--n-h))',
  'n-500': 'oklch(55%   0.012 var(--n-h))',
  'n-600': 'oklch(46%   0.013 var(--n-h))',
  'n-700': 'oklch(37%   0.014 var(--n-h))',
  'n-800': 'oklch(27%   0.014 var(--n-h))',
  'n-850': 'oklch(22%   0.013 var(--n-h))',
  'n-900': 'oklch(17%   0.012 var(--n-h))',
  'n-950': 'oklch(13%   0.011 var(--n-h))',
};

export const tokens = {
  /**
   * The three values a palette preset writes, at their defaults (warm-clay).
   * The Customizer overrides these and nothing else.
   */
  base: {
    'n-h': '68',
    'accent-h': '40',
    'accent-c': '0.088',
    'focus-ring-alpha': '0.18',
  },

  neutrals,

  colors: {
    light: {
      primary: 'oklch(47% var(--accent-c) var(--accent-h))',
      'primary-hover': 'oklch(40% var(--accent-c) var(--accent-h))',
      'primary-foreground': 'oklch(99% 0.008 var(--accent-h))',
      ring: 'oklch(47% var(--accent-c) var(--accent-h))',

      background: 'var(--n-0)',
      foreground: 'var(--n-900)',
      card: 'oklch(100% 0 0)',
      'card-foreground': 'var(--n-900)',
      popover: 'oklch(100% 0 0)',
      'popover-foreground': 'var(--n-900)',
      secondary: 'var(--n-100)',
      'secondary-foreground': 'var(--n-800)',
      muted: 'var(--n-50)',
      // Half-step between --n-500 and --n-600. The scale is raw material; this
      // semantic token is TUNED so secondary text clears 4.5:1 on --background,
      // --card AND --surface-2 — at n-500 it only made 4.48:1 on --surface-2.
      // Do not point it back at a bare scale step; the gate measures all three.
      'muted-foreground': 'oklch(52% 0.012 var(--n-h))',
      accent: 'var(--n-100)',
      'accent-foreground': 'var(--n-900)',
      border: 'var(--n-200)',
      'border-strong': 'var(--n-300)',
      input: 'var(--n-200)',
      'surface-2': 'var(--n-50)',
      'surface-3': 'var(--n-100)',

      destructive: 'oklch(55% 0.19 25)',
      'destructive-foreground': 'oklch(99% 0.01 25)',
      success: 'oklch(52% 0.13 155)',
      'success-foreground': 'oklch(99% 0.01 155)',
      warning: 'oklch(66% 0.14 75)',
      'warning-foreground': 'oklch(20% 0.04 75)',
      // Amber is unusable as text at fill lightness (~3:1 on a light surface).
      // Text-on-surface gets its own darker step; dark inverts it to a lighter one.
      'warning-text': 'oklch(48% 0.12 75)',
      'success-text': 'oklch(44% 0.13 155)',
      sale: 'oklch(55% 0.19 25)',
      'sale-foreground': 'oklch(99% 0.01 25)',
      star: 'oklch(72% 0.15 78)',

      'ring-halo': 'oklch(47% var(--accent-c) var(--accent-h) / var(--focus-ring-alpha))',
      'primary-wash': 'oklch(47% var(--accent-c) var(--accent-h) / .07)',
      'select-bg': 'oklch(47% var(--accent-c) var(--accent-h) / .20)',
      scrim: 'oklch(15% 0.01 var(--n-h) / .5)',

      // The footer is deliberately dark in BOTH schemes.
      'footer-bg': 'var(--n-950)',
      'footer-fg': 'var(--n-100)',
      'footer-muted': 'var(--n-400)',
      'footer-border': 'oklch(30% 0.01 var(--n-h))',
      'footer-chip': 'oklch(26% 0.01 var(--n-h))',
    },

    dark: {
      background: 'var(--n-950)',
      foreground: 'var(--n-100)',
      card: 'var(--n-900)',
      'card-foreground': 'var(--n-100)',
      popover: 'var(--n-850)',
      'popover-foreground': 'var(--n-100)',
      secondary: 'var(--n-850)',
      'secondary-foreground': 'var(--n-150)',
      muted: 'var(--n-900)',
      'muted-foreground': 'var(--n-400)',
      accent: 'var(--n-850)',
      'accent-foreground': 'var(--n-50)',
      border: 'oklch(30% 0.012 var(--n-h))',
      'border-strong': 'oklch(40% 0.014 var(--n-h))',
      input: 'oklch(34% 0.013 var(--n-h))',
      'surface-2': 'var(--n-900)',
      'surface-3': 'var(--n-850)',

      primary: 'oklch(72% calc(var(--accent-c) * .95) var(--accent-h))',
      'primary-hover': 'oklch(78% calc(var(--accent-c) * .95) var(--accent-h))',
      'primary-foreground': 'oklch(18% 0.02 var(--accent-h))',
      ring: 'oklch(72% var(--accent-c) var(--accent-h))',

      destructive: 'oklch(66% 0.17 25)',
      'destructive-foreground': 'oklch(16% 0.03 25)',
      success: 'oklch(70% 0.13 155)',
      'success-foreground': 'oklch(16% 0.03 155)',
      sale: 'oklch(70% 0.17 25)',
      'sale-foreground': 'oklch(16% 0.03 25)',
      star: 'oklch(78% 0.14 78)',
      'warning-text': 'oklch(80% 0.13 75)',
      'success-text': 'oklch(76% 0.13 155)',

      'ring-halo': 'oklch(72% var(--accent-c) var(--accent-h) / .25)',
      'primary-wash': 'oklch(72% var(--accent-c) var(--accent-h) / .10)',
      'select-bg': 'oklch(72% var(--accent-c) var(--accent-h) / .25)',
      scrim: 'oklch(8% 0.01 var(--n-h) / .66)',

      'footer-bg': 'var(--n-900)',
      'footer-fg': 'var(--n-100)',
      'footer-muted': 'var(--n-400)',
      'footer-border': 'var(--border)',
      'footer-chip': 'var(--n-850)',
    },
  },

  /**
   * Elevation. Light leans on soft drop shadows; dark cannot — it substitutes a
   * hairline plus a faint accent glow, which is why --glow exists at all.
   */
  shadows: {
    light: {
      'shadow-xs': '0 1px 2px oklch(20% 0.02 var(--n-h) / .06)',
      'shadow-sm':
        '0 1px 2px oklch(20% 0.02 var(--n-h) / .05), 0 2px 6px oklch(20% 0.02 var(--n-h) / .06)',
      'shadow-md':
        '0 2px 4px oklch(20% 0.02 var(--n-h) / .05), 0 8px 20px oklch(20% 0.02 var(--n-h) / .09)',
      'shadow-lg':
        '0 8px 24px oklch(20% 0.02 var(--n-h) / .10), 0 24px 48px oklch(20% 0.02 var(--n-h) / .12)',
      glow: 'none',
    },
    dark: {
      'shadow-xs': '0 0 0 1px oklch(100% 0 0 / .02)',
      'shadow-sm': '0 1px 2px oklch(0% 0 0 / .4)',
      'shadow-md': '0 2px 8px oklch(0% 0 0 / .45)',
      'shadow-lg': '0 12px 40px oklch(0% 0 0 / .55)',
      glow:
        '0 0 0 1px oklch(72% var(--accent-c) var(--accent-h) / .18),\n' +
        '          0 8px 30px oklch(72% var(--accent-c) var(--accent-h) / .10)',
    },
  },

  /**
   * The seven shipped palettes. A palette is three numbers — that is the whole
   * point of the token architecture. `warm-clay` equals the :root defaults, so
   * it emits no override at all.
   *
   * Labels are NOT here: they are user-facing strings and must be translatable,
   * so they live in PHP (Customizer\Settings) where __() can see them.
   */
  palettes: {
    'warm-clay': { 'n-h': '68', 'accent-h': '40', 'accent-c': '0.088' },
    'cold-petrol': { 'n-h': '264', 'accent-h': '214', 'accent-c': '0.105' },
    graphite: { 'n-h': '264', 'accent-h': '250', 'accent-c': '0.024' },
    forest: { 'n-h': '70', 'accent-h': '152', 'accent-c': '0.100' },
    sand: { 'n-h': '74', 'accent-h': '75', 'accent-c': '0.110' },
    wine: { 'n-h': '60', 'accent-h': '18', 'accent-c': '0.130' },
    'night-indigo': { 'n-h': '274', 'accent-h': '274', 'accent-c': '0.130' },
  },

  /** One base; every other radius is calc()'d off it, so the Customizer's
   * "rounded → square" control is a single property. */
  radius: {
    radius: '10px',
    'radius-sm': 'calc(var(--radius) - 4px)',
    'radius-md': 'calc(var(--radius) - 2px)',
    'radius-lg': 'var(--radius)',
    'radius-xl': 'calc(var(--radius) + 6px)',
    'radius-full': '999px',
  },

  /** 4pt base. */
  spacing: {
    'space-2xs': '0.25rem',
    'space-xs': '0.5rem',
    'space-sm': '0.75rem',
    'space-md': '1rem',
    'space-lg': '1.5rem',
    'space-xl': '2rem',
    'space-2xl': '3rem',
    'space-3xl': '4.5rem',
    'space-4xl': '6.5rem',
  },

  /**
   * Three type roles (ADR-007). Each falls back to the system stack, so an
   * unloaded webfont degrades to the v1 behaviour rather than to nothing.
   */
  fontRoles: {
    'font-display': '"Golos Text", system-ui, "Segoe UI", Roboto, sans-serif',
    'font-body': '"IBM Plex Sans", system-ui, "Segoe UI", Roboto, sans-serif',
    'font-mono': '"IBM Plex Mono", ui-monospace, "SF Mono", Menlo, monospace',
  },

  /** Modular scale — a perfect fourth, tightened for UI density. */
  typeScale: {
    'text-2xs': '0.6875rem',
    'text-xs': '0.75rem',
    'text-sm': '0.8125rem',
    'text-base': '0.9375rem',
    'text-md': '1.0625rem',
    'text-lg': '1.25rem',
    'text-xl': '1.5rem',
    'text-2xl': '1.875rem',
    'text-3xl': '2.375rem',
    'text-4xl': '3rem',
    'text-display': 'clamp(2.5rem, 4.2vw + 1rem, 4rem)',
    'lh-tight': '1.08',
    'lh-snug': '1.28',
    'lh-body': '1.6',
    measure: '68ch',
  },

  layout: {
    container: '1200px',
    'container-wide': '1320px',
  },

  motion: {
    'ease-out': 'cubic-bezier(.22,.61,.36,1)',
    'ease-in': 'cubic-bezier(.55,.06,.68,.19)',
    'ease-in-out': 'cubic-bezier(.65,.05,.36,1)',
    'dur-fast': '120ms',
    dur: '200ms',
    'dur-slow': '320ms',
  },

  misc: {
    rule: '1px solid var(--border)',
    // Alpha stencil for the <select> chevron mask. The stroke colour inside the
    // data URI is never rendered — the visible colour comes from a token.
    'icon-chevron':
      'url("data:image/svg+xml;utf8,' +
      "<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16' " +
      "fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'>" +
      "<path d='m4 6 4 4 4-4'/></svg>\")",
  },

  /**
   * Aliases kept so nothing that already reads Basecoat's/Tailwind's spelling
   * breaks when the identity lands. --font-sans is the BODY role: that is what
   * every existing consumer meant by "the sans font". --font-mono needs no
   * alias — the role already carries that exact name.
   */
  aliases: {
    'font-sans': 'var(--font-body)',
  },
};
