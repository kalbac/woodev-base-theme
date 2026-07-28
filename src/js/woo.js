// src/js/woo.js — WooCommerce-only front-end behavior. Plain vanilla JS, no
// Alpine: this bundle is enqueued independently of app.js (Woo\Assets.php),
// and their relative load order is not guaranteed, so this module cannot
// depend on window.Alpine having run yet.
//
// Drives two things, both of them enhancements over markup that already works
// without this file:
//
//   - the product-page quantity stepper (inc/Woo/ProductPage.php, B8). The
//     buttons render `hidden` in PHP, so a visitor with JS disabled gets a
//     plain, always-usable number input rather than two dead buttons; this
//     module un-hides them before wiring up clicks.
//   - the catalogue filter rail's mobile collapse (inc/Woo/FilterRail.php,
//     A14). PHP renders a static head over an open panel — every filter
//     visible with no script at all — and this module swaps the head's title
//     for a real `<button aria-expanded>` and collapses the panel while the
//     rail is stacked above the products.

const STEP_BUTTON_SELECTOR = '.wtb-qty-step';
const FILTER_RAIL_SELECTOR = '.wtb-filter-rail';

// Must match the `@media (min-width: 64rem)` breakpoint in src/css/woo.css at
// which the rail becomes a sticky column beside the grid. Below it the rail is
// stacked above the products, which is where a collapse earns its keep; above
// it, a closed rail would leave a 248px column empty.
const FILTER_RAIL_STACKED = '(max-width: 63.999rem)';

/**
 * Reimplements stepUp()/stepDown()'s clamping arithmetic by hand, for the
 * fallback path below. Pure and DOM-free on purpose — this is the one part
 * of the module with actual logic to get wrong (off-by-one clamping, a `step`
 * of "" parsing to NaN, …), so it is exported and unit-tested directly
 * (tests/js/woo.test.mjs) rather than only indirectly through a DOM event.
 *
 * @param {number} current Current numeric value.
 * @param {1|-1} delta Direction to step.
 * @param {{min?: string, max?: string, step?: string}} bounds Raw attribute
 *   strings, exactly as HTMLInputElement exposes them — '' means "no bound".
 * @returns {number}
 */
export function computeSteppedValue(current, delta, bounds) {
  const step = Number.parseFloat(bounds.step ?? '') || 1;
  const min =
    bounds.min === undefined || bounds.min === '' ? -Infinity : Number.parseFloat(bounds.min);
  const max =
    bounds.max === undefined || bounds.max === '' ? Infinity : Number.parseFloat(bounds.max);
  const base = Number.isFinite(current) ? current : 0;

  return Math.min(max, Math.max(min, base + delta * step));
}

/**
 * Apply one step to a quantity <input>, honouring its own min/max/step
 * exactly as the browser's native stepper would, then tell the rest of the
 * page (Woo's own cart-totals / variation-price listeners) that the value
 * changed.
 *
 * @param {HTMLInputElement} input
 * @param {1|-1} delta
 */
function applyStep(input, delta) {
  const before = input.value;

  // stepUp()/stepDown() honour min/max/step for free — the manual fallback
  // below has to reimplement that arithmetic itself. They are documented to
  // throw (InvalidStateError) when the current value cannot be stepped
  // (e.g. it does not align with `step`), so the manual path also serves as
  // the recovery from that throw, not only as the no-support fallback.
  const method = delta > 0 ? 'stepUp' : 'stepDown';

  if (typeof input[method] === 'function') {
    try {
      input[method]();
    } catch {
      manualStep(input, delta);
    }
  } else {
    manualStep(input, delta);
  }

  if (input.value !== before) {
    // Woo's own scripts (cart totals, variation price) listen for `change`,
    // not `input` — dispatching anything else would leave them unaware the
    // quantity moved.
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }
}

/**
 * @param {HTMLInputElement} input
 * @param {1|-1} delta
 */
function manualStep(input, delta) {
  const next = computeSteppedValue(Number.parseFloat(input.value), delta, {
    min: input.min,
    max: input.max,
    step: input.step,
  });

  input.value = String(next);
}

