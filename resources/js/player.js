/*
 * The player (player contract), ported from itpmobile's scripts/{words,breath,why}.js without
 * animejs, animate.css or the native audio plugin. Each frame works out from one clock what every
 * sentence and word should look like at that moment, and changes an attribute only when it
 * differs; CSS keyframes (components.css, "The player and the welcome") do the motion.
 *
 *   words    the clock is the voice's audio.currentTime, so pause, resume and restart stay in step
 *   breathe  the clock is the pacer's own (5 s in, 5 s out); each line's voice plays inside its
 *            in-breath, and its words follow that audio's currentTime
 *   why      no audio: one Why this device hasn't shown for this ITP, after 4 s
 *
 * The page renders the timings as data attributes (resources/views/components/player/words):
 *   [data-sentence][data-start][data-end]  data-state = hidden | in | out
 *   [data-word][data-start]                data-up once spoken (its blurred ghost lifts away)
 *
 * Phones only start audio from a tap, so everything begins from the big play button. Finishing or
 * stopping calls $wire.finish(), which answers with player-navigate.
 */

const BREATH = 5            // seconds per in- or out-breath (breath.js)
const LINE_DELAY = 0.25     // a line's voice starts this long into its in-breath
const LINE_LINGER = 2       // a line's words stay this long after its voice
const WHY_AFTER = 4000      // ms before the Why arrives (why.js)
const CONTROLS_AFTER = 1000 // ms after starting before the controls show

const seconds = (el, name) => parseFloat(el.dataset[name])

function setState(el, state) {
    if (el.dataset.state !== state) el.dataset.state = state
}

function setUp(el, up) {
    if (up === el.hasAttribute('data-up')) return
    up ? el.setAttribute('data-up', '') : el.removeAttribute('data-up')
}

function readSentences(root) {
    return [...root.querySelectorAll('[data-sentence]')].map(el => ({
        el,
        start: seconds(el, 'start'),
        end: seconds(el, 'end'),
        words: [...el.querySelectorAll('[data-word]')].map(word => ({ el: word, start: seconds(word, 'start') })),
    }))
}

function resetSentences(root) {
    root.querySelectorAll('[data-state]').forEach(el => setState(el, 'hidden'))
    root.querySelectorAll('[data-up]').forEach(el => el.removeAttribute('data-up'))
}

// Plays a sound from the start. A rejected play (no tap yet, a missing file) is never an error.
function play(audio, volume = 1) {
    if (!audio) return
    audio.volume = volume
    try { audio.currentTime = 0 } catch {}
    audio.play()?.catch(() => {})
}

// Inside the tap: let the element play later without one (iOS asks per element).
function unlock(audio) {
    if (!audio) return
    audio.muted = true
    const done = () => { audio.pause(); audio.muted = false }
    audio.play()?.then(done, done)
}

// A clock in seconds that can pause, for the pacer and for the moment after the voice has ended.
function pausableClock() {
    let base = 0
    let since = null
    return {
        now: () => since === null ? base : base + (performance.now() - since) / 1000,
        run() { if (since === null) since = performance.now() },
        pause() { if (since !== null) { base += (performance.now() - since) / 1000; since = null } },
        reset(at = 0) { base = at; since = null },
    }
}

const inOutCubic = p => p < 0.5 ? 4 * p ** 3 : 1 - (-2 * p + 2) ** 3 / 2

/*
 * The finish: back where the person came from (Player::finish). Back uses the page transitions'
 * real history.back() when it can; the welcome goes on to Today instead of back to itself.
 */
export function itpPlayerNavigate({ url, direction }) {
    if (direction === 'back' && window.pageTransitions) window.pageTransitions.back(url)
    else window.Livewire.navigate(url)
}

/*
 * What the player shares: the play button, pause, the frame loop and stopping when the page goes.
 */
function basePlayer() {
    return {
        started: false,
        failed: false,
        paused: false,
        controlsShown: false,
        stopping: false,
        frameId: null,
        timers: [],

        loop() {
            cancelAnimationFrame(this.frameId)
            const tick = () => {
                if (this.paused || this.stopping) return
                this.frame()
                this.frameId = requestAnimationFrame(tick)
            }
            this.frameId = requestAnimationFrame(tick)
        },

        later(ms, callback) {
            this.timers.push(setTimeout(callback, ms))
        },

        showControlsSoon() {
            this.controlsShown = false
            this.later(CONTROLS_AFTER, () => { this.controlsShown = true })
        },

        toggle() {
            this.paused ? this.resume() : this.pause()
        },

        // The browser wouldn't start the audio: without a tap, offer the play button again;
        // a file that can't load says so. A play cut short by a pause is neither.
        refused(error) {
            if (error?.name === 'AbortError') return
            this.halt()
            this.started = false
            if (error?.name !== 'NotAllowedError') this.failed = true
        },

        stop() {
            if (this.stopping) return
            this.halt()
            this.stopping = true
            this.$wire.finish()
        },

        // Silence everything; nothing may keep playing once the page is gone.
        halt() {
            cancelAnimationFrame(this.frameId)
            this.timers.forEach(clearTimeout)
            this.timers = []
            this.$root.querySelectorAll('audio').forEach(audio => audio.pause())
        },

        init() {
            // A page restored from Livewire's history cache keeps the attributes it had.
            resetSentences(this.$root)
            this.onNavigating = () => this.halt()
            document.addEventListener('livewire:navigating', this.onNavigating)
        },

        destroy() {
            this.halt()
            document.removeEventListener('livewire:navigating', this.onNavigating)
        },
    }
}

