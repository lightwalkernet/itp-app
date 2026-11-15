/*
 * The field of light (design/direction.md "Light"; architecture/field-persistence.md).
 *
 * Draws everything that moves — the soft light forms, the horizon glow, the rays born at the
 * horizon, the rising motes and the night stars — on two canvases that live inside
 * @persist('field') in the layout. Nothing here is a CSS animation, because Livewire re-inserts
 * a persisted element on every navigation and that would restart one; canvas state carries on.
 *
 * It reads every colour from computed CSS (tokens.css), so the phase palette and its 2.4 s
 * transition belong to CSS alone. It writes exactly one thing: html[data-phase], from the clock.
 *
 * Still mode (html[data-still]) and prefers-reduced-motion draw a single frame and stop. In a
 * white room (html[data-room="white"]) nothing is drawn at all.
 */

const FPS = 30
const RAYS = 44
const MOTES = 26
const STARS = 140

const root = document.documentElement

export const phaseFor = hour =>
    (hour >= 5 && hour <= 8) ? 'dawn'
    : (hour >= 9 && hour <= 16) ? 'day'
    : (hour >= 17 && hour <= 20) ? 'dusk'
    : 'night'

/* A tiny seeded generator, so the field looks the same on every launch. */
function seeded(seed) {
    let s = seed
    return () => (s = (s * 48271) % 2147483647) / 2147483647
}

/* Resolve any CSS colour to [r, g, b] via a probe canvas. */
const probe = document.createElement('canvas').getContext('2d')
function rgb(css) {
    probe.fillStyle = '#000'
    probe.fillStyle = css
    const v = probe.fillStyle
    if (v[0] === '#') return [parseInt(v.slice(1, 3), 16), parseInt(v.slice(3, 5), 16), parseInt(v.slice(5, 7), 16)]
    const m = v.match(/[\d.]+/g)
    return m ? [+m[0], +m[1], +m[2]] : [255, 255, 255]
}
const rgba = (c, a) => `rgba(${c[0]},${c[1]},${c[2]},${a})`

