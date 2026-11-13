import { computed, getCurrentScope, onScopeDispose, reactive, ref, watch, type UnwrapNestedRefs } from 'vue';
import { api, ApiError } from '@/api/client';
import type {
  CatalogCredential, IssueMultipleResponse, StatusResponse, TraceEntry,
} from '@/api/types';
import { createPoller } from '@/utils/poller';
import { pretty } from '@/utils/format';
import { pollDelayMs, STATUS_EVENTS } from '../deferred/flowLogic';
import { credentialState, overallPhase, stepForStatus, type CredentialState, type Step } from './flowLogic';

/** the key of the bundle in the back-end catalog */
const BUNDLE = 'multiple';
const MAX_TRACE = 200;

export interface TimelineEvent {
  at: Date;
  title: string;
  detail?: string;
}

/** A credential of the offer, in the order of the offer */
export interface OfferedCredential {
  key: string;
  title: string;
  deferred: boolean;
  fields: CatalogCredential['fields'];
}

function hasContent(value: unknown): boolean {
  if (value === null || value === undefined) return false;
  if (Array.isArray(value)) return value.length > 0;
  if (typeof value === 'object') return Object.keys(value).length > 0;
  return true;
}

/**
 * The state of one run of the multiple credentials flow: the form for every credential, the offer, the
 * polled status and the technical detail. The wizard step follows from the polled status and never goes back.
 */
