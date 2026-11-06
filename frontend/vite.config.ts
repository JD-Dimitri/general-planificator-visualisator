import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// En développement, le front tourne sur 5173 et l'API Symfony sur 8000.
// Le proxy les met sur la même origine : le cookie de session fonctionne
// comme en production, sans configuration CORS particulière.
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: process.env.VITE_API_TARGET ?? 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: 'dist',
    sourcemap: true,
  },
})
