<script setup lang="ts">
import { computed } from 'vue';
import Message from 'primevue/message';
import ProgressSpinner from 'primevue/progressspinner';
import JsonPanel from '@/components/JsonPanel.vue';
import StatusTag from '@/components/StatusTag.vue';
import { formatAgo, formatDateTime } from '@/utils/format';
import type { DeferredFlow } from './useDeferredFlow';

const props = defineProps<{ flow: DeferredFlow }>();

const interval = computed(() => (props.flow.pollIntervalMs ? props.flow.pollIntervalMs / 1000 : null));
const exchange = computed(() => props.flow.status?.requests?.['deferred-request']);
</script>

<template>
  <section class="step">
    <h2>Collection by the wallet</h2>

    <Message v-if="flow.phase === 'expired'" severity="warn" :closable="false">
      <b>The transaction has expired</b> before the wallet collected the credential.
    </Message>
    <div v-else class="waiting">
      <ProgressSpinner style="width: 2rem; height: 2rem" stroke-width="6" />
      <span>
        The credential is ready for issuance. The issuer holds it until the wallet polls the deferred endpoint
        (the issuer asked for an interval of {{ flow.deferred?.own?.interval ?? '?' }} s). Keep the wallet open.
      </span>
    </div>

    <dl class="facts">
      <dt>Status</dt><dd><StatusTag :phase="flow.phase" :status="flow.status?.status" /></dd>
      <dt>Last updated</dt><dd>{{ formatDateTime(flow.status?.lastUpdatedAt) }}</dd>
      <dt>Polling</dt>
      <dd>
        <template v-if="interval">every {{ interval }} s, last {{ formatAgo(flow.lastPoll?.getTime()) }} ({{ flow.pollCount }} polls)</template>
        <template v-else>stopped</template>
      </dd>
    </dl>
    <Message v-if="flow.pollError" severity="warn" :closable="false">Polling problem: {{ flow.pollError }}. Retrying.</Message>

    <template v-if="exchange">
      <h3>Last wallet poll</h3>
      <p class="muted">The issuer keeps only the latest deferred request of the wallet.</p>
      <JsonPanel :value="exchange" label="deferred-request" />
    </template>
  </section>
</template>

<style scoped>
.waiting { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
.facts { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: .5rem 1.5rem; margin: 1rem 0; }
.facts dt { font-weight: 600; }
.facts dd { margin: 0; }
h3 { margin: 1.5rem 0 .5rem; font-size: 1rem; }
.muted { opacity: .75; margin: 0 0 .75rem; }
</style>
