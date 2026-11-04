<script setup lang="ts">
import Button from 'primevue/button';
import Card from 'primevue/card';
</script>

<template>
  <div class="home">
    <h1>Credential flows</h1>
    <p>
      Each flow runs against the live development issuer and shows what happens technically at every step.
    </p>
    <div class="cards">
      <Card>
        <template #title>Deferred credential</template>
        <template #content>
          <p>
            Issue a credential that is not released immediately. The wallet requests it, the back-office
            approves it, and the wallet collects it afterwards.
          </p>
        </template>
        <template #footer>
          <router-link to="/deferred"><Button label="Start" icon="pi pi-arrow-right" icon-pos="right" as="span" /></router-link>
        </template>
      </Card>
    </div>
  </div>
</template>

<style scoped>
.home h1 { margin-top: 0; }
.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
</style>
