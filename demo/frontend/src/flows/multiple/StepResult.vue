<script setup lang="ts">
import { computed } from 'vue';
import Accordion from 'primevue/accordion';
import AccordionPanel from 'primevue/accordionpanel';
import AccordionHeader from 'primevue/accordionheader';
import AccordionContent from 'primevue/accordioncontent';
import Button from 'primevue/button';
import Message from 'primevue/message';
import JsonPanel from '@/components/JsonPanel.vue';
import { formatDateTime } from '@/utils/format';
import CredentialList from './CredentialList.vue';
import type { MultipleFlow } from './useMultipleFlow';

const props = defineProps<{ flow: MultipleFlow }>();

const phases = computed(() => Object.keys(props.flow.status?.requests ?? {}));
const denied = computed(() => props.flow.phase === 'denied');
const reason = computed(() => props.flow.status?.deferred.find((t) => t.reason)?.reason ?? props.flow.status?.reason);
</script>

<template>
  <section class="step">
    <h2>Completed</h2>

    <Message v-if="denied" severity="error" :closable="false">
      <b>The deferred credential was denied.</b>
      <span v-if="reason"> Reason: {{ reason }}</span>
    </Message>
    <Message v-else severity="success" :closable="false">
      <b>All {{ flow.credentials.length }} credentials are issued.</b>
      The wallet collected the generic credentials directly and the deferred credential after its release.
    </Message>

    <CredentialList :flow="flow" />

    <dl class="facts">
      <dt>Last updated</dt><dd>{{ formatDateTime(flow.status?.lastUpdatedAt) }}</dd>
    </dl>

    <h3>Notification</h3>
    <JsonPanel v-if="flow.notification" :value="flow.notification" label="notification-request" />
    <p v-else class="muted">
      The wallet has not sent a notification (yet). Wallets may do so after storing the credentials;
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
.facts { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: .5rem 1.5rem; margin: 1rem 0; }
.facts dt { font-weight: 600; }
.facts dd { margin: 0; }
h3 { margin: 1.5rem 0 .5rem; font-size: 1rem; }
.muted { opacity: .75; margin: 0 0 .75rem; }
.mono { font-family: monospace; font-size: .85rem; word-break: break-all; }
.actions { margin-top: 2rem; }
</style>
