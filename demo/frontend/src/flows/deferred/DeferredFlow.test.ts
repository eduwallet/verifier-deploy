import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { reactive } from 'vue';
import PrimeVue from 'primevue/config';
import type { DeferredDetail } from '@/api/types';

vi.mock('@/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('@/api/client')>();
  return {
    ...original,
    api: { catalog: vi.fn(), issue: vi.fn(), status: vi.fn(), offer: vi.fn(), deferred: vi.fn(), authorize: vi.fn(), deny: vi.fn() },
  };
});

import { api } from '@/api/client';
import DeferredFlow from './DeferredFlow.vue';
import StepBackoffice from './StepBackoffice.vue';
import StepOffer from './StepOffer.vue';
import StepCollect from './StepCollect.vue';
import StepResult from './StepResult.vue';
import StepWallet from './StepWallet.vue';
import DebugPanels from '@/components/DebugPanels.vue';
import type { DeferredFlow as FlowState } from './useDeferredFlow';

const mocked = vi.mocked(api, true);
const global = { plugins: [PrimeVue], stubs: { 'router-link': true } };

const own: DeferredDetail = {
  id: 'abcdef12-0000-0000-0000-000000000000', status: 'CREDENTIAL_PENDING', createdAt: 1_700_000_000_000, expiresAt: 1_700_003_600_000,
  expiresInSeconds: 3500, credential_configuration_id: 'DeferredCredential', format: 'vc+jwt', proofs: 1, ttl: 3600, interval: 5,
  data: { name: 'Demo User' }, claims_supplied: {},
  claims: [{ path: ['name'], type: 'string', mandatory: true, origin: [['#any', 'name']], supply: [['name']] }],
};

function fakeFlow(overrides: Record<string, unknown> = {}): FlowState {
  return reactive({
    credential: { title: 'Deferred credential', issuer: 'sandbox', deferred: true, fields: [{ name: 'name', label: 'Name', default: 'Demo User', maxLength: 100 }] },
    issuerConfigured: true, form: { name: 'Demo User' },
    run: { run: 'r1', offerId: 'o', offerUri: 'openid-credential-offer://?credential_offer_uri=x', txCode: null, data: {} },
    offer: { credential_issuer: 'https://issuer.test' },
    status: {
      run: 'r1', status: 'CREDENTIAL_PENDING', phase: 'pending', createdAt: 1, lastUpdatedAt: 2, expiresAt: null, reason: null, credentialUuid: null,
      requests: { 'get_credential-response': { transaction_id: 't' }, 'notification-request': { event: 'credential_accepted' } },
      notification: { event: 'credential_accepted' },
    },
    deferred: {
      items: [
        { id: own.id, shortId: 'abcdef12', credential: 'DeferredCredential', status: 'CREDENTIAL_PENDING', createdAt: 1, expiresAt: 2, expiresInSeconds: 3500, claimsKnown: ['name'], proofs: 1, own: true },
        { id: 'other', shortId: 'ffffffff', credential: 'DeferredCredential', status: 'CREDENTIAL_PENDING', createdAt: 1, expiresAt: 2, expiresInSeconds: 60, claimsKnown: [], proofs: 0, own: false },
      ],
      own,
    },
    claims: { name: 'Demo User' }, authorized: false, authorizeResult: null,
    timeline: [{ at: new Date(), title: 'Offer created at the issuer' }],
    trace: [{ step: 'create-offer', at: '', method: 'POST', url: 'https://issuer.test/api/create-offer', requestHeaders: { Authorization: 'Bearer ***' }, requestBody: {}, status: 200, responseHeaders: {}, responseBody: {}, durationMs: 12, error: null }],
    error: null, errorDetail: null, pollError: null, busy: false, lastPoll: new Date(), pollCount: 3,
    phase: 'pending', step: 4, pollIntervalMs: 1000, notification: { event: 'credential_accepted' },
    init: vi.fn(), issue: vi.fn(), loadOffer: vi.fn(), refreshDeferred: vi.fn(), authorize: vi.fn(), deny: vi.fn(), reset: vi.fn(),
    ...overrides,
  }) as unknown as FlowState;
}

