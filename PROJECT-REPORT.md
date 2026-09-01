# Kidora — Project Report

Reverse-engineered reference for the `Kidora_MIRAR_` codebase. Written after reading
every PHP/JS/CSS file and walking the full signup → admin flow in a real browser
against the auto-seeded SQLite database.

Repo: `https://github.com/moeelbadri/Kidora_MIRAR_.git` (branch `main`)

---

## 1. What this is

**Kidora is an Arabic-language (RTL) gamified behaviour-and-skills platform for
children roughly aged 4–12.** Hero tagline: *"حيث يتحول التعلم إلى مغامرة بطولية"*
(where learning turns into a heroic adventure).

A child registers their own account (supplying a parent name + parent WhatsApp
number), picks two cartoon companion characters, then runs a daily loop of
tasks → historical-figure cards → mini-games → a generated personal story, while
the platform profiles their behaviour across six axes and reports progress to the
parent over WhatsApp.

Two defining design decisions:

1. **The companion character is the entire product skin.** One of the child's two
   chosen characters is "active" and appears on every page as a floating widget
   that walks across the bottom of the screen, speaks Arabic aloud via the browser
   `SpeechSynthesis` API, and delivers a different context-appropriate line per page.
   Its `color` + `icons_json` also drive the site-wide animated background gradient
   and the floating emoji, so swapping companion re-themes the whole app.

2. **Monetisation runs through WhatsApp, manually — there is no payment gateway.**
   Only the first two characters are free; the rest render dimmed with a 🔒.

---

## 2. Business model (character gating + manual WhatsApp upgrade)

| Step | What happens | Where |
|---|---|---|
| 1 | Child registers, may only pick `is_premium = 0` characters | `index.php` L62–110 |
| 2 | Free plan row auto-inserted as `active` | `index.php` L98–102 |
| 3 | Child presses "اشترك عبر واتساب" on a paid plan | `subscriptions.php` L10–31 |
| 4 | `subscriptions` row written as `pending`; `wa.me` deep link opened in a new tab with a pre-filled message (child name, age, plan, parent name + phone, email) | `subscriptions.php` L21–27, `whatsapp_link()` in `includes/functions.php` L95–103 |
| 5 | Admin confirms payment out-of-band, clicks "✅ ترقية" | `admin/tabs/subscriptions.php` |
| 6 | Row flips to `active` → premium characters unlock + VIP badge appears | `is_premium_active()` / `selectable_characters()` in `includes/functions.php` L82–93 |

- Operator WhatsApp number lives in `settings.whatsapp_number` (currently
  `972592038364`), editable from the admin Settings tab.
- Plans seeded: **البداية** (free) · **المستكشف** ₪49/month · **العائلة** ₪99/month.
- Every outbound message is also logged to the `wa_log` table (`log_wa()`).
- `is_premium_active()` deliberately requires `price_ils > 0`, so the free plan
  being `active` does not unlock premium characters.

---

## 3. The daily loop (page by page)

The flow is rigidly sequenced; each stage gates the next.

```
index.php (landing + login/register)
   ├─ register ─> subscriptions.php?welcome=1   FIRST screen after signup
   │                 │  welcome banner + "تخطّي الآن وابدأ مغامرتي" escape hatch
   │                 └─> welcome.php
   ├─ login, assessment due ─> welcome.php
   └─ login, otherwise      ─> dashboard.php    the real signed-in home (670 lines)
        └─> assessment.php  behaviour quiz — only every 10 days
             └─> tasks.php   4 age-filtered tasks, one at a time
                  │  after each task: story line + LINKED historical figure + themed game
                  └─> safety.php      (hard redirect once all 4 are done)
                       └─> games.php  play >= 2 games   (free plan: only 2 exist)
                            └─> story.php       PAID — free plan sees a paywall
                                 └─> grand-story.php   every 30 stories
```

Under the free plan the loop still runs end to end — missions, their mini-games, the
historical figures, safety, and the two library games are all free. What the paywall
holds back is the **daily story**, and with it the 30-story grand adventure.

`dashboard.php` is the post-login landing page for a returning child (welcome banner,
companion cards, plan status, and story recommendations keyed to the child's *weakest*
behaviour axis via a hardcoded `$RECO_MAP`). `welcome.php` is only the first-run /
assessment-due greeting.

### `assessment.php` — behaviour analysis
- Six axes: الثقة بالنفس (self-confidence), المهارات الاجتماعية (social skills),
  الذكاء العاطفي (emotional intelligence), الإبداع (creativity), التركيز (focus),
  الأمان الشخصي (personal safety).
- Three answers each, scored **1–3**, each answer inserted as a `quiz_history` row.
- Question set is pinned in the session (`$_SESSION['assess_qids']`) so leaving the
  page mid-quiz resumes rather than reshuffles.
- Gated by `needs_assessment()` (`includes/functions.php` L129–133): first run, then
  **every 10 days** via `children.last_assessment_at`.
