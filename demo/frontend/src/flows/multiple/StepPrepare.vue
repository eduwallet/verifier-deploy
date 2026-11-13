<script setup lang="ts">
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Panel from 'primevue/panel';
import type { MultipleFlow } from './useMultipleFlow';

defineProps<{ flow: MultipleFlow }>();
</script>

<template>
  <section class="step">
    <h2>Prepare the credentials</h2>
    <p>
      The issuer offers <b>{{ flow.credentials.length }} credentials</b> in one credential offer: two generic
      credentials that are issued at once, and one deferred credential that is released afterwards.
      The values below are sent with the offer. The values are not important for the demo.
    </p>
    <Message v-if="!flow.issuerConfigured" severity="warn" :closable="false">
      The sandbox issuer is not configured in the back-end (no admin token).
    </Message>

    <div class="panels">
      <Panel v-for="credential in flow.credentials" :key="credential.key" :header="credential.title">
        <div class="fields">
          <div v-for="field in credential.fields" :key="field.name" class="field">
            <label :for="`field-${credential.key}-${field.name}`">{{ field.label }}</label>
            <InputText
              :id="`field-${credential.key}-${field.name}`"
              v-model="flow.form[credential.key][field.name]"
              :maxlength="field.maxLength"
              fluid
            />
          </div>
        </div>
      </Panel>
    </div>

    <Button
      class="issue"
      label="Issue"
      icon="pi pi-send"
      size="large"
      :loading="flow.busy"
      :disabled="!flow.credentials.length || !flow.issuerConfigured"
      @click="flow.issue()"
    />
  </section>
</template>

<style scoped>
.panels { display: grid; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); gap: 1rem; margin: 1.5rem 0; }
.fields { display: grid; gap: 1rem; }
.field { display: flex; flex-direction: column; gap: .35rem; }
.field label { font-weight: 600; }
.issue { min-width: 12rem; font-size: 1.25rem; }
</style>
