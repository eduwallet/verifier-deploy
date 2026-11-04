<script setup lang="ts">
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import { pretty } from '@/utils/format';

const props = defineProps<{ value: unknown; label?: string }>();

const text = computed(() => pretty(props.value));
const copied = ref(false);

async function copy() {
  try {
    await navigator.clipboard.writeText(text.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
  } catch {
    // the clipboard can be unavailable on plain http
  }
}
</script>

<template>
  <div class="json-panel">
    <div class="json-head">
      <span v-if="label" class="json-label">{{ label }}</span>
      <Button
        :icon="copied ? 'pi pi-check' : 'pi pi-copy'"
        severity="secondary"
        text
        size="small"
        aria-label="Copy"
        @click="copy"
      />
    </div>
    <pre>{{ text }}</pre>
  </div>
</template>

<style scoped>
.json-panel { border: 1px solid var(--p-content-border-color); border-radius: var(--p-content-border-radius); overflow: hidden; }
.json-head { display: flex; align-items: center; justify-content: space-between; padding: 0 .5rem 0 .75rem; background: var(--p-surface-100); min-height: 2rem; }
.json-label { font-size: .8rem; font-weight: 600; font-family: var(--p-font-family, monospace); }
pre { margin: 0; padding: .75rem; max-height: 22rem; overflow: auto; font-size: .8rem; line-height: 1.4; white-space: pre-wrap; word-break: break-word; }
@media (prefers-color-scheme: dark) { .json-head { background: var(--p-surface-800); } }
</style>
