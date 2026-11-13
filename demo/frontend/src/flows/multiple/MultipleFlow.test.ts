import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import PrimeVue from 'primevue/config';

vi.mock('@/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('@/api/client')>();
  return {
    ...original,
    api: { catalog: vi.fn(), issueMultiple: vi.fn(), status: vi.fn(), offer: vi.fn(), release: vi.fn() },
  };
});

import { api } from '@/api/client';
import MultipleFlow from './MultipleFlow.vue';

const mocked = vi.mocked(api, true);
const global = { plugins: [PrimeVue], stubs: { 'router-link': true } };

const generic = (title: string, credential: string, grade: string) => ({ title, issuer: 'sandbox', deferred: false, fields: [
  { name: 'institute', label: 'Instituut', default: 'Education Inc', maxLength: 100 },
  { name: 'credential', label: 'Credential', default: credential, maxLength: 100 },
  { name: 'grade', label: 'Grade', default: grade, maxLength: 100 },
] });

function status(statusName: string, phase: string, extra: Record<string, unknown> = {}) {
  return {
    run: 'r1', status: statusName, phase, createdAt: 1, lastUpdatedAt: 2, expiresAt: null, reason: null, credentialUuid: null,
    issued: [], deferred: [], requests: {}, notification: null, ...extra,
  } as never;
}

describe('MultipleFlow', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mocked.catalog.mockResolvedValue({
      csrfToken: 't', environment: 'dev', issuers: { sandbox: { name: 'Sandbox', configured: true } },
      credentials: {
        generic1: generic('Generic credential 1', 'Lessons Learned', 'B-'),
        generic2: generic('Generic credential 2', 'Digital Knowledge', 'A+'),
        deferred: { title: 'Deferred credential', issuer: 'sandbox', deferred: true, fields: [{ name: 'name', label: 'Name', default: 'Demo User', maxLength: 100 }] },
      },
      bundles: { multiple: { title: 'Multiple credentials', issuer: 'sandbox', credentials: ['generic1', 'generic2', 'deferred'] } },
    });
    mocked.issueMultiple.mockResolvedValue({ run: 'r1', offerId: 'o', offerUri: 'openid-credential-offer://?credential_offer_uri=x', txCode: null, data: {} });
    mocked.status.mockResolvedValue(status('OFFER_CREATED', 'created'));
    mocked.offer.mockResolvedValue({ offerUri: 'x', offer: { credential_issuer: 'https://issuer.test' } });
  });

  it('offers a form for each credential with the defaults', async () => {
    const wrapper = mount(MultipleFlow, { global });
    await flushPromises();
    const values = wrapper.findAll('input').map((i) => (i.element as HTMLInputElement).value);
    expect(values).toEqual(['Education Inc', 'Lessons Learned', 'B-', 'Education Inc', 'Digital Knowledge', 'A+', 'Demo User']);
    expect(wrapper.text()).toContain('Instituut');
    wrapper.unmount();
  });

  it('creates one offer for all credentials and shows their progress', async () => {
    const wrapper = mount(MultipleFlow, { global });
    await flushPromises();
    await wrapper.findAll('button').find((b) => b.text() === 'Issue')!.trigger('click');
    await flushPromises();
    expect(mocked.issueMultiple).toHaveBeenCalledWith('multiple', {
      generic1: { institute: 'Education Inc', credential: 'Lessons Learned', grade: 'B-' },
      generic2: { institute: 'Education Inc', credential: 'Digital Knowledge', grade: 'A+' },
      deferred: { name: 'Demo User' },
    });
    expect(wrapper.text()).toContain('Scan the credential offer');
    expect(wrapper.text()).toContain('Waiting for the wallet');
    wrapper.unmount();
  });

  it('releases the deferred credential with a button and closes with the result', async () => {
    const pending = { id: 's.t', key: 'deferred', status: 'CREDENTIAL_PENDING', expiresAt: 1_700_000_000_000, reason: null };
    mocked.status.mockResolvedValue(status('CREDENTIAL_PENDING', 'pending', {
      issued: [{ key: 'generic1', uuid: 'u1' }, { key: 'generic2', uuid: 'u2' }], deferred: [pending],
    }));
    mocked.release.mockResolvedValue({ released: true, claims: {}, result: {} });
    const wrapper = mount(MultipleFlow, { global });
    await flushPromises();
    await wrapper.findAll('button').find((b) => b.text() === 'Issue')!.trigger('click');
    await flushPromises();

    expect(wrapper.text()).toContain('release the deferred credential');
    expect(wrapper.text()).toContain('1 deferred credential');
    const button = wrapper.findAll('button').find((b) => b.text() === 'Release credential')!;
    expect(button.attributes('disabled')).toBeUndefined();
    await button.trigger('click');
    await flushPromises();
    expect(mocked.release).toHaveBeenCalledWith('r1');
    expect(wrapper.text()).toContain('The credential is released');

    mocked.status.mockResolvedValue(status('CREDENTIAL_ISSUED', 'issued', {
      issued: [{ key: 'generic1', uuid: 'u1' }, { key: 'generic2', uuid: 'u2' }, { key: 'deferred', uuid: 'u3' }],
      deferred: [{ ...pending, status: 'CREDENTIAL_ISSUED' }],
    }));
    await vi.waitFor(() => expect(wrapper.text()).toContain('All 3 credentials are issued'), { timeout: 3000 });
    expect(wrapper.text()).toContain('Start over');
    wrapper.unmount();
  });
});
