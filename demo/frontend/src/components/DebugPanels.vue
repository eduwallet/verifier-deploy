<script setup lang="ts">
import { computed } from 'vue';
import Accordion from 'primevue/accordion';
import AccordionPanel from 'primevue/accordionpanel';
import AccordionHeader from 'primevue/accordionheader';
import AccordionContent from 'primevue/accordioncontent';
import Panel from 'primevue/panel';
import Tag from 'primevue/tag';
import JsonPanel from './JsonPanel.vue';
import type { StatusResponse, TraceEntry } from '@/api/types';
import { formatTime } from '@/utils/format';

/** What the panels need of a flow; every flow of the demo provides it */
interface DebugFlow {
  trace: TraceEntry[];
  timeline: { at: Date; title: string; detail?: string }[];
  status: StatusResponse | null;
  step: number;
  phase: string | null;
}

const props = defineProps<{ flow: DebugFlow }>();

// newest exchange first; the index keeps the keys unique
const exchanges = computed(() => props.flow.trace.map((entry, index) => ({ entry, index })).reverse());
const events = computed(() => [...props.flow.timeline].reverse());
const state = computed(() => {
  const { requests: _requests, ...rest } = props.flow.status ?? ({} as Record<string, unknown>);
  return { step: props.flow.step, phase: props.flow.phase, ...rest };
});

function path(url: string): string {
  try {
    const u = new URL(url);
    return u.pathname + u.search;
  } catch {
    return url;
  }
}
</script>

<template>
  <div class="debug">
    <Panel header="Timeline" toggleable>
      <p v-if="!events.length" class="empty">Nothing has happened yet.</p>
      <ol v-else class="timeline">
        <li v-for="(event, i) in events" :key="i">
          <time>{{ formatTime(event.at) }}</time>
          <div>
            <div>{{ event.title }}</div>
            <pre v-if="event.detail" class="detail">{{ event.detail }}</pre>
          </div>
        </li>
      </ol>
    </Panel>

    <Panel header="Back-end &harr; issuer exchanges" toggleable>
      <p class="hint">
        The calls the back-end made to the issuer. The admin token is replaced by <code>***</code>.
        Status polls are not listed.
      </p>
      <p v-if="!exchanges.length" class="empty">No calls yet.</p>
      <Accordion v-else multiple>
        <AccordionPanel v-for="{ entry, index } in exchanges" :key="index" :value="String(index)">
          <AccordionHeader>
            <span class="exchange">
              <Tag :value="String(entry.status || 'ERR')" :severity="entry.status >= 200 && entry.status < 300 ? 'success' : 'danger'" />
              <b>{{ entry.method }}</b>
              <span class="path">{{ path(entry.url) }}</span>
              <small>{{ entry.durationMs }} ms</small>
            </span>
          </AccordionHeader>
          <AccordionContent>
            <div class="stack">
              <JsonPanel :value="entry.requestHeaders" label="Request headers" />
              <JsonPanel v-if="entry.requestBody !== null" :value="entry.requestBody" label="Request body" />
              <JsonPanel :value="entry.responseBody" label="Response body" />
            </div>
          </AccordionContent>
        </AccordionPanel>
      </Accordion>
    </Panel>

    <Panel header="Session state" toggleable :collapsed="true">
      <JsonPanel :value="state" />
    </Panel>
  </div>
</template>

<style scoped>
.debug { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
.debug :deep(.p-panel) { min-width: 0; overflow: hidden; }
.debug :deep(.p-accordionheader) { min-width: 0; }
/* the panel content is a grid whose column would otherwise grow with the longest URL */
.debug :deep(.p-panel-content-container),
.debug :deep(.p-panel-content-wrapper),
.debug :deep(.p-panel-content),
.debug :deep(.p-accordion),
.debug :deep(.p-accordionpanel),
.debug :deep(.p-accordioncontent-content) { min-width: 0; max-width: 100%; }
.debug :deep(.p-panel-content) { overflow-x: hidden; }
.empty, .hint { margin: 0 0 .5rem; font-size: .85rem; opacity: .75; }
.timeline { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .5rem; max-height: 20rem; overflow: auto; }
.timeline li { display: flex; gap: .75rem; font-size: .85rem; }
.timeline time { font-variant-numeric: tabular-nums; opacity: .7; flex: none; }
.detail { margin: .25rem 0 0; font-size: .75rem; white-space: pre-wrap; word-break: break-word; opacity: .8; }
.exchange { display: flex; gap: .5rem; align-items: center; min-width: 0; flex: 1; }
.exchange small { white-space: nowrap; margin-left: auto; }
.exchange > * { flex: none; }
.exchange > .path { flex: 0 1 auto; min-width: 0; }
.path { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: monospace; font-size: .85rem; }
.stack { display: flex; flex-direction: column; gap: .5rem; }
</style>
