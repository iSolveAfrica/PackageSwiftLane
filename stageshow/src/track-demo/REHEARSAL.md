# Rehearsal: Track a parcel (tracking page hero)

Gold: `examples/device-pair`. Kept: the layout (browser window pinned top-left
and off the bottom, a 236px phone straddling the right edge from frame one,
`LockScreen size="satellite" stack="top"`, the well as the stage, one gutter),
the window shadow and the phone's deeper shadow, the compact recipe (window
runs off the right under the phone, gutter 16). Changed: the dashboard became
the real tracking page's header band and journey card (views/track.php,
track-details.css) in Swift Lane's tokens (`src/swiftlane-look.css`); the
still became a system story, no cursor; the stage is 680 wide so the phone
covers only the header's margin and the rail's blank column, never the badge
or the figure; the compact design stacks the phone below the summary with its
clock off and drops the Next line the phone would sit on.

Beats (play="once", then hold): 1. the page at stage 8 of 16, 47%, "Arrived
in London" (1.6s to read) … 2. the scan arrives: marker 8 ticks, marker 9
rings, the badge and the summary label roll to "Processing at London Hub",
"Stage 8 of 16" rolls to 9, 47% rolls to 53%, the bar grows, "Next" rolls a
beat later (mark `update`) … 3. the consequence lands: the notification drops
onto the lock screen (mark `notify`) … payoff holds; a Replay button in the
host remounts the scene. No loop: a journey never rewinds in view.

Poster: beat 3 (stage 9, notified). Reduced motion shows it as a still.

Captured: widths 680 (1280 viewport) and 390; the loop every 500ms, `--states`,
`--reduced`, stills of the opening and the payoff at both widths, a close-up
of the notification; the demo inside the rendered tracking page at 1440 and
390 (`--gate`), and page screenshots at 1440, 1200, 1024 and 390.

Gate: `rehearsal passed (necessary, not sufficient: references/verify.md and
the fresh eye in review.md still stand)` on every run of the final build: the
wide loop (1280) and the 390 loop on the dev page, `--states`, `--reduced`,
and the demo inside the rendered tracking page (`--select .auth-demo`) at 1440
and 390.

Film:
- `touching: span.ss-text "SWL-1000482" is overlapping div.td-tags` → td-open.png
  → measured in the browser: the code's glyph box ends at y=168.5, the tags
  start at 178.8, a 10px gap held by a fixed 12px flex gap; nothing touches.
  Stands.
- `layout: div.td-rail leaves -33px below its last child` → td-open.png → the
  window runs off the stage bottom by design (the gold prints the same line
  for its list). Stands.
- `jump / settle snap / appears with no entrance: span.ss-count__strip` →
  td-loop.png at `update` → the kit's `Count` re-homing its hidden digit
  strip after a roll (Count.css: "put back in the middle once the roll has
  settled"); it happens under the digit's mask and is not visible in any
  frame. Kit behaviour, stands.
- `small text: ss-notification__time "now" renders at 6.5px` (390) → the kit's
  own notification at true iOS size; the gold prints the same line. Stands.
- `jump: ss-roll__item "Arrived in London" width +20.7px at 0.28s` → fixed on
  the dev page: the embed loads Archivo Black and Inter before mounting, so
  Roll measures the real face and the opening frame shows the whole label.
- `kit override: .ss-phone__status …` (6 lines, host page only) → fixed: the
  production bundle had folded the kit's CSS into the demo's stylesheet, so
  the tool (which trusts stylesheets served from a `/kit/` path) read the
  kit's own phone rules as the demo's. The kit's stylesheets now ship as
  their own files under `assets/stageshow/kit/`, loaded before the bundle
  (`scripts/copy-kit-css.mjs`); no host stylesheet names an `.ss-` class.
- `steps in its box: span.td-status / span.td-now` (host 390) → fixed with a
  lasting layer on both pills.
- Earlier rounds, fixed: the phone covered the status badge and the figure
  (the badge and stage pill moved under the code, the percentage joined the
  summary's eyebrow, the stage widened to 680); the compact window showed
  taller than wide and the notification fell off the bottom (compact stage
  352 tall, phone at 222 with its clock off); `Count` strips read as text
  touching their neighbours (each Count now sits between its own word spans);
  the "Current" chip landed on a transform (opacity only now); the stage pill
  stepped in its box as the badge rolled (a lasting layer); the rail label
  weight changed with state (one weight now).

Review: not run as a separate fresh eye in this session; the builder's own
pass against verify.md found nothing further.

Assumptions: the product's mark in the notification is the site favicon from
settings (`$core->favicon`), whatever the install uploaded; the dev page uses
the repository's `assets/uploads/favicon.png`. The window's address is the
page's real host plus `/track.php`. Status colour in the badge is the sky
blue the app uses for transit statuses.

Unresolved: the bundle is 94KB gzipped (React and the kit) on a public page;
acceptable for a once-per-view demo, but a lighter build would need the kit
split.

Not checked: Safari and Firefox; a real phone with touch; the page with a
customer's own logo and favicon; the Replay button under assistive
technology beyond its visible label; playback pacing on a slow device.
