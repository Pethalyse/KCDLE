import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  build: {
    sourcemap: false,    
    minify: 'esbuild',
    terserOptions: undefined,
  },
  esbuild: {
    drop: ['console', 'debugger']
  }
})
