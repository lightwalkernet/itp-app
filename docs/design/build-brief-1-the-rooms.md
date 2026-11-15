# ITP app — build brief 1: the rooms

**Repo:** `itp-app` (Laravel 13, Livewire 4.4, Tailwind 4, NativePHP Mobile; one codebase, two builds). Read `.ai/rules/platform.md` first — every rule in it applies, especially §3 (device safety), §3a (the platform flag), §7 (assets through `asset()`) and §8 (navigation: `data-transition`, never `wire:navigate`).

**Read with this brief:** `docs/design/what-we-ask.md` (the idea), `docs/design/direction.md` (every design decision), `docs/design/system.md` (the design system's files and rules), `docs/architecture/field-persistence.md` (how the field and bar survive navigation), and `docs/design/assembly.html` (every screen, in a browser — the arc, the prompt's Speak/Keep buttons and the card states are all drawn there). The About paragraph comes from `docs/codex/creative-codex-v6.md` §1.

**What exists already:** the design system — `resources/css/{tokens,field,bar,components}.css`, `resources/js/{field,rooms}.js`, the Blade components in `resources/views/components/`, both layouts, and `/design` (local only), which shows every token and component. Build the pages below *with* those files; add to them only when a page needs something they don't have. Don't restyle them.

**What this brief builds:** the four rooms a person moves between all day — **Today**, **Your ITPs**, **an ITP's page**, **You** — plus the two shared tables they need. Not in this brief (later briefs): the player, Breathe 11, the Quantum Why screen, making a Personal, the record, the beginning path, notifications. Where a page links to one of those, link to the nearest thing that exists today (noted per page) and leave a `{{-- brief 2 --}}` comment.

---

## 1. The idea, in one paragraph

A person **lives with one ITP at a time** — a Universal, or their own Personal — until they choose another. An ITP is one transmission at three sizes: the **attunement** (3 min, the player), its **Breathe 11** (2 min), and its **Quantum Why** (one sentence). The day has two anchors and an open middle: in the **morning** you enter the ITP, then answer one question (*What does my ITP Self want today?*); in the **afternoon** nothing is asked, but a way back to centre is offered and a Quantum Why arrives; in the **evening** the whole card is *What shifted today?*, and it hands the afternoon's Why back. Everything a person keeps goes in their record. The ask is small: listen in the morning, notice in the evening, reach for the rest when you need it.

