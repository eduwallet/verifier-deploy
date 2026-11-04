import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

// The build goes into the Apache docroot of the back-end, next to index.php and .htaccess,
// which is why the output directory is not emptied.
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  build: {
    outDir: '../app/html',
    emptyOutDir: false,
    assetsDir: 'assets',
  },
  server: {
    // the PHP container of docker/docker-compose.yml
    proxy: { '/api': 'http://localhost:8081' },
  },
  test: {
    environment: 'jsdom',
    include: ['src/**/*.test.ts'],
  },
});