- Results render as a bar chart expressed as a **level out of 3, deliberately not a
  percentage** (`assessment_axis_summary()` → `AVG(value)`).
- A button WhatsApps the full report to the parent (`profile.php`).

### `tasks.php` — the core
- 4 tasks drawn at random, filtered `age_min <= age <= age_max`, then **pinned for
  the day** in `daily_progress.task_pool_ids` so a refresh doesn't reroll them.
- **The mission package is topically coherent.** Each task row carries the whole
  package: `title`/`description` (read aloud on load), `story_line` (shown on
  completion), optional `youtube_id` (a video *about the mission*), `figure_id`
  (a historical figure related to the mission), and `game_type` + `game_title`.
- On completion: award `points`, then a session flash shows the story line + the
  **linked Arab/Islamic historical figure** (resolved by `figure_for_task()` in
  `includes/functions.php`), with optional YouTube embed, then a mini-game whose
  content is themed to the task's `category`.
- Figure resolution order: `tasks.figure_id` → a random active figure whose
  `history_figures.category` matches the task's category → any active figure. So
  admin-added tasks still get a sensible figure without manual linking.
- All 24 seeded tasks are explicitly linked (e.g. "اقرأ قصة قصيرة" → شهرزاد,
  "تحدي الحساب السريع" → الخوارزمي, "تحدي الابتكار" → عباس بن فرناس).
- Once all 4 are done: **hard redirect to `safety.php`** (`tasks.php` L51–53).
  Note the README's section 4 claims it redirects to the games library — the code
  redirects to safety.

### `safety.php` — child-protection module
Body autonomy, safe strangers, saying no, digital safety — presented as narrated
animated scenes plus a true/false game, sourced from `safety_content`.

### `games.php` — games library
36 seeded rows grouped into 6 categories (تربوي / علمي / اجتماعي / سلوكي / ثقافي / صحي)
× the 6 real mechanics, each with an icon + colour, age-filtered. Completion POSTs to
`api/play-game.php` which increments `daily_progress.games_played`. **No score is sent
to the server.** Each card passes its category to `GamesEngine.run()` via `data-*`
attributes, so a صحي game asks health questions and a ثقافي game asks heritage ones.

**Subscription gating (Sep 2026).** Without an active paid plan the child sees only
`FREE_LIBRARY_GAMES = 2` cards, picked by `visible_library_games()` to be **two
different mechanics** so the sample shows range, followed by one upgrade card naming
how many remain. The mini-game that follows each mission is **not** part of this
allowance — it belongs to the mission package and stays free.

### `story.php` — daily personal story
- **Paid feature.** Without an active plan the page shows a paywall card and the
  `generate_story` POST handler refuses (`$isPremium` is checked server-side, not
  just in the view). An already-generated story stays viewable if the plan lapses.
- Gated on `tasksDone && games_played >= FREE_LIBRARY_GAMES`.
- Child may upload a photo; scenes are assembled from the day's **actual completed
  tasks'** `story_line` values plus an opening and closing scene, saved to
  `daily_stories.scenes_json`, and `children.ring_days` is incremented (+10 points).
- Each scene carries `icon` (from `category_icon()` of the task) and `title` (the task
  title), and rendering passes `animate: true` plus the companion's first
  `icons_json` emoji as `spriteFace` — so the daily story autoplays with a floating
  companion and a per-scene chapter header, matching the grand story's presentation.
- Exportable as a real video file from the browser — Canvas + `MediaRecorder`,
  640×360, 2.2 s per scene, tries `video/mp4` then falls back to `video/webm`.
  No audio track, no paid AI service.

### `grand-story.php` — the 30-day payoff
Consumes **30** daily stories (`GRAND_STORY_DAYS`) and builds one "Grand Adventure"
from the child's *actual month*, not from concatenating scenes.
`child_achievement_summary()` reads `daily_progress` over the story date range plus
`quiz_history`, and `grand_story_scenes()` turns that into ~14 captioned chapters:
missions completed, the two strongest categories, the historical figures met (via
`tasks.figure_id`), games played, stars earned, assessment growth (first session
average vs latest), strongest axis, three sampled highlights from the daily stories,
and a finale. Both helpers live in `includes/functions.php`.
Scenes carry optional `icon` + `title`, which `StoryPlayer` renders as a floating
chapter header and also draws into the exported video.
This is what the `0/30` ring in the navbar tracks (`children.ring_days`).
Copy is deliberately **gender-neutral** (nominal sentences) because `children` has no
gender column — avoid adding verb forms that need agreement with the child's name.

### Other pages
- `friends.php` — per-character friend stories (**hardcoded** in JS, not DB).
- `culture.php` — Arab/Islamic cultural story bank (**hardcoded** in JS, not DB).
- `games2.php` — 4 self-contained canvas arcade games: Snake (الأفعى),
  Brick Breaker (ضرب الطوب), Flappy Bird, Road Race (سباق الطريق). Fully
  client-side, not in the `games` table, not admin-manageable.
- `profile.php` — editable profile, companion switcher, behaviour chart,
  story archive, WhatsApp report button.

