import { computed, getCurrentScope, onScopeDispose, reactive, ref, watch, type UnwrapNestedRefs } from 'vue';
import { api, ApiError } from '@/api/client';
import type {
  CatalogCredential, DeferredListResponse, IssueResponse, StatusResponse, TraceEntry,
} from '@/api/types';
import { createPoller } from '@/utils/poller';
import { pretty } from '@/utils/format';
import { pollDelayMs, STATUS_EVENTS, stepForPhase, suppliedClaimNames, type Step } from './flowLogic';

/** the key of the credential in the back-end catalog */
const CREDENTIAL = 'deferred';
const MAX_TRACE = 200;
const DEFERRED_REFRESH_MS = 5_000;

export interface TimelineEvent {
  at: Date;
  title: string;
  detail?: string;
}

function hasContent(value: unknown): boolean {
  if (value === null || value === undefined) return false;
  if (Array.isArray(value)) return value.length > 0;
  if (typeof value === 'object') return Object.keys(value).length > 0;
  return true;
}

/**
 * The state of one run of the deferred credential flow: the form, the offer, the polled status,
 * the open deferred transactions, and the technical detail (timeline and upstream exchanges).
 * The wizard step follows from the polled status and never goes back.
 */
export function useDeferredFlow() {
  const credential = ref<CatalogCredential | null>(null);
  const issuerConfigured = ref(true);
  const form = reactive<Record<string, string>>({});
  const run = ref<IssueResponse | null>(null);
  const offer = ref<Record<string, unknown> | null>(null);
  const status = ref<StatusResponse | null>(null);
  const deferred = ref<DeferredListResponse | null>(null);
  const claims = reactive<Record<string, string>>({});
  const authorized = ref(false);
  const authorizeResult = ref<unknown>(null);
  const timeline = ref<TimelineEvent[]>([]);
  const trace = ref<TraceEntry[]>([]);
  const error = ref<string | null>(null);
  const errorDetail = ref<string | null>(null);
  const pollError = ref<string | null>(null);
  const busy = ref(false);
  const lastPoll = ref<Date | null>(null);
  const pollCount = ref(0);
  const highest = ref<Step>(1);
  let claimsInitialised = false;

  const phase = computed(() => status.value?.phase ?? (run.value ? 'created' : null));
  const step = computed(() => highest.value);
  const pollIntervalMs = computed(() => pollDelayMs(phase.value));
  const notification = computed(() => (hasContent(status.value?.notification) ? status.value?.notification : null));

  watch([phase, authorized], () => {
    const reached = authorized.value ? 5 : stepForPhase(phase.value);
    if (reached > highest.value) {
      highest.value = reached as Step;
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

  function applyStatus(next: StatusResponse) {
    const previous = status.value;
    if (previous?.status !== next.status) {
      addEvent(STATUS_EVENTS[next.status] ?? next.status, next.status);
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

  const deferredPoller = createPoller(
    loadDeferred,
    () => (step.value === 4 && !authorized.value ? DEFERRED_REFRESH_MS : null),
    { onError: (e) => fail(e, 'Could not read the open transactions') },
  );

  watch(step, (value) => {
    if (value === 4 && !authorized.value) {
      deferredPoller.start();
    } else {
      deferredPoller.stop();
    }
  });

  if (getCurrentScope()) {
    onScopeDispose(() => {
      statusPoller.stop();
      deferredPoller.stop();
    });
  }

  // ---- actions ------------------------------------------------------------------------------------

  async function init() {
    try {
      const catalog = await api.catalog();
      credential.value = catalog.credentials[CREDENTIAL] ?? null;
      const issuer = credential.value ? catalog.issuers[credential.value.issuer] : undefined;
      issuerConfigured.value = issuer?.configured ?? false;
      for (const field of credential.value?.fields ?? []) {
        form[field.name] = field.default;
      }
    } catch (e) {
      fail(e, 'Could not load the configuration');
    }
  }

  async function issue() {
    busy.value = true;
    error.value = null;
    try {
      const result = await api.issue(CREDENTIAL, { ...form });
      addTrace(result.trace);
      run.value = result;
      addEvent('Offer requested', pretty(result.data));
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

  async function loadDeferred() {
    if (!run.value) return;
    const result = await api.deferred(run.value.run);
    addTrace(result.trace);
    deferred.value = result;
    // fill the claim inputs once, so that edits are not lost by the periodic refresh
    if (!claimsInitialised && result.own) {
      claimsInitialised = true;
      const names = suppliedClaimNames(result.own.claims);
      for (const name of names.length ? names : (credential.value?.fields ?? []).map((f) => f.name)) {
        claims[name] = String(result.own.claims_supplied[name] ?? result.own.data[name] ?? form[name] ?? '');
      }
    }
    error.value = null;
  }

  async function refreshDeferred() {
    try {
      await loadDeferred();
    } catch (e) {
      fail(e, 'Could not read the open transactions');
    }
  }

  async function authorize() {
    if (!run.value) return;
    busy.value = true;
    error.value = null;
    errorDetail.value = null;
    try {
      const result = await api.authorize(run.value.run, { ...claims });
      addTrace(result.trace);
      authorizeResult.value = result.result;
      authorized.value = true;
      addEvent('Credential authorized by the demo back-office', pretty(result.claims));
    } catch (e) {
      fail(e, 'Could not authorize the credential');
    } finally {
      busy.value = false;
    }
  }

  async function deny(reason?: string) {
    if (!run.value) return;
    busy.value = true;
    error.value = null;
    try {
      const result = await api.deny(run.value.run, reason);
      addTrace(result.trace);
      addEvent('Credential denied by the demo back-office', reason);
    } catch (e) {
      fail(e, 'Could not deny the credential');
    } finally {
      busy.value = false;
    }
  }

  async function reset() {
    statusPoller.stop();
    deferredPoller.stop();
    run.value = null;
    offer.value = null;
    status.value = null;
    deferred.value = null;
    authorized.value = false;
    authorizeResult.value = null;
    timeline.value = [];
    trace.value = [];
    error.value = null;
    errorDetail.value = null;
    pollError.value = null;
    lastPoll.value = null;
    pollCount.value = 0;
    highest.value = 1;
    claimsInitialised = false;
    for (const key of Object.keys(claims)) delete claims[key];
    await init();
  }

  return {
    credential, issuerConfigured, form, run, offer, status, deferred, claims, authorized, authorizeResult,
    timeline, trace, error, errorDetail, pollError, busy, lastPoll, pollCount,
    phase, step, pollIntervalMs, notification,
    init, issue, loadOffer, refreshDeferred, authorize, deny, reset,
  };
}

/** The flow as the components use it: wrapped in `reactive()`, so refs are unwrapped in templates */
export type DeferredFlow = UnwrapNestedRefs<ReturnType<typeof useDeferredFlow>>;
