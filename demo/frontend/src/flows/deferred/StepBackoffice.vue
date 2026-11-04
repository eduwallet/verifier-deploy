<script setup lang="ts">
import { computed } from 'vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Panel from 'primevue/panel';
import Tag from 'primevue/tag';
import JsonPanel from '@/components/JsonPanel.vue';
import type { DeferredItem } from '@/api/types';
import { formatDateTime, formatDuration, formatRemaining } from '@/utils/format';
import type { DeferredFlow } from './useDeferredFlow';

const props = defineProps<{ flow: DeferredFlow }>();

const own = computed(() => props.flow.deferred?.own ?? null);
const claimNames = computed(() => Object.keys(props.flow.claims));
const mandatory = computed(() => new Set(
  (own.value?.claims ?? []).filter((c) => c.mandatory).flatMap((c) => c.supply.map((p) => p.join('.'))),
));

function label(name: string): string {
  return props.flow.credential?.fields.find((f) => f.name === name)?.label ?? name;
}

function rowClass(item: DeferredItem): string {
  return item.own ? 'own-row' : '';
}
</script>

<template>
  <section class="step">
    <h2>Back-office: authorize the credential</h2>
    <p>
      The wallet is waiting. In a real system an employee would now check the request and supply the
      claims. The demo plays that role: complete the claims below and authorize the credential.
    </p>

    <h3>Open deferred transactions</h3>
    <DataTable
      :value="flow.deferred?.items ?? []"
      :row-class="rowClass"
      size="small"
      data-key="id"
      :loading="!flow.deferred"
      striped-rows
    >
      <template #empty>No open transactions.</template>
      <Column header="Transaction">
        <template #body="{ data }">
          <span :title="data.id" class="mono">{{ data.shortId }}</span>
          <Tag v-if="data.own" value="yours" severity="info" class="own-tag" />
        </template>
      </Column>
      <Column field="credential" header="Credential" />
      <Column header="Created">
        <template #body="{ data }"><span :title="formatDateTime(data.createdAt)">{{ formatDateTime(data.createdAt) }}</span></template>
      </Column>
      <Column header="Expires">
        <template #body="{ data }"><span :title="formatDateTime(data.expiresAt)">{{ formatRemaining(data.expiresInSeconds) }}</span></template>
      </Column>
      <Column header="Claims known">
        <template #body="{ data }">
          <span v-if="!data.claimsKnown.length" class="none">none</span>
          <Tag v-for="name in data.claimsKnown" :key="name" :value="name" severity="secondary" class="claim-tag" />
        </template>
      </Column>
      <Column field="proofs" header="Holder keys" />
    </DataTable>
    <Button label="Refresh" icon="pi pi-refresh" severity="secondary" text size="small" @click="flow.refreshDeferred()" />

    <Message v-if="flow.deferred && !own" severity="warn" :closable="false" class="spaced">
      Your transaction is not in the issuer (anymore). It may have expired.
    </Message>

    <Panel v-if="own" header="Your transaction" class="spaced">
      <dl class="facts">
        <dt>Transaction id</dt><dd class="mono">{{ own.id }}</dd>
        <dt>Status</dt><dd>{{ own.status }}</dd>
        <dt>Credential</dt><dd>{{ own.credential_configuration_id }} <small v-if="own.format">({{ own.format }})</small></dd>
        <dt>Created</dt><dd>{{ formatDateTime(own.createdAt) }}</dd>
        <dt>Expires</dt><dd>{{ formatDateTime(own.expiresAt) }} <small>({{ formatRemaining(own.expiresInSeconds) }})</small></dd>
        <dt>TTL</dt><dd>{{ formatDuration(own.ttl) }}</dd>
        <dt>Wallet poll interval</dt><dd>{{ formatDuration(own.interval) }}</dd>
        <dt>Holder keys</dt><dd>{{ own.proofs }}</dd>
      </dl>

      <h4>Claims the credential defines</h4>
      <DataTable :value="own.claims" size="small">
        <Column header="Claim"><template #body="{ data }">{{ data.path.join('.') }}</template></Column>
        <Column field="type" header="Type" />
        <Column header="Mandatory"><template #body="{ data }">{{ data.mandatory ? 'yes' : 'no' }}</template></Column>
        <Column header="Origin"><template #body="{ data }"><span class="mono">{{ data.origin.map((o: string[]) => o.join('.')).join(', ') }}</span></template></Column>
      </DataTable>

      <div class="data-grid">
        <JsonPanel :value="own.data" label="data (from the offer)" />
        <JsonPanel :value="own.claims_supplied" label="claims_supplied (by the back-office)" />
      </div>

      <h4>Claims to release</h4>
      <div class="claims">
        <div v-for="name in claimNames" :key="name" class="claim">
          <label :for="`claim-${name}`">{{ label(name) }}<span v-if="mandatory.has(name)"> *</span></label>
          <InputText :id="`claim-${name}`" v-model="flow.claims[name]" fluid />
        </div>
      </div>

      <Message v-if="flow.error" severity="error" :closable="false">
        {{ flow.error }}
        <pre v-if="flow.errorDetail" class="error-detail">{{ flow.errorDetail }}</pre>
      </Message>

      <div class="actions">
        <Button label="Authorize" icon="pi pi-check" size="large" class="authorize" :loading="flow.busy" @click="flow.authorize()" />
        <Button label="Deny" icon="pi pi-times" severity="danger" text :disabled="flow.busy" @click="flow.deny('Denied in the demo')" />
      </div>
    </Panel>
  </section>
</template>

<style>
.own-row { background: var(--p-primary-50) !important; font-weight: 600; }
@media (prefers-color-scheme: dark) { .own-row { background: color-mix(in srgb, var(--p-primary-color) 18%, transparent) !important; } }
</style>

<style scoped>
.spaced { margin-top: 1.5rem; }
.mono { font-family: monospace; font-size: .85rem; }
.own-tag, .claim-tag { margin-left: .4rem; }
.none { opacity: .6; }
.facts { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: .35rem 1.5rem; margin: 0 0 1rem; }
.facts dt { font-weight: 600; }
.facts dd { margin: 0; word-break: break-all; }
h4 { margin: 1.5rem 0 .5rem; }
.data-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); gap: 1rem; margin-top: 1rem; }
.claims { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.claim { display: flex; flex-direction: column; gap: .35rem; }
.claim label { font-weight: 600; }
.actions { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
.authorize { min-width: 12rem; font-size: 1.25rem; }
.error-detail { margin: .5rem 0 0; white-space: pre-wrap; font-size: .8rem; }
</style>
