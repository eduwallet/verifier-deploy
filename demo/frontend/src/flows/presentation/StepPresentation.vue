<script setup lang="ts">
import Message from 'primevue/message';
import Accordion from 'primevue/accordion';
import AccordionPanel from 'primevue/accordionpanel';
import AccordionHeader from 'primevue/accordionheader';
import AccordionContent from 'primevue/accordioncontent';
import Button from 'primevue/button';
import JsonPanel from '@/components/JsonPanel.vue';
import StatusTag from '@/components/StatusTag.vue';
import { MILESTONES, reached } from './flowLogic';
import type { PresentationFlow } from './usePresentationFlow';

defineProps<{ flow: PresentationFlow }>();
</script>

<template>
  <section class="step">
    <h2>Presentation</h2>
    <p>
      The wallet asks the issuer for authorization. The issuer answers that a presentation is needed, the wallet shows
      the request for your eduID, and sends it to the verifier. When the verifier accepts the presentation, the issuer
      gives the wallet an authorization code and the wallet exchanges it for an access token to get the credential.
    </p>

    <Message v-if="flow.denied" severity="error" :closable="false" class="msg">
      <b>The presentation was rejected.</b> The issuer denied the authorization. The eduID was not accepted by the
      verifier, or a requested claim was missing.
    </Message>
    <Message v-else-if="flow.issuance.phase === 'presentation'" severity="info" :closable="false" class="msg">
      <b>Waiting for your presentation.</b> Choose your eduID in the wallet and share it.
    </Message>

    <StatusTag :phase="flow.issuance.phase" :status="flow.issuance.status?.status" />

    <ol class="milestones">
      <li v-for="m in MILESTONES" :key="m.id" :class="{ done: reached(m.id, flow.issuance.status?.status) }">
        <i :class="['pi', reached(m.id, flow.issuance.status?.status) ? 'pi-check-circle' : 'pi-clock']" aria-hidden="true" />
        {{ m.label }}
      </li>
    </ol>

    <h3>Issuer messages</h3>
    <Accordion v-if="Object.keys(flow.issuance.status?.requests ?? {}).length" multiple :value="[]">
      <AccordionPanel v-for="name in Object.keys(flow.issuance.status?.requests ?? {})" :key="name" :value="name">
        <AccordionHeader><span class="mono">{{ name }}</span></AccordionHeader>
        <AccordionContent><JsonPanel :value="flow.issuance.status?.requests[name]" /></AccordionContent>
      </AccordionPanel>
    </Accordion>

    <div v-if="flow.denied" class="actions">
      <Button label="Start over" icon="pi pi-replay" severity="secondary" @click="flow.reset()" />
    </div>
  </section>
</template>

<style scoped>
.msg { margin: 1rem 0; }
.milestones { list-style: none; margin: 1rem 0; padding: 0; display: flex; flex-direction: column; gap: .5rem; }
.milestones li { opacity: .6; }
.milestones li.done { opacity: 1; }
.pi-check-circle { color: var(--p-green-500); }
h3 { margin: 1.5rem 0 .5rem; font-size: 1rem; }
.mono { font-family: monospace; font-size: .85rem; word-break: break-all; }
.actions { margin-top: 2rem; }
</style>