---

## 4. UI / design system

Two distinct visual identities.

### Public landing (`index.php`, 1,145 lines — mostly inline `<style>`)
Modern dark-purple SaaS marketing page: `#0a061a` base with radial violet glows,
56px hero headline with gradient text, glassmorphism feature cards, embedded intro
video, character showcase, pricing tiers, and the login/register card at the bottom
as the `#auth` anchor target.

### Signed-in app
- **Background** (`#animated-bg` in `includes/header.php`, styled in `main.css`):
  a 4-stop gradient `#1B1035 → #241645 → #3A2A75 → #1B1035` animating on a 12 s
  loop, **recoloured live to the active character's colour**, overlaid with 20–30
  emoji rising from the bottom on per-character motion curves, plus 3 drifting wave
  layers. Net effect: bright cards floating on a deep animated night sky.
- **The background must stay dark — this is a load-bearing constraint, not taste.**
  All text on top of it is light (`#fff`, `#f1f5f9`, `#D9D0FF`), so any change that
  brightens the background makes text vanish as the gradient animates. Three
  mechanisms keep it dark, and they interact — see §8 "Contrast" before touching any
  of them:
  1. `ThemeEngine.updateBackgroundColor()` computes the lightest gradient stop and
     darkens it until its WCAG relative luminance is `<= MAX_BG_LUMINANCE` (0.075),
     scaling RGB so the character's **hue survives** while brightness is capped.
  2. `#animated-bg::after` is a flat `rgba(10,6,26,.42)` scrim above every animated
     layer — the guarantee that still holds for characters an admin adds later, and
     for the static CSS fallback if JS never runs.
  3. `glowPulse` peaks at `.28` opacity. It was `.7`; the glow blobs sit directly
     behind the hero text, so raising it past ~`.28` measurably drops the smallest
     text under 4.5:1.
- **Cards** are near-opaque white/cream (`rgba(255,255,255,.94)`) with heavy shadows
  and `backdrop-filter: blur(14px)`.
- **Motion vocabulary** — every character has a `move_type` that drives both the
  companion idle animation and the floating-icon animation:
  `wiggle | bounce | dash | float | hop | stomp`.
- **Typography**: Baloo Bhaijaan 2 (display) + Cairo (body) via `main.css`;
  `includes/navbar.php` separately imports **Tajawal** for the nav only.
- **Palette**: coral→pink gradient CTAs, mint for safety, violet for charts. The
  `--gold` token is **indigo `#818cf8`**, not gold — a rebrand renamed the value but
  not the token, so it disagrees with the literal `#fbbf24` hardcoded in
  `navbar.php`. ~20 surfaces read `var(--gold)` (auth tabs, character-card borders,
  `.eyebrow`, points-fly, story ring, admin tabs), so "correcting" it to gold is a
  redesign, not a cleanup. Left as-is with a comment at `main.css` L8.

### Navigation (`includes/navbar.php`, 1,007 lines — self-contained HTML+CSS+JS)
Fixed dark glass bar (`rgba(10,18,35,.92)` + 24px blur): logo, 30-day progress ring,
**5 primary links** (الرئيسية / مهامي / قصتي اليومية / قصص أصدقائي / ألعابي), a
"المزيد" dropdown opening as a **2-column grid** for the remaining 7, VIP/free badge,
and separate 🗣️ voice + 🔇 music toggles. Below 1024px it collapses to a
right-side sliding sidebar carrying all 12 links.

### Admin panel
Deliberately different: dark navy sidebar (230px) + cream content area, white cards
and tables. Styled by `assets/css/admin.css` (22 lines) layered on `main.css`.

---

## 5. Architecture

Plain **PHP 8 + PDO. No framework, no Composer, no build step.**
~6,530 lines across 36 PHP files, 5 vanilla-JS engines, 2 CSS files.

```
config/config.php     constants, BASE_PATH auto-detection, admin creds, TZ Asia/Gaza
config/db.php         kidaura_connect() — PDO; on first SQLite run creates schema + seeds
includes/functions.php shared helpers (auth, daily progress, characters, subs, uploads)
includes/header.php   <head>, animated background, KIDAURA_* JS globals, loads 4 engines
includes/navbar.php   nav + sidebar (self-contained)
includes/footer.php   companion widget + toast host + app.js
api/                  the only 2 AJAX endpoints
admin/                guard + tab router + 9 tab files
database/             schema.sql (MySQL) · schema_sqlite.sql · seed.php
```

### Database driver
- `DB_DRIVER` defaults to `sqlite`; `php -S localhost:8000` is genuinely all it takes.
- On first request `config/db.php` detects a missing DB file, executes
  `database/schema_sqlite.sql`, then runs `kidora_seed()` from `database/seed.php`.
- Production path: set `DB_DRIVER = 'mysql'` and run `database/schema.sql` once; the
  connect path then auto-seeds if `characters` is empty.
