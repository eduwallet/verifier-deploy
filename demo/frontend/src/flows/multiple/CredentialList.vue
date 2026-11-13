<script setup lang="ts">
import Tag from 'primevue/tag';
import { STATE_LABELS, stateSeverity } from './flowLogic';
import type { MultipleFlow } from './useMultipleFlow';

defineProps<{ flow: MultipleFlow }>();
</script>

<template>
  <ol class="credentials">
    <li v-for="item in flow.items" :key="item.key">
      <i :class="['pi', item.state === 'issued' ? 'pi-check-circle' : item.state === 'denied' ? 'pi-times-circle' : 'pi-clock']" aria-hidden="true" />
      <span class="title">{{ item.title }}</span>
      <Tag v-if="item.deferred" value="deferred" severity="secondary" />
      <Tag :value="STATE_LABELS[item.state]" :severity="stateSeverity(item.state)" />
      <span v-if="item.uuid" class="uuid" :title="item.uuid">{{ item.uuid }}</span>
    </li>
  </ol>
</template>

<style scoped>
.credentials { list-style: none; margin: 1rem 0; padding: 0; display: flex; flex-direction: column; gap: .6rem; }
.credentials li { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }
.title { font-weight: 600; }
.uuid { font-family: monospace; font-size: .8rem; opacity: .7; overflow: hidden; text-overflow: ellipsis; max-width: 18rem; }
.pi-check-circle { color: var(--p-green-500); }
.pi-times-circle { color: var(--p-red-500); }
</style>