export function boot(field) {
    if (!field || field.dataset.booted) return
    field.dataset.booted = 'true'

    const lightCanvas = field.querySelector('.field__canvas--light')
    const canvas = field.querySelector('.field__canvas:not(.field__canvas--light)')
    const lctx = lightCanvas.getContext('2d')
    const ctx = canvas.getContext('2d')

    const rnd = seeded(23)
    const rays = Array.from({ length: RAYS }, () => ({
        x: rnd(), w: 1.5 + rnd() * 4, h: 0.3 + rnd() * 0.65, base: 0.12 + rnd() * 0.5,
        period: 5 + rnd() * 14, phase: rnd() * Math.PI * 2, drift: (rnd() - 0.5) * 0.0025, mix: rnd(),
    }))
    const spawnMote = anywhere => ({
        x: rnd(), y: anywhere ? 0.3 + rnd() * 0.7 : 0.95 + rnd() * 0.1, r: 2 + rnd() * 6,
        vy: 0.012 + rnd() * 0.03, wob: 0.5 + rnd() * 1.2, wphase: rnd() * Math.PI * 2,
        life: 0, span: 9 + rnd() * 12, base: 0.35 + rnd() * 0.65,
    })
    const motes = Array.from({ length: MOTES }, () => spawnMote(true))
    const stars = Array.from({ length: STARS }, (_, i) => {
        const kind = i % 9 === 0 ? 'bright' : i % 3 === 0 ? 'near' : 'far'
        const period = kind === 'far' ? 8 + rnd() * 10 : kind === 'near' ? 3.5 + rnd() * 4 : 5 + rnd() * 5
        return { kind, x: rnd(), y: rnd() * 0.9, period, phase: rnd() * Math.PI * 2 }
    })
    const blobs = [
        { cx: 0.25, cy: 0.25, r: 0.62, ax: 0.14, ay: 0.10, period: 46, slot: 'color' },
        { cx: 0.85, cy: 0.50, r: 0.52, ax: -0.16, ay: -0.08, period: 38, slot: 'borderTopColor' },
        { cx: 0.35, cy: 1.00, r: 0.42, ax: 0.18, ay: -0.14, period: 31, slot: 'outlineColor' },
        { cx: 0.80, cy: 0.85, r: 0.32, ax: -0.12, ay: -0.18, period: 23, slot: 'textDecorationColor' },
    ]

    let W = 0, H = 0, vmax = 0
    function resize() {
        const dpr = Math.min(1.5, window.devicePixelRatio || 1)
        W = field.clientWidth
        H = field.clientHeight
        vmax = Math.max(W, H)
        for (const [c, x] of [[canvas, ctx], [lightCanvas, lctx]]) {
            c.width = Math.round(W * dpr)
            c.height = Math.round(H * dpr)
            x.setTransform(dpr, 0, 0, dpr, 0, 0)
        }
        needsFrame = true
    }

    const num = name => parseFloat(getComputedStyle(root).getPropertyValue(name)) || 0
    const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)')
    const still = () => root.hasAttribute('data-still') || reduceMotion.matches
    const white = () => root.dataset.room === 'white'

    let last = 0, acc = 0, needsFrame = true, running = false, settleUntil = 0

    function drawLight(t) {
        const cs = getComputedStyle(lightCanvas)
        const op = num('--light-opacity')
        lctx.clearRect(0, 0, W, H)
        lctx.globalCompositeOperation = 'lighter'
        for (const b of blobs) {
            const k = Math.sin(t / b.period * Math.PI * 2)
            const x = (b.cx + b.ax * 0.5 * (1 + k)) * W
            const y = (b.cy + b.ay * 0.5 * (1 + k)) * H
            const r = b.r * vmax * (1 + 0.08 * k)
            const c = rgb(cs[b.slot])
            const g = lctx.createRadialGradient(x, y, 0, x, y, r)
            g.addColorStop(0, rgba(c, 0.85 * op))
            g.addColorStop(0.45, rgba(c, 0.32 * op))
            g.addColorStop(1, rgba(c, 0))
            lctx.fillStyle = g
            lctx.fillRect(x - r, y - r, r * 2, r * 2)
        }
        // the horizon glow
        const hc = rgb(cs.columnRuleColor)
        const hg = lctx.createRadialGradient(W / 2, H * 1.15, 0, W / 2, H * 1.15, H * 0.75)
        const hop = num('--ray-opacity') * 0.9
        hg.addColorStop(0, rgba(hc, 0.9 * hop))
        hg.addColorStop(0.5, rgba(hc, 0.25 * hop))
        hg.addColorStop(1, rgba(hc, 0))
        lctx.fillStyle = hg
        lctx.fillRect(0, H * 0.3, W, H * 0.7)
        lctx.globalCompositeOperation = 'source-over'
    }

    function drawRays(t, step) {
        const cs = getComputedStyle(canvas)
        const warm = rgb(cs.color), cool = rgb(cs.borderTopColor), mote = rgb(cs.outlineColor)
        const rayOp = num('--ray-opacity'), moteOp = num('--mote-opacity'), starOp = num('--stars-opacity')
        ctx.clearRect(0, 0, W, H)
        ctx.globalCompositeOperation = 'lighter'

        for (const r of rays) {
            r.x = (r.x + r.drift * step + 1) % 1
            const breath = 0.5 + 0.5 * Math.sin(t / r.period * Math.PI * 2 + r.phase)
            const a = r.base * (0.25 + 0.75 * breath) * rayOp
            if (a < 0.005) continue
            const x = r.x * W, top = H - r.h * H
            const g = ctx.createLinearGradient(0, H, 0, top)
            g.addColorStop(0, rgba(warm, 1))
            g.addColorStop(0.25 + 0.3 * (1 - r.mix), rgba(cool, 0.8))
            g.addColorStop(1, rgba(cool, 0))
            ctx.fillStyle = g
            ctx.globalAlpha = a
            ctx.fillRect(x - r.w / 2, top, r.w, H - top)
            ctx.globalAlpha = a * 0.35
            ctx.fillRect(x - r.w * 1.5, top, r.w * 3, H - top)
            ctx.globalAlpha = a * 0.12
            ctx.fillRect(x - r.w * 3.5, top, r.w * 7, H - top)
        }

        for (let i = 0; i < motes.length; i++) {
            let m = motes[i]
            m.life += step
            m.y -= m.vy * step
            if (m.life > m.span || m.y < -0.05) motes[i] = m = spawnMote(false)
            const env = Math.sin(Math.min(1, m.life / m.span) * Math.PI)
            const a = m.base * env * moteOp * (0.6 + 0.4 * Math.sin(t * m.wob + m.wphase))
            if (a < 0.005) continue
            const x = (m.x + 0.02 * Math.sin(t * m.wob * 0.5 + m.wphase)) * W, y = m.y * H
            const g = ctx.createRadialGradient(x, y, 0, x, y, m.r * 2.4)
            g.addColorStop(0, rgba(mote, 1))
            g.addColorStop(0.35, rgba(mote, 0.55))
            g.addColorStop(1, rgba(mote, 0))
            ctx.globalAlpha = a
            ctx.fillStyle = g
            ctx.beginPath()
            ctx.arc(x, y, m.r * 2.4, 0, Math.PI * 2)
            ctx.fill()
        }

        if (starOp > 0.01) {
            ctx.globalCompositeOperation = 'source-over'
            for (const s of stars) {
                const k = 0.5 + 0.5 * Math.sin(t / s.period * Math.PI * 2 + s.phase)
                const fade = s.y < 0.55 ? 1 : Math.max(0, 1 - (s.y - 0.55) / 0.35)   // stars thin out toward the horizon
                let a, r
                if (s.kind === 'far') { a = 0.04 + 0.56 * k; r = 0.8 }
                else if (s.kind === 'near') { a = 0.12 + 0.88 * k; r = 1.1 }
                else { a = k * k; r = 1.4 + 0.5 * k }
                a *= fade * starOp
                if (a < 0.01) continue
                const x = s.x * W, y = s.y * H
                ctx.globalAlpha = a
                ctx.fillStyle = '#fff'
                ctx.beginPath()
                ctx.arc(x, y, r, 0, Math.PI * 2)
                ctx.fill()
                if (s.kind === 'bright') {
                    const g = ctx.createRadialGradient(x, y, 0, x, y, 6)
                    g.addColorStop(0, 'rgba(255,255,255,0.5)')
                    g.addColorStop(1, 'rgba(255,255,255,0)')
                    ctx.fillStyle = g
                    ctx.fillRect(x - 6, y - 6, 12, 12)
                }
            }
        }
        ctx.globalAlpha = 1
        ctx.globalCompositeOperation = 'source-over'
    }

    function frame(ts) {
        running = false
        if (white()) return               // nothing to see; wake() restarts us
        const t = ts / 1000
        const dt = last ? Math.min(0.1, (ts - last) / 1000) : 0
        last = ts
        acc += dt
        const stillNow = still()
        if (stillNow && !needsFrame && ts > settleUntil) return
        if (acc >= 1 / FPS || needsFrame) {
            const step = stillNow ? 0 : acc
            acc = 0
            needsFrame = false
            drawLight(t)
            drawRays(t, step)
        }
        schedule()
    }

    function schedule() {
        if (running) return
        running = true
        requestAnimationFrame(frame)
    }

    /* Draw again (a phase transition takes 2.4 s, so keep redrawing until the colours settle). */
    function wake() {
        needsFrame = true
        settleUntil = performance.now() + 2600
        last = 0
        schedule()
    }

    new MutationObserver(wake).observe(root, { attributes: true, attributeFilter: ['data-phase', 'data-room', 'data-still'] })
    reduceMotion.addEventListener?.('change', wake)
    addEventListener('resize', resize)
    document.addEventListener('visibilitychange', () => { if (!document.hidden) wake() })

    resize()
    wake()
}