- **`database/seed.php` is the single source of seed data.** Both schema files are
  structure-only. (Until Aug 2026 they each carried their own `INSERT` blocks that
  ran *first* and silently made most of `seed.php` dead code — the count guards in
  `kidora_seed()` always saw non-zero. If you add content, add it to `seed.php`.)
- `kidora_migrate()` in `config/db.php` adds missing columns idempotently on every
  connect (`PRAGMA table_info` / `SHOW COLUMNS` then `ALTER TABLE`), so an existing
  database picks up new columns without a reset. It does **not** backfill content —
  to get new seed content into an existing dev DB, delete `storage/kidora.sqlite`.
- `storage/*.sqlite` is gitignored (`storage/.gitignore`).

### Page contract
Every logged-in page follows the same shape:

```php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();                       // redirects out if no session
$progress = ensure_daily_progress($pdo, $child['id']);
// ... POST handling, then always header('Location: <self>'); exit;  (POST-redirect-GET)
$__pageTitle = '...'; $__pageLine = '...';      // $__pageLine = what the companion says
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
// ... markup ...
require_once __DIR__ . '/includes/footer.php';
```

Session flash keys (`$_SESSION['flash_*']`) carry cross-redirect state:
`flash_story`, `flash_points`, `flash_figure`, `flash_game_type`, `flash_game_title`,
`flash_quiz_msg`, `flash_toast`, `flash_wa_link`, `admin_flash`.

### PHP → JS wire
| Global | Set in | Contents |
|---|---|---|
| `KIDAURA_ACTIVE_CHARACTER` | `header.php` L53–59 | `slug`, `color`, `icons[]`, `audio`, `name` — **note: no `move`**, so floating icons always default to `wiggle` in-app |
| `KIDAURA_BASE` | `header.php` L60 | `BASE_PATH` |
| `KIDAURA_CHILD` | `navbar.php` | `id`, `name`, `points` |
| `KIDAURA_PAGE_LINE` | each page | Arabic line the companion speaks on load |

### JS engines (`assets/js/`)
| File | Global | Role |
|---|---|---|
| `theme-engine.js` | `ThemeEngine` | `applyBackground()` / `previewCharacter()` — recolours gradient, sets `--theme-accent`/`--theme-glow`, spawns floating icons |
| `sound-engine.js` | `SoundEngine` | `speak()` via `SpeechSynthesis` (`ar-SA`, prefers an Arabic female voice), pre-speech chime, optional Web Audio background music. Toggles persist in `localStorage` (`kidaura_voice`, `kidaura_music`) |
| `story-player.js` | `StoryPlayer` | `render()` / `narrate()` / `share()` / `exportVideo()` — used by story, grand-story, friends, culture, profile. `opts.animate` adds autoplay (4.5 s/scene), a play/pause button, and a caption/chapter cross-fade via the `is-out` class; manual navigation cancels autoplay |
| `games-engine.js` | `GamesEngine` | `run(type, host, title, color, onDone, {category})` → `catch` / `match` / `quiz` / `reaction` / `memory` / `adventure`. **Content is fetched from `api/game-content.php`, not hardcoded** (a small `FALLBACK` bank exists only so a failed request never shows a broken screen). `game_types()` in `includes/functions.php` is the authoritative slug→label list |
| `app.js` | `companionSay()` | bootstrap: nav toggle, voice/music buttons, companion click + swap, auto page greeting |

**Age-adaptive play (Sep 2026).** `GAME_TIMER_MIN_AGE = 10` in
`includes/functions.php`. The server decides which version a child gets and returns
`calm` on the content endpoint — the client cannot opt into timers. When `calm`:

- `quiz` drops the countdown entirely and reads each question aloud via `SoundEngine`.
- `reaction` is **swapped for `match`**, because reaction time *is* the mechanic —
  there is no timer to remove. The displayed title is replaced too, so
  "سرعة القفز" does not promise a speed game the child will not play.
- `adventure` reads the situation and its choices aloud and waits longer on outcomes.
- `catch` spawns slower, `match` uses 4 pairs instead of 6, `memory` caps at 3 levels.

Read-aloud goes through `SoundEngine.speak()`, so the existing mute toggle still wins.

### API endpoints
All require `$_SESSION['child_id']` and have no CSRF token.
- `POST api/play-game.php` → `games_played += 1`, returns `{ok, games_played}`.
- `POST api/swap-companion.php` → toggles `active_character` between `character_1`
  and `character_2`, returns `{ok, active_character}`; client reloads the page.
- `GET api/game-content.php?category=<arabic>` → `{ok, age, calm, topic, label, icons,
  quiz, adventure}`. The Arabic category is resolved to a topic through
  `game_topics.categories_json` (unknown → `general`), rows are filtered by the
  child's age and returned in random order so replays differ. **Age comes from the
  `children` row, never from the query string** — the calm/timed split is a
  child-protection decision, not a client preference.

---

## 6. Data model

