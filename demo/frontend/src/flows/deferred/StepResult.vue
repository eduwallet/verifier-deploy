<script setup lang="ts">
import { computed } from 'vue';
import Accordion from 'primevue/accordion';
import AccordionPanel from 'primevue/accordionpanel';
import AccordionHeader from 'primevue/accordionheader';
import AccordionContent from 'primevue/accordioncontent';
import Button from 'primevue/button';
import Message from 'primevue/message';
import ProgressSpinner from 'primevue/progressspinner';
import JsonPanel from '@/components/JsonPanel.vue';
import StatusTag from '@/components/StatusTag.vue';
import { formatAgo, formatDateTime } from '@/utils/format';
import type { DeferredFlow } from './useDeferredFlow';

const props = defineProps<{ flow: DeferredFlow }>();

const phases = computed(() => Object.keys(props.flow.status?.requests ?? {}));
const interval = computed(() => (props.flow.pollIntervalMs ? props.flow.pollIntervalMs / 1000 : null));
</script>

<template>
  <section class="step">
    <h2>Issuance</h2>

    <Message v-if="flow.phase === 'issued'" severity="success" :closable="false">
      <b>The wallet collected the credential.</b> The issuance is complete.
    </Message>
    <Message v-else-if="flow.phase === 'denied'" severity="error" :closable="false">
      <b>The credential was denied.</b>
      <span v-if="flow.status?.reason"> Reason: {{ flow.status.reason }}</span>
    </Message>
    <Message v-else-if="flow.phase === 'expired'" severity="warn" :closable="false">
      <b>The transaction has expired</b> before the wallet collected the credential.
    </Message>
    <div v-else class="waiting">
      <ProgressSpinner style="width: 2rem; height: 2rem" stroke-width="6" />
      <span>
        The credential is released. The wallet collects it the next time it polls the deferred endpoint
        (the issuer asked for an interval of {{ flow.deferred?.own?.interval ?? '?' }} s).
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
      <dt v-if="flow.status?.credentialUuid">Credential id</dt>
      <dd v-if="flow.status?.credentialUuid" class="mono">{{ flow.status.credentialUuid }}</dd>
    </dl>
    <Message v-if="flow.pollError" severity="warn" :closable="false">Polling problem: {{ flow.pollError }}. Retrying.</Message>

    <h3>Notification</h3>
    <JsonPanel v-if="flow.notification" :value="flow.notification" label="notification-request" />
    <p v-else class="muted">
      The wallet has not sent a notification (yet). Wallets may do so after storing the credential;
      the page keeps checking.
    </p>

    <h3>Issuer messages</h3>
    <p class="muted">Per protocol phase, in the order of the protocol. The issuer keeps the last message of each phase.</p>
    <Accordion v-if="phases.length" multiple :value="[]">
      <AccordionPanel v-for="phase in phases" :key="phase" :value="phase">
        <AccordionHeader><span class="mono">{{ phase }}</span></AccordionHeader>
        <AccordionContent><JsonPanel :value="flow.status?.requests[phase]" /></AccordionContent>
      </AccordionPanel>
    </Accordion>

    <div class="actions">
      <Button label="Start over" icon="pi pi-replay" severity="secondary" @click="flow.reset()" />
    </div>
  </section>
</template>

<style scoped>
.waiting { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
.facts { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: .5rem 1.5rem; margin: 1rem 0; }
.facts dt { font-weight: 600; }
.facts dd { margin: 0; }
h3 { margin: 1.5rem 0 .5rem; font-size: 1rem; }
.muted { opacity: .75; margin: 0 0 .75rem; }
.mono { font-family: monospace; font-size: .85rem; word-break: break-all; }
.actions { margin-top: 2rem; }
</style>