/*
 * The attunement and the Introduction (words.js). A sentence fades in from above half a second
 * before it is spoken, or when the one before it ends if the gap is under a second; each word's
 * ghost lifts away as it is spoken; the sentence falls away when it ends. One second after the
 * last sentence, it is over.
 */
export function itpWordsPlayer({ marksTheDay = false } = {}) {
    return {
        ...basePlayer(),
        sentences: [],
        finishAt: 0,
        tail: pausableClock(),
        ended: false,
        entered: false,

        init() {
            basePlayer().init.call(this)
            this.sentences = readSentences(this.$root).map((sentence, index, all) => {
                const previous = all[index - 1]
                sentence.appear = !previous ? 0
                    : sentence.start - previous.end < 1 ? Math.min(sentence.start, previous.end)
                    : sentence.start - 0.5
                return sentence
            })
            this.finishAt = (this.sentences.at(-1)?.end ?? 0) + 1

            const voice = this.$refs.voice
            voice.addEventListener('error', () => { if (this.started) this.refused(voice.error) })
            voice.addEventListener('ended', () => {
                this.ended = true
                this.tail.reset(Number.isFinite(voice.duration) ? voice.duration : voice.currentTime)
                if (!this.paused) this.tail.run()
            })
        },

        // The voice's own position; after it ends, a clock carries on to the finish.
        time() {
            return this.ended ? this.tail.now() : this.$refs.voice.currentTime
        },

        frame() {
            const t = this.time()
            for (const sentence of this.sentences) {
                setState(sentence.el, t < sentence.appear ? 'hidden' : t < sentence.end ? 'in' : 'out')
                for (const word of sentence.words) setUp(word.el, t >= word.start)
            }
            if (t >= this.finishAt) this.stop()
        },

        start() {
            if (this.started) return
            this.started = true
            this.play()
        },

        play() {
            const voice = this.$refs.voice
            this.paused = false
            this.ended = false
            this.tail.reset()
            voice.currentTime = 0
            voice.play()?.then(() => {
                if (marksTheDay && !this.entered) {
                    this.entered = true
                    this.$wire.markEntered()
                }
            }, error => this.refused(error))
            this.showControlsSoon()
            this.loop()
        },

        pause() {
            this.paused = true
            this.$refs.voice.pause()
            this.tail.pause()
        },

        resume() {
            this.paused = false
            if (this.ended) this.tail.run()
            else this.$refs.voice.play()?.catch(() => {})
            this.loop()
        },

        restart() {
            this.halt()
            resetSentences(this.$root)
            this.play()
        },
    }
}

/*
 * Breathe 11 (breath.js). The pacer: a lead-in breath, then for each line an in-breath during
 * which the line is spoken (from half the gap before it to half the gap after) and its words
 * appear, and an out-breath; then "And so it is" on a last in-breath, gone on the out-breath.
 */
