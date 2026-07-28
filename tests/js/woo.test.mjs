// tests/js/woo.test.mjs — pure-logic coverage for the quantity stepper's
// clamping arithmetic (B8, docs/plans/2026-07-28-catalogue-and-product.md).
//
// This project's Vitest setup has no jsdom/happy-dom (see vitest.config.mjs
// and package.json — the only other tests/js specs are Node-side build
// scripts), so the DOM-wiring half of src/js/woo.js (the delegated click
// listener, un-hiding the buttons) is not exercised here; that half is a
// thin, mechanical wrapper with no arithmetic to get wrong, and is left to
// tests/e2e-woo. computeSteppedValue() is exported specifically so the part
// that DOES have logic — clamping to min/max, defaulting `step`, tolerating
// an unparsable current value — can be tested directly, without a browser.
import { describe, expect, it } from 'vitest';
import { computeSteppedValue } from '../../src/js/woo.js';

describe('computeSteppedValue', () => {
  it('adds one default step when no step attribute is set', () => {
    expect(computeSteppedValue(1, 1, {})).toBe(2);
    expect(computeSteppedValue(5, -1, {})).toBe(4);
  });

  it('honours a custom step size', () => {
    expect(computeSteppedValue(10, 1, { step: '5' })).toBe(15);
    expect(computeSteppedValue(10, -1, { step: '5' })).toBe(5);
  });

  it('clamps at the configured max', () => {
    expect(computeSteppedValue(9, 1, { max: '10' })).toBe(10);
    expect(computeSteppedValue(10, 1, { max: '10' })).toBe(10);
  });

  it('clamps at the configured min', () => {
    expect(computeSteppedValue(1, -1, { min: '1' })).toBe(1);
    expect(computeSteppedValue(0, -1, { min: '1' })).toBe(1);
  });

  it('treats an empty min/max as "no bound"', () => {
    expect(computeSteppedValue(1000000, 1, { min: '', max: '' })).toBe(1000001);
  });

  it('falls back to 0 when the current value cannot be parsed', () => {
    expect(computeSteppedValue(Number.NaN, 1, {})).toBe(1);
  });

  it('falls back to a step of 1 when the step attribute is not a number', () => {
    expect(computeSteppedValue(1, 1, { step: 'any' })).toBe(2);
  });
});
