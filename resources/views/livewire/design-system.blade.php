{{--
    The design system, on one screen. Local only.

    The page is a light room by default; the "room" chip turns the whole page white, which is what
    a white room does in the app. The clock chip locks the phase so each hour can be checked.
--}}
<div data-room="light" :data-preview-room="room" x-data="{
    room: 'light',
    hour: new Date().getHours(),
    still: false,
    get label() { const h = this.hour; return `${h % 12 === 0 ? 12 : h % 12} ${h >= 12 ? 'pm' : 'am'}` },
    init() { try { this.still = localStorage.getItem('itp.still') === '1' } catch (e) {} },
    setHour() { window.itpField.setPhase(window.itpField.phaseFor(this.hour)) },
    destroy() { window.itpField.unlockPhase() },
}">
    <style>
        /* Page-local: this page alone can swap its own room, so the tokens can be seen both ways. */
        [data-preview-room="white"] .ds-light { display: none; }
        [data-preview-room="light"] .ds-white { display: none; }
        .ds-chips { position: fixed; z-index: 7; left: 0; right: 0; bottom: calc(var(--tabbar-height) + 8px + env(safe-area-inset-bottom)); display: flex; justify-content: center; gap: 8px; pointer-events: none; padding: 0 12px; flex-wrap: wrap; }
        .ds-chips > * { pointer-events: auto; }
        .ds-chip { appearance: none; border-radius: 999px; padding: 6px 12px; font: inherit; font-size: 13px; font-style: italic; background: rgba(0,0,0,.4); border: 1px solid rgba(255,255,255,.18); color: #fff; cursor: pointer; }
        .ds-clock { display: grid; grid-template-columns: auto 1fr; align-items: center; gap: 8px; width: 150px; padding: 4px 12px; border-radius: 999px; background: rgba(0,0,0,.4); border: 1px solid rgba(255,255,255,.18); color: #fff; font-size: 13px; }
        .ds-clock input { width: 100%; margin: 0; }
        .ds-section { margin-top: 34px; }
        .ds-h { margin: 0 0 12px; font-size: 15px; font-style: italic; opacity: .7; }
        .ds-swatches { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .ds-swatch { height: 48px; border-radius: 12px; display: grid; place-items: end start; padding: 6px; font-size: 12px; font-style: italic; border: 1px solid rgba(127,127,127,.25); }
        .ds-words span { display: inline-block; margin: 0 .18em; }
        .ds-words .now { text-shadow: 0 0 18px var(--glow), 0 0 42px color-mix(in oklab, var(--glow) 60%, transparent); }
        .ds-words .said { opacity: .82; text-shadow: 0 0 10px color-mix(in oklab, var(--glow) 40%, transparent); }
        .ds-words .ahead { opacity: 0; }
    </style>

    <div class="col" style="padding-top: 12px; padding-bottom: 120px">

        {{-- ---------- in the light ---------- --}}
        <section class="ds-light">
            <p class="t-kicker lit-soft" style="color: var(--ink-soft)">Design system</p>
            <h1 class="t-room lit lit-glow" style="color: var(--ink)">In the light</h1>
            <p class="t-body lit-soft" style="margin-top: 10px">A light room. The field is drawn behind the page; the words sit in it; the one thing to do sits on white.</p>

            <div class="ds-section">
                <p class="ds-h lit-soft">The card</p>
                <x-card kicker="This morning" title="The Quiet Yes">
                    <p class="t-body">Enter it first. Three minutes with the light, then one question.</p>
                    <x-button meta="attunement · 3 min">Enter</x-button>
                    <button type="button" class="btn-sec">Something else today? <em>Your ITPs</em></button>
                </x-card>
            </div>

            <div class="ds-section">
                <p class="ds-h lit-soft">A card quoting what you said</p>
                <x-card kicker="This afternoon, a Quantum Why" title="“Why is the step so warm at five o’clock?”" :quote="true">
                    <p class="t-body t-soft">Nothing to answer. Just carry it, and see what the day does with it.</p>
                    <div class="rows">
                        <x-row meta="the quiet yes · 2 min">Breathe 11</x-row>
                        <x-row meta="primer · 2 min">Return to Coherence</x-row>
                    </div>
                </x-card>
            </div>

            <div class="ds-section lit" style="text-align: center">
                <p class="ds-h lit-soft">Words in the light</p>
                <p class="t-kicker" style="color: var(--ink-soft)">Personal · <em>The Quiet Yes</em></p>
                <p class="t-lit ds-words" style="margin-top: 14px"><span class="said">At</span><span class="said">five</span><span class="said">o’clock</span><span class="said">the</span><span class="said">work</span><span class="said">sets</span><span class="now">like</span><span class="ahead">the</span><span class="ahead">sun</span></p>
                <div style="display: flex; justify-content: center; gap: 22px; margin-top: 24px">
                    <button type="button" class="pill" aria-label="Back ten seconds"><svg viewBox="0 0 24 24"><path d="M4 12a8 8 0 1 0 2.6-5.9M4 4v5h5"/></svg></button>
                    <button type="button" class="pill pill--lg" aria-label="Pause"><svg viewBox="0 0 24 24"><path d="M9 5v14M15 5v14"/></svg></button>
                    <button type="button" class="pill" aria-label="Finish"><svg viewBox="0 0 24 24"><path d="M6 5l10 7-10 7zM18 5v14"/></svg></button>
                </div>
            </div>

            <div class="ds-section lit" style="text-align: center">
                <p class="ds-h lit-soft">A Quantum Why, and a question</p>
                <p class="t-why lit-glow">Why is the step so warm at five o’clock?</p>
                <p class="t-lit" style="margin-top: 34px">Who, or what, is this for?</p>
                <p class="t-hint lit-soft">Yourself, most often. Sometimes a season of your life, or someone you’re carrying.</p>
                <x-button variant="light" style="margin-top: 22px">Begin</x-button>
            </div>

            <div class="ds-section">
                <p class="ds-h lit-soft">The field’s tokens at this hour</p>
                <div class="ds-swatches">
                    <div class="ds-swatch" style="background: var(--sky-top); color: var(--ink)">sky-top</div>
                    <div class="ds-swatch" style="background: var(--sky-mid); color: var(--ink)">sky-mid</div>
                    <div class="ds-swatch" style="background: var(--sky-hot); color: var(--ink)">sky-hot</div>
                    <div class="ds-swatch" style="background: var(--sky-low); color: var(--ink)">sky-low</div>
                    <div class="ds-swatch" style="background: var(--ray-warm)">ray-warm</div>
                    <div class="ds-swatch" style="background: var(--ray-cool)">ray-cool</div>
                    <div class="ds-swatch" style="background: var(--mote)">mote</div>
                    <div class="ds-swatch" style="background: var(--glow)">glow</div>
                </div>
            </div>
        </section>

        {{-- ---------- on white ---------- --}}
        <section class="ds-white room">
            <x-room kicker="Design system" title="On white" />
            <p class="t-body t-soft" style="margin-top: 10px">A white room. The field is away; the page is the paper. Gold is the accent, and only here.</p>

            <div class="ds-section">
                <p class="ds-h">Tabs and library rows</p>
                <div class="tabs" role="tablist">
                    <button class="tab" role="tab" aria-selected="true">Universal <small>13</small></button>
                    <button class="tab" role="tab" aria-selected="false">Personal <small>1</small></button>
                    <button class="tab" role="tab" aria-selected="false">Primers <small>2</small></button>
                </div>
                <p class="tabnote">Thirteen, for everyone. Live with one for a while, or just listen to one today.</p>
                <div>
                    <button type="button" class="lrow"><span class="lrow__thumb"></span><span><span class="lrow__name">Awaken Wonder</span><span class="lrow__meta">Light Up Your Life</span></span><span class="lrow__side">3 min</span></button>
                    <button type="button" class="lrow lrow--locked"><span class="lrow__thumb"></span><span><span class="lrow__name">Nervous System Reset</span><span class="lrow__meta">Return to Presence and Peace</span></span><span class="lrow__side">sign in</span></button>
                    <button type="button" class="lrow lrow--plain"><span><span class="lrow__name">Return to Coherence</span><span class="lrow__meta">for right now · 2 min</span></span><span class="lrow__side"></span></button>
                </div>
            </div>

            <div class="ds-section">
                <p class="ds-h">An ITP’s page</p>
                <p class="t-kicker">Nervous System Reset</p>
                <h2 class="t-room" style="font-size: clamp(30px, 8.5vw, 38px); margin-top: 6px">Return to Presence and Peace</h2>
                <p class="t-body t-body--lg" style="margin-top: 14px"><i class="t-soft" style="display: block; margin-bottom: 6px">Feeling overwhelmed? Tired, but wired?</i>Use this ITP to help your whole system unwind, gently shifting out of fight-or-flight and into rest and renewal.</p>
                <x-button meta="attunement · 3 min">Enter</x-button>
                <button type="button" class="btn-sec">Live with this one <em>instead</em></button>
                <div class="rows">
                    <x-row meta="eleven lines · 2 min">Breathe 11</x-row>
                    <x-row meta="one of eleven">A Quantum Why</x-row>
                </div>
                <x-fold title="The words" meta="read before you listen, or after">
                    <p class="o">Beloved One,</p>
                    <p>the hour you keep is not the hour that keeps you.</p>
                </x-fold>
                <x-fold title="The eleven" meta="Breathe 11">
                    <p>I am allowed to stop.</p>
                    <p>The ground holds me without being asked.</p>
                </x-fold>
            </div>

            <div class="ds-section">
                <p class="ds-h">Settings rows and the switch</p>
                <h3 class="section-h">The day’s three reminders</h3>
                <div class="srow"><span>Morning<span class="srow__meta">enter, then one question</span></span><span class="srow__side"><span class="srow__value">7:00</span><button type="button" class="switch" role="switch" aria-checked="true" aria-label="Morning reminder" @click="$el.setAttribute('aria-checked', $el.getAttribute('aria-checked') !== 'true')"></button></span></div>
                <div class="srow"><span>A Quantum Why<span class="srow__meta">one a day, from your ITP</span></span><span class="srow__side"><span class="srow__value">2:30 pm</span><button type="button" class="switch" role="switch" aria-checked="true" aria-label="Quantum Why" @click="$el.setAttribute('aria-checked', $el.getAttribute('aria-checked') !== 'true')"></button></span></div>
                <div class="srow"><span>Still mode<span class="srow__meta">the light rests; nothing moves</span></span><button type="button" class="switch" role="switch" :aria-checked="still ? 'true' : 'false'" aria-label="Still mode" @click="still = !still; window.itpField.setStill(still)"></button></div>
            </div>

            <div class="ds-section">
                <p class="ds-h">Buttons and an input</p>
                <x-button>Primary</x-button>
                <x-button variant="ghost" meta="about ten minutes">Ghost</x-button>
                <x-button :disabled="true">Disabled</x-button>
                <input class="field-input" type="email" placeholder="you@…" aria-label="Email">
                <a href="#" class="back" style="margin-top: 14px"><svg viewBox="0 0 24 24"><path d="M15 5l-7 7 7 7"/></svg> Your ITPs</a>
            </div>

            <div class="ds-section">
                <p class="ds-h">Type</p>
                <p class="t-room">Room title</p>
                <p class="t-title" style="margin-top: 10px">Card title</p>
                <p class="t-title t-title--quote" style="margin-top: 10px">“A card quoting what you said”</p>
                <p class="t-kicker" style="margin-top: 10px">A kicker, italic, 16px — the smallest informational text</p>
                <p class="t-body" style="margin-top: 10px">Body, 18px, line height 1.5. No informational text under 16px; targets 48px.</p>
                <p class="t-meta" style="margin-top: 10px">Meta, 15px italic, only for truly secondary notes beside a thing</p>
            </div>
        </section>
    </div>

    <div class="ds-chips">
        <button type="button" class="ds-chip" @click="room = room === 'light' ? 'white' : 'light'; document.documentElement.dataset.room = room" x-text="'room: ' + room"></button>
        <div class="ds-clock">
            <label for="ds-hour" x-text="label"></label>
            <input id="ds-hour" type="range" min="0" max="23" step="1" x-model.number="hour" @input="setHour()" aria-label="Hour of day">
        </div>
    </div>

</div>