| Table | Purpose |
|---|---|
| `characters` | slug, name, title, trait, `color`, `move_type`, `image_path`, `audio_path`, `icons_json`, `is_premium`, `sort_order` |
| `children` | the user account: credentials, age, parent name/phone, `character_1/2`, `active_character`, `points`, `ring_days`, `last_assessment_at` |
| `tasks` | title, description, category, age range, `story_line`, `youtube_id`, `game_type`, `points`, `active` |
| `games` | title, `type`, category, age range, description, `is_active` |
| `game_topics` | `topic_key`, `label`, `icons_json`, `categories_json` (which Arabic task/game categories map to this topic), `active`, `sort_order` |
| `game_questions` | in-game true/false bank: `topic_key`, `question`, `answer`, age range, `active`, `reviewed` |
| `game_scenarios` | branching-adventure situations: `topic_key`, `prompt`, `choices_json` (`[{l,g,r}]`), age range, `active`, `reviewed` |
| `history_figures` | the post-task hero cards (+ optional `youtube_id`) |
| `quiz_questions` | the daily **assessment** questions: 12 axes × 3 options, each option has `value` (1–3) + `msg`. Distinct from `game_questions`, which is in-game content |
| `quiz_history` | append-only `(child_id, axis, value)` — the real behaviour data |
| `daily_progress` | one row per `(child_id, day_key)`: `task_pool_ids`, `completed_task_ids`, `games_played`, `quiz_*`, `story_generated` |
| `daily_stories` / `grand_stories` | generated story scenes as JSON |
| `safety_content` | protection module items |
| `subscription_plans` / `subscriptions` | plans + one row per child (`pending`/`active`) |
| `institutions` | partner school / org supervisors |
| `wa_log` | every WhatsApp message generated |
| `settings` | `whatsapp_number`, `platform_name`, `story_api_key` (key-value) |

Seeded volumes: 6 characters, 24 tasks, 36 games (6 mechanics × 6 categories),
12 assessment questions, 29 history figures, 4 safety items, 3 plans, 3 settings,
and — for in-game content — 9 topics, **180 true/false questions** (20 per topic) and
**72 adventure scenarios** (8 per topic).

