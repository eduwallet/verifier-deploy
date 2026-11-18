<script setup lang="ts">
import { ref } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import JsonPanel from '@/components/JsonPanel.vue';
import QrPanel from '@/components/QrPanel.vue';
import StatusTag from '@/components/StatusTag.vue';
import MappingTable from './MappingTable.vue';
import type { PresentationFlow } from './usePresentationFlow';

defineProps<{ flow: PresentationFlow }>();

const copied = ref(false);
async function copy(text: string) {
  try {
    await navigator.clipboard.writeText(text);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
  } catch {
    // the clipboard can be unavailable on plain http
  }
}
</script>

<template>
  <section class="step">
    <h2>Credential offer</h2>
    <p>
      The credential offer has an <b>authorization code</b> grant. Before the wallet gets the credential, the issuer
      asks it to present an eduID. The issuer copies the presented claims into the new credential:
    </p>
    <MappingTable v-if="flow.presentation" :presentation="flow.presentation" />
    <Message v-if="flow.error" severity="error" :closable="false" class="msg">{{ flow.error }}</Message>

    <Button
      v-if="!flow.issuance.run"
      class="create"
      label="Create the offer"
      icon="pi pi-send"
      size="large"
      :loading="flow.busy"
      :disabled="!flow.issuerConfigured"
      @click="flow.startIssuance()"
    />

    <template v-else>
      <Message v-if="flow.issuance.phase === 'retrieved'" severity="info" :closable="false" class="msg">
        <b>The wallet retrieved the offer.</b> Accept it in your wallet. Your wallet will then ask you to present your eduID.
      </Message>
      <Message v-else-if="flow.issuance.phase === 'expired'" severity="warn" :closable="false" class="msg">The offer has expired.</Message>

      <div class="layout">
        <div class="qr">
          <QrPanel :text="flow.issuance.run.offerUri" />
          <StatusTag :phase="flow.issuance.phase" :status="flow.issuance.status?.status" />
        </div>
        <div class="details">
          <label for="offer-uri">Credential offer URI</label>
          <div class="uri">
            <InputText id="offer-uri" :model-value="flow.issuance.run.offerUri" readonly fluid />
            <Button
              :icon="copied ? 'pi pi-check' : 'pi pi-copy'"
              severity="secondary"
              aria-label="Copy the offer URI"
              @click="copy(flow.issuance.run.offerUri)"
            />
            <a :href="flow.issuance.run.offerUri"><Button label="Open in wallet" icon="pi pi-external-link" severity="secondary" as="span" /></a>
          </div>
          <h3>Credential offer</h3>
          <JsonPanel v-if="flow.issuance.offer" :value="flow.issuance.offer" label="credential_offer" />
          <p v-else>The offer is being fetched&hellip;</p>
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
.msg { margin: 1rem 0; }
.create { margin-top: 1.5rem; min-width: 12rem; font-size: 1.25rem; }
.layout { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 2rem; margin-top: 1rem; }
.qr { display: flex; flex-direction: column; gap: .75rem; align-items: flex-start; }
.details { min-width: 0; display: flex; flex-direction: column; gap: .5rem; }
.details label { font-weight: 600; }
.details h3 { margin: 1rem 0 0; font-size: 1rem; }
.uri { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
.uri :deep(input) { font-family: monospace; font-size: .8rem; }
.uri .p-inputtext { flex: 1 1 18rem; }
@media (max-width: 720px) { .layout { grid-template-columns: minmax(0, 1fr); } }
</style>
