<script setup lang="ts">
import { ref } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import QrPanel from '@/components/QrPanel.vue';
import StatusTag from '@/components/StatusTag.vue';
import JsonPanel from '@/components/JsonPanel.vue';
import CredentialList from './CredentialList.vue';
import type { MultipleFlow } from './useMultipleFlow';

defineProps<{ flow: MultipleFlow }>();

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
  <section v-if="flow.run" class="step">
    <h2>Scan the credential offer</h2>
    <p>
      One offer holds all credentials. Scan the QR code with your wallet, or open the offer on this device,
      and accept the credentials. The generic credentials are issued immediately; the deferred credential
      has to wait for the back-office in the next step.
    </p>

    <Message v-if="flow.phase === 'retrieved'" severity="info" :closable="false">
      <b>The wallet retrieved the offer.</b> Accept the credentials in your wallet now.
    </Message>
    <Message v-else-if="flow.phase === 'expired'" severity="warn" :closable="false">The offer has expired.</Message>

    <div class="layout">
      <div class="qr">
        <QrPanel :text="flow.run.offerUri" />
        <StatusTag :phase="flow.phase" :status="flow.status?.status" />
      </div>

      <div class="details">
        <label for="offer-uri">Credential offer URI</label>
        <div class="uri">
          <InputText id="offer-uri" :model-value="flow.run.offerUri" readonly fluid />
          <Button
            :icon="copied ? 'pi pi-check' : 'pi pi-copy'"
            severity="secondary"
            aria-label="Copy the offer URI"
            @click="copy(flow.run.offerUri)"
          />
          <a :href="flow.run.offerUri"><Button label="Open in wallet" icon="pi pi-external-link" severity="secondary" as="span" /></a>
        </div>
        <p v-if="flow.run.txCode" class="tx">Transaction code: <b>{{ flow.run.txCode }}</b></p>

        <h3>Credentials in the offer</h3>
        <CredentialList :flow="flow" />

        <h3>Credential offer</h3>
        <JsonPanel v-if="flow.offer" :value="flow.offer" label="credential_offer" />
        <p v-else>The offer is being fetched&hellip;</p>
      </div>
    </div>
  </section>
</template>

<style scoped>
.layout { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 2rem; margin-top: 1rem; }
.qr { display: flex; flex-direction: column; gap: .75rem; align-items: flex-start; }
.details { min-width: 0; display: flex; flex-direction: column; gap: .5rem; }
.details label { font-weight: 600; }
.details h3 { margin: 1rem 0 0; font-size: 1rem; }
.uri { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
.uri :deep(input) { font-family: monospace; font-size: .8rem; }
.uri .p-inputtext { flex: 1 1 18rem; }
.tx { margin: 0; }
@media (max-width: 720px) { .layout { grid-template-columns: minmax(0, 1fr); } }
</style>
