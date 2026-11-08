import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { resolve } from 'node:path'

// Dev: `npm run dev` serves index.html (the demo on a plain page, the URL
// scripts/film.mjs in the skill folder films). Build: one IIFE bundle plus one
// stylesheet into ../assets/stageshow/, loaded by views/tracking.php with the
// repo's cdp_asset() cache-busting. React is bundled; the page has no module
// loader and no Node at runtime.
// In the build the kit's CSS imports become empty: the page loads the kit's
// stylesheets from assets/stageshow/kit/ (scripts/copy-kit-css.mjs) so they
// keep their own origin. In dev Vite serves every file as it is.
const kitCssOut = {
  name: 'swl-kit-css-out',
  apply: 'build' as const,
  enforce: 'pre' as const,
  load(id: string) {
    return /[\\/]kit[\\/].*\.css(\?.*)?$/.test(id) ? '' : null
  },
}

export default defineConfig(({ command }) => ({
  plugins: [react(), kitCssOut],
  // public/ holds the dev page's favicon only; the build copies nothing from it
  publicDir: command === 'serve' ? 'public' : false,
  build: {
    outDir: resolve(__dirname, '../assets/stageshow'),
    emptyOutDir: true,
    cssCodeSplit: false,
    sourcemap: false,
    lib: {
      entry: resolve(__dirname, 'src/embed.tsx'),
      name: 'SwiftLaneStageshow',
      formats: ['iife'],
      fileName: () => 'track-demo.js',
    },
    rollupOptions: {
      output: { assetFileNames: 'track-demo[extname]' },
    },
  },
  define: { 'process.env.NODE_ENV': JSON.stringify('production') },
}))