describe('DeferredFlow', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mocked.catalog.mockResolvedValue({
      csrfToken: 't', environment: 'dev', issuers: { sandbox: { name: 'Sandbox', configured: true } },
      credentials: { deferred: { title: 'Deferred credential', issuer: 'sandbox', deferred: true, fields: [
        { name: 'name', label: 'Name', default: 'Demo User', maxLength: 100 },
        { name: 'email', label: 'Email', default: 'demouser@example.com', maxLength: 100 },
        { name: 'institute', label: 'Institute', default: 'Example Inc', maxLength: 100 },
      ] } },
    });
  });

  it('shows the six numbered steps and the prefilled form with a big Issue button', async () => {
    const wrapper = mount(DeferredFlow, { global });
    await flushPromises();
    const text = wrapper.text();
    for (const title of ['Prepare', 'Offer', 'Wallet request', 'Back-office', 'Collection', 'Completed']) {
      expect(text).toContain(title);
    }
    const inputs = wrapper.findAll('input[type="text"]').map((i) => (i.element as HTMLInputElement).value);
    expect(inputs).toEqual(['Demo User', 'demouser@example.com', 'Example Inc']);
    const issue = wrapper.findAll('button').find((b) => b.text() === 'Issue');
    expect(issue).toBeTruthy();
    expect(issue!.classes()).toContain('p-button-lg');
  });

  it('creates the offer when Issue is clicked', async () => {
    mocked.issue.mockResolvedValue({ run: 'r1', offerId: 'o', offerUri: 'openid-credential-offer://?x', txCode: null, data: {} });
    mocked.status.mockResolvedValue({ run: 'r1', status: 'OFFER_CREATED', phase: 'created', createdAt: 1, lastUpdatedAt: 1, expiresAt: null, reason: null, credentialUuid: null, requests: {}, notification: null });
    mocked.offer.mockResolvedValue({ offerUri: 'x', offer: { credential_issuer: 'https://issuer.test' } });
    const wrapper = mount(DeferredFlow, { global });
    await flushPromises();
    await wrapper.findAll('button').find((b) => b.text() === 'Issue')!.trigger('click');
    await flushPromises();
    expect(mocked.issue).toHaveBeenCalledWith('deferred', { name: 'Demo User', email: 'demouser@example.com', institute: 'Example Inc' });
    expect(wrapper.text()).toContain('Scan the credential offer');
    expect(wrapper.text()).toContain('credential_issuer');
    wrapper.unmount();
  });
});

describe('step components', () => {
  it('renders the offer step with the URI, QR and offer JSON', async () => {
    const wrapper = mount(StepOffer, { props: { flow: fakeFlow({ phase: 'retrieved' }) }, global });
    await flushPromises();
    expect(wrapper.text()).toContain('The wallet retrieved the offer');
    expect((wrapper.find('#offer-uri').element as HTMLInputElement).value).toContain('credential_offer_uri');
    expect(wrapper.find('pre').text()).toContain('credential_issuer');
  });

  it('renders the wallet step with the exchanges that are known', () => {
    const wrapper = mount(StepWallet, { props: { flow: fakeFlow() }, global });
    expect(wrapper.text()).toContain('202');
    expect(wrapper.text()).toContain('get_credential-response');
    expect(wrapper.text()).not.toContain('access_token-request');
  });

  it('renders the back-office step with the transaction table and the authorize button', async () => {
    const flow = fakeFlow();
    const wrapper = mount(StepBackoffice, { props: { flow }, global });
    await flushPromises();
    expect(wrapper.text()).toContain('abcdef12');
    expect(wrapper.text()).toContain('yours');
    expect(wrapper.text()).toContain('in 58 min');
    expect(wrapper.text()).toContain('1 h'); // ttl
    expect(wrapper.text()).toContain('5 s'); // interval
    expect(wrapper.find('tr.own-row').exists()).toBe(true);
    expect((wrapper.find('#claim-name').element as HTMLInputElement).value).toBe('Demo User');

    await wrapper.find('#claim-name').setValue('Changed');
    expect(flow.claims.name).toBe('Changed');
    const authorize = wrapper.findAll('button').find((b) => b.text() === 'Authorize')!;
    expect(authorize.classes()).toContain('p-button-lg');
    await authorize.trigger('click');
    expect(flow.authorize).toHaveBeenCalled();
  });

  it('renders the collection step while the wallet has not yet collected the credential', () => {
    const flow = fakeFlow({ phase: 'ready' });
    const wrapper = mount(StepCollect, { props: { flow }, global });
    expect(wrapper.text()).toContain('ready for issuance');
    expect(wrapper.text()).not.toContain('The wallet collected the credential');
  });

  it('renders the result step for an issued credential with the notification', () => {
    const flow = fakeFlow({ phase: 'issued', pollIntervalMs: 10_000 });
    const wrapper = mount(StepResult, { props: { flow }, global });
    expect(wrapper.text()).toContain('The wallet collected the credential');
    expect(wrapper.text()).toContain('every 10 s');
    expect(wrapper.text()).toContain('credential_accepted');
    // the issuer messages are listed in protocol order
    const headers = wrapper.findAll('.mono').map((e) => e.text());
    expect(headers.indexOf('get_credential-response')).toBeLessThan(headers.indexOf('notification-request'));
  });

  it('renders a denied credential with its reason', () => {
    const flow = fakeFlow({ phase: 'denied', pollIntervalMs: null });
    flow.status!.reason = 'Not eligible';
    const wrapper = mount(StepResult, { props: { flow }, global });
    expect(wrapper.text()).toContain('The credential was denied');
    expect(wrapper.text()).toContain('Not eligible');
    expect(wrapper.text()).toContain('stopped');
  });

  it('renders the debug panels without the token', () => {
    const wrapper = mount(DebugPanels, { props: { flow: fakeFlow() }, global });
    expect(wrapper.text()).toContain('Offer created at the issuer');
    expect(wrapper.text()).toContain('/api/create-offer');
  });
});
