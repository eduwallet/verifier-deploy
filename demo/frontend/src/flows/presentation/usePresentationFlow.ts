import { computed, getCurrentScope, onScopeDispose, reactive, ref, watch, type UnwrapNestedRefs } from 'vue';
import { api, ApiError } from '@/api/client';
import type { CatalogField, CatalogPresentation, IssueResponse, StatusResponse, TraceEntry } from '@/api/types';
import { createPoller } from '@/utils/poller';
import { pretty } from '@/utils/format';
import { STATUS_EVENTS } from '../deferred/flowLogic';
import { eduidPollDelayMs, pollDelayMs, stepForPhase, type Step } from './flowLogic';

/** the key of the presentation in the back-end catalog */
const PRESENTATION = 'eduid';
const MAX_TRACE = 200;

export interface TimelineEvent {
  at: Date;
  title: string;
  detail?: string;
}

/** What the visitor chose for the eduID: not yet, an eduID that the wallet already has, or one to issue first */
export type EduidChoice = 'none' | 'have' | 'issue';

/** One offer that the demo creates and follows: the optional eduID, and the issuance with the presentation */
function useOffer(delay: (phase: StatusResponse['phase'] | null) => number | null, eventPrefix: string,
                  addEvent: (title: string, detail?: string) => void, addTrace: (entries?: TraceEntry[]) => void) {
  const run = ref<IssueResponse | null>(null);
  const offer = ref<Record<string, unknown> | null>(null);
  const status = ref<StatusResponse | null>(null);
  const pollError = ref<string | null>(null);
  const lastPoll = ref<Date | null>(null);
  const phase = computed(() => status.value?.phase ?? (run.value ? 'created' : null));

  const poller = createPoller(
    async () => {
      if (!run.value) return;
      const next = await api.status(run.value.run);
      if (status.value?.status !== next.status) {
        addEvent(eventPrefix + (STATUS_EVENTS[next.status] ?? next.status), next.status);
      }
      addTrace(next.trace);
      status.value = next;
      lastPoll.value = new Date();
      pollError.value = null;
    },
    () => delay(phase.value),
    {
      onError: (e) => {
        pollError.value = e instanceof ApiError ? e.message : 'Polling failed';
        if (e instanceof ApiError && e.code === 'unknown_run') poller.stop();
      },
    },
  );

  async function loadOffer() {
    if (!run.value) return;
    const result = await api.offer(run.value.run);
    addTrace(result.trace);
    offer.value = result.offer;
  }

  function start(result: IssueResponse) {
    run.value = result;
    status.value = null;
    offer.value = null;
    poller.start();
  }

  function reset() {
    poller.stop();
    run.value = null;
    offer.value = null;
    status.value = null;
    pollError.value = null;
    lastPoll.value = null;
  }

  return { run, offer, status, pollError, lastPoll, phase, poller, loadOffer, start, reset };
}

/**
 * One run of the flow 'present an eduID while a credential is issued'. Step 1 is the optional issuance of an eduID,
 * after which the visitor continues. The issuance offer that follows has an authorization code grant: the wallet
 * presents its eduID to the issuer, and the issuer fills the credential from the presented claims. The step follows
 * the status of that offer and never goes back.
 */
