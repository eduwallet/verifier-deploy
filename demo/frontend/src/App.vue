<script setup lang="ts">
import ToggleSwitch from 'primevue/toggleswitch';
import { showDetails } from '@/utils/settings';
</script>

<template>
  <header class="top">
    <router-link to="/" class="brand">eduWallet demo</router-link>
    <span class="env">development</span>
    <label class="toggle">
      <span>Technical details</span>
      <ToggleSwitch v-model="showDetails" input-id="details-toggle" />
    </label>
  </header>
  <main class="page">
    <router-view />
  </main>
</template>

<style>
body { margin: 0; background: var(--p-content-background); color: var(--p-text-color); font-family: var(--p-font-family, system-ui, sans-serif); }
:root { color-scheme: light dark; }
code { font-size: .9em; }
</style>

<style scoped>
.top { display: flex; align-items: center; gap: 1rem; padding: .75rem 1.5rem; border-bottom: 1px solid var(--p-content-border-color); position: sticky; top: 0; background: var(--p-content-background); z-index: 10; }
.brand { font-weight: 700; font-size: 1.1rem; color: inherit; text-decoration: none; }
.env { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; padding: .15rem .5rem; border-radius: 1rem; background: var(--p-orange-100, #ffedd5); color: var(--p-orange-800, #9a3412); }
.toggle { margin-left: auto; display: flex; align-items: center; gap: .5rem; font-size: .9rem; }
.page { padding: 1.5rem; max-width: 100rem; margin: 0 auto; }
@media (max-width: 600px) { .top { padding: .75rem 1rem; flex-wrap: wrap; } .page { padding: 1rem; } }
</style>
