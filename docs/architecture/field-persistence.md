# The field across pages — how the living background survives navigation

Decision (10 Nov 2026): the ITP app is **separate Livewire pages** (`Livewire.navigate()` behind `data-transition` links, as `.ai/rules/platform.md` §8 describes), not an SPA with cross-component messaging like the old Omni design. The dancing-light field must still feel continuous across pages. It does, with no shared state. Built 15 Nov 2026 — see `docs/design/system.md` for the files.

## Pattern

1. **The field lives in the layout inside `@persist('field')`.** Livewire keeps a `@persist` block's DOM alive across navigations, so the two canvases that draw the light never unmount, and their state (ray positions, mote lifetimes, star phases) carries on. Same mechanism as the persisted audio player and the bar.
2. **Nothing inside a persisted region animates in CSS.** Livewire removes and re-inserts a persisted element on every navigation (platform.md §8), which restarts any CSS animation — the drifting light forms and twinkling stars would jump. So everything that moves is drawn by `field.js` on canvas; the only CSS in the field is the sky gradient and the grain. CSS *transitions* (which fire on a property change, not on insertion) are fine, and the 2.4 s phase transition uses them.
3. **The field is driven only by CSS custom properties (`tokens.css`) and a few attributes on `<html>`:** `data-phase` (dawn | day | dusk | night — set from the device clock by `field.js`), `data-room` (light | white — whether the field shows at all), `data-bar` (hidden — the bar slides away), `data-scene` (optional, for a page that wants the field to react) and `data-still` (still mode). The canvases read their colours each frame from computed CSS, so a phase change is a token change plus a transition.
4. **Pages know nothing about each other.** Each page is its own Livewire component. It declares what kind of place it is on its root element — `data-room="white"`, `data-bar="hidden"` — and `rooms.js` copies that to `<html>` on first paint and after every `livewire:navigated`. That is the whole contract. No Livewire events between components, no global store.
5. **Livewire's navigate swap overwrites `<html>`'s attributes** with the incoming document's (transitions.js already deals with this for `data-nav-direction`). So `field.js` re-applies `data-phase` and `data-still`, and `rooms.js` re-applies `data-room`/`data-bar`, on `livewire:navigated` — synchronously, before the next paint, so no transition fires and nothing flashes. (Both scripts listen for that event; they are idempotent.)
6. **The entrance is two pages and one attribute** (brief 2): the leaving page sets `data-scene="entering"` (the field floods toward white), then navigates; the arriving page's content fades in and sets `data-scene="inside"`. The rays never stop, so the light carries across.
7. **Audio persists the same way:** `@persist('player')` holds the `<audio>` element and its controls so a stacked session (Primer → Breathe 11 → Attunement) continues across page changes.
8. **The bar persists too** (`@persist('tabbar')`, `components/bar.blade.php`): three links with `data-transition="tab"` (Your ITPs · Today, the raised logo · You). Its colours follow `data-phase`/`data-room` through tokens, not component state; transitions.js keeps `aria-current` right, and the logo's glow follows it in CSS.

## What the design uses, and how it maps to the stack

Everything is **plain CSS, two `<canvas>` elements, and a few hundred lines of vanilla JS** — nothing that needs a framework.

| In the design | In the app |
|---|---|
| Sky gradient, grain | `field.css`, inside `@persist`. Tokens in `tokens.css` (`@property` registrations; Tailwind can't express these, so they stay in plain CSS). |
| Light forms, horizon glow | `field.js`, the light canvas (`.field__canvas--light`, `mix-blend-mode: screen`), radial gradients drifting on sine curves. |
| Rays, motes, stars | `field.js`, the rays canvas (blur 1.2px, screen), 30 fps, paused when hidden or in a white room. Stars breathe per-star; one in nine sparkles. |
| Time-of-day palette | `data-phase` on `<html>`, set by `field.js` every minute from the device clock; tokens transition 2.4 s. The design page can lock it (`window.itpField.setPhase`). |
| Page fade (one page to the next) | **Mobile:** the push slide from the `itpinterface` spike (`transitions.js`/`transitions.css`, view transitions), unchanged. **Web:** a class toggled on `livewire:navigating`/`navigated` that fades `.app-page` (brief 1, §7). Pages are see-through to the field, so a slide or fade shows the light beneath. |
| Card states, folds | Livewire state for anything the server knows; native `<details>` for folds. CSS transitions on transform/opacity only. |
| Word reveal | `player.js` (brief 2): words split into spans on mount; each frame compare `audio.currentTime` to the ITP's `attunement_timings` and toggle classes; CSS keyframes do the light — the player page is not persisted, so keyframes are fine there. |
| Breathe 11 pacer | CSS animation on the page (10 s cycle) plus a JS tick for the line changes; Anime.js for the petals variant later. |
| White rooms | `data-room="white"` on `<html>`: the field fades to 0, the canvases stop drawing, the page surface and the bar go white. |
| Still mode | `html[data-still]` (from `localStorage` for now, `window.itpField.setStill`): one frame, no motion. Also triggered by `prefers-reduced-motion`. |

**Mobile (NativePHP):** iOS is WKWebView (Safari engine), Android is the system Chromium WebView. Everything above works in both — `@property` needs iOS 16.4+ / any recent Android WebView; `backdrop-filter` and `mix-blend-mode` are fine in both. Two cautions: (1) `backdrop-filter` plus a blurred canvas plus `mix-blend-mode` on the same screen is the expensive combination — blur is on one canvas only, and the bar's backdrop blur can drop to a flat tint on low-end Android; (2) test on a mid-range Android early, and keep **still mode** first-class.

## Constraints to remember

- The persisted markup must be identical in every page's layout and placed in the same position; keep it in the base layouts only (`layouts/app`, `layouts/guest`).
- The canvases handle `resize`/orientation and `visibilitychange` (the draw loop stops when hidden).
- ~30 fps; dpr capped at 1.5; test on a mid-range Android WebView early.
- Word-reveal timing lives with the player page, driven off `audio.currentTime` each frame; it does not need the field.
- Only the app shell uses the field; the player may optionally place a photograph layer *inside* the persisted field (beneath the rays), toggled by `data-scene`, so the rays sit over the image.
