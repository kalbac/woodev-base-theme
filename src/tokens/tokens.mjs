// src/tokens/tokens.mjs
/**
 * Single source of truth for design tokens.
 * NEVER edit theme.json or tokens.generated.css by hand — run `npm run tokens`.
 */
export const tokens = {
  colors: {
    light: {
      background: 'oklch(1 0 0)',
      foreground: 'oklch(0.145 0 0)',
      primary: 'oklch(0.205 0 0)',
      'primary-foreground': 'oklch(0.985 0 0)',
      secondary: 'oklch(0.97 0 0)',
      'secondary-foreground': 'oklch(0.205 0 0)',
      muted: 'oklch(0.97 0 0)',
      'muted-foreground': 'oklch(0.556 0 0)',
      accent: 'oklch(0.97 0 0)',
      'accent-foreground': 'oklch(0.205 0 0)',
      destructive: 'oklch(0.577 0.245 27.325)',
      border: 'oklch(0.922 0 0)',
      input: 'oklch(0.922 0 0)',
      ring: 'oklch(0.708 0 0)',
    },
    dark: {
      background: 'oklch(0.145 0 0)',
      foreground: 'oklch(0.985 0 0)',
      primary: 'oklch(0.922 0 0)',
      'primary-foreground': 'oklch(0.205 0 0)',
      secondary: 'oklch(0.269 0 0)',
      'secondary-foreground': 'oklch(0.985 0 0)',
      muted: 'oklch(0.269 0 0)',
      'muted-foreground': 'oklch(0.708 0 0)',
      accent: 'oklch(0.269 0 0)',
      'accent-foreground': 'oklch(0.985 0 0)',
      destructive: 'oklch(0.704 0.191 22.216)',
      border: 'oklch(1 0 0 / 10%)',
      input: 'oklch(1 0 0 / 15%)',
      ring: 'oklch(0.556 0 0)',
    },
  },
  radius: '0.625rem',
  /**
   * Curated accent presets (spec §6). Values copied verbatim from the shipped
   * node_modules/tailwindcss/theme.css: `light` is the palette's -600 shade,
   * `dark` its -400. Neutral's hue is 0 rather than Tailwind's `none` — chroma
   * is 0, so it is the same colour in the spelling the rest of this file uses.
   *
   * The `--primary-foreground` and `--ring` halves of each tuple are DERIVED in
   * buildPrimaryPresets(); do not add them here or the pair can drift.
   */
  primaryPalette: {
    neutral: { light: 'oklch(43.9% 0 0)', dark: 'oklch(70.8% 0 0)' },
    blue: { light: 'oklch(54.6% 0.245 262.881)', dark: 'oklch(70.7% 0.165 254.624)' },
    green: { light: 'oklch(62.7% 0.194 149.214)', dark: 'oklch(79.2% 0.209 151.711)' },
    red: { light: 'oklch(57.7% 0.245 27.325)', dark: 'oklch(70.4% 0.191 22.216)' },
    rose: { light: 'oklch(58.6% 0.253 17.585)', dark: 'oklch(71.2% 0.194 13.428)' },
    orange: { light: 'oklch(64.6% 0.222 41.116)', dark: 'oklch(75% 0.183 55.934)' },
    yellow: { light: 'oklch(68.1% 0.162 75.834)', dark: 'oklch(85.2% 0.199 91.936)' },
    violet: { light: 'oklch(54.1% 0.281 293.009)', dark: 'oklch(70.2% 0.183 293.541)' },
  },
  fonts: {
    sans: "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
    mono: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
  },
};
