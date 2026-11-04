import { ref, watch } from 'vue';

const KEY = 'demo.showDetails';

function load(): boolean {
  try {
    return localStorage.getItem(KEY) !== 'false';
  } catch {
    return true; // storage can be unavailable
  }
}

/** Show the technical detail panels. The setting is only a convenience of this browser. */
export const showDetails = ref(load());

watch(showDetails, (value) => {
  try {
    localStorage.setItem(KEY, String(value));
  } catch {
    // ignore
  }
});
