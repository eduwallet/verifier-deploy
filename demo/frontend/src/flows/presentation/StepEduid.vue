<script setup lang="ts">
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Panel from 'primevue/panel';
import QrPanel from '@/components/QrPanel.vue';
import StatusTag from '@/components/StatusTag.vue';
import type { PresentationFlow } from './usePresentationFlow';

defineProps<{ flow: PresentationFlow }>();
</script>

<template>
  <section class="step">
    <h2>Does your wallet have an eduID?</h2>
    <p>
      The credential that is issued in this flow is filled with the data of an <b>eduID</b> that your wallet
      presents. If your wallet has no eduID yet, the demo can issue one first. The eduID of this demo (and
      the eduID of the eduID issuer) are accepted.
    </p>
    <Message v-if="!flow.issuerConfigured" severity="warn" :closable="false">
      The sandbox issuer is not configured in the back-end (no admin token).
    </Message>
    <Message v-if="flow.error" severity="error" :closable="false">{{ flow.error }}</Message>

    <div v-if="flow.choice === 'none'" class="choices">
      <Button label="My wallet has an eduID" icon="pi pi-check" size="large" severity="secondary" :disabled="!flow.issuerConfigured" @click="flow.choose('have')" />
      <Button label="Issue an eduID first" icon="pi pi-id-card" size="large" :disabled="!flow.issuerConfigured" @click="flow.choose('issue')" />
    </div>

    <div v-else-if="flow.choice === 'have'" class="choices">
      <p class="grow">Continue with the eduID that is in your wallet.</p>
      <Button label="Continue" icon="pi pi-arrow-right" icon-pos="right" size="large" @click="flow.proceed()" />
      <Button label="Back" severity="secondary" text @click="flow.choose('none')" />
    </div>

    <template v-else>
      <Panel v-if="!flow.eduid.run" header="eduID data">
        <p class="hint">These values are put in the eduID. The values are not important for the demo.</p>
        <div class="fields">
          <div v-for="field in flow.eduidFields" :key="field.name" class="field">
            <label :for="`eduid-${field.name}`">{{ field.label }}</label>
            <InputText :id="`eduid-${field.name}`" v-model="flow.form[field.name]" :maxlength="field.maxLength" fluid />
          </div>
        </div>
        <p class="hint">
          A wallet key can only hold one eduID identifier. To get a second eduID in the same wallet, use
          the same identifier.
        </p>
      </Panel>
      <div v-if="!flow.eduid.run" class="choices">
        <Button label="Issue eduID" icon="pi pi-send" size="large" :loading="flow.busy" @click="flow.issueEduid()" />
        <Button label="Back" severity="secondary" text @click="flow.choose('none')" />
      </div>

      <template v-else>
        <Message v-if="flow.eduidIssued" severity="success" :closable="false">
          <b>The eduID is in your wallet.</b> Continue with the issuance of the credential.
        </Message>
        <Message v-else-if="flow.eduid.phase === 'retrieved'" severity="info" :closable="false">
          <b>The wallet retrieved the offer.</b> Accept the eduID in your wallet now.
        </Message>
        <Message v-else-if="flow.eduid.phase === 'expired'" severity="warn" :closable="false">The eduID offer has expired.</Message>

        <div class="offer">
          <QrPanel :text="flow.eduid.run.offerUri" />
          <div class="side">
            <StatusTag :phase="flow.eduid.phase" :status="flow.eduid.status?.status" />
            <p>Scan the QR code with your wallet, or open the offer on this device.</p>
            <a :href="flow.eduid.run.offerUri"><Button label="Open in wallet" icon="pi pi-external-link" severity="secondary" as="span" /></a>
          </div>
        </div>
        <div class="choices">
          <Button label="Continue" icon="pi pi-arrow-right" icon-pos="right" size="large" :severity="flow.eduidIssued ? 'primary' : 'secondary'" @click="flow.proceed()" />
        </div>
      </template>
    </template>
  </section>
</template>

<style scoped>
.choices { display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; margin: 1.5rem 0; }
.grow { flex: 1 1 100%; margin: 0; }
.fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); gap: 1rem; }
.field { display: flex; flex-direction: column; gap: .35rem; }
.field label { font-weight: 600; }
.hint { opacity: .75; font-size: .9rem; }
.offer { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 2rem; margin-top: 1rem; }
.side { display: flex; flex-direction: column; gap: .75rem; align-items: flex-start; }
@media (max-width: 720px) { .offer { grid-template-columns: minmax(0, 1fr); } }
</style>
