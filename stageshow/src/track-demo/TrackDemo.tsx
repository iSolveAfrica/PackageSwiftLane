import { Badge, Collapse, Count, Icon, LockScreen, Notification, Phone, Roll, Stage, States, Bar, Text, Window, WALLPAPER, type Act } from '../../kit'
import '../swiftlane-look.css'
import './track-demo.css'

/**
 * "Every parcel, every stage": the public tracking page in a browser window,
 * a phone straddling its edge. One thing happens: a scan at the London hub
 * arrives, the journey rail advances a stage, the figure rolls, and the same
 * update lands on the customer's lock screen.
 *
 * Gold: examples/device-pair (window pinned top-left off the bottom, phone
 * straddling the right edge, present from frame one, LockScreen stack="top").
 * Changed: the dashboard became the tracking page's header band and journey
 * card; the still gained a story (system story, no cursor) that plays once
 * and holds, since a journey never rewinds.
 *
 * Real words: the tracking number, the status (twice: the badge and the rail,
 * one fact said where the product says it), "Stage n of 16", the percentage,
 * three stage labels around the current one, and the notification.
 */

type S = {
  /** The stage the shipment has reached, 1-based. 8 = Arrived in London. */
  stage: 8 | 9
  notified: boolean
}

const CODE = 'SWL-1000482'
const TOTAL = 16

// Stages 6..11 of the air lane (helpers/air_journey.php). Rows the eye lands
// on carry words; the rest are bars.
const RAIL: { n: number; label?: string; w: number }[] = [
  { n: 6, w: 84 },
  { n: 7, label: 'In Transit to London', w: 0 },
  { n: 8, label: 'Arrived in London', w: 0 },
  { n: 9, label: 'Processing at London Hub', w: 0 },
  { n: 10, label: 'Scheduled for Accra', w: 0 },
  { n: 11, w: 96 },
  { n: 12, w: 78 },
]

const LABEL: Record<S['stage'], string> = { 8: 'Arrived in London', 9: 'Processing at London Hub' }
const NEXT: Record<S['stage'], string> = { 8: 'Processing at London Hub', 9: 'Scheduled for Accra' }
const percent = (stage: number) => Math.round(((stage - 1) / (TOTAL - 1)) * 100)

const initial: S = { stage: 8, notified: false }
const POSTER: S = { stage: 9, notified: true }

async function script(a: Act<S>) {
  // Open on the finished page, long enough to read where the parcel is
  await a.wait(1600)
  // The scan arrives: the rail, the figure and the badge move together,
  // the figure a beat behind the marker that caused it
  await a.set({ stage: 9 })
  await a.mark('update')
  await a.wait(1500)
  // The consequence lands somewhere else: the customer's phone
  await a.set({ notified: true })
  await a.mark('notify')
  await a.wait(2600)
  // play="once": the page holds here; Replay in the host starts it again
}

export interface TrackDemoProps {
  /** The real page address, shown in the browser bar. */
  url?: string
  /** The product's name, as the notification's app title. */
  name?: string
  /** The product's real mark (the site favicon) for the notification. */
  icon?: string
}

export function TrackDemo({ url = 'swiftlane.example/track.php', name = 'Swift Lane', icon }: TrackDemoProps) {
  return (
    <Stage<S>
      width={680}
      height={400}
      compact={{ below: 600, width: 358, height: 352 }}
      initial={initial}
      poster={POSTER}
      script={script}
      play="once"
      className="swl-stageshow td"
    >
      {(s, _rect, layout) => (
        <>
          <Window url={url} className="td-window">
            {/* The tracking page's header band, as views/track.php draws it */}
            <div className="td-head">
              <div className="td-head__left">
                <Bar w={72} h={5} tone={2} className="td-head__eyebrow" />
                <Text className="td-code">{CODE}</Text>
                <div className="td-tags">
                  <span className="td-status">
                    <span className="td-status__dot" />
                    <Roll className="td-status__text">{LABEL[s.stage]}</Roll>
                  </span>
                  <span className="td-stagepill">
                    <span>Stage</span>
                    <Count value={s.stage} duration={500} />
                    <span>of {TOTAL}</span>
                  </span>
                </div>
              </div>
            </div>

            {/* The journey card: summary, then the rail */}
            <div className="td-card">
              <div className="td-progress">
                <div className="td-progress__row">
                  <div className="td-progress__now">
                    <span className="td-progress__eyebrow">
                      <span>Stage</span>
                      <Count value={s.stage} duration={500} />
                      <span>of {TOTAL}</span>
                      <span className="td-progress__sep" />
                      <Count value={percent(s.stage)} format={(n) => `${Math.round(n)}%`} duration={800} />
                      <span>of journey</span>
                    </span>
                    <Roll className="td-progress__label">{LABEL[s.stage]}</Roll>
                  </div>
                </div>
                <div className="td-progress__bar">
                  <span className="td-progress__fill" style={{ width: `${percent(s.stage)}%` }} />
                </div>
                <div className="td-progress__meta">
                  <span className="td-progress__next">
                    Next: <Roll delay={220} className="td-progress__nextv">{NEXT[s.stage]}</Roll>
                  </span>
                </div>
              </div>

              <div className="td-rail">
                {RAIL.map((r) => {
                  const state = r.n < s.stage ? 'done' : r.n === s.stage ? 'current' : 'next'
                  return (
                    <div key={r.n} className="td-stage" data-state={state}>
                      <span className="td-stage__dot">
                        <States
                          value={state === 'done' ? 'check' : 'mark'}
                          states={{ check: <Icon name="check" size={12} stroke={2.5} />, mark: <span className="td-stage__pip" /> }}
                        />
                      </span>
                      <span className="td-stage__num">{r.n}.</span>
                      {r.label ? <Text className="td-stage__label">{r.label}</Text> : <Bar w={r.w} h={7} tone={2} className="td-stage__bar" />}
                      <span className="td-now" data-show={state === 'current' || undefined}>
                        Current
                      </span>
                    </div>
                  )
                })}
              </div>
            </div>
          </Window>

          <Phone width={236} frame="pro" dark wallpaper={WALLPAPER} className="td-phone">
            <LockScreen size="satellite" stack="top" clock={layout === 'wide'}>
              <Collapse open={s.notified} reveal="drop">
                <Notification
                  icon={icon ? <img className="td-appicon" src={icon} alt="" /> : <Icon name="bell" filled size={20} />}
                  title={name}
                  body={`${CODE} is being processed at the London hub.`}
                />
              </Collapse>
            </LockScreen>
          </Phone>
        </>
      )}
    </Stage>
  )
}

export default TrackDemo
