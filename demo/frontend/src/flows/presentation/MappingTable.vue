<script setup lang="ts">
import type { CatalogPresentation } from '@/api/types';

defineProps<{ presentation: CatalogPresentation }>();
</script>

<template>
  <table class="mapping">
    <thead>
      <tr><th>Credential claim</th><th>Copied from the presented eduID</th></tr>
    </thead>
    <tbody>
      <tr v-for="(source, claim) in presentation.mapping" :key="claim">
        <td class="mono">{{ claim }}</td>
        <td class="mono">{{ source }}</td>
      </tr>
    </tbody>
  </table>
</template>

<style scoped>
.mapping { border-collapse: collapse; margin: 1rem 0; min-width: min(28rem, 100%); }
.mapping th, .mapping td { text-align: left; padding: .4rem 1rem .4rem 0; border-bottom: 1px solid var(--p-content-border-color); }
.mono { font-family: monospace; }
</style>
