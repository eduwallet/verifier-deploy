<script setup lang="ts">
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import type { DeferredFlow } from './useDeferredFlow';

defineProps<{ flow: DeferredFlow }>();
</script>

<template>
  <section class="step">
    <h2>Prepare the credential</h2>
    <p>
      The <b>{{ flow.credential?.title ?? 'deferred credential' }}</b> is issued by the sandbox issuer.
      The values below are sent with the credential offer. The values are not important for the demo.
    </p>
    <Message v-if="!flow.issuerConfigured" severity="warn" :closable="false">
      The sandbox issuer is not configured in the back-end (no admin token).
    </Message>

    <div class="fields">
      <div v-for="field in flow.credential?.fields ?? []" :key="field.name" class="field">
        <label :for="`field-${field.name}`">{{ field.label }}</label>
        <InputText :id="`field-${field.name}`" v-model="flow.form[field.name]" :maxlength="field.maxLength" fluid />
      </div>
    </div>

    <Button
      class="issue"
      label="Issue"
      icon="pi pi-send"
      size="large"
      :loading="flow.busy"
      :disabled="!flow.credential || !flow.issuerConfigured"
      @click="flow.issue()"
    />
  </section>
</template>

<style scoped>
.fields { display: grid; gap: 1rem; max-width: 32rem; margin: 1.5rem 0; }
.field { display: flex; flex-direction: column; gap: .35rem; }
.field label { font-weight: 600; }
.issue { min-width: 12rem; font-size: 1.25rem; }
</style>
