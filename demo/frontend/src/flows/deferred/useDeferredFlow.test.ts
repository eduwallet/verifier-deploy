import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope, nextTick, reactive } from 'vue';
import type { DeferredDetail, StatusResponse } from '@/api/types';

vi.mock('@/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('@/api/client')>();
  return {
    ...original,
    api: { catalog: vi.fn(), issue: vi.fn(), status: vi.fn(), offer: vi.fn(), deferred: vi.fn(), authorize: vi.fn(), deny: vi.fn() },
  };
});

import { api } from '@/api/client';
import { useDeferredFlow } from './useDeferredFlow';

const mocked = vi.mocked(api, true);

function status(statusName: string, phase: StatusResponse['phase'], extra: Partial<StatusResponse> = {}): StatusResponse {
  return {
    run: 'r1', status: statusName, phase, createdAt: 1, lastUpdatedAt: 2, expiresAt: null, reason: null,
    credentialUuid: null, requests: {}, notification: null, ...extra,
  };
}

const own: DeferredDetail = {
  id: 'abcdef12-0000-0000-0000-000000000000', status: 'CREDENTIAL_PENDING', createdAt: 1, expiresAt: 2, expiresInSeconds: 3000,
  credential_configuration_id: 'DeferredCredential', format: 'vc+jwt', proofs: 1, ttl: 3600, interval: 5,
  data: { name: 'Demo User' }, claims_supplied: {},
  claims: [
    { path: ['name'], type: 'string', mandatory: true, origin: [['#any', 'name']], supply: [['name']] },
    { path: ['email'], type: 'string', mandatory: true, origin: [['#any', 'email']], supply: [['email']] },
  ],
};

describe('useDeferredFlow', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.clearAllMocks();
    mocked.catalog.mockResolvedValue({
      csrfToken: 't', environment: 'dev',
      issuers: { sandbox: { name: 'Sandbox', configured: true } },
      credentials: { deferred: { title: 'Deferred credential', issuer: 'sandbox', deferred: true, fields: [
        { name: 'name', label: 'Name', default: 'Demo User', maxLength: 100 },
        { name: 'email', label: 'Email', default: 'demouser@example.com', maxLength: 100 },
      ] } },
    });
    mocked.issue.mockResolvedValue({ run: 'r1', offerId: 'abcdef12-0000-0000-0000-000000000000', offerUri: 'openid-credential-offer://?x', txCode: null, data: {} });
    mocked.offer.mockResolvedValue({ offerUri: 'x', offer: { credential_issuer: 'https://issuer' } });
    mocked.deferred.mockResolvedValue({ items: [], own });
    mocked.authorize.mockResolvedValue({ authorized: true, claims: {}, result: {} });
  });
  afterEach(() => vi.useRealTimers());

  async function setup() {
    const scope = effectScope();
    const flow = scope.run(() => reactive(useDeferredFlow()))!;
    await flow.init();
    return { flow, scope };
  }

  it('starts with the defaults of the catalog', async () => {
    const { flow, scope } = await setup();
    expect(flow.form).toEqual({ name: 'Demo User', email: 'demouser@example.com' });
    expect(flow.step).toBe(1);
    scope.stop();
  });

  it('follows the offer through all steps and slows the poll when issued', async () => {
    const { flow, scope } = await setup();
    mocked.status
      .mockResolvedValueOnce(status('OFFER_CREATED', 'created'))
      .mockResolvedValueOnce(status('OFFER_URI_RETRIEVED', 'retrieved'))
      .mockResolvedValueOnce(status('ACCESS_TOKEN_CREATED', 'token'))
      .mockResolvedValue(status('CREDENTIAL_PENDING', 'pending'));

    await flow.issue();
    await vi.advanceTimersByTimeAsync(0);
    await nextTick();
    expect(flow.step).toBe(2);
    expect(flow.offer).toEqual({ credential_issuer: 'https://issuer' });

    await vi.advanceTimersByTimeAsync(1000); // retrieved: still step 2
    expect(flow.step).toBe(2);
    await vi.advanceTimersByTimeAsync(1000); // token
    expect(flow.step).toBe(3);
    await vi.advanceTimersByTimeAsync(1000); // pending
    expect(flow.step).toBe(4);

    // the open transactions are read, and the claim inputs are filled from the transaction
    await vi.advanceTimersByTimeAsync(0);
    expect(mocked.deferred).toHaveBeenCalled();
    expect(flow.claims).toEqual({ name: 'Demo User', email: 'demouser@example.com' });

    flow.claims.name = 'Edited';
    await vi.advanceTimersByTimeAsync(5000); // periodic refresh must not reset the edit
    expect(flow.claims.name).toBe('Edited');

    await flow.authorize();
    expect(mocked.authorize).toHaveBeenCalledWith('r1', { name: 'Edited', email: 'demouser@example.com' });
    await nextTick();
    expect(flow.step).toBe(5);

    const refreshes = mocked.deferred.mock.calls.length;
    await vi.advanceTimersByTimeAsync(10_000);
    expect(mocked.deferred.mock.calls.length).toBe(refreshes); // no more refreshes after authorizing

    mocked.status.mockResolvedValue(status('CREDENTIAL_ISSUED', 'issued'));
    await vi.advanceTimersByTimeAsync(1000);
    expect(flow.phase).toBe('issued');
    expect(flow.pollIntervalMs).toBe(10_000);
    const polls = mocked.status.mock.calls.length;
    await vi.advanceTimersByTimeAsync(9_000);
    expect(mocked.status.mock.calls.length).toBe(polls);
    await vi.advanceTimersByTimeAsync(1_000);
    expect(mocked.status.mock.calls.length).toBe(polls + 1);

    expect(flow.timeline.map((e) => e.title)).toContain('Wallet collected the credential');
    scope.stop();
  });

  it('shows the notification of the wallet', async () => {
    const { flow, scope } = await setup();
    mocked.status.mockResolvedValue(status('CREDENTIAL_ISSUED', 'issued', { notification: { event: 'credential_accepted' } }));
    await flow.issue();
    await vi.advanceTimersByTimeAsync(0);
    expect(flow.notification).toEqual({ event: 'credential_accepted' });
    expect(flow.timeline.some((e) => e.title === 'Wallet sent a notification')).toBe(true);
    scope.stop();
  });

  it('stops polling on a denied credential', async () => {
    const { flow, scope } = await setup();
    mocked.status.mockResolvedValue(status('CREDENTIAL_DENIED', 'denied', { reason: 'no' }));
    await flow.issue();
    await vi.advanceTimersByTimeAsync(0);
    const polls = mocked.status.mock.calls.length;
    await vi.advanceTimersByTimeAsync(30_000);
    expect(mocked.status.mock.calls.length).toBe(polls);
    expect(flow.step).toBe(5);
    scope.stop();
  });

  it('reports a rejected authorization without leaving the step', async () => {
    const { ApiError } = await import('@/api/client');
    const { flow, scope } = await setup();
    mocked.status.mockResolvedValue(status('CREDENTIAL_PENDING', 'pending'));
    await flow.issue();
    await vi.advanceTimersByTimeAsync(0);
    mocked.authorize.mockRejectedValue(new ApiError(422, 'claims_rejected', 'The issuer rejected the request', { body: { errors: ['email missing'] } }));
    await flow.authorize();
    expect(flow.error).toContain('The issuer rejected the request');
    expect(flow.errorDetail).toContain('email missing');
    expect(flow.authorized).toBe(false);
    expect(flow.step).toBe(4);
    scope.stop();
  });
});
