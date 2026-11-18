import { describe, expect, it } from 'vitest';
import { eduidPollDelayMs, MILESTONES, pollDelayMs, reached, stepForPhase } from './flowLogic';

describe('stepForPhase', () => {
  it.each([
    ['created', 2], ['retrieved', 2],
    ['presentation', 3], ['verified', 3], ['token', 3], ['processing', 3], ['denied', 3],
    ['issued', 4],
  ] as const)('maps %s to step %i', (phase, step) => {
    expect(stepForPhase(phase)).toBe(step);
  });

  it('leaves the step alone without an offer, when expired and on errors', () => {
    expect(stepForPhase(null)).toBeNull();
    expect(stepForPhase('expired')).toBeNull();
    expect(stepForPhase('error')).toBeNull();
  });
});

describe('reached', () => {
  it('follows the order of the authorization code flow', () => {
    expect(MILESTONES.map((m) => reached(m.id, 'OFFER_CREATED'))).toEqual([false, false, false, false, false]);
    expect(MILESTONES.map((m) => reached(m.id, 'PRESENTATION_REQUESTED'))).toEqual([true, true, false, false, false]);
    expect(MILESTONES.map((m) => reached(m.id, 'PRESENTATION_VERIFIED'))).toEqual([true, true, true, false, false]);
    expect(MILESTONES.map((m) => reached(m.id, 'ACCESS_TOKEN_CREATED'))).toEqual([true, true, true, true, false]);
    expect(MILESTONES.map((m) => reached(m.id, 'CREDENTIAL_ISSUED'))).toEqual([true, true, true, true, true]);
  });

  it('knows nothing of a denied or missing status', () => {
    expect(reached('retrieved', 'AUTHORIZATION_DENIED')).toBe(false);
    expect(reached('retrieved', null)).toBe(false);
  });
});

describe('poll delays', () => {
  it('stops for the end states and slows down after the credential is issued', () => {
    expect(pollDelayMs('presentation')).toBe(1000);
    expect(pollDelayMs('denied')).toBeNull();
    expect(pollDelayMs('issued')).toBe(10_000);
    expect(eduidPollDelayMs('issued')).toBeNull();
    expect(eduidPollDelayMs('retrieved')).toBe(1000);
  });
});