Columns added Aug 2026 (both schemas + `kidora_migrate()`):
`tasks.figure_id` (the mission's linked historical figure) and
`history_figures.category` (matches the task category vocabulary, used as the
fallback when a task has no explicit link).

Tables added Sep 2026: `game_topics`, `game_questions`, `game_scenarios`. Existing
databases pick them up through `kidora_migrate()`, which checks for `game_topics` as
the sentinel (all three are created together), executes each `CREATE TABLE` **read
out of the schema file** — so the DDL is never duplicated in PHP — and then calls
`kidora_seed_game_content()`. That function is separate from `kidora_seed()` for
exactly this reason and is safe to re-run: each block seeds only if its table is empty.

`reviewed = 0` marks content authored by tooling rather than approved by a human. It
does **not** hide the row — `active` does that. All 252 seeded rows ship
`active = 1, reviewed = 0`, and the admin tab surfaces the pending count with a
per-topic bulk approve.

Characters: **mimo (ميمو)** and **zizo (زيزو)** are free; **finn, nova, lulu, rex**
are premium.

---

## 7. Admin panel

Entry `admin/index.php`, tab via `?tab=` against a whitelist. Auth is a single
boolean `$_SESSION['is_admin']`, set by comparing POSTed credentials with **plain
string equality** against constants in `config/config.php` L43–44
(`admin@kidora.com` / `admin123`).

| Tab | Capabilities |
|---|---|
| نظرة عامة (overview) | counts + active-subscriber-per-plan bar chart |
| المستخدمون (users) | sortable table (name/age/points), parent data, plan badge, per-child behaviour analysis |
| الشخصيات (characters) | full CRUD **incl. edit**, image + audio upload, toggle free↔premium |
| المهام (tasks) | add / delete / toggle active (no edit form) |
| الألعاب (games) | add / delete |
| محتوى الألعاب (gamecontent) | per-topic editor for `game_questions` + `game_scenarios`: add, approve, enable/disable, delete, and bulk-approve a whole topic. Shows which Arabic categories feed each topic |
| أسئلة التحليل (assessment) | `quiz_questions` editor: add (axis autocompletes from existing axes), enable/disable, delete |
| الشخصيات التاريخية (history) | add / delete |
| الاشتراكات (subscriptions) | approve / reject pending requests with direct WhatsApp link, add/delete plans |
| المؤسسات (institutions) | add / delete partner orgs |
| الإعدادات (settings) | WhatsApp number, platform name, future story API key |

Character media is stored per-character in `assets/images/characters/{slug}/` and
`assets/audio/characters/{slug}/` via `character_media_dir()`.

---

## 8. Known defects and inconsistencies

All verified against the running app, not inferred.

### Blocking / visual
1. ~~**The companion avatar is forced to `40vh` (widget `45vh`) by inline CSS in
   `includes/footer.php`, overriding the `118px` in `main.css`.**~~
   **RESOLVED Aug 2026** — the entire inline `<style>` override block was deleted, so
   `main.css` wins again (118px desktop / 88px mobile, circular badge with its border
   and shadow intact). If a giant avatar ever reappears, look for a new inline block
   in `footer.php` before touching `main.css`.
2. ~~`footer.php` also defines a `.move-walk` keyframe that PHP never emits.~~
   **RESOLVED Aug 2026** — removed together with the override block above.

### Contrast — FIXED (Aug 2026), keep it that way
Text used to disappear whenever the animated gradient's lightest phase swept under
it. Root cause was **not** the static palette: `ThemeEngine.updateBackgroundColor()`
took the character colour and *lightened* it 35%, so it painted a background far
brighter than the near-black `#0a061a` the page was designed against. Zizo (gold
`#FFC93C`) was worst at **1.1:1** — invisible.

Fixed by the three mechanisms in §4 plus, on the landing page: light-on-light
gradient-text stops (`#7c3aed` → `#c4b5fd`), `text-shadow` on the hero, and
`--text-muted` raised `#8b7aa8` → `#b9abd4` (it was 2.7:1 on 13px labels, the footer
and the disclaimer line).

Measured on all 6 seeded characters plus a pure-white worst case, at 8 phases of the
12 s loop, sampling the **lightest** background pixel behind the hero:

| text | needs | before | after |
|---|---|---|---|
| `h1` `#ffffff` | 3.0 | 1.1–4.3 | **10.4–12.1** |
| subtitle `#f1f5f9` | 3.0 | fails | **9.5–11.0** |
| body `#d9d0ff` | 4.5 | fails | **7.1–8.3** |
| small `#b9abd4` | 4.5 | 2.7 | **4.87–5.65** |

`#b9abd4` at 4.87 is the binding constraint — it is what stops the background being
made lighter or the glow brighter. Re-measure it before changing the scrim, the
luminance ceiling, or `glowPulse`.

Two gotchas found while fixing this:
- Use `background-image`, never the `background` shorthand, on gradient-clipped text.
  The shorthand resets `background-clip` to `border-box`, so the gradient renders as
  a solid rectangle instead of being clipped to the glyphs.
- `--text-secondary` (`#c4b5d4`, 5.4:1) already passed and was left alone; only the
  `muted` step needed raising, which compresses the muted/secondary hierarchy
  slightly. That is intentional.

### Data / logic
3. ~~**Assessment length**: only 6 `quiz_questions` seeded, so the UI showed
   "سؤال 1 من 6".~~ **RESOLVED Aug 2026** — 12 questions now seed from `seed.php`
   (the 6-row version lived in the schema files' `INSERT` blocks, which are gone).
4. **Ring denominator mismatch**: `admin/tabs/users.php` L36 prints
   `ring_days . '/10'` while the navbar, `grand-story.php`, and the README all use
   **30**. *(Still open.)*
5. ~~**Unhandled game types**: `reaction` / `memory` / `adventure` silently fell back
   to `catch`.~~ **RESOLVED Aug 2026** — all six mechanics are implemented in
   `games-engine.js`. `reaction` is a 5-round tap-on-green timer, `memory` is a
   Simon-says sequence (no longer a duplicate of `match`), `adventure` is a 3-scene
   branching choice story. `game_types()` in `includes/functions.php` is the single
   source of the slug list and both admin tabs whitelist against it.
6. ~~**Game variety is thinner than the catalogue implies**: one hardcoded
   8-question bank regardless of title, category or age.~~ **RESOLVED Aug 2026** —
   content is topic-driven. Nine topic banks (learn / health / values / social /
   creative / culture / safety / life / general), each with 8 icons, 8 true-false
   questions and a 3-scene adventure. `TOPIC_BY_CATEGORY` maps both the task
   vocabulary (تعلّم، صحة، قيم…) and the games-library vocabulary
   (تربوي، علمي، سلوكي…) onto them.
7. **Orphaned character art**: images exist at
   `assets/images/characters/mimo/u_6a8ad4cbd08a9.png` and
   `assets/images/characters/zizo/u_6a8ad4f849c2a.png`, but **every**
   `characters.image_path` is `NULL`, so all six characters render as emoji fallbacks.
8. `KIDAURA_ACTIVE_CHARACTER` omits `move`, so in-app floating icons always animate
   as `wiggle` regardless of character. Only the registration preview passes `move`.
9. `SoundEngine.playCharacterClip()` (the only consumer of uploaded character audio)
   is exported but **never called**, so admin-uploaded voice files are unused.

### Content not admin-manageable (contradicts the "admin controls everything" claim)
10. `friends.php` L31 `FRIEND_STORY_BANK` and `culture.php` L25 `CULTURE_STORY_BANK`
    are hardcoded JS objects. **Still open** — these are the last two hardcoded
    content banks; `games-engine.js` was moved to the database in Sep 2026 and is the
    worked example to copy.
11. `games2.php`'s 4 arcade games are hardcoded canvas implementations outside the
    `games` table.

### Security
12. Hardcoded plaintext admin credentials in source; no `session_regenerate_id()`,
    no rate limiting, no lockout.
13. **No CSRF token on any form**, admin or child-facing. A logged-in admin's browser
    can be induced to approve subscriptions or delete content cross-site.
14. `save_upload()` (`includes/functions.php` L111–119) validates by **file extension
    only** — no MIME check, no size limit. Directories are created `0777`.
15. `admin/tabs/*.php` are not self-guarded (they rely on `admin/index.php` having
    required `guard.php`), so the guard is architecturally load-bearing on one line.
16. `admin/logout.php` only unsets `is_admin`; it does not destroy the session, while
    its nav label says "تسجيل خروج للمنصة" (log out of the platform).
17. `settings.story_api_key` is stored in plaintext.

### Naming / docs debt
18. Half-finished rename from **Kidaura → Kidora**: `kidaura_connect()`, the
    `KIDAURA_DB_*` env prefixes, and the `KIDAURA_*` JS globals still say Kidaura,
    while `DB_NAME` defaults to `kidora` and `SQLITE_PATH` is `storage/kidora.sqlite`.
    The README still documents `storage/kidaura.sqlite` and
    `mysql … kidaura < database/schema.sql`.
19. README internal contradiction on the post-task destination (top "latest updates"
    says safety, section 4 says games library). Code does safety.
20. `main.css` `--gold` is overridden to indigo with `!important` while the navbar
    hardcodes the original gold — the token no longer means what its name says.

### Found and fixed during the Aug 2026 mission-package pass
These were not in the original list; recorded so they are not reintroduced.

21. ~~**`grand-story.php` gated on 30 pending stories but merged only
    `array_slice($pending, 0, 10)`.**~~ The child waited 30 days for a story built
    from 10, its opening caption said "رحلة عشرة أيام", and 20 stories were left
    stranded as pending forever. Now gated and consumed at `GRAND_STORY_DAYS = 30`.
22. ~~**The post-task historical figure was `ORDER BY RANDOM() LIMIT 1`**~~, so a
    child who tidied their room could get Al-Khwarizmi and algebra. Now resolved
    through `figure_for_task()` (explicit link → category match → any).
23. ~~**`ORDER BY RANDOM()` is SQLite-only** and would have thrown on the MySQL
    production path.~~ Now goes through `sql_random()`.
24. ~~**Both schema files carried their own seed `INSERT` blocks** that ran before
    `kidora_seed()` and made most of `seed.php` unreachable.~~ Schemas are
    structure-only; `seed.php` is the single source. See §5.
25. ~~`games.php` built its play button with `h(addslashes($title))` inside an
    `onclick` attribute~~ — a title containing an apostrophe would have broken the
    handler. Now passes `data-*` attributes and `playGame(this)`.
26. ~~`tasks.php` passed the *task* title to the mini-game instead of the seeded
    `game_title` column~~, so "التقط النجوم" and the rest were never displayed.
27. ~~`admin/tabs/games.php` offered a `jump` game type that no engine implements,
    and did not whitelist the POST value.~~ Both admin tabs now render and validate
    against `game_types()`.

### Found and fixed during the Sep 2026 age/gating/content pass

28. ~~**Timed games reached children as young as 4.**~~ `quiz` ran a 25 s countdown and
    `reaction` scored milliseconds, and `GamesEngine.run()` had no age input at all, so
    a 4-year-old and a 12-year-old played identical games. Age now comes from the
    server (`GAME_TIMER_MIN_AGE = 10`); see §5 for what changes under `calm`.
29. ~~**Games were silent.**~~ `SoundEngine.speak()` existed and worked in Arabic but
    `games-engine.js` never called it, so a pre-reader could not play a text game.
    Questions, adventure prompts, choices and outcomes are now read aloud under `calm`.
30. ~~**Every child saw the whole games library and could generate a daily story, paid
    or not.**~~ `is_premium_active()` only guarded character selection and the VIP
    badge. Now the library shows 2 games without a plan and `story.php` refuses the
    POST — see §3.
31. ~~**The daily story was a static, manually-clicked slideshow.**~~ `StoryPlayer`
    already supported chapter icons, titles and a floating sprite — the grand story
    used them, the daily story passed only `caption` and `grad`. It now passes all
    three and enables autoplay.
32. ~~**Game content was 72 questions and 27 scenarios hardcoded in JavaScript**~~, so
    a topic recycled its 8 questions immediately, `general` was 7/8 duplicated from
    other banks, replays were identical, and nothing was editable. Content is now
    180 questions / 72 scenarios in the database, age-filtered and randomly ordered.
33. ~~**`quiz_questions` had no admin screen at all**~~ — the assessment that drives
    every growth axis could only be changed with raw SQL.
34. ~~**Luqman's advice was framed as "أولها" (the first of his commandments)**~~,
    which conflicts with the Qur'anic list. Reworded as "ومن أشهر ما يُروى عنه" — the
    honest framing for an *adab*-literature aphorism.
35. ~~**Shahrazad was presented as a historical figure**~~ under a card reading
    "تعرّف على بطل من تاريخنا". Her description now opens by saying she is from the
    tales and not from history, and the card reads "شخصية من تراثنا", which is true of
    all 29 entries.
36. ~~**Narration assumed the child's gender.**~~ `children` has no gender column, yet
    4 figure lines said "تعلّمت البطلة" (addressing every boy as female) and the other
    25, all 24 task lines, the `story.php` opener/closer, and the admin default
    `story_line` used masculine verbs bound to the child's name. All of it is now
    nominal phrasing with no verb agreement. **Do not reintroduce a bound verb here**
    — the grand story already worked this way and the two must match.
37. ~~**The mechanic swap left a misleading title.**~~ Swapping `reaction` for `match`
    kept the label "سرعة القفز", promising a speed game.

**Still open from the content review** (not fixed, needs product input): all 24
missions have `youtube_id = NULL` despite the mission package specifying a video;
several history figures — mostly the female ones — are reachable only through the
category fallback because no task links to them; and the 252 seeded content rows are
`reviewed = 0` pending a human pass.

---

## 9. Running it locally

```bash
cd /root/download/Kidora_MIRAR_
php -S localhost:8000
# open http://localhost:8000 — SQLite DB is created and seeded on first request
```

Requirements: PHP 8 with `pdo_sqlite` (verified on PHP 8.5.4). No npm, no Composer.

- Admin: `/admin/login.php` — `admin@kidora.com` / `admin123`
- To reset all data: delete `storage/kidora.sqlite` and reload.
- For MySQL: run `database/schema.sql` once, then set `DB_DRIVER`/`DB_*` in
  `config/config.php` (or `KIDAURA_DB_*` env vars).
- If deployed under a subfolder, `BASE_PATH` auto-detects, but can be pinned in
  `config/config.php`.
- `uploads/`, `assets/images`, `assets/audio` must be writable for uploads to work.

### Dokploy (this host)

Root `Dockerfile` (`php:8.3-apache`, `pdo_mysql`) is what Dokploy builds. There is
no Composer/Nixpacks path. Project **kidoora** on the local Dokploy (`:3000`)
already points at `moeelbadri/Kidora_MIRAR_` `main`. Production DB is host MySQL
via `KIDAURA_DB_*` (`DB_NAME=kidoora`). MySQL does **not** auto-create tables —
run `database/schema.sql` then `kidora_seed()` once. Persist `uploads/` and
character media with Dokploy volumes; do not mount over all of `assets/` (that
hides CSS/JS). Traefik listens on host **81/444**; aaPanel nginx on 80/443 must
proxy the public hostname to `127.0.0.1:81`.

---

## 10. Conventions to follow when changing code

- **Arabic RTL first.** All user-facing copy is Arabic; `<html dir="rtl">`. Keep new
  strings Arabic and test with RTL layout.
- **Escape all output with `h()`** (`htmlspecialchars`) — the codebase is consistent
  about this; don't break the pattern.
- **Always use prepared statements.** The one interpolation
  (`users.php` `ORDER BY {$sort}`) is guarded by a whitelist; keep that idiom.
- **POST-redirect-GET**: POST handlers end with `header('Location: <self>'); exit;`
  and pass state via `$_SESSION['flash_*']`.
- **Styling lives inline per page.** `main.css` holds shared tokens/components;
  page-specific CSS goes in that page's `<style>` block. `navbar.php` and
  `footer.php` are self-contained (HTML + CSS + JS in one file).
- **Whitelist enumerated POST values.** For game mechanics, validate against
  `game_types()` in `includes/functions.php` — both admin tabs already do.
- **Adding a game mechanic** means three coordinated edits: a `run*()` branch in
  `assets/js/games-engine.js`, an entry in `game_types()`, and seed rows in
  `database/seed.php`. The admin dropdowns and validation follow `game_types()`
  automatically.
- **Adding a task** should set `figure_id` and `youtube_id`; if you leave `figure_id`
  null, give the figure a `category` matching the task's so the fallback still pairs
  them sensibly.
- **All seed content goes in `database/seed.php`**, never in the schema files.
- **In-game content belongs in the database**, not in `games-engine.js`. Add rows to
  `game_questions` / `game_scenarios` (or use the محتوى الألعاب admin tab). The
  `FALLBACK` object in the engine is a request-failure safety net — do not grow it
  into a content bank. A new topic needs a `game_topics` row whose `categories_json`
  lists the Arabic task/game categories it serves.
- **Never let the client decide the age split.** `api/game-content.php` reads age from
  the `children` row on purpose; removing timers for under-10s is a protection rule.
- **Narration must not assume gender.** `children` has no gender column, so any copy
  that sits next to the child's name — task `story_line`, figure `story_line`, story
  captions — has to be a nominal sentence. See §8 item 36.
- **Two question tables, different jobs.** `quiz_questions` is the daily assessment
  that feeds `quiz_history` and the growth axes; `game_questions` is true/false
  content inside mini-games. They are not interchangeable.
