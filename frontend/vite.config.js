import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'node:path';
export default defineConfig({
  plugins: [tailwindcss()],
  server: { proxy: { '/api': 'http://127.0.0.1:8080' } },
  build: { rollupOptions: { input: { form: resolve(import.meta.dirname, 'index.html'), admin: resolve(import.meta.dirname, 'admin.html') } } },
});
