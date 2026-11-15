# ITP redesign — visual direction (Nov 2026)

**The whole app, assembled (15 Nov):** `docs/design/assembly.html` (open it in a browser; tap "map" for the index of screens; the clock chip sets the hour). This is the reference for the build. The spine it follows: `docs/design/what-we-ask.md`. The build: `docs/design/system.md` (what's in the repo) and `docs/design/build-brief-1-the-rooms.md`.

Earlier living sketches (claude.ai artifacts, each exploring one area in more motion than the assembly): ante-room (light, time of day, word reveal), threshold as the day, record, player, Breathe 11, making a Personal ITP, library + an ITP's page. The assembly supersedes them.

Content source of truth: `docs/codex/creative-codex-v6.md`. Real intake fields and status machine: `CreateItp`, `GenerationStatus`, `ShowGeneration`. Universal content: `resources/content/snapshot.json` (13 Universals; four flagged `is_preview`: Awaken Wonder, Nervous System Reset, Abundance, Healing). How the field and bar persist across Livewire pages, and what the stack can do: `docs/architecture/field-persistence.md`.

## Concept

- The app is a **portal**, not a library. The home screen is a threshold with one invitation, not a dashboard of cards.
- **Live with one ITP at a time** (decided 15 Nov; see `what-we-ask.md`). The ITP is one transmission at three sizes — attunement, its Breathe 11, its Quantum Why. "Living with · *The Quiet Yes*" sits under the greeting on Today as the organising object.
- **Magical realism** is the register: the world is ordinary and beautiful; the light moving through it is the magic. The rays and motes are the one element that exists in every lit scene, so crossing a threshold carries the light with you.
- **Light rooms and white rooms.** Today, the player, Breathe 11, the Why, the making and the beginning are made of light; Your ITPs, an ITP's page, the record and You are white rooms. Leaving the light for white is itself the transition.
- **The field of light is the identity.** Tested against full photographs in the player (10 Nov): the coloured, time-of-day field was the stronger element and the photos the weakest. Photography is optional in the player, never required, and never in the shell.
- Explanatory copy ("What is an ITP?") moves to the beginning; it does not repeat on Today.
- **The daily rhythm is the information architecture** (Codex §23). Two anchors and an open middle: morning enters the ITP then asks the one question; midday offers a way back (its Breathe 11, Return to Coherence) and the Quantum Why arrives; evening asks what shifted and hands the Why back. The aligned step is optional, never a tracked stage.
- **Legibility rule:** atmosphere may be subtle, information may not. No informational text under 16px, body 18px, 48px touch targets, soft text only for truly secondary lines; scale with the phone's text size.

## Navigation (decided 15 Nov 2026)

- **A bottom bar of four: Today · Return · ITPs · You.** Replaces the logo-mark menu on the card (too subtle for older audiences once the app had real rooms). "Create a personal ITP" and "search" were proposed for the bar and rejected: making a Personal is a once-in-weeks act and lives on the Personal tab; thirteen Universals don't need search — the three tabs are the search.
- **Return is not a page.** It slides up a small sheet from anywhere: Return to Coherence · the ITP's Breathe 11 · the afternoon's Why. The thing a dysregulated person must find without thinking.
- **You** holds: your record, what you're living with, the day's three reminders (morning · a Quantum Why · evening, each with a time and a switch), still mode, text size, about, account. **No feedback form and no "pool"** (removed 15 Nov).
- The bar hides inside the player, Breathe 11, the Why, the making and the beginning — places you are *in*, not places you navigate from.
- **The bar must read at every hour** (fixed 15 Nov after the icons vanished at dawn/dusk): glass over the field with dark ink in the warm hours and by day, pale ink on dark glass at night, white with a rule in white rooms. No filled icons; labels always.

## Materials (decided 10 Nov 2026)

- **Pure white cards on the colour field** (not off-white, not tinted glass). Three materials, three jobs: **light** for the field, **white** for anything read or written, **image** (optional) for the player.
- **The attunement itself is first read in light on the field**, not on white. White cards hold the actions around it.
- **The library and the other white rooms are plain white (decided 14 Nov).** No field, no fade.
- Glass remains for small chrome only: the bar, player controls.
- **Accent: a real gold, not orange (decided 15 Nov).** `#b08d3c` on white for switches, the selected tab's underline, hover. The earlier `#c9772f` read as orange. The assembly has an "accent" chip to compare gold · ink-blue (`#2f4f9e`) · the old orange.

## Product structure (decided 10 Nov 2026)

- **Audio + visual pieces** (play in the player): Universal Attunements, Personal ITPs, Primers (Return to Coherence, Highest Possible Outcome), Breathe 11s.
- **Breathe 11s and Quantum Whys belong only to Attunements and Personals**, never stand alone, and are never shown in the review (the companions arrive with the voice).
- **Prompt pieces** (user answers): Morning question, One Aligned Step (optional), Evening Reflection, Celebrate the Evidence. Answers can be **spoken (recorded and transcribed) or written**.
- **The evidence record is persistent**; part of the gentle gamification.
- **Rhythm is tracked but not aggressive**: three reminders, user-set, one tap to silence; the Quantum Why is content, not a nag. State is known but never nags.
- **Stacking in the player**: a session may be a sequence of pieces with light handing over between them ("Continue · Breathe 11" after the seal).
- **A beginning path** for new users: Enter the field → Meet Your ITP Self (a few days) → Choose one to live with (the four open Universals) → make your own. Shown only while new.
- **Pacer variants** for Breathe 11: *light* (preferred), *petals* (optional later), plain circle.
- Player gifts from the Codex: "Beloved One" opening; the closing seal gets a soft full-screen bloom on each line.

## The library and an ITP's page (sketched 10–14 Nov)

- **Your ITPs: a white room with three tabs — Universal (13) · Personal · Primers.** One-line note under each tab. Rows: thumbnail (optional) · short name · full title · duration. Guest view: Personal tab invites sign-in; non-preview Universals show a lock but stay visible and tappable.
- **An ITP's page** (the `universal/{slug}` route; same template for a Personal), on white: back · small emblem (optional) · short name · full title · the "Feeling…?" description with its question in italic · **Enter** · "Live with this one instead" (or "You're living with this one") · Breathe 11 and A Quantum Why as rows · three folds: *The words*, *The eleven*, *Quantum Whys*. For a guest on a locked Universal, Enter becomes "Sign in to listen".
- **The words are readable before listening**, including on locked Universals; the lines and Whys too. Open to reversal.
- **No large hero image on the page** (decided 11 Nov). Images survive only as thumbnails/emblems; whether they mean anything is still open.

## Making a Personal ITP (sketched 10 Nov; approved)

The real flow: intake → queued/classifying/assessing → (awaiting_followup ⇄ assessing) → generating → awaiting_resonance ⇄ regenerating → accept → deriving_companions → complete → sync. Held: deferred, blocked, failed. Co-authored in up to three sittings (intake, follow-ups, review); the waits are only the gaps.

- **Begin**: white card, "Made from your own words." Five questions, speak or write. Address style previewed as the first line: *Beloved Aaron,* / *Beloved One,*.
- **Questions one at a time** in light, help line beneath, points of light above (five large = required, four small = optional). The nine real fields in order: dedication, challenge, old story, phrase (opt), what it protected (opt), vision, scene or sign (opt), soulful, anything to leave out (opt). Optional questions arrive as offers with *Pass, for now*.
- **Speak**: listening ring → "hearing you" → editable transcript in the white card → *Say it again* / *Keep*.
- **Waits are rooms** with honest stage names and light gathering inward; each says you can leave and offers Breathe 11.
- **Follow-ups** use the same form, framed *Before I write, may I ask…*.
- **The review**: the attunement in light, section by section, "it has no voice yet"; per section *this doesn't feel true yet* → flag card → *Write it again* → two versions → *Use this* / *Keep the first*. Accept disabled while a rewrite is pending.
- **Accept → the long wait**: *Sealing the words → Recording the voice → Making your Breathe 11 and Quantum Whys*.
- **Ready**: title in italic, "It has your voice in it now. Two companions came with it," *Enter* / *Later*.
- **Paused (deferred)**: the approved warm-hold wording ("Someone will read what you shared. Not a machine — a person…").

## Voice input (decision 10 Nov 2026)

- **Record on device, upload, transcribe on `itp-server`** behind a `Transcriber` interface (Mistral Voxtral first; Nova-3/Whisper alternatives). NativePHP microphone plugin (M4A) on mobile; MediaRecorder on web. Same Livewire upload.

## The player (sketched 10 Nov)

- Entrance: the field floods toward white; the words appear in it; the rays never stop. ~3.5 s from Enter to first word. Photograph optional (slow push-in, rays tinted from the image). Open question: upcoming words invisible vs faintly present.

## The record (sketched 10 Nov; revisit in white)

- The day's arc is the unit; a star above a celebrated day. No streaks. Week first, month on request. **One from earlier** resurfaced with "Something to notice." Empty day: "Nothing kept this day. That's allowed." Long spoken entries → summary line with full text beneath.

## Light

- Living light: soft drifting colour forms, **vertical rays born at the horizon** breathing on their own periods (canvas, 30 fps), **motes** rising, grain. **Stars at night come and go** (every star on its own period; one in nine sparkles). **Clouds: tried and rejected (14 Nov).**

## Time of day

- **Dawn (5–8) and dusk (17–20): one palette, a sun** — `#d8432c` → `#ea6a2d` → `#f9932c` → `#ffc64d`. No dark reds, no purple.
- **Day (9–16):** flipped — warm white at the top (`#f9f7f2`) to blue at the bottom (`#4a8fcf`) so the rays show. White light only; blue word-light; dark ink. **Gold in the day field was tried and rejected.** (Gold survives only as the accent on white.)
- **Night (21–4):** near-black indigo, faint blue horizon, breathing stars.

## Type

- One bundled variable serif, **Fraunces** (`@fontsource-variable/fraunces`). Fallback: Palatino, Book Antiqua, Georgia.

## Word reveal (the sacred part)

- Whole words arrive at their spoken moment; ~two letters in three carry a soft ray (quick rise ~0.45 s, long release, ~3.2 s total, reach ≈ 12× letter height); upcoming words invisible; spoken words rest with afterglow. Timing from `audio.currentTime` each frame. Transform/opacity only.

## Breathe 11 (sketched 10 Nov)

- Five in, five out, eleven breaths. Words arrive on the in-breath, hold on the out-breath. Counter in words. Ends "rest — eleven breaths, eleven lights."

## Known risks

Test the field on mid-range Android early and ship still mode first-class; it is an audio app — lock-screen/background audio shapes the player more than any visual; symmetry fatigue over weeks; a bar makes it more "app" and less "portal" — keep it quiet.

## Build order

Layout + field + bar (done, `system.md`) → Today → player → Your ITPs + ITP page → You + reminders → Breathe 11 + Why → making → record → beginning + sign-in. Brief 1 covers Today, Your ITPs, an ITP's page and You.