Two kinds of room. **Light rooms** (Today) sit on the living field; **white rooms** (Your ITPs, an ITP's page, You) put the field away. A page declares which it is on its root element: `data-room="light"` or `data-room="white"`. `rooms.js` does the rest.

---

## 2. Data

Two shared tables in `itp-core`, synced to the phone (user-scoped, `$table->syncable()`, `Syncable` trait, ULID keys, portable across MySQL and SQLite — platform.md §4). Both are written by the app through Eloquent models only.

### `user_settings` — one row per user, created on first need

| column | type | notes |
|---|---|---|
| `id` | ulid | |
| `user_id` | ulid, FK users | unique |
| `living_with_type` | string, nullable | `universal` or `itp` |
| `living_with_id` | ulid, nullable | the `universal_itps.id` or `itps.id` |
| `morning_at` | time, nullable | default `07:00` when created |
| `why_at` | time, nullable | default `14:30` |
| `evening_at` | time, nullable | default `19:30` |
| `morning_reminder` / `why_reminder` / `evening_reminder` | boolean | default true |
| timestamps, syncable columns | | |

Model `Itp\Core\Models\UserSetting` with `livingWith(): MorphTo`-style accessor returning the `UniversalItp` or `Itp` (resolve by type, not a polymorphic column pair that MySQL/SQLite would treat differently — two nullable columns and a method is fine). Register with sync as a user-scoped type `user_settings`, pushed and pulled.

### `entries` — what a person keeps

| column | type | notes |
|---|---|---|
| `id` | ulid | |
| `user_id` | ulid, FK users | |
| `kind` | string | `morning` \| `step` \| `evening` \| `evidence` |
| `for_date` | date | the day it belongs to, in the device's local date |
| `body` | text | the words |
| `source` | string | `written` \| `spoken` (spoken arrives in brief 3; store the column now) |
| `why` | text, nullable | the Quantum Why the evening was asked against, if any |
| `living_with_type` / `living_with_id` | as above, nullable | what they were living with that day |
| timestamps, syncable columns | | |

Index `(user_id, for_date, kind)`. Model `Itp\Core\Models\Entry`. Sync type `entries`, user-scoped, pushed and pulled. One entry per `(user, for_date, kind)` is the rule the app enforces (replace, don't duplicate).

Still mode is device-only and already lives in `localStorage` (`field.js`); leave it there for now.

---

## 3. Today — `/` (`App\Livewire\Home`, rebuilt)

Light room. Root: `<div data-room="light" class="page-center">`.

Rename the component's purpose in its docblock; keep the route name `home`, the `wire:init="refresh"` sync behaviour, guest handling, and the open-generation check that exist today. Replace the view entirely.

### Layout (top to bottom)

1. **Topline** (`.topline` in the `.col`): greeting on the left — *Good morning* (05–11), *Good afternoon* (12–16), *Good evening* (17–20), *Good night* (21–04) — and the date on the right (`l, j F`). Both in `--ink-soft`.
2. **Living with** (`.living`): the words *Living with* and the ITP's name as a link to its page. For a guest or a user with no `user_settings` row yet: *Living with* **Meet Your ITP Self** (the `base` kind Universal — the Introduction) and that is what Today enters.
3. **The arc** (`.arc`, the SVG from `/design`'s source is not there yet — port it from the assembly): a quadratic path, a sun that travels it from 5 am (left) to 9 pm (right) and is hidden at night, two points labelled *morning* and *evening*, filled when that entry exists for today.
4. **The card** (`<x-card>`), which changes with the hour and with what's been kept today. Exactly one card. Its states:

| when | kicker | title | line | primary | secondary |
|---|---|---|---|---|---|
| morning, not yet entered today | *This morning* | the ITP's name | *Enter it first. Three minutes with the light, then one question.* | **Enter** → the ITP's page (brief 2: the player) | *Something else today? **Your ITPs*** → `/itps` |
| morning, entered, no morning entry | *In its afterglow* | *What does my ITP Self want today?* | *Speak it or write it. A sentence is enough.* | **Begin** → the prompt (§3.1) | *Or sit a moment longer — **Breathe 11** · 2 min* → the ITP's page (brief 2) |
| morning, morning entry kept | *This morning* | the entry, quoted (`:quote="true"`) | *Carry it lightly. The day will meet you.* | **Breathe 11 · 2 min** → the ITP's page (brief 2) | *Add one aligned step, if you wish* → the prompt, kind `step` |
| afternoon, before `why_at` | *Whenever you need it* | *Return to alignment* | *This morning: "…"* if kept, else *The day pulls. Here is the way back to centre.* | two rows: **Breathe 11** *the {itp} · 2 min*, **Return to Coherence** *primer · 2 min* (both → the ITP's page / the Return to Coherence Universal's page; brief 2) | *Add one aligned step, if you wish* |
| afternoon, after `why_at` | *This afternoon, a Quantum Why* | the day's Why, quoted | *Nothing to answer. Just carry it, and see what the day does with it.* | the same two rows | the same |
| evening or night, no evening entry | *Evening reflection* (night: *Before sleep*) | *What shifted today?* | *This afternoon it asked: "{why}" Did you notice anything?* | **Reflect** → the prompt, kind `evening` | *Not tonight — **that's allowed*** → keeps an evening entry with body `—` |
| evening, evening entry kept (not `—`), no evidence | *Celebrate the evidence* | *What are you proud of today?* | *Glimmers, synchronicities, courage. It all counts.* | **Add to the record** → the prompt, kind `evidence` | *Or enter — **{itp}** · 3 min* |
| evening, evidence kept | *Today is complete* | the evidence, quoted | *Kept in your record.* | **Enter — {itp}** | *Open **your record*** → `/you` (brief 4: the record) |
| night, evening done | *Before sleep* | the ITP's name | *Let the day go quiet before it closes.* | **Enter** | *Open **your record*** |

"Entered today" = a play of the attunement today. Until the player exists (brief 2), treat visiting the ITP's page from Today's Enter as entering: set a session flag `entered:{date}` on the way through. Replace with the play record in brief 2.

**The day's Why** is chosen deterministically from the ITP's `quantum_whys`: index = day-of-year mod count, so it is the same all day on every device without a sync round-trip. Store it on the evening entry (`why`) when that's kept.

**Hour** comes from the device clock in JS, not the server: render the card for the server's guess, then let a tiny Alpine `x-init` compare `new Date().getHours()` and call `$wire.setHour(h)` only if it differs (the web build on Forge is in a different timezone from the person). Simpler alternative if you prefer: pass the hour as a query/`wire:model` from a one-line script before first paint. Either way the card must never show the morning state at 7 pm because the server is in UTC.

**Guest:** the card is the morning state with *Meet Your ITP Self* every hour, line *Four Universals are open before you sign in. Your own ITP comes after.*; secondary *Sign in — **email and a code***.

**Open generation** (exists today): if there is one, show it as a second, smaller `<x-card>` under the main one: kicker *Your ITP is being made*, title by status as the current view does, primary **Continue** → `generations.show`.

### 3.1 The prompt — `/today/{kind}` (`App\Livewire\Prompt`)

Light room, `data-bar="hidden"`. One question, written answer (spoken arrives in brief 3 — build the `Speak` button disabled with `small` text *soon*). Root `<div data-room="light" data-bar="hidden" class="page-center">`.

- Back link (`.back`) → `/`.
- Kicker + question by kind: `morning` — *Morning practice* / *What does my ITP Self want today?* / hint *A sentence is enough.*; `step` — *One aligned step* / *What is one step toward it today?* / *Small counts: the email, the question, the rest.*; `evening` — *Evening reflection* / *What shifted today?* / *Movement, not achievement.* with the Why echoed beneath in `.t-hint`: *This afternoon it asked: "{why}"*; `evidence` — *Celebrate the evidence* / *What are you proud of today?* / *Glimmers, openings, courage — anything.*
- Question in `.t-lit`, hint in `.t-hint`, then a white `.answer`-style card (add `.answer` to `components.css`: `background: var(--card); color: var(--card-ink); border-radius: 24px; padding: 18px 20px; min-height: 110px; margin-top: 20px`) holding a `<textarea>` (`.field-input`, 3 rows, placeholder = the hint).
- Two `.mode` buttons centred beneath (add `.modes`/`.mode` from the assembly): **Speak** (disabled) and **Keep**. Keep validates non-empty, upserts the `entries` row for today and kind, and navigates back to `/` with `data-transition="back"` semantics (use `Livewire.navigate` via `window.pageTransitions.back('/')`).
- Auth only. A guest hitting it goes to sign-in.

---

## 4. Your ITPs — `/itps` (`App\Livewire\Library`)

White room. Root `<div data-room="white">`. Route name `itps.index` (the bar's ITPs tab already looks for it). Open to guests.

- `<x-room kicker="Everything you can enter" title="Your ITPs" />`
- `.tabs`: **Universal** *13* · **Personal** *{count}* · **Primers** *2*. Tabs are Livewire state (`$tab`), remembered in the URL (`#[Url]`). Guests see the Personal count blank.
- `.tabnote` under the tabs: Universal — *Thirteen, for everyone. Live with one for a while, or just listen to one today — that doesn't change what you're living with.* (guest: *…Four are open before you sign in; the rest wait for you after.*); Personal — *Made from your words, for you alone.*; Primers — *For a moment, not a day. Breathe 11s and Quantum Whys aren't here — they belong to the ITP you're living with.*
- **Universal tab:** every `UniversalItp` of kind `universal`, by `sort_order`, as `.lrow` links to `universals.show`: thumbnail (`media->url($image_path)` through `asset()`), `lrow__name` = `short_name` (fall back to `display_name`), `lrow__meta` = `display_name`, `lrow__side` = *3 min*. A guest sees non-preview rows with `.lrow--locked` and side text *sign in*; they still link to the page (the page handles the lock).
- **Personal tab:** the user's `Itp` rows as `.lrow` (meta *Made from your words · {created_at:j F}*, append *· living with* on the one they live with), then `<x-button variant="ghost" href="{{ route('itps.create') }}" meta="about ten minutes">Make a new personal ITP</x-button>`. Guest: `.t-body` *Your own ITP is made from your words — a few questions, answered in your own time.* then `.t-meta` *After you sign in. Email and a six-digit code; no password.* then `<x-button href="sign-in">Sign in</x-button>`.
- **Primers tab:** two `.lrow lrow--plain` rows — **Return to Coherence** *for right now · 2 min*, **Highest Possible Outcome** *before something that matters · 3 min*. These are Universals of kind `base` (or a new kind `primer` if the content bundle lacks them — check `resources/content/snapshot.json`; if they're not there, render the rows with `aria-disabled` and meta *coming soon* rather than inventing content).
- The current Home's "Continue your ITP" and the download polling move with the data they belong to: keep `wire:poll.3s="downloadNext"` here for universal audio downloads on the phone.

---

## 5. An ITP's page — `/universal/{slug}` and `/itps/{itp}` (`ShowUniversalItp`, `ShowItp`, rebuilt views)

White room, same template for both (make it a Blade component `<x-itp-page>` taking the model and a few flags, used by both components). Root `<div data-room="white">`.

Top to bottom:

1. `.back` → `/itps` *Your ITPs* (`data-transition="back"`).
2. Emblem: the image as a 64px rounded square (`.emblem`: `width/height 64px; border-radius 20px; object-fit cover; margin-top 14px`). Omit if no image.
3. `.t-kicker`: Universal — the `short_name`; Personal — *Made from your words · {date}*.
4. `h1.t-room` at `clamp(30px, 8.5vw, 38px)`: Universal — `display_name`; Personal — `title`, italic.
5. `.t-body.t-body--lg` description: Universal — split `description` at the first newline; the first line (the *Feeling…?* question) in italic `--card-ink-soft` on its own line, the rest beneath. Personal — `description` plain.
6. **Enter** `<x-button meta="attunement · 3 min">` → the player (brief 2). Until then it plays with the existing audio controls this page has now — keep the current `<audio>` and the available/downloading/waiting/locked states, restyled: the status line in `.t-meta`, the button disabled with `meta` = *downloading…* / *waiting for a connection* as appropriate.
   Guest on a locked Universal: `<x-button href="sign-in" meta="email and a code">Sign in to listen</x-button>` and `.t-body.t-soft` *You can read the words below first. Four Universals are open to everyone; this one waits for you after sign-in.*
7. Under Enter: if this is what they live with, `.tabnote` centred *You're living with this one.*; otherwise `.btn-sec` **Live with this one *instead*** — a `wire:click` that upserts `user_settings.living_with_*`, then re-renders with the line above. Guests don't see it.
8. `.rows`: `<x-row meta="eleven lines · 2 min">Breathe 11</x-row>` and `<x-row meta="one of {n}">A Quantum Why</x-row>` — brief 2 targets; for now `aria-disabled`.
9. Three `<x-fold>`s: **The words** *read before you listen, or after* — `attunement_text` as paragraphs, the first (*Beloved One,* / *Beloved {name},*) in `.o`; **The eleven** *Breathe 11* — `breath11_lines` one per `<p>`; **Quantum Whys** *{n}* — each in `.o`.

Keep every existing behaviour (preview gating, audio availability, download states) — this is a re-skin plus the live-with action, not a rewrite of the data path.

---

## 6. You — `/you` (`App\Livewire\You`, replaces `Account`)

White room, auth only. Root `<div data-room="white">`. Route name `you`; update the bar (`components/bar.blade.php`) so the You tab points at `route('you')` with `data-tab-match="/you"`, and remove the `account` route and component (or redirect `account` → `you`).

- `<x-room :kicker="$user->first_name" title="You" />`
- `.rows`: `<x-row href="/you/record" meta="what you've kept">Your record</x-row>` (brief 4; for now link to `/itps`), `<x-row href="{living-with page}" meta="{name} · change">Living with</x-row>`.
- `h3.section-h` *The day's three reminders*, then three `.srow`s — **Morning** *enter, then one question* · `morning_at` · `.switch`; **A Quantum Why** *one a day, from your ITP* · `why_at` · switch; **Evening** *what shifted today?* · `evening_at` · switch. Switches are `wire:click` toggles on `user_settings`; the times are `<input type="time">` styled as `.srow__value` (tapping shows the native picker). Notifications themselves are brief 5; this screen only stores the preferences. Mobile-only platform screens stay gated by `Runtime::isMobile()` (platform.md §8) — these preferences are not mobile-only.
- `h3.section-h` *The field* — **Still mode** *the light rests; nothing moves* · `.switch` bound to `window.itpField.setStill()` (Alpine, device-local); **Text size** *follows your phone* · value *on* (static).
- `h3.section-h` *About* — `.row` **About It's Totally Possible** → a static page or sheet with the Codex's one-paragraph description (write it as a Blade view; no new data).
- `h3.section-h` *Account* — `.srow` **Signed in** *{email}* with the existing sign-out form as a `.btn-sec` **Sign out**; and a *Delete account* link that opens the web build's deletion page in the browser (platform.md §5) — keep it if the current Account view has one, add it if not.

---

## 7. Shared pieces

- **Page fade on the web build** (platform.md §8 leaves it open): in `transitions.js`, when `!canAnimate()` (the web build), add `html.is-fading` on `livewire:navigating` and remove it on `livewire:navigated`; in `transitions.css`, `html.is-fading .app-page { opacity: 0 }` with `transition: opacity 200ms ease` on `.app-page`. No view transitions on web.
- **The light room's scroll** must not show a hard edge against the bar: `.app-page--with-tabs` already pads for the bar; keep it.
- **Copy** is in this brief verbatim. Curly quotes, one space after full stops, *italic* for the second action's emphasised word. Don't add explanatory copy ("What is an ITP?") — it lives in the beginning path (brief 6).

---

## 8. Tests (Pest; `php artisan make:test --pest`)

- `Home`: renders the right card state for a fixed hour and set of entries (test `setHour` with 7, 10 with/without a morning entry, 15 before/after `why_at`, 19 with/without evening, 22). Guest sees the Meet Your ITP Self card. Open generation shows the second card.
- `Prompt`: keeps an entry, replaces rather than duplicates for the same day and kind, rejects empty, guest redirected.
- `Library`: three tabs render; guest sees locked rows and the sign-in invitation on Personal; `#[Url]` tab round-trips.
- `ShowUniversalItp` / `ShowItp`: existing tests still pass; "Live with this one" writes `user_settings`; the page shows *You're living with this one.* afterwards.
- `You`: switches and times persist; sign-out still works.
- Arch test unchanged (nothing new references `Itp\Server`, AI SDKs, or secrets).
- Run `vendor/bin/pint --dirty --format agent` before finishing.

## 9. Done when

- `/`, `/itps`, `/universal/{slug}`, `/itps/{itp}`, `/you`, `/today/{kind}` all render in both rooms correctly (field present on Today, gone on the white rooms) at dawn, day, dusk and night (use `/design`'s clock to compare).
- The bar's four tabs highlight correctly on each; Return opens its sheet everywhere.
- Navigating between any two pages never flashes the wrong phase or restarts the light.
- Tests green; Pint clean; `npm run build` clean; `find vendor -type l` prints nothing.

## Later briefs

2 — the player (port from `itpmobile`: words timed to `attunement_timings`, the light on the words, stacked sessions, play records); 3 — speaking an answer (record → upload → transcribe on `itp-server`, editable text); 4 — the record; 5 — reminders and the Quantum Why notification; 6 — the beginning path and sign-in restyled; 7 — making a Personal restyled over the existing generation flow.
