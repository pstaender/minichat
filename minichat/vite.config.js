import { svelte } from '@sveltejs/vite-plugin-svelte'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig } from 'vite'
import Icons from 'unplugin-icons/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [tailwindcss(), svelte(), Icons({ compiler: 'svelte' })],
  server: {
    proxy: {
      '/api': {
        target: 'http://localhost:8811',
        changeOrigin: false,
      },
    },
  },
})
