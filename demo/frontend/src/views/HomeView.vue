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
      <Card>
        <template #title>Multiple credentials</template>
        <template #content>
          <p>
            Issue two generic credentials and one deferred credential with a single credential offer. The generic
            credentials arrive at once, the deferred one is released by the back-office afterwards.
          </p>
        </template>
        <template #footer>
          <router-link to="/multiple"><Button label="Start" icon="pi pi-arrow-right" icon-pos="right" as="span" /></router-link>
        </template>
      </Card>
      <Card>
        <template #title>Presentation during issuance</template>
        <template #content>
          <p>
            Receive a credential after presenting an eduID. First issue an eduID if your wallet has none, then
            the issuer asks your wallet for it and fills the new credential with the presented name and e-mail address.
          </p>
        </template>
        <template #footer>
          <router-link to="/presentation"><Button label="Start" icon="pi pi-arrow-right" icon-pos="right" as="span" /></router-link>
        </template>
      </Card>
    </div>
  </div>
</template>

<style scoped>
.home h1 { margin-top: 0; }
.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
</style>
