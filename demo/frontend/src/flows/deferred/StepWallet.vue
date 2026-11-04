<script setup lang="ts">
import { computed } from 'vue';
import ProgressSpinner from 'primevue/progressspinner';
import StatusTag from '@/components/StatusTag.vue';
import JsonPanel from '@/components/JsonPanel.vue';
import type { DeferredFlow } from './useDeferredFlow';

const props = defineProps<{ flow: DeferredFlow }>();

const phases = ['access_token-request', 'access_token-response', 'get_credential-request', 'get_credential-response'];
const exchanges = computed(() => phases.filter((p) => props.flow.status?.requests && p in props.flow.status.requests));
</script>

<template>
  <section class="step">
    <h2>The wallet requests the credential</h2>
    <p>
      The wallet exchanges the pre-authorized code for an access token and then requests the credential.
      Because this credential is <b>deferred</b>, the issuer does not issue it yet. It answers with
      <code>202</code> and a <code>transaction_id</code>, and the wallet has to come back later.
    </p>
    <div class="waiting">
      <ProgressSpinner style="width: 2rem; height: 2rem" stroke-width="6" />
      <span>Waiting for the credential request of the wallet&hellip;</span>
      <StatusTag :phase="flow.phase" :status="flow.status?.status" />
    </div>

    <div v-if="exchanges.length" class="exchanges">
      <JsonPanel v-for="phase in exchanges" :key="phase" :label="phase" :value="flow.status?.requests[phase]" />
    </div>
  </section>
</template>

<style scoped>
.waiting { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin: 1.5rem 0; }
.exchanges { display: grid; grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr)); gap: 1rem; }
</style>
