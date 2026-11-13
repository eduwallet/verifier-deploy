<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue';
import Message from 'primevue/message';
import Step from 'primevue/step';
import StepList from 'primevue/steplist';
import StepPanel from 'primevue/steppanel';
import StepPanels from 'primevue/steppanels';
import Stepper from 'primevue/stepper';
import DebugPanels from '@/components/DebugPanels.vue';
import { showDetails } from '@/utils/settings';
import { STEPS } from './flowLogic';
import StepOffer from './StepOffer.vue';
import StepPrepare from './StepPrepare.vue';
import StepRelease from './StepRelease.vue';
import StepResult from './StepResult.vue';
import { useMultipleFlow, type MultipleFlow } from './useMultipleFlow';

const flow: MultipleFlow = reactive(useMultipleFlow());

// the visitor can look back at earlier steps; the view follows the flow when it advances
const viewStep = ref<number>(1);
watch(() => flow.step, (step) => (viewStep.value = step));

onMounted(() => flow.init());
</script>

<template>
  <div :class="['flow', { 'with-details': showDetails }]">
    <div class="main">
      <h1>{{ flow.bundleTitle }}</h1>
      <Message v-if="flow.error && flow.step !== 3" severity="error" :closable="false" class="error">{{ flow.error }}</Message>

      <Stepper :value="viewStep" @update:value="(v) => (viewStep = Number(v))">
        <StepList>
          <Step v-for="s in STEPS" :key="s.step" :value="s.step" :disabled="s.step > flow.step">{{ s.title }}</Step>
        </StepList>
        <StepPanels>
          <StepPanel :value="1"><StepPrepare :flow="flow" /></StepPanel>
          <StepPanel :value="2"><StepOffer :flow="flow" /></StepPanel>
          <StepPanel :value="3"><StepRelease :flow="flow" /></StepPanel>
          <StepPanel :value="4"><StepResult :flow="flow" /></StepPanel>
        </StepPanels>
      </Stepper>
    </div>

    <aside v-if="showDetails" class="details" aria-label="Technical details">
      <DebugPanels :flow="flow" />
    </aside>
  </div>
</template>

<style scoped>
.flow { display: grid; gap: 2rem; grid-template-columns: minmax(0, 1fr); }
.flow.with-details { grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); }
.main, .details { min-width: 0; }
.main h1 { margin: 0 0 1rem; font-size: 1.75rem; }
.error { margin-bottom: 1rem; }
/* on a phone only the active step shows its title */
@media (max-width: 600px) { .main :deep(.p-step:not(.p-step-active) .p-step-title) { display: none; } }
@media (max-width: 1100px) { .flow.with-details { grid-template-columns: minmax(0, 1fr); } }
</style>
