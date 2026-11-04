<script setup lang="ts">
import { ref, watch } from 'vue';
import QRCode from 'qrcode';

const props = defineProps<{ text: string }>();
const image = ref<string | null>(null);
const failed = ref(false);

watch(
  () => props.text,
  async (text) => {
    failed.value = false;
    try {
      // the QR is rendered in the browser: the offer URI is not sent to a third party
      image.value = await QRCode.toDataURL(text, { margin: 2, width: 288, errorCorrectionLevel: 'M' });
    } catch {
      image.value = null;
      failed.value = true;
    }
  },
  { immediate: true },
);
</script>

<template>
  <div class="qr">
    <img v-if="image" :src="image" alt="QR code of the credential offer" width="288" height="288" />
    <p v-else-if="failed">The QR code could not be created. Use the offer URI instead.</p>
  </div>
</template>

<style scoped>
.qr img { display: block; max-width: 100%; height: auto; border-radius: .5rem; background: #fff; }
</style>
