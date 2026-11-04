<script setup lang="ts">
import Tag from 'primevue/tag';
import type { Phase } from '@/api/types';
import { PHASE_LABELS, phaseSeverity } from '@/flows/deferred/flowLogic';

defineProps<{ phase: Phase | null; status?: string | null }>();
</script>

<template>
  <span class="status-tag">
    <Tag :value="phase ? PHASE_LABELS[phase] : 'No offer yet'" :severity="phaseSeverity(phase)" />
    <code v-if="status" class="raw-status">{{ status }}</code>
  </span>
</template>

<style scoped>
.status-tag { display: inline-flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
.raw-status { font-size: .75rem; opacity: .7; }
</style>
