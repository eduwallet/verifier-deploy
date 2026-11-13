import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope, nextTick, reactive } from 'vue';
import type { StatusResponse } from '@/api/types';

vi.mock('@/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('@/api/client')>();
  return {
    ...original,
    api: { catalog: vi.fn(), issueMultiple: vi.fn(), status: vi.fn(), offer: vi.fn(), release: vi.fn() },
  };
});

import { api } from '@/api/client';
import { useMultipleFlow } from './useMultipleFlow';

const mocked = vi.mocked(api, true);

function status(statusName: string, phase: StatusResponse['phase'], extra: Partial<StatusResponse> = {}): StatusResponse {
  return {
    run: 'r1', status: statusName, phase, createdAt: 1, lastUpdatedAt: 2, expiresAt: null, reason: null,
    credentialUuid: null, issued: [], deferred: [], requests: {}, notification: null, ...extra,
  };
}
const generic = (label: string) => ({ title: label, issuer: 'sandbox', deferred: false, fields: [
  { name: 'institute', label: 'Instituut', default: 'Education Inc', maxLength: 100 },
  { name: 'grade', label: 'Grade', default: 'B-', maxLength: 100 },
] });
const pending = { id: 's.t', key: 'deferred', status: 'CREDENTIAL_PENDING', expiresAt: 9, reason: null };

describe('useMultipleFlow', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.clearAllMocks();
    mocked.catalog.mockResolvedValue({
      csrfToken: 't', environment: 'dev', issuers: { sandbox: { name: 'Sandbox', configured: true } },
      credentials: {
        generic1: generic('Generic credential 1'),
        generic2: generic('Generic credential 2'),
        deferred: { title: 'Deferred credential', issuer: 'sandbox', deferred: true, fields: [
          { name: 'name', label: 'Name', default: 'Demo User', maxLength: 100 },
        ] },
      },
      bundles: { multiple: { title: 'Multiple credentials', issuer: 'sandbox', credentials: ['generic1', 'generic2', 'deferred'] } },
    });
    mocked.issueMultiple.mockResolvedValue({ run: 'r1', offerId: 'o', offerUri: 'openid-credential-offer://?x', txCode: null, data: {} });
    mocked.offer.mockResolvedValue({ offerUri: 'x', offer: { credential_issuer: 'https://issuer' } });
    mocked.release.mockResolvedValue({ released: true, claims: {}, result: {} });
  });
  afterEach(() => vi.useRealTimers());

  async function setup() {
    const scope = effectScope();
    const flow = scope.run(() => reactive(useMultipleFlow()))!;
    await flow.init();
    return { flow, scope };
  }

  it('has a form per credential with the defaults of the catalog', async () => {
    const { flow, scope } = await setup();
    expect(flow.credentials.map((c) => c.key)).toEqual(['generic1', 'generic2', 'deferred']);
    expect(flow.form.generic2).toEqual({ institute: 'Education Inc', grade: 'B-' });
    expect(flow.form.deferred).toEqual({ name: 'Demo User' });
    expect(flow.step).toBe(1);
    scope.stop();
  });

  it('sends the edited values of every credential', async () => {
    const { flow, scope } = await setup();
    mocked.status.mockResolvedValue(status('OFFER_CREATED', 'created'));
    flow.form.generic2.grade = 'A+';
    await flow.issue();
    expect(mocked.issueMultiple).toHaveBeenCalledWith('multiple', {
      generic1: { institute: 'Education Inc', grade: 'B-' },
      generic2: { institute: 'Education Inc', grade: 'A+' },
      deferred: { name: 'Demo User' },
    });
    scope.stop();
  });

  it('goes through offer, release and completion', async () => {
    const { flow, scope } = await setup();
    mocked.status
      .mockResolvedValueOnce(status('OFFER_CREATED', 'created'))
      .mockResolvedValueOnce(status('ACCESS_TOKEN_CREATED', 'token', { issued: [{ key: 'generic1', uuid: 'u1' }] }))
      .mockResolvedValue(status('CREDENTIAL_PENDING', 'pending', {
        issued: [{ key: 'generic1', uuid: 'u1' }, { key: 'generic2', uuid: 'u2' }], deferred: [pending],
      }));

    await flow.issue();
    await vi.advanceTimersByTimeAsync(0);
    await nextTick();
    expect(flow.step).toBe(2);
    expect(flow.offer).toEqual({ credential_issuer: 'https://issuer' });

    await vi.advanceTimersByTimeAsync(1000); // one generic credential issued, the deferred one not requested
    expect(flow.step).toBe(2);
    expect(flow.items.map((i) => i.state)).toEqual(['issued', 'waiting', 'waiting']);

    await vi.advanceTimersByTimeAsync(1000); // deferred transaction
    expect(flow.step).toBe(3);
    expect(flow.phase).toBe('pending');
    expect(flow.items.map((i) => i.state)).toEqual(['issued', 'issued', 'pending']);

    await flow.release();
    expect(mocked.release).toHaveBeenCalledWith('r1');
    expect(flow.released).toBe(true);
    expect(flow.step).toBe(3);

    mocked.status.mockResolvedValue(status('CREDENTIAL_ISSUED', 'issued', {
      issued: [{ key: 'generic1', uuid: 'u1' }, { key: 'generic2', uuid: 'u2' }, { key: 'deferred', uuid: 'u3' }],
      deferred: [{ ...pending, status: 'CREDENTIAL_ISSUED' }],
    }));
    await vi.advanceTimersByTimeAsync(1000);
    expect(flow.step).toBe(4);
    expect(flow.phase).toBe('issued');
    expect(flow.items.every((i) => i.state === 'issued')).toBe(true);
    expect(flow.pollIntervalMs).toBe(10_000);
    expect(flow.timeline.map((e) => e.title)).toContain('Deferred credential released by the demo back-office');
    scope.stop();
  });

  it('ends at the last step when the deferred credential is denied', async () => {
    const { flow, scope } = await setup();
    mocked.status.mockResolvedValue(status('CREDENTIAL_DENIED', 'denied', { deferred: [{ ...pending, status: 'CREDENTIAL_DENIED', reason: 'no' }] }));
    await flow.issue();
    await vi.advanceTimersByTimeAsync(0);
    expect(flow.step).toBe(4);
    expect(flow.phase).toBe('denied');
    expect(flow.pollIntervalMs).toBeNull();
    scope.stop();
  });

  it('starts over with a clean form', async () => {
    const { flow, scope } = await setup();
    mocked.status.mockResolvedValue(status('CREDENTIAL_ISSUED', 'issued'));
    flow.form.generic1.grade = 'edited';
    await flow.issue();
    await vi.advanceTimersByTimeAsync(0);
    await flow.reset();
    expect(flow.step).toBe(1);
    expect(flow.run).toBeNull();
    expect(flow.form.generic1.grade).toBe('B-');
    scope.stop();
  });
});
