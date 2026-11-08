// Ship the kit's stylesheets as their own files under assets/stageshow/kit/,
// loaded by the page before the demo bundle. The bundle leaves them out (see
// vite.config.ts), so the kit's CSS stays the kit's: one layer, one origin,
// and the skill's film tool can tell it from the demo's own rules.
import { cpSync, mkdirSync, readdirSync, rmSync, statSync } from 'node:fs'
import { dirname, join, relative, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const src = join(root, 'kit')
const out = resolve(root, '../assets/stageshow/kit')

rmSync(out, { recursive: true, force: true })
let n = 0
const walk = (dir) => {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name)
    if (statSync(p).isDirectory()) walk(p)
    else if (name.endsWith('.css')) {
      const to = join(out, relative(src, p))
      mkdirSync(dirname(to), { recursive: true })
      cpSync(p, to)
      n++
    }
  }
}
walk(src)
console.log(`kit css: ${n} files -> ${relative(root, out)}`)