export function usePresentationFlow() {
  const presentation = ref<CatalogPresentation | null>(null);
  const issuerConfigured = ref(true);
  const eduidFields = ref<CatalogField[]>([]);
  const form = reactive<Record<string, string>>({});
  const choice = ref<EduidChoice>('none');
  const timeline = ref<TimelineEvent[]>([]);
  const trace = ref<TraceEntry[]>([]);
  const error = ref<string | null>(null);
  const busy = ref(false);
  const highest = ref<Step>(1);

  function addEvent(title: string, detail?: string) {
    timeline.value.push({ at: new Date(), title, detail });
  }
  function addTrace(entries?: TraceEntry[]) {
    if (!entries?.length) return;
    trace.value.push(...entries);
    if (trace.value.length > MAX_TRACE) trace.value.splice(0, trace.value.length - MAX_TRACE);
  }
  function fail(e: unknown, what: string) {
    if (e instanceof ApiError) {
      addTrace(e.trace);
      error.value = `${what}: ${e.message}`;
    } else {
      error.value = `${what}: unexpected error`;
    }
  }

  const eduid = useOffer(eduidPollDelayMs, 'eduID: ', addEvent, addTrace);
  const issuance = useOffer(pollDelayMs, '', addEvent, addTrace);

  // what the debug panels show: the issuance once it exists, the eduID before that
  const status = computed(() => issuance.status.value ?? eduid.status.value);
  const phase = computed(() => (issuance.run.value ? issuance.phase.value : eduid.phase.value));
  const step = computed(() => highest.value);
  const eduidIssued = computed(() => eduid.phase.value === 'issued');
  const denied = computed(() => issuance.phase.value === 'denied');

  watch(issuance.phase, (value) => {
    const reached = stepForPhase(value);
    if (reached !== null && reached > highest.value) highest.value = reached;
  });

  if (getCurrentScope()) {
    onScopeDispose(() => {
      eduid.poller.stop();
      issuance.poller.stop();
    });
  }

  async function init() {
    try {
      const catalog = await api.catalog();
      presentation.value = catalog.presentations?.[PRESENTATION] ?? null;
      const prerequisite = presentation.value?.prerequisite ? catalog.credentials[presentation.value.prerequisite] : undefined;
      eduidFields.value = prerequisite?.fields ?? [];
      for (const field of eduidFields.value) form[field.name] = field.default;
      issuerConfigured.value = presentation.value ? (catalog.issuers[presentation.value.issuer]?.configured ?? false) : false;
    } catch (e) {
      fail(e, 'Could not load the configuration');
    }
  }

  function choose(value: EduidChoice) {
    choice.value = value;
    error.value = null;
  }

  async function issueEduid() {
    if (!presentation.value?.prerequisite) return;
    busy.value = true;
    error.value = null;
    try {
      const result = await api.issue(presentation.value.prerequisite, JSON.parse(JSON.stringify(form)));
      addTrace(result.trace);
      addEvent('eduID: offer requested', pretty(result.data));
      eduid.start(result);
      void eduid.loadOffer().catch((e) => fail(e, 'Could not fetch the credential offer'));
    } catch (e) {
      fail(e, 'Could not create the eduID offer');
    } finally {
      busy.value = false;
    }
  }

  /** Continue to the issuance with the presentation. The eduID offer is no longer followed once it is issued or skipped. */
  function proceed() {
    eduid.poller.stop();
    if (highest.value < 2) highest.value = 2;
  }

  async function startIssuance() {
    busy.value = true;
    error.value = null;
    try {
      const result = await api.issuePresentation(PRESENTATION);
      addTrace(result.trace);
      addEvent('Offer requested: the wallet has to present an eduID first');
      issuance.start(result);
      void issuance.loadOffer().catch((e) => fail(e, 'Could not fetch the credential offer'));
    } catch (e) {
      fail(e, 'Could not create the offer');
    } finally {
      busy.value = false;
    }
  }

  async function reset() {
    eduid.reset();
    issuance.reset();
    choice.value = 'none';
    timeline.value = [];
    trace.value = [];
    error.value = null;
    highest.value = 1;
    await init();
  }

  return {
    presentation, issuerConfigured, eduidFields, form, choice, eduid, issuance,
    timeline, trace, error, busy, status, phase, step, eduidIssued, denied,
    init, choose, issueEduid, proceed, startIssuance, reset,
  };
}

/** The flow as the components use it: wrapped in `reactive()`, so refs are unwrapped in templates */
export type PresentationFlow = UnwrapNestedRefs<ReturnType<typeof usePresentationFlow>>;
