import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { viteSingleFile } from 'vite-plugin-singlefile'

// Tout part dans un seul fichier HTML : code, styles et images d'ambiance.
// C'est la condition pour que GPV s'ouvre d'un double-clic et fonctionne
// sans réseau, sans serveur et sans installation.
export default defineConfig({
  plugins: [react(), viteSingleFile()],
  build: {
    outDir: 'dist',
    // Aucune limite : les images d'ambiance deviennent des URI de données
    // plutôt que des fichiers à côté.
    assetsInlineLimit: 100 * 1024 * 1024,
    cssCodeSplit: false,
    chunkSizeWarningLimit: 8000,
    reportCompressedSize: false,
  },
})
