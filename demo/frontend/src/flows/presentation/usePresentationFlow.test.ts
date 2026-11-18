import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope, nextTick, reactive } from 'vue';
import type { StatusResponse } from '@/api/types';

vi.mock('@/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('@/api/client')>();
  return {
    ...original,
    api: { catalog: vi.fn(), issue: vi.fn(), issuePresentation: vi.fn(), status: vi.fn(), offer: vi.fn() },
  };
});

import { api } from '@/api/client';
import { usePresentationFlow } from './usePresentationFlow';

const mocked = vi.mocked(api, true);

function status(statusName: string, phase: StatusResponse['phase']): StatusResponse {
  return {
    run: 'r', status: statusName, phase, createdAt: 1, lastUpdatedAt: 2, expiresAt: null, reason: null,
    credentialUuid: null, issued: [], deferred: [], requests: {}, notification: null,
  };
}
const offer = (run: string) => ({ run, offerId: 'o', offerUri: 'openid-credential-offer://?x', txCode: null, data: {} });

describe('usePresentationFlow', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.clearAllMocks();
    mocked.catalog.mockResolvedValue({
      csrfToken: 't', environment: 'dev', issuers: { sandbox: { name: 'Sandbox', configured: true } },
      credentials: {
        eduid: { title: 'eduID', issuer: 'sandbox', deferred: false, fields: [
          { name: 'sub', label: 'eduID identifier', default: 'demo-abc', maxLength: 100 },
          { name: 'email', label: 'Email', default: 'demouser@example.com', maxLength: 100 },
        ] },
      },
      bundles: {},
      presentations: { eduid: { title: 'Generic credential from an eduID', issuer: 'sandbox', prerequisite: 'eduid',
        mapping: { firstName: 'given_name' }, requested: ['given_name'] } },
    });
    mocked.issue.mockResolvedValue(offer('eduid-run'));
    mocked.issuePresentation.mockResolvedValue(offer('pdi-run'));
    mocked.offer.mockResolvedValue({ offerUri: 'x', offer: { credential_issuer: 'https://issuer' } });
  });
  afterEach(() => vi.useRealTimers());

  async function setup() {
    const scope = effectScope();
    const flow = scope.run(() => reactive(usePresentationFlow()))!;
    await flow.init();
    return { flow, scope };
  }

  it('loads the presentation and the form of the eduID with the defaults', async () => {
    const { flow, scope } = await setup();
    expect(flow.presentation?.title).toBe('Generic credential from an eduID');
    expect(flow.form).toEqual({ sub: 'demo-abc', email: 'demouser@example.com' });
    expect(flow.step).toBe(1);
    expect(flow.choice).toBe('none');
    scope.stop();
  });

  it('can skip the eduID: the wallet has one', async () => {
    const { flow, scope } = await setup();
    flow.choose('have');
    flow.proceed();
    expect(flow.step).toBe(2);
    expect(mocked.issue).not.toHaveBeenCalled();
    scope.stop();
  });

  it('issues an eduID with the edited values, follows it, and stops polling once issued', async () => {
    const { flow, scope } = await setup();
    mocked.status.mockResolvedValue(status('OFFER_URI_RETRIEVED', 'retrieved'));
    flow.choose('issue');
    flow.form.email = 'alice@example.com';
    await flow.issueEduid();
    expect(mocked.issue).toHaveBeenCalledWith('eduid', { sub: 'demo-abc', email: 'alice@example.com' });
    await vi.advanceTimersByTimeAsync(0);
    expect(flow.eduid.phase).toBe('retrieved');
    expect(flow.eduidIssued).toBe(false);

    mocked.status.mockResolvedValue(status('CREDENTIAL_ISSUED', 'issued'));
    await vi.advanceTimersByTimeAsync(1000);
    expect(flow.eduidIssued).toBe(true);
    const calls = mocked.status.mock.calls.length;
    await vi.advanceTimersByTimeAsync(30_000);
    expect(mocked.status.mock.calls.length).toBe(calls);
    expect(flow.step).toBe(1); // the visitor continues by themselves
    scope.stop();
  });

  it('follows the issuance through the presentation to the end', async () => {
    const { flow, scope } = await setup();
    flow.choose('have');
    flow.proceed();
    mocked.status.mockResolvedValue(status('OFFER_CREATED', 'created'));
    await flow.startIssuance();
    expect(mocked.issuePresentation).toHaveBeenCalledWith('eduid');
    await vi.advanceTimersByTimeAsync(0);
    expect(flow.step).toBe(2);

    mocked.status.mockResolvedValue(status('PRESENTATION_REQUESTED', 'presentation'));
    await vi.advanceTimersByTimeAsync(1000);
    await nextTick();
    expect(flow.step).toBe(3);

    mocked.status.mockResolvedValue(status('CREDENTIAL_ISSUED', 'issued'));
    await vi.advanceTimersByTimeAsync(1000);
    await nextTick();
    expect(flow.step).toBe(4);
    expect(flow.timeline.map((e) => e.detail)).toContain('PRESENTATION_REQUESTED');
    scope.stop();
  });

  it('stays at the presentation when it is rejected, and stops polling', async () => {
    const { flow, scope } = await setup();
    flow.proceed();
    mocked.status.mockResolvedValue(status('PRESENTATION_REQUESTED', 'presentation'));
    await flow.startIssuance();
    await vi.advanceTimersByTimeAsync(0);
    mocked.status.mockResolvedValue(status('AUTHORIZATION_DENIED', 'denied'));
    await vi.advanceTimersByTimeAsync(1000);
    await nextTick();
    expect(flow.step).toBe(3);
    expect(flow.denied).toBe(true);
    const calls = mocked.status.mock.calls.length;
    await vi.advanceTimersByTimeAsync(30_000);
    expect(mocked.status.mock.calls.length).toBe(calls);
    scope.stop();
  });

  it('starts over with a clean state', async () => {
    const { flow, scope } = await setup();
    flow.proceed();
    mocked.status.mockResolvedValue(status('CREDENTIAL_ISSUED', 'issued'));
    await flow.startIssuance();
    await vi.advanceTimersByTimeAsync(0);
    await flow.reset();
    expect(flow.step).toBe(1);
    expect(flow.issuance.run).toBeNull();
    expect(flow.timeline).toEqual([]);
    expect(flow.choice).toBe('none');
    scope.stop();
  });
});