/*
 * The clock: set the phase now and at the top of every minute. A page may lock it (the design
 * page's clock). Livewire's navigate swap copies the new document's <html> attributes over the
 * live ones (see transitions.js), which drops data-phase and data-still, so both are re-applied
 * on livewire:navigated — synchronously, before the next paint, so no transition fires.
 */
let lockedPhase = null
const STILL_KEY = 'itp.still'

function tick() {
    const phase = lockedPhase ?? phaseFor(new Date().getHours())
    if (root.dataset.phase !== phase) root.dataset.phase = phase
    let still = false
    try { still = localStorage.getItem(STILL_KEY) === '1' } catch { /* private mode */ }
    if (still !== root.hasAttribute('data-still')) root.toggleAttribute('data-still', still)
}

export function setPhase(phase) {
    lockedPhase = phase
    tick()
}

export function unlockPhase() {
    lockedPhase = null
    tick()
}

export function setStill(on) {
    try { localStorage.setItem(STILL_KEY, on ? '1' : '0') } catch { /* private mode */ }
    tick()
}

export function startClock() {
    tick()
    const toMinute = 60000 - (Date.now() % 60000)
    setTimeout(() => { tick(); setInterval(tick, 60000) }, toMinute)
}

/* Boot against the persisted field in the layout. Idempotent: the field survives navigation, so this is a no-op after the first page. */
function bootFromDocument() {
    boot(document.querySelector('.field'))
}
startClock()
bootFromDocument()
document.addEventListener('livewire:navigated', () => { tick(); bootFromDocument() })

window.itpField = { boot, phaseFor, setPhase, unlockPhase, setStill }
