# Swift Lane product demos (Stageshow)

Faux-product illustrations of Swift Lane built with the Stageshow kit that
lives in `.agents/skills/stageshow/` (see its `SKILL.md`). The kit is copied
into `kit/` here so the demos build without reaching into the skill folder.

## Demos

| Demo | Where it shows | Source |
| --- | --- | --- |
| Track a parcel | `tracking.php`, beside the tracking form (`views/tracking.php`, `data-swl-demo="track"`) | `src/track-demo/` |

Each demo folder carries its `REHEARSAL.md`: the gold example it was copied
from, the beats, what was filmed, the gate result and what was not checked.

## Build

```bash
cd stageshow && npm install
```

```bash
npm run build
```

`npm run build` writes `assets/stageshow/track-demo.js`,
`assets/stageshow/track-demo.css` (the demos' own styles) and
`assets/stageshow/kit/` (the kit's stylesheets, loaded first through
`kit/stageshow.css`), which the PHP pages load through `cdp_asset()`. The built files are committed, so the server needs no Node.
Rebuild and commit them after any change under `src/` or `kit/`.

## Develop and film

```bash
npm run dev
```

serves `index.html` (the demo on a plain page) at `http://127.0.0.1:5181/`.
Film it with the skill's tools from the skill folder:

```bash
cd .agents/skills/stageshow && npm install
```

```bash
CHROME_PATH=/path/to/chrome node scripts/film.mjs "http://127.0.0.1:5181/" --select .td --every 500 --count 16 --gate
```

```bash
CHROME_PATH=/path/to/chrome node scripts/film.mjs "http://127.0.0.1:5181/" --select .td --width 390 --every 500 --count 16 --gate
```

`--gate` must end `rehearsal passed`; the rules the lines enforce are in the
skill's `references/`.

## Embedding

```html
<link href="<?= cdp_asset('assets/stageshow/kit/stageshow.css') ?>" rel="stylesheet">
<link href="<?= cdp_asset('assets/stageshow/track-demo.css') ?>" rel="stylesheet">
<div data-swl-demo="track" data-url="host/track.php" data-name="Swift Lane" data-icon="assets/favicon.png" data-replay="Replay demo"></div>
<script src="<?= cdp_asset('assets/stageshow/track-demo.js') ?>" defer></script>
```

The demo plays once when it comes into view and holds its payoff (a journey
never rewinds); the Replay button the embed renders restarts it. Under
`prefers-reduced-motion` it shows the payoff as a still. `src/swiftlane-look.css`
maps the app's tokens onto the kit's, so every Swift Lane demo shares one look.
