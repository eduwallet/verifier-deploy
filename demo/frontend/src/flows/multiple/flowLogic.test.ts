import { describe, expect, it } from 'vitest';
import type { StatusResponse } from '@/api/types';
import { credentialState, overallPhase, stepForStatus } from './flowLogic';

function status(statusName: string, extra: Partial<StatusResponse> = {}): StatusResponse {
  return {
    run: 'r1', status: statusName, phase: 'token', createdAt: 1, lastUpdatedAt: 2, expiresAt: null, reason: null,
    credentialUuid: null, issued: [], deferred: [], requests: {}, notification: null, ...extra,
  };
}
const transaction = (state: string) => ({ id: 's.t', key: 'deferred', status: state, expiresAt: 9, reason: null });

describe('stepForStatus', () => {
  it('starts at the form', () => {
    expect(stepForStatus(null)).toBe(1);
  });

  it('stays at the offer until the issuer has a deferred transaction', () => {
    expect(stepForStatus(status('OFFER_CREATED'))).toBe(2);
    // the generic credentials are issued, but the wallet has not requested the deferred one
    expect(stepForStatus(status('ACCESS_TOKEN_CREATED', { issued: [{ key: 'generic1', uuid: 'u' }] }))).toBe(2);
  });

  it('is at the release as soon as the transaction exists, whatever the session status says', () => {
    expect(stepForStatus(status('CREDENTIAL_PENDING', { deferred: [transaction('CREDENTIAL_PENDING')] }))).toBe(3);
    // the wallet requested a generic credential after the deferred one
    expect(stepForStatus(status('CREDENTIAL_REQUEST_RECEIVED', { deferred: [transaction('CREDENTIAL_PENDING')] }))).toBe(3);
    expect(stepForStatus(status('CREDENTIAL_READY', { deferred: [transaction('CREDENTIAL_READY')] }))).toBe(3);
    // collected, but another credential is still open
    expect(stepForStatus(status('CREDENTIAL_READY', { deferred: [transaction('CREDENTIAL_ISSUED')] }))).toBe(3);
  });

  it('completes when everything is issued or the deferred credential is denied', () => {
    expect(stepForStatus(status('CREDENTIAL_ISSUED', { deferred: [transaction('CREDENTIAL_ISSUED')] }))).toBe(4);
    expect(stepForStatus(status('CREDENTIAL_DENIED'))).toBe(4);
    expect(stepForStatus(status('CREDENTIAL_PENDING', { deferred: [transaction('CREDENTIAL_DENIED')] }))).toBe(4);
  });
});

describe('credentialState', () => {
  const s = status('CREDENTIAL_PENDING', { issued: [{ key: 'generic1', uuid: 'u' }], deferred: [transaction('CREDENTIAL_PENDING')] });

  it('tells issued, deferred and open credentials apart', () => {
    expect(credentialState('generic1', s)).toBe('issued');
    expect(credentialState('generic2', s)).toBe('waiting');
    expect(credentialState('deferred', s)).toBe('pending');
    expect(credentialState('generic1', null)).toBe('waiting');
  });

  it('follows the transaction', () => {
    expect(credentialState('deferred', status('x', { deferred: [transaction('CREDENTIAL_READY')] }))).toBe('ready');
    expect(credentialState('deferred', status('x', { deferred: [transaction('CREDENTIAL_DENIED')] }))).toBe('denied');
    expect(credentialState('deferred', status('x', { deferred: [transaction('CREDENTIAL_ISSUED')] }))).toBe('issued');
  });
});

describe('overallPhase', () => {
  it('reports the deferred transaction while the session status is not telling', () => {
    expect(overallPhase(null, false)).toBeNull();
    expect(overallPhase(null, true)).toBe('created');
    expect(overallPhase(status('ACCESS_TOKEN_CREATED', { deferred: [transaction('CREDENTIAL_PENDING')] }), true)).toBe('pending');
    expect(overallPhase(status('CREDENTIAL_READY', { deferred: [transaction('CREDENTIAL_READY')] }), true)).toBe('ready');
    expect(overallPhase(status('x', { deferred: [transaction('CREDENTIAL_DENIED')] }), true)).toBe('denied');
    expect(overallPhase(status('CREDENTIAL_ISSUED', { phase: 'issued' }), true)).toBe('issued');
    expect(overallPhase(status('x', { phase: 'expired' }), true)).toBe('expired');
  });
});
