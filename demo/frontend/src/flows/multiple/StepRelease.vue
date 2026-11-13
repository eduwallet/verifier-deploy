<script setup lang="ts">
import { computed } from 'vue';
import Button from 'primevue/button';
import Message from 'primevue/message';
import Panel from 'primevue/panel';
import ProgressSpinner from 'primevue/progressspinner';
import { formatDateTime } from '@/utils/format';
import CredentialList from './CredentialList.vue';
import type { MultipleFlow } from './useMultipleFlow';

const props = defineProps<{ flow: MultipleFlow }>();

const transaction = computed(() => props.flow.transaction);
const deferredCount = computed(() => props.flow.status?.deferred.length ?? 0);
const canRelease = computed(() => transaction.value?.status === 'CREDENTIAL_PENDING' && !props.flow.released);
const waiting = computed(() => props.flow.released || transaction.value?.status === 'CREDENTIAL_READY');
</script>

<template>
  <section class="step">
    <h2>Back-office: release the deferred credential</h2>
    <p>
      The wallet requested all credentials. The issuer issued the generic credentials at once, but it holds
      <b>{{ deferredCount }} deferred credential</b> until the back-office releases it. In a real system an
      employee would check the request first. The demo plays that role: release the credential with the data
      from the offer.
    </p>

    <CredentialList :flow="flow" />

    <Panel v-if="transaction" header="Deferred transaction" class="spaced">
      <dl class="facts">
        <dt>Transaction id</dt><dd class="mono">{{ transaction.id }}</dd>
        <dt>Status</dt><dd>{{ transaction.status }}</dd>
        <dt>Expires</dt><dd>{{ formatDateTime(transaction.expiresAt) }}</dd>
      </dl>
    </Panel>

    <Message v-if="flow.error" severity="error" :closable="false" class="spaced">
      {{ flow.error }}
      <pre v-if="flow.errorDetail" class="error-detail">{{ flow.errorDetail }}</pre>
    </Message>

    <div v-if="waiting" class="waiting">
      <ProgressSpinner style="width: 2rem; height: 2rem" stroke-width="6" />
      <span>
        The credential is released. The wallet collects it the next time it polls the deferred endpoint
        (the issuer asked for an interval of a few seconds). Keep the wallet open.
      </span>
    </div>
    <div v-else class="actions">
      <Button
        label="Release credential"
        icon="pi pi-check"
        size="large"
        class="release"
        :loading="flow.busy"
        :disabled="!canRelease"
        @click="flow.release()"
      />
      <small v-if="!canRelease">Waiting for the issuer to report the transaction&hellip;</small>
    </div>

    <Message v-if="flow.pollError" severity="warn" :closable="false" class="spaced">Polling problem: {{ flow.pollError }}. Retrying.</Message>
  </section>
</template>

<style scoped>
.spaced { margin-top: 1.5rem; }
.facts { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: .35rem 1.5rem; margin: 0; }
.facts dt { font-weight: 600; }
.facts dd { margin: 0; word-break: break-all; }
.mono { font-family: monospace; font-size: .85rem; }
.actions { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-top: 1.5rem; }
.release { min-width: 14rem; font-size: 1.25rem; }
.waiting { display: flex; align-items: center; gap: 1rem; margin-top: 1.5rem; }
.error-detail { margin: .5rem 0 0; white-space: pre-wrap; font-size: .8rem; }
</style>
