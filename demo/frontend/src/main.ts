import { createApp } from 'vue';
import { createRouter, createWebHashHistory } from 'vue-router';
import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';
import 'primeicons/primeicons.css';
import App from './App.vue';
import HomeView from './views/HomeView.vue';
import DeferredFlow from './flows/deferred/DeferredFlow.vue';
import MultipleFlow from './flows/multiple/MultipleFlow.vue';
import PresentationFlow from './flows/presentation/PresentationFlow.vue';

// hash based routing: the Apache docroot has no fallback to index.html for deep links
const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: '/', component: HomeView },
    { path: '/deferred', component: DeferredFlow },
    { path: '/multiple', component: MultipleFlow },
    { path: '/presentation', component: PresentationFlow },
  ],
});

createApp(App)
  .use(router)
  .use(PrimeVue, {
    theme: {
      preset: Aura,
      // follow the operating system setting
      options: { darkModeSelector: 'system', cssLayer: false },
    },
  })
  .mount('#app');
