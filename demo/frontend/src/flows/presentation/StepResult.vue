<script setup lang="ts">
import Accordion from 'primevue/accordion';
import AccordionPanel from 'primevue/accordionpanel';
import AccordionHeader from 'primevue/accordionheader';
import AccordionContent from 'primevue/accordioncontent';
import Button from 'primevue/button';
import Message from 'primevue/message';
import JsonPanel from '@/components/JsonPanel.vue';
import { formatDateTime } from '@/utils/format';
import MappingTable from './MappingTable.vue';
import type { PresentationFlow } from './usePresentationFlow';

defineProps<{ flow: PresentationFlow }>();
</script>

<template>
  <section class="step">
    <h2>Completed</h2>
    <Message severity="success" :closable="false">
      <b>The credential is issued.</b> The wallet presented its eduID and received a Generic Credential
      with <span class="mono">firstName</span>, <span class="mono">lastName</span> and <span class="mono">eMail</span> from that eduID.
    </Message>
    <MappingTable v-if="flow.presentation" :presentation="flow.presentation" />

    <dl class="facts">
      <dt>Credential</dt><dd class="mono">{{ flow.issuance.status?.credentialUuid ?? '-' }}</dd>
      <dt>Last updated</dt><dd>{{ formatDateTime(flow.issuance.status?.lastUpdatedAt) }}</dd>
    </dl>

    <h3>Issuer messages</h3>
    <p class="muted">Per protocol phase, in the order of the protocol. The issuer keeps the last message of each phase.</p>
    <Accordion v-if="Object.keys(flow.issuance.status?.requests ?? {}).length" multiple :value="[]">
      <AccordionPanel v-for="name in Object.keys(flow.issuance.status?.requests ?? {})" :key="name" :value="name">
        <AccordionHeader><span class="mono">{{ name }}</span></AccordionHeader>
        <AccordionContent><JsonPanel :value="flow.issuance.status?.requests[name]" /></AccordionContent>
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