/**
 * Delegated click handler: a stepper button is always inside Woo's
 * `.quantity` wrapper next to the `input.qty` it controls (see
 * templates/global/quantity-input.php, and ProductPage::quantity_step_down()
 * / quantity_step_up()).
 *
 * @param {MouseEvent} event
 */
function handleClick(event) {
  const button =
    event.target instanceof Element ? event.target.closest(STEP_BUTTON_SELECTOR) : null;

  if (!button) {
    return;
  }

  const wrapper = button.closest('.quantity');
  const input = wrapper ? wrapper.querySelector('input.qty') : null;

  if (!(input instanceof HTMLInputElement)) {
    return;
  }

  applyStep(input, button.dataset.step === 'up' ? 1 : -1);
}

/**
 * Turn the filter rail's static head into a real disclosure, and keep it
 * collapsed while the rail is stacked above the products.
 *
 * The toggle BUTTON is created here rather than rendered by PHP, and that is
 * the whole progressive-enhancement argument: a button that toggles a panel is
 * useless without this script, and a visible dead control is worse than none.
 * Without JavaScript the rail is a heading over an open panel — every filter
 * visible and usable — which is the correct degraded state and needs no markup
 * of its own.
 *
 * `<details>`/`<summary>` was tried first, twice, and template-parts/woo/
 * filter-rail.php records why it does not work here. The short version: closed
 * it renders nothing and CSS cannot undo that; served open and closed by this
 * function it left the grid row sized for the OPEN panel, i.e. a screen-height
 * gap under the button.
 *
 * The breakpoint listener re-syncs rather than only running at load, and that
 * is not tidiness. Collapsing once on a narrow viewport and never reopening
 * would leave a visitor who rotates a tablet — or drags a window wider —
 * looking at a 248px column with a "Filters" button and nothing under it. It
 * does discard a toggle the visitor made themselves, but only when they cross
 * the breakpoint, at which point the rail's whole layout has changed anyway.
 */
function enhanceFilterRail() {
  const rail = document.querySelector(FILTER_RAIL_SELECTOR);
  const panel = rail && rail.querySelector('.wtb-filter-rail__panel');
  const title = rail && rail.querySelector('.wtb-filter-rail__title');

  if (!panel || !title || !panel.id) {
    return;
  }

  const toggle = document.createElement('button');
  toggle.type = 'button';
  toggle.className = 'wtb-filter-rail__toggle';
  toggle.setAttribute('aria-controls', panel.id);

  // Move the title's own children across rather than copying `innerHTML`:
  // same rendered result, no re-parsing of markup, and the icon SVG keeps its
  // identity instead of being cloned.
  while (title.firstChild) {
    toggle.append(title.firstChild);
  }

  title.replaceWith(toggle);

  const setExpanded = (expanded) => {
    panel.hidden = !expanded;
    toggle.setAttribute('aria-expanded', String(expanded));
  };

  const stacked = window.matchMedia(FILTER_RAIL_STACKED);
  const syncToBreakpoint = () => setExpanded(!stacked.matches);

  toggle.addEventListener('click', () => setExpanded(panel.hidden));
  stacked.addEventListener('change', syncToBreakpoint);
  syncToBreakpoint();
}

// `typeof document !== 'undefined'` rather than a bare reference: this bundle
// is enqueued only on a real front-end request (Woo\Assets.php), where
// `document` always exists, so the guard changes nothing there. It exists so
// `computeSteppedValue()` above stays importable from a plain Node test
// (tests/js/woo.test.mjs) — this project's toolchain has no jsdom/happy-dom,
// so a DOM-dependent module-level side effect would otherwise crash the
// import before a single assertion ran.
if (typeof document !== 'undefined') {
  document.addEventListener('click', handleClick);

  document.querySelectorAll(`${STEP_BUTTON_SELECTOR}[hidden]`).forEach((button) => {
    button.hidden = false;
  });

  enhanceFilterRail();
}
