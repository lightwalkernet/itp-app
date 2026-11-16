# The design system in `itp-app` (built 15 Nov 2026)

The tokens, the field, the bar and the components from `docs/design/direction.md`, as real files. Everything a page needs to look like the assembly (`docs/design/assembly.html`) is here; a page is Blade markup using these classes and components, and nothing else.

## Files

| File | What it is |
|---|---|
| `resources/css/tokens.css` | Every colour and scale, as custom properties on `:root`, with `@property` registrations so phase changes transition (2.4 s). The four phase palettes; the white material; gold accent; glass; the bar's colours per phase and room. |
| `resources/css/field.css` | The field's static layers (sky gradient, grain) and the two canvases. No CSS animation here — `@persist` would restart it on every navigation. |
| `resources/css/bar.css` | The bar (`.app-tabbar`): the original app's floating glass pill, 64px high, the logo raised half above it. Pads pages by `--tabbar-height`. |
| `resources/css/components.css` | Type (`.t-room`, `.t-title`, `.t-kicker`, `.t-body`, `.t-lit`, `.t-why`, `.t-hint`), `.card`, `.room`, `.btn` (+ `--ghost`, `--light`), `.btn-sec`, `.pill`, `.rows`/`.row`, `.lrow` (library row), `.srow` (settings row), `.tabs`/`.tab`, `.fold`, `.switch`, `.field-input`, `.back`, `.col`, `.page-center`. |
| `resources/css/app.css` | Imports Tailwind, the bundled Fraunces (`@fontsource-variable/fraunces`, full axes), the files above and the existing `transitions.css`; exposes tokens to Tailwind (`text-ink`, `bg-paper`, `text-accent`…); sets the page surface per room. |
| `resources/js/field.js` | Draws the light forms, horizon glow, rays, motes and stars on the two canvases at 30 fps; reads every colour from computed CSS; sets `html[data-phase]` from the clock each minute; re-applies phase and still mode after each Livewire swap; pauses when hidden or in a white room; still mode / reduced motion draw one frame. `window.itpField.{setPhase, unlockPhase, setStill}`. |
| `resources/js/rooms.js` | Copies the page's `data-room` / `data-bar` / `data-scene` to `<html>` on first paint and after each navigation. |
| `resources/views/components/field.blade.php` | The field markup (inside `@persist('field')` in both layouts). |
| `resources/views/components/bar.blade.php` | Your ITPs (list icon) · Today (the logo, centre, raised) · You (person icon). Icons are `components/icons/{list,logo,user}`, ported from the original, in `currentColor`. No Return button (removed 17 Nov). Inside `@persist('tabbar')`. |
| `resources/views/components/{card,room,button,row,fold}.blade.php` | `<x-card kicker title :quote>`, `<x-room kicker title>`, `<x-button href variant meta>`, `<x-row href meta>`, `<x-fold title meta>`. |
| `resources/views/layouts/app.blade.php`, `guest.blade.php` | Field persisted first, then the page, then the player and the bar. |
| `app/Livewire/DesignSystem.php` + `livewire/design-system.blade.php` | `/design`, local only: every token and component on one screen, with a clock to lock the phase and a chip to swap the room. |

## How a page declares itself

```blade
<div data-room="white">…</div>                     {{-- a white room: the field fades out --}}
<div data-room="light">…</div>                     {{-- a room made of light (the default) --}}
<div data-room="light" data-bar="hidden">…</div>   {{-- a place you're in: the bar slides away --}}
```

Light rooms: Today, the player, Breathe 11, the Why, the making, the beginning. White rooms: Your ITPs, an ITP's page, the record, You.

## Rules the files encode

- Three materials: light (the field), white (anything read or written), glass (the bar, player controls). Gold `#b08d3c` is the accent on white only; never in the field.
- No informational text under 16px; body 18px; 48px targets. The scale is in `components.css`; don't add sizes below it.
- Phase palettes: dawn/dusk a sun (`#d8432c → #ffc64d`), day warm-white-to-blue, night indigo. Only `tokens.css` holds colours.
- Nothing animates in CSS inside a persisted region. Motion is the canvas (field.js), CSS transitions on state changes, or Alpine transitions on page content.
- Links use `data-transition="forward|back|tab"`, never `wire:navigate` (`.ai/rules/platform.md` §8). `<x-button href>` and `<x-row href>` do this for you.
- The bar's tabs match one or more path prefixes (`data-tab-match="/itps /universal"`), so an ITP's page lights Your ITPs and About lights You.

## Still to do in the build

See `docs/design/build-brief-1-the-rooms.md` for the first slice (Today, Your ITPs, an ITP's page, You). Then: player, speaking, the record, reminders, the beginning, making.

Verify locally with `composer run dev` (or `npm run build`) and open `/design`. Tests: `php artisan test --compact tests/Feature/Livewire/DesignSystemTest.php`.