export function useMultipleFlow() {
  const bundleTitle = ref('Multiple credentials');
  const credentials = ref<OfferedCredential[]>([]);
  const issuerConfigured = ref(true);
  /** the values of the form, per catalog key of the credential */
  const form = reactive<Record<string, Record<string, string>>>({});
  const run = ref<IssueMultipleResponse | null>(null);
  const offer = ref<Record<string, unknown> | null>(null);
  const status = ref<StatusResponse | null>(null);
  const released = ref(false);
  const releaseResult = ref<unknown>(null);
  const timeline = ref<TimelineEvent[]>([]);
  const trace = ref<TraceEntry[]>([]);
  const error = ref<string | null>(null);
  const errorDetail = ref<string | null>(null);
  const pollError = ref<string | null>(null);
  const busy = ref(false);
  const lastPoll = ref<Date | null>(null);
  const pollCount = ref(0);
  const highest = ref<Step>(1);

  const phase = computed(() => overallPhase(status.value, run.value !== null));
  const step = computed(() => highest.value);
  const pollIntervalMs = computed(() => pollDelayMs(phase.value));
  const notification = computed(() => (hasContent(status.value?.notification) ? status.value?.notification : null));
  const transaction = computed(() => status.value?.deferred[0] ?? null);

  /** every credential of the offer with what the issuer reports about it */
  const items = computed(() => credentials.value.map((credential) => {
    const state: CredentialState = credentialState(credential.key, status.value);
    const uuid = status.value?.issued.find((i) => i.key === credential.key)?.uuid ?? null;
    return { ...credential, state, uuid };
  }));

  watch(status, () => {
    const reached = stepForStatus(status.value);
    if (reached > highest.value) {
      highest.value = reached;
    }
  });

  // ---- bookkeeping ------------------------------------------------------------------------------

  function addEvent(title: string, detail?: string) {
    timeline.value.push({ at: new Date(), title, detail });
  }

  function addTrace(entries?: TraceEntry[]) {
    if (!entries?.length) return;
    trace.value.push(...entries);
    if (trace.value.length > MAX_TRACE) {
      trace.value.splice(0, trace.value.length - MAX_TRACE);
    }
  }

  function fail(e: unknown, what: string) {
    if (e instanceof ApiError) {
      addTrace(e.trace);
      error.value = `${what}: ${e.message}`;
      const errors = (e.detail as { body?: { errors?: unknown } } | undefined)?.body?.errors;
      errorDetail.value = errors !== undefined ? pretty(errors) : null;
    } else {
      error.value = `${what}: unexpected error`;
      errorDetail.value = null;
    }
  }

  function titleOf(key: string): string {
    return credentials.value.find((c) => c.key === key)?.title ?? key;
  }

  function applyStatus(next: StatusResponse) {
    const previous = status.value;
    if (previous?.status !== next.status) {
      addEvent(STATUS_EVENTS[next.status] ?? next.status, next.status);
    }
    for (const issued of next.issued) {
      if (!previous?.issued.some((i) => i.key === issued.key)) {
        addEvent(`${titleOf(issued.key)} issued to the wallet`, issued.uuid);
      }
    }
    for (const t of next.deferred) {
      const before = previous?.deferred.find((p) => p.id === t.id);
      if (before?.status !== t.status) {
        addEvent(`${titleOf(t.key)}: ${STATUS_EVENTS[t.status] ?? t.status}`, t.reason ?? undefined);
      }
    }
    if (!hasContent(previous?.notification) && hasContent(next.notification)) {
      addEvent('Wallet sent a notification', pretty(next.notification));
    }
    addTrace(next.trace);
    status.value = next;
  }

  // ---- polling ------------------------------------------------------------------------------------

  const statusPoller = createPoller(
    async () => {
      if (!run.value) return;
      applyStatus(await api.status(run.value.run));
      lastPoll.value = new Date();
      pollCount.value++;
      pollError.value = null;
    },
    () => pollDelayMs(phase.value),
    {
      onError: (e) => {
        pollError.value = e instanceof ApiError ? e.message : 'Polling failed';
        if (e instanceof ApiError && e.code === 'unknown_run') {
          statusPoller.stop();
        }
      },
    },
  );

  if (getCurrentScope()) {
    onScopeDispose(() => statusPoller.stop());
  }

  // ---- actions ------------------------------------------------------------------------------------

  async function init() {
    try {
      const catalog = await api.catalog();
      const bundle = catalog.bundles[BUNDLE];
      bundleTitle.value = bundle?.title ?? bundleTitle.value;
      credentials.value = (bundle?.credentials ?? []).flatMap((key) => {
        const credential = catalog.credentials[key];
        return credential ? [{
          key,
          title: credential.title,
          deferred: credential.deferred,
          fields: credential.fields,
        }] : [];
      });
      issuerConfigured.value = bundle ? (catalog.issuers[bundle.issuer]?.configured ?? false) : false;
      for (const credential of credentials.value) {
        form[credential.key] = Object.fromEntries(credential.fields.map((f) => [f.name, f.default]));
      }
    } catch (e) {
      fail(e, 'Could not load the configuration');
    }
  }

  async function issue() {
    busy.value = true;
    error.value = null;
    try {
      const result = await api.issueMultiple(BUNDLE, JSON.parse(JSON.stringify(form)));
      addTrace(result.trace);
      run.value = result;
      addEvent('Offer requested for ' + credentials.value.length + ' credentials', pretty(result.data));
      statusPoller.start();
      void loadOffer();
    } catch (e) {
      fail(e, 'Could not create the offer');
    } finally {
      busy.value = false;
    }
  }

  async function loadOffer() {
    if (!run.value) return;
    try {
      const result = await api.offer(run.value.run);
      addTrace(result.trace);
      offer.value = result.offer;
    } catch (e) {
      fail(e, 'Could not fetch the credential offer');
    }
  }

  async function release() {
    if (!run.value) return;
    busy.value = true;
    error.value = null;
    errorDetail.value = null;
    try {
      const result = await api.release(run.value.run);
      addTrace(result.trace);
      releaseResult.value = result.result;
      released.value = true;
      addEvent('Deferred credential released by the demo back-office', pretty(result.claims));
    } catch (e) {
      fail(e, 'Could not release the credential');
    } finally {
      busy.value = false;
    }
  }

  async function reset() {
    statusPoller.stop();
    run.value = null;
    offer.value = null;
    status.value = null;
    released.value = false;
    releaseResult.value = null;
    timeline.value = [];
    trace.value = [];
    error.value = null;
    errorDetail.value = null;
    pollError.value = null;
    lastPoll.value = null;
    pollCount.value = 0;
    highest.value = 1;
    await init();
  }

  return {
    bundleTitle, credentials, issuerConfigured, form, run, offer, status, released, releaseResult,
    timeline, trace, error, errorDetail, pollError, busy, lastPoll, pollCount,
    phase, step, pollIntervalMs, notification, transaction, items,
    init, issue, loadOffer, release, reset,
  };
}

/** The flow as the components use it: wrapped in `reactive()`, so refs are unwrapped in templates */
export type MultipleFlow = UnwrapNestedRefs<ReturnType<typeof useMultipleFlow>>;
