import { StrictMode, useState } from 'react'
import { createRoot } from 'react-dom/client'
import { TrackDemo } from './track-demo/TrackDemo'
import './host.css'

/**
 * Mounts a demo into every `[data-swl-demo]` element on the page:
 *
 *   <div data-swl-demo="track" data-url="host/track.php" data-name="Swift Lane"
 *        data-icon="assets/favicon.png"></div>
 *
 * The demo plays once in view and holds its payoff; the Replay button under
 * it remounts the scene at the story boundary (kit.md, Explicit replay).
 */
function Host({ el }: { el: HTMLElement }) {
  const [run, setRun] = useState(0)
  const d = el.dataset
  return (
    <div className="swl-demo-host">
      <TrackDemo key={run} url={d.url} name={d.name} icon={d.icon} />
      <button type="button" className="swl-demo-replay" onClick={() => setRun((n) => n + 1)}>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7" /><path d="M3 4v5h5" /></svg>
        {d.replay || 'Replay demo'}
      </button>
    </div>
  )
}

function mountAll() {
  document.querySelectorAll<HTMLElement>('[data-swl-demo]').forEach((el) => {
    if (el.dataset.mounted) return
    el.dataset.mounted = '1'
    createRoot(el).render(
      <StrictMode>
        <Host el={el} />
      </StrictMode>,
    )
  })
}

// Mount once the page's fonts are in: the kit measures text (Roll, Count) at
// mount, and a label measured in the fallback face is cut when Archivo Black
// and Inter arrive a frame later. The faces are asked for after the window's
// load event, when the page's font stylesheet (preloaded, then swapped in by
// views/inc/auth_head.php) has registered them; a font only loads once
// something uses it. Mount when they are in, or after 1.5s whatever happened.
function ready() {
  const fonts = (document as Document & { fonts?: FontFaceSet }).fonts
  if (!fonts || !fonts.load) return mountAll()
  const families = ['Archivo Black', 'Inter']
  const faces = ['400 22px "Archivo Black"', '600 13px "Inter"', '700 13px "Inter"', '800 13px "Inter"']
  const registered = () => {
    const seen = new Set<string>()
    fonts.forEach((f) => seen.add(f.family.replace(/["']/g, '')))
    return families.every((f) => seen.has(f))
  }
  const start = Date.now()
  let done = false
  const finish = () => {
    if (done) return
    done = true
    mountAll()
  }
  // The font stylesheet may still be applying when load fires; wait for the
  // faces to be registered, then for them to load, up to 1.5s in all.
  const tick = () => {
    if (done) return
    if (registered()) {
      Promise.all(faces.map((f) => fonts.load(f).catch(() => []))).then(finish, finish)
    } else if (Date.now() - start > 1500) {
      finish()
    } else {
      setTimeout(tick, 40)
    }
  }
  setTimeout(finish, 1500)
  tick()
}
if (document.readyState === 'complete') ready()
else window.addEventListener('load', ready, { once: true })
