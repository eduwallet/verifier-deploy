import { describe, expect, it } from 'vitest';
import { phaseSeverity, pollDelayMs, stepForPhase, suppliedClaimNames } from './flowLogic';

describe('stepForPhase', () => {
  it.each([
    [null, 1], ['created', 2], ['retrieved', 2], ['token', 3], ['processing', 3],
    ['pending', 4], ['ready', 5], ['issued', 5], ['denied', 5],
  ] as const)('maps %s to step %i', (phase, step) => {
    expect(stepForPhase(phase)).toBe(step);
  });
});

describe('pollDelayMs', () => {
  it('polls every second, and every 10 seconds once the credential is issued', () => {
    for (const phase of ['created', 'retrieved', 'token', 'pending', 'ready', null] as const) {
      expect(pollDelayMs(phase)).toBe(1000);
    }
    expect(pollDelayMs('issued')).toBe(10_000);
  });

  it('stops on final states', () => {
    for (const phase of ['denied', 'expired', 'error'] as const) {
      expect(pollDelayMs(phase)).toBeNull();
    }
  });
});

describe('suppliedClaimNames', () => {
  it('lists the single-level supply paths once', () => {
    expect(suppliedClaimNames([
      { supply: [['name']] }, { supply: [['email'], ['name']] }, { supply: [] }, { supply: [['a', 'b']] },
    ])).toEqual(['name', 'email']);
  });
});

describe('phaseSeverity', () => {
  it('marks success and failure', () => {
    expect(phaseSeverity('issued')).toBe('success');
    expect(phaseSeverity('denied')).toBe('danger');
    expect(phaseSeverity('created')).toBe('secondary');
  });
});