export function itpBreathePlayer() {
    return {
        ...basePlayer(),
        label: '',
        breaths: [],
        lines: [],
        nextBreath: 0,
        activeLine: -1,
        finishAt: 0,
        clock: pausableClock(),

        init() {
            basePlayer().init.call(this)
            this.itis = this.$root.querySelector('[data-itis]')

            const breaths = [
                { at: 0, in: true, sounds: [['breathin', 1], ['in', 0.2]] },
                { at: BREATH, in: false, sounds: [['breathout', 1], ['out', 0.2]] },
            ]
            let timing = BREATH

            this.lines = readSentences(this.$root).map((sentence, index, all) => {
                timing += BREATH
                breaths.push({ at: timing, in: true, sounds: [['in', 0.2]] })

                const before = index > 0 ? (sentence.start - all[index - 1].end) / 2 : 0
                const after = index < all.length - 1 ? (all[index + 1].start - sentence.end) / 2 : 0.2
                const at = timing + LINE_DELAY
                const duration = sentence.end - sentence.start + before + after

                timing += BREATH
                breaths.push({ at: timing, in: false, sounds: [['out', 0.2]] })

                return { ...sentence, from: sentence.start - before, at, until: at + duration, fadeAt: at + duration + LINE_LINGER }
            })

            timing += BREATH
            breaths.push({ at: timing, in: true, sounds: [['soitis', 1], ['in', 0.2]] })
            breaths.push({ at: timing + BREATH, in: false, sounds: [['out', 0.2]] })
            this.itisAt = timing
            this.finishAt = timing + 2 * BREATH
            this.breaths = breaths
        },

        sound(name) {
            return this.$root.querySelector(`audio[data-sound="${name}"]`)
        },

        frame() {
            const t = this.clock.now()

            // The breath sounds, once each, as the pacer reaches them.
            while (this.nextBreath < this.breaths.length && this.breaths[this.nextBreath].at <= t) {
                this.breaths[this.nextBreath].sounds.forEach(([name, volume]) => play(this.sound(name), volume))
                this.nextBreath++
            }

            const breath = this.breaths[this.nextBreath - 1]
            // The ripple grows from 30% to 95% on the in-breath and back on the out, dimming
            // to 0.6 halfway (breath.js: inOutCubic over 5 s, opacity [1, 0.6, 1]).
            if (breath) {
                const progress = Math.min(1, Math.max(0, (t - breath.at) / BREATH))
                const eased = inOutCubic(progress)
                const ripple = this.$refs.ripple.style
                ripple.width = ripple.height = `${breath.in ? 30 + 65 * eased : 95 - 65 * eased}%`
                ripple.opacity = String(1 - 0.4 * Math.sin(Math.PI * progress))
                if (this.label !== (breath.in ? 'Breathe In' : 'Breathe Out')) this.label = breath.in ? 'Breathe In' : 'Breathe Out'
            }

            // The line whose voice is playing now: start it where it begins, stop it where it ends.
            const voice = this.$refs.voice
            const current = this.lines.findLastIndex(line => t >= line.at && t < line.until)
            if (current !== this.activeLine) {
                voice.pause()
                if (current !== -1) {
                    voice.currentTime = Math.max(0, this.lines[current].from)
                    voice.play()?.catch(() => {})
                }
                this.activeLine = current
            }

            this.lines.forEach((line, index) => {
                const spoken = index === current ? voice.currentTime : (t >= line.until ? Infinity : -Infinity)
                setState(line.el, t < line.at ? 'hidden' : t >= line.fadeAt ? 'out' : spoken >= line.start ? 'in' : 'hidden')
                line.words.forEach(word => setUp(word.el, t >= line.at && spoken >= word.start))
            })

            setState(this.itis, t < this.itisAt ? 'hidden' : t < this.itisAt + BREATH ? 'in' : 'out')

            if (t >= this.finishAt) this.stop()
        },

        start() {
            if (this.started) return
            this.started = true
            ;[this.$refs.voice, this.sound('breathout'), this.sound('out'), this.sound('soitis')].forEach(unlock)
            this.play()
        },

        play() {
            this.paused = false
            this.nextBreath = 0
            this.activeLine = -1
            this.clock.reset()
            this.clock.run()
            this.frame()
            this.showControlsSoon()
            this.loop()
        },

        pause() {
            this.paused = true
            this.clock.pause()
            this.$root.querySelectorAll('audio').forEach(audio => audio.pause())
        },

        resume() {
            this.paused = false
            this.clock.run()
            if (this.activeLine !== -1) this.$refs.voice.play()?.catch(() => {})
            this.loop()
        },

        restart() {
            this.halt()
            resetSentences(this.$root)
            this.play()
        },
    }
}

/*
 * A Quantum Why (why.js): one this device hasn't shown for this ITP yet, until all have been, then
 * round again. It fades in from above after four seconds, with the way back.
 */
export function itpWhyPlayer() {
    return {
        ...basePlayer(),
        why: '',

        init() {
            basePlayer().init.call(this)
            this.why = this.pick()
            this.later(WHY_AFTER, () => {
                setState(this.$refs.why, 'in')
                this.controlsShown = true
            })
        },

        pick() {
            const whys = [...this.$root.querySelectorAll('[data-whys] li')].map(li => li.textContent.trim()).filter(Boolean)
            const key = `itp.whys.${this.$root.dataset.whysKey}`
            let shown = []
            try { shown = JSON.parse(localStorage.getItem(key) ?? '[]') } catch {}
            shown = Array.isArray(shown) ? shown.filter(why => whys.includes(why)) : []

            let available = whys.filter(why => !shown.includes(why))
            if (!available.length) {
                shown = []
                available = whys
            }

            const why = available[Math.floor(Math.random() * available.length)] ?? ''
            try { localStorage.setItem(key, JSON.stringify([...shown, why])) } catch {}
            return why
        },
    }
}

window.itpPlayerNavigate = itpPlayerNavigate
window.itpWordsPlayer = itpWordsPlayer
window.itpBreathePlayer = itpBreathePlayer
window.itpWhyPlayer = itpWhyPlayer
