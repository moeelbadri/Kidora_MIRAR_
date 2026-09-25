# Kidora — Project Report

Reverse-engineered reference for the `Kidora_MIRAR_` codebase. Written after reading
every PHP/JS/CSS file and walking the full signup → admin flow in a real browser
against the auto-seeded SQLite database.

Repo: `https://github.com/moeelbadri/Kidora_MIRAR_.git` (branch `main`)

---

## 1. What this is

**Kidora is an Arabic-language (RTL) gamified behaviour-and-skills platform for
children of any age the family chooses, 1–60** (`CHILD_AGE_MIN` / `CHILD_AGE_MAX` in `includes/functions.php`). The stored age does not hide missions, library games, safety lessons, or in-game questions. Games have no countdown timers. Hero tagline: *"حيث يتحول التعلم إلى مغامرة بطولية"*
(where learning turns into a heroic adventure).

A child registers their own account (supplying a parent name + parent WhatsApp
number), picks **one** companion character (the server assigns the second free one
automatically; both are switchable from the profile), then runs a daily loop of
tasks → historical-figure video → mini-games → safety mission or a generated personal
story, while the platform profiles their behaviour across six axes and reports
progress to the parent over WhatsApp.

Two defining design decisions:

1. **The companion character is the entire product skin and the child's guide.** One
   of the child's two characters is "active" and appears on every page as a large
   floating widget (`assets/js/companion.js`, `Companion` API) that walks slowly
   across the bottom of the screen, has moods (`talk|cheer|think|wave|point`),
   speaks Arabic aloud via `SpeechSynthesis` (background music pauses while it
   talks), reads questions/tasks/stories, announces every transition and celebrates
   every win. Its `color` + `icons_json` + `theme_json` drive the site-wide animated
   background gradient, the floating emoji **and a per-character "motif" layer**
   (bubbles for SpongeBob's Bikini Bottom, leaves for Dora's jungle, webs for
   Spider-Man…), so swapping companion re-themes the whole app into that world.

   The Sep 2026 character set is **SpongeBob, Dora, Gumball, Ladybug, Spider-Man,
   Batman, Ben 10, Detective Conan** (`kidora_characters_v2()` in `database/seed.php`;
   SpongeBob + Dora free). The shipped avatars are **placeholder SVGs** under
   `assets/images/characters/{slug}/avatar.svg` — the names are third-party IP and
   real artwork must be licensed and uploaded through the admin Characters tab.

2. **Monetisation runs through WhatsApp, manually — there is no payment gateway.**
   Every account gets seven full days of the whole platform. After that, stories and
   both game libraries stay open; missions, safety, assessment, drawing, and premium
   companions require a paid plan. A locked companion is only hidden, never forgotten.

---

## 2. Business model (seven-day trial + manual WhatsApp upgrade)

| Step | What happens | Where |
|---|---|---|
| 1 | Child registers and may pick any companion; the server keeps another free companion in `character_2`. `trial_ends_at` is written as now + 7×24h in `Asia/Gaza` | `index.php` |
| 2 | Free plan row auto-inserted as `active`; it does not itself grant full access | `index.php` |
| 3 | For seven days, `has_full_access()` is true: tasks, safety, assessment, drawing, and every companion are open | `includes/functions.php` |
| 4 | Child presses "اشترك عبر واتساب" on a paid plan | `subscriptions.php` |
| 5 | `subscriptions` row written as `pending`; `wa.me` deep link opened in a new tab | `subscriptions.php`, `whatsapp_link()` |
| 6 | Admin confirms payment and clicks "✅ ترقية" | `admin/tabs/subscriptions.php` |
| 7 | Paid plan (`price_ils > 0`, `active`) keeps full access after the trial. Admin can also set one child's `trial_ends_at` from the Users tab | `has_full_access()`, `admin/tabs/users.php` |

- Operator WhatsApp number lives in `settings.whatsapp_number` (currently
  `972592038364`), editable from the admin Settings tab.
- Plans seeded (fresh DB only): **البداية** (free) · **الشهرية** ₪15/month ·
  **السنوية** ₪150/year. Seed values never overwrite an existing database — the
  admin Subscriptions tab has a full **edit** form (name, price, cycle, features one
  per line, display order), and that is how the live plans are meant to be changed.
  Deleting a plan that has subscriptions is refused (it would orphan `subscriptions.plan_id`).
- Every outbound message is also logged to the `wa_log` table (`log_wa()`).
- `is_premium_active()` deliberately requires `price_ils > 0`, so the free plan
  being `active` does not unlock premium characters.
- Existing accounts with a null `trial_ends_at` are measured from `created_at`.
  There is no fresh seven-day grant on deploy. An admin date overrides that default.

---

## 3. The daily loop (page by page)

The flow is rigidly sequenced; each stage gates the next.

```
index.php (landing + login/register, age 1–60, ONE character)
   ├─ register ─> welcome.php   full-screen animated greeting: companion + child's
   │                            photo, spoken lines, auto-continues (no subscription
   │                            prompt here any more)
   ├─ login during trial, assessment due ─> welcome.php ─> assessment.php
   └─ login after trial or otherwise ─> dashboard.php    the signed-in home
        └─> assessment.php  SILENT quiz: companion reads each question, answers go
             │              to api/assess-answer.php, no progress/feedback shown,
             │              auto-redirect to tasks.php. Results only in admin.
             └─> tasks.php   client-side state machine (api/complete-task.php):
                  │  task → companion narrates story_line + pair line → figure video
                  │  autoplays (KidoraYT) → themed mini-game → next task. No page
                  │  reloads, no «متابعة» button.
                  └─> games.php?from=tasks   companion announces «game of the day»
                       │  (deterministic per child/day); after ANY game a choice modal:
                       ├─> safety.php   3-step mission: rule (+video) → game → medal
                       └─> story.php    free — one-scene story of the real day
                                          └─> grand-story.php  every 30 stories, 8 chapters
```

Once tasks + a game + (safety **or** story) are flagged done for the day
(`localStorage` `kidora_done_*`), `dashboard.php` shows the spoken goodbye modal.

During the seven-day trial the loop runs end to end. After it, an unpaid child keeps
every story surface (`story.php`, `friends.php`, `culture.php`, `grand-story.php`)
and both game pages (`games.php`, `games2.php`). Tasks, safety, assessment, and
drawing render the shared Arabic upgrade card, and their APIs return 403.
`STORY_MIN_GAMES = 1`: one free game unlocks today's story. Completed missions enrich
that story, but they are no longer required to create it. The goodbye modal appears
only while full access is active.

`dashboard.php` is the post-login landing page for a returning child (welcome banner,
companion cards, plan status, and story recommendations keyed to the child's *weakest*
behaviour axis via a hardcoded `$RECO_MAP`). `welcome.php` is only the first-run /
assessment-due greeting.

### `assessment.php` — behaviour analysis
- Six axes: الثقة بالنفس (self-confidence), المهارات الاجتماعية (social skills),
  الذكاء العاطفي (emotional intelligence), الإبداع (creativity), التركيز (focus),
  الأمان الشخصي (personal safety).
- Three answers each, scored **1–3**, each answer inserted as a `quiz_history` row.
- **Silent by design (Sep 2026).** The child sees one question at a time, the
  companion reads it aloud, the answer is POSTed to `api/assess-answer.php`
  (`question_id`, `option` 1–3) which returns the next question as JSON — no
  reload, no progress counter, no per-answer comment, no chart. On the last answer
  the companion celebrates and the page redirects to `tasks.php`. The axis summary
  is a **radar chart** (scale 1–3, not a percent) from `behavior_radar_svg()` on
  the child profile and in the admin Users tab. The parent WhatsApp report stays
  a text list of axes.
- Question set is pinned in the session (`$_SESSION['assess_qids']`) so leaving the
  page mid-quiz resumes rather than reshuffles.
- Gated by `needs_assessment()` (`includes/functions.php` L129–133): first run, then
  **every 10 days** via `children.last_assessment_at`.
- Results are a **level out of 3, deliberately not a percentage**
  (`assessment_axis_summary()` → `AVG(value)`), drawn as a radar: inner ring = 1,
  outer ring = 3, short labels on the spokes and the full axis name in the legend.
- A button WhatsApps the full report to the parent (`profile.php`).
- **Read-aloud (Sep 2026).** The question card has a `🔊 اسمع السؤال والخيارات`
  button (question + numbered options) and the companion's reply card has `🔊 اسمع`.
  Same on `tasks.php`: `🔊 اسمع المهمة` on the mission card and
  `🔊 اسمع القصة والشخصية` on the completion screen. All of these are the shared
  `data-say` button (handler in `app.js`, `.btn-listen` in `main.css`); if the 🗣️
  voice toggle is off the button shows a toast pointing at it instead of doing nothing.
- A session needs **10** active `quiz_questions`. Databases seeded before Aug 2026
  had only 6, which is why the UI said "سؤال 1 من 6". `kidora_migrate()` now calls
  `kidora_seed_assessment_questions($pdo, 10)`, which adds seed questions for any
  **axis** that has no question yet (matching by axis, not text, so drifted wording
  does not duplicate) until the active count reaches 10. Admin-written or disabled
  questions are never touched; the admin tab shows a warning while the count is below 10.

### `tasks.php` — the core
- Up to 4 tasks drawn at random from **every active task** — age is not a filter —
  then **pinned for the day** in `daily_progress.task_pool_ids` (`daily_task_pool()`).
  Changing the stored age does not redraw the pool.
- **Client-driven state machine (Sep 2026).** The page renders once; everything after
  is JS. «أنجزت المهمة» POSTs to `api/complete-task.php`, which awards points and
  returns `story_line`, `pair_line` (`companion_pair_line()` — ties the task
  category to the companion + sidekick), the linked `figure` (+ `youtube_id`), the
  mission `game`, and `all_done`. The companion then narrates, the figure video
  **autoplays with sound** through `KidoraYT` (YouTube IFrame API, `youtube-nocookie`;
  the child's tap on the task button is the user activation that permits it), the
  mini-game runs in place, and the next task slides in — no reloads, no «متابعة».
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
- Once all 4 are done the companion announces it and the page goes to
  `games.php?from=tasks`, where the game of the day is highlighted; safety or the
  story come **after** that game through the choice modal (see below).

### `safety.php` — «بطل الأمان» (3-step mission, Sep 2026)
One **lesson per day**: `LESSONS` = every free `safety_content` row (age does not filter); the day index
lives in `localStorage` (`kidora_safety_v5`, advances when the medal is earned). The
page is a companion-led mission with a 3-step bar and one big card at a time:
**1 القاعدة** — the rule read aloud by the companion, then the lesson video autoplays
via `KidoraYT` (or the big «فهمت، إلى اللعبة» button) → **2 اللعبة** — one of the
eight engines below → **3 الوسام** — confetti, medal, `kidoraMarkDone('safety')`, and
three big choices (daily story if premium / another game / home). The old owl
«رفيقتك الحكيمة», the gold «أنهيت المهمة» button and the goodbye screen are gone
(goodbye is the dashboard's job). Engine `speak()` calls route through
`Companion.say()`. Engines are short on purpose (`SF_MAX_QUIZ = 3` questions,
rotating by day; `SF_MAX_SCENES = 2` situations) and every choice/answer button is
≥ 64 px tall. The mini-game is chosen by `safety_content.game_type`: `body` (tap the private zone), `distance` (drag yourself
away from the stranger), `hotspot` (tap the dangers in a kitchen/pool/home scene),
`scenario` (3-choice situations), `match` (emergency numbers), `quiz` (yes/no
internet safety), `password` (build a strong password), `street` (cross on green).
The four seeded lessons map to `body / distance / quiz / scenario` (seed +
`kidora_migrate()` for DBs seeded earlier).

**The safety module never says «خطأ», shows no X and no score.** A safe choice is
praised («أحسنت», and in the yes/no quiz «أحسنت يا {اسم الطفل}»). Any other attempt
is not praised: `teach(rule)` says «حسناً، لكنّ الأفضلَ أن نتصرّفَ مثلَ {اسم الرفيق}:
{القاعدة}». The active companion (`KIDAURA_ACTIVE_CHARACTER.name`) is the model of
the safe action, then the rule itself, in gender-neutral «نحن» — no «تعمل», no
«فكرة جيدة», no «الأأمن». Hotspot, choice, and emergency-number misses use the same
frame (`teachSafe` / `teachChoice` / `teachNumber`). Warm gold, never red. Quiz
`tip`s are written in «نحن» and listed at the end under «أنت الآن بطل الحماية!».
Keep any new engine on `teach()`.

### `games.php` — games library
36 seeded rows grouped into 6 categories (تربوي / علمي / اجتماعي / سلوكي / ثقافي / صحي)
× the 6 mechanics, each with an icon + colour. Age does not hide cards; the free plan still shows two of them. Completion POSTs to
`api/play-game.php` which increments `daily_progress.games_played`. **No score is sent
to the server.**

**Game of the day + choice (Sep 2026).** `game_of_the_day()` picks one visible card
deterministically from `crc32(day|child_id)` and renders it as a hero card; arriving
with `?from=tasks` makes the companion announce it and scroll to it. After **any**
game finishes, `openChoice()` shows a two-option modal narrated by the companion —
🛡️ بطل الأمان (`safety.php`) or 📖 قصتي اليومية (`story.php`, locked copy for free
plan) — instead of a fixed redirect. Each card passes its category to `GamesEngine.run()` via `data-*`
attributes, so a صحي game asks health questions and a ثقافي game asks heritage ones.

**Mechanics (Sep 2026 rewrite — `reaction` and `memory` are gone):**

| slug | label | what the child does |
|---|---|---|
| `catch` | التقط الصحيح | one target icon is announced; tap only it among drifting icons. Keeps spawning until 6 (5 calm) are caught, retargets every 3 |
| `match` | مطابقة الأزواج | 3-D flip cards, find the pairs (4 pairs calm / 6 timed) |
| `quiz` | طريق البطل (أسئلة) | a walker advances along a path one step per answered yes/no question (5 questions from `game_questions`) |
| `puzzle` | البازل | a topic icon is tiled N×N (2 calm, 3 otherwise); tap two tiles to swap until the picture is whole |
| `hide` | أين اختبأ صاحبي؟ | cup-shuffle: the companion hides under one of three cups, they shuffle, tap the right one — 3 rounds |
| `adventure` | مغامرة بالاختيارات | 4 branching situations from `game_scenarios`, each choice gets its own gentle response |

Every mechanic ends with the same `celebrate()` screen (confetti, companion, praise
line) and **no mechanic ever tells the child «خطأ»/«غلط» or shows an X-of-Y score** —
misses get one of the `ENCOURAGE` lines and the game continues. Puzzle and hide are
the replacements for the old memory/reaction cards; `kidora_migrate()` renames legacy
rows in place (`kidora_legacy_game_titles()` in `database/seed.php`) so a production
DB seeded before Sep 2026 gets «بازل الحروف» / «أين اختبأ الحرف؟» without reseeding.
Card titles with a judging tone were also renamed («قرارات صح وخطأ» → «قرارات البطل»,
safety «صح أم خطأ: الإنترنت الآمن» → «بطل الإنترنت الآمن»).

### `draw.php` — the drawing board (لوحتي)
A free-expression canvas, added Sep 2026 because a child who draws is a child who
lets something out. Tools: brush / thick marker / spray / eraser / stamps (the
companions' icons + emoji), 12-colour palette + rainbow, six backgrounds, 25-step
undo/redo, clear. **Save is server-side**: `POST api/save-drawing.php` with a PNG data
URL → validated (magic bytes + `getimagesize`, ≤ 2 MiB, 16–4096 px) → stored under
`uploads/drawings/{child_id}/` → row in `drawings`. **The first saved drawing of the
day counts as one of today's games** (`games_played + 1`) so the daily loop treats
drawing as play, not a side activity. After saving the child can download the PNG or
share it through the parent's WhatsApp. The gallery (latest 12, delete-own-only) lives
in `profile.php#drawings`; the board is linked from the navbar, `games.php` and the
dashboard.

**Subscription gating (Sep 2026, revised).** Both game libraries stay complete after
the trial. The mission mini-game is part of the paid mission package, so it is
available only while `has_full_access()` is true.

### `story.php` — daily personal story
- **Free after the trial as well.** Story, friend, culture, and grand-story pages do
  not check the paid plan. An already-generated story stays viewable either way.
- Gated on `games_played >= STORY_MIN_GAMES` (= 1). Finished missions are included
  when they exist, and a game-only day still produces a complete scene.
- Child may upload a photo; the story comes from `daily_story_scenes()` in
  `includes/functions.php`. **Since Sep 2026 it is ONE scene** (`kind: 'single'`): a
  single flowing paragraph about the real day — a seeded opening, the completed
  tasks' `story_line`s joined with «أولاً / ثم / وبعدها / وقبل الغروب», the linked
  historical figure (`figure_for_task()`), the day's points, and a one-line moral from
  the strongest category — plus a companion `speaker`/`quote`. Sentence pools are
  seeded with `crc32(child_id|date)` so a refresh never changes the text. Saved to
  `daily_stories.scenes_json`, `children.ring_days` +1 (+10 points). **No AI API** —
  PHP templating on purpose. Stories generated before this change (multi-scene) still
  open in book mode.
- Rendering: `StoryPlayer.render(..., {book: true, animate: true})` auto-selects
  **single mode** (`renderSingle`) when the story has exactly one scene: one big poster
  card (art + icon, child's photo/name, companion, full text, quote bubble) and a
  single «اقرأ لي» button; the companion reads the whole card via `Companion.say()`
  and starts automatically. Multi-scene stories (old daily stories, grand story) use
  **book mode**: cream page, chapter ribbon, page-turn, dots, narration-synced autoplay.
- Exportable as a silent browser video — Canvas + `MediaRecorder` at **1920×1080**.
  The one-scene daily card is redrawn as the on-screen cream poster (art, hills,
  child photo, companion, full Arabic text, quote). Fonts and images load first;
  Arabic wraps by whole words, and overflow continues on another frame instead of
  being clipped. Multi-scene stories use the same resolution. The recorder tries
  `video/mp4`, then WebM. `xopts.onBlob` receives the Blob. No audio track.

### `grand-story.php` — the 30-day payoff
Consumes **30** daily stories (`GRAND_STORY_DAYS`) and builds one "Grand Adventure"
from the child's *actual month*, not from concatenating scenes.
`child_achievement_summary()` reads `daily_progress` over the story date range plus
`quiz_history`, and `grand_story_scenes()` turns that into **exactly 8 chapters**
told as one journey (Sep 2026): البداية → الطريق (missions) → الكنز المخفي (strongest
category) → رفاق من التاريخ (figures met) → العقبة (games as training) → من دفتر
الرحلة (three sampled lines from the daily stories) → القمة (stars, assessment
growth, strongest axis) → النهاية… والبداية. Every chapter always exists; when its
data source is empty it is narrated generically so the arc never has holes. Both
helpers live in `includes/functions.php`. No `mb_*` — truncation is `preg` `/u`.
Scenes carry optional `icon` + `title`, which `StoryPlayer` renders as a floating
chapter header and also draws into the exported video.
This is what the `0/30` ring in the navbar tracks (`children.ring_days`).
Copy is deliberately **gender-neutral** (nominal sentences) because `children` has no
gender column — avoid adding verb forms that need agreement with the child's name.

### Mail (`includes/mailer.php`, Sep 2026)
Hand-rolled SMTP client (implicit TLS 465 or STARTTLS, `peer_name` verification,
AUTH PLAIN → LOGIN, multipart text+HTML, base64 bodies, dot-stuffing). Config comes
from env first — `KIDORA_SMTP_HOST/PORT/USER/PASS/FROM/FROM_NAME/TLS_NAME` — then from
`settings.smtp_*` (editable in Admin → الإعدادات, with an «إرسال تجريبي» button).
`mail_is_configured()` gates the reset form so an unconfigured install shows a
WhatsApp fallback instead of a silent failure. **Production sends only from
`no-reply@anivia.site`** through the host's own aaPanel Postfix (SPF `ip4:` +
DKIM `default._domainkey` + DMARC published on Cloudflare; the SMTPS cert is
`mail.ggpanel.site`, hence `TLS_NAME`). Nothing is sent from trafficwar.tech and no
third-party mail API is used.

### Other pages
- `friends.php` — per-character friend stories (**hardcoded** in JS, not DB).
- `culture.php` — Arab/Islamic cultural story bank (**hardcoded** in JS, not DB).
- `games2.php` — «ألعاب الذكاء» (rewritten Sep 2026): 4 self-contained **educational**
  canvas games with a real end state, touch-only, no timers. The companion is pinned
  above the modal and reads the question, the short result, and a wrong-answer hint:
 `numbers` صيّاد الأرقام (tap the bubble completing the equation, 10
  rounds), `trace` تتبّع الحروف (draw the Arabic letter with a finger over its
  dashed outline — the glyph is rasterised to an 8 px cell mask on an offscreen
  canvas and the letter completes at 62 % / 75 % coverage; strokes off the letter are
  ignored, never punished; 6 letters), `sort` فرّز بذكاء (drag each item into one of
  two baskets — صحي/غير صحي or يُعاد تدويره/نفايات; tapping a basket also works; 10
  items), `memory` إيقاع الذاكرة (Simon-style sequence built from the active
  companion's theme icons, 6 rounds). `LEVEL` is a server decision from age (1: 6–8,
  2: 9–12). Winning POSTs `api/play-game.php` and flags `kidora_done_game_*`. Still
  hardcoded (not in the `games` table) by design — they are canvas mechanics, not
  content. The old Snake/Breakout/Flappy/Racer are gone.
- `welcome.php` — full-screen animated post-registration greeting: the companion,
  the child's uploaded photo, spoken lines with progress dots, then auto-continue to
  `assessment.php` / `dashboard.php`.
- `profile.php` — big profile photo with change/upload, **single companion switcher**
  (POST `set_companion`; always keeps a second free character), voice picker
  (`SoundEngine.listVoices()/setPreferredVoice()`), behaviour chart, story archive,
  drawings gallery, WhatsApp report button.
- **Remember-me (Sep 11 2026, collaborator):** `kidora_remember_login()` stores a
  90-day token in `children.remember_token/remember_expires` + `kidora_remember`
  cookie; `index.php` shows «متابعة كـ <name>» when the cookie is present and logs in
  on `?continue=1` (token rotated). Expiry compare is passed from PHP (`date()`),
  not `NOW()`, so it works on SQLite too. `dashboard.php` shows a spoken goodbye
  modal once tasks + game + (safety **or** story) are flagged done for the day
  (`localStorage` `kidora_done_*` keys; `window.kidoraMarkDone(type)`). `test-cookie.php` is a leftover debug page.
- `forgot-password.php` / `reset-password.php` — public password-reset pair (Sep
  2026). Request: always the same neutral message (no account enumeration), max
  `PASSWORD_RESET_MAX_PER_HOUR = 3` tokens per child, token = 64 hex chars stored as
  SHA-256 in `password_resets`, TTL `PASSWORD_RESET_TTL_MIN = 60`, single use (all
  pending tokens of the child are consumed together). Mail goes through
  `includes/mailer.php`. Shared card styles: `includes/public-card.php`.

---

## 4. UI / design system

Two distinct visual identities.

### Public surfaces (`index.php` + `demo.php`)
The guest experience is a dark-purple cinematic surface over the shared
contrast-safe background. `index.php` now opens with an optional local
`assets/video/intro.mp4`/`intro.webm` (and `intro-poster.webp`) overlay; when those
files are absent, the same overlay uses a GSAP character/logo motion fallback.
It then presents a gold CTA, database-driven counts, an RTL-friendly character
carousel with premium badges and an accessible details modal, six scroll-revealed
features, subscription plans, and the two-tab login/register form.

`demo.php` is public and does not call `require_login()`. Its three-step state
machine lets a visitor choose any of the six characters, play an 8-card/4-pair
memory game, enter a name, and hear a five-scene nominal-sentence story
automatically through `SoundEngine`. The finale links to the registration form
with the name and selected free character prefilled. `includes/public-nav.php`
is the guest-only sticky navigation; `includes/demo-content.php` is the PHP story
template bank. The public-only gold token is `--k-gold`; the signed-in app's
historical `--gold` token remains indigo.

### Signed-in app
- **Background** (`#animated-bg` in `includes/header.php`, styled in `main.css`):
  a 4-stop gradient `#1B1035 → #241645 → #3A2A75 → #1B1035` animating on a 12 s
  loop, **recoloured live to the active character's colour**, overlaid with 20–30
  emoji rising from the bottom on per-character motion curves, plus 3 drifting wave
  layers, plus (Sep 2026) a **motif layer** (`.theme-motif-layer`,
  `ThemeEngine.updateMotif()`): glyphs and animation chosen by
  `theme_json.motif` (`bubbles | leaves | confetti | stars | webs | bats | clues`,
  table `MOTIFS` in `theme-engine.js`) so SpongeBob's pages bubble and Batman's have bats.
  Net effect: bright cards floating on a deep animated night sky themed to the
  companion's world. The motif layer sits **under** the `::after` scrim, so it does
  not change the contrast budget below.
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
- **Companion widget** (`#companionWidget`, `assets/js/companion.js` + `main.css`):
  150px avatar (104px mobile), a **slow** 34 s walk across the bottom that pauses
  while talking, mood classes on the avatar (`mood-talk|mood-cheer|mood-think|
  mood-wave|mood-point`) and a speech bubble that shows the
  sidekick (`theme_json.sidekick`) and stays exactly as long as the utterance.
  `Companion.say(text, {mood})` returns a promise resolved when speech ends;
  `sequence([...])`, `celebrate()`, `guideTo(selector, text)`, `readAloud()`, `stop()`.
  Pages narrate through this API rather than calling `SoundEngine.speak` directly.
- **Typography**: Baloo Bhaijaan 2 (display) + Cairo (body) via `main.css`;
  `includes/navbar.php` separately imports **Tajawal** for the nav only.
- **Palette**: coral→pink gradient CTAs, mint for safety, violet for charts. The
  `--gold` token is **indigo `#818cf8`**, not gold — a rebrand renamed the value but
  not the token, so it disagrees with the literal `#fbbf24` hardcoded in
  `navbar.php`. ~20 surfaces read `var(--gold)` (auth tabs, character-card borders,
  `.eyebrow`, points-fly, story ring, admin tabs), so "correcting" it to gold is a
  redesign, not a cleanup. Left as-is with a comment at `main.css` L8.
- **Favicon**: `KIDORA_FAVICON` in `config/config.php` (Kidora wordmark PNG). Linked
  from `includes/header.php` (every child-facing page) and the two admin `<head>`s
  (`admin/login.php`, `admin/index.php`).

### Navigation (`includes/navbar.php` — self-contained HTML+CSS+JS)
Fixed dark glass bar (`rgba(10,18,35,.92)` + 24px blur): **RTL-start brand cluster**
(Kidora wordmark on the far right, then the 30-day progress ring),
**4 primary links** (الرئيسية / مهامي / ألعابي / قصتي اليومية — child-sized targets,
bigger type), a "المزيد" dropdown for the other 9 (safety, friends, culture, grand
story, ألعاب الذكاء, drawing, assessment, subscription, profile), the child's **photo
avatar** linking to the profile, VIP/free badge, and 🗣️ voice + 🔇 music toggles
(all such buttons are wired centrally in `app.js`). Below 1024px it collapses to a
right-side sliding sidebar (`100dvh`, scrollable link list, footer pinned) carrying
all links plus the voice controls. A compact 🗣️ button stays in the mobile header.

### Admin panel
Deliberately different: dark navy sidebar (230px) + cream content area, white cards
and tables. Styled by `assets/css/admin.css` (22 lines) layered on `main.css`.

---

## 5. Architecture

Plain **PHP 8 + PDO. No framework, no Composer, no build step.**
The public layer adds two page-specific vanilla-JS controllers and locally vendored
GSAP files; the application remains build-free.

```
config/config.php     constants, BASE_PATH auto-detection, admin creds, TZ Asia/Gaza
config/db.php         kidaura_connect() — PDO; on first SQLite run creates schema + seeds
includes/functions.php shared helpers (auth, daily progress, characters, subs, uploads)
includes/header.php   <head> (incl. favicon), animated background, KIDAURA_* JS globals, loads 4 engines
includes/navbar.php   nav + sidebar (self-contained)
includes/public-nav.php guest navigation (self-contained)
includes/demo-content.php PHP demo story templates and guide lines
includes/footer.php   companion widget + toast host + companion.js + app.js
assets/js/companion.js Companion API (speech bubble, moods, sidekick) + KidoraYT (IFrame API)
demo.php              public character / memory / narrated-story experience
assets/js/landing.js  public intro, carousel, modal, reveals, and auth interactions
assets/js/demo.js     public demo state machine, memory game, and narration
assets/vendor/gsap/   GSAP + ScrollTrigger, vendored for the no-build public layer
api/                  6 AJAX endpoints (see §5 API endpoints)
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
  database picks up new columns without a reset. On MySQL it also widens any
  `youtube_id VARCHAR(<64)` to `VARCHAR(64)` (`SHOW COLUMNS … LIKE` then `MODIFY`).
  It does **not** backfill content, with one deliberate exception — the assessment
  top-up described in §3 — so to get other new seed content into an existing dev DB,
  delete `storage/kidora.sqlite`.
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
`flash_toast`, `flash_wa_link`, `flash_profile`, `admin_flash`. (The task-completion
and assessment flashes are gone — both flows moved to JSON endpoints in Sep 2026.)

### PHP → JS wire
| Global | Set in | Contents |
|---|---|---|
| `KIDAURA_ACTIVE_CHARACTER` | `header.php` | `slug`, `name`, `color`, `icons[]`, `image`, `audio`, **`move`**, **`theme`** (`world`, `sidekick{name,icon}`, `motif`) |
| `KIDAURA_CHILD_PHOTO` | `header.php` | URL of the child's uploaded photo or `null` |
| `KIDAURA_BASE` | `header.php` | `BASE_PATH` |
| `KIDAURA_CHILD` | `navbar.php` | `id`, `name`, `points` |
| `KIDAURA_PAGE_LINE` | each page | Arabic line the companion speaks on load |
| `KIDORA_LANDING` | `index.php` | public character data, intro state, and registration prefill |
| `KIDORA_DEMO` | `demo.php` | all six demo characters, story templates, guide lines, and session state |

### JS engines (`assets/js/`)
| File | Global | Role |
|---|---|---|
| `theme-engine.js` | `ThemeEngine` | `applyBackground()` / `previewCharacter()` — recolours gradient, sets `--theme-accent`/`--theme-glow`, spawns floating icons |
| `sound-engine.js` | `SoundEngine` | `speak()` via `SpeechSynthesis` (`ar-SA`; `pickBestVoice()` scores Arabic voices, `preferredVoiceName` from the profile voice picker wins), `cleanSpeech()` strips emoji/symbols so nothing is read as «رمز», optional `onEnd`, **background music pauses while speaking and resumes after**, `sfx()` tones, optional Web Audio music. `listVoices()/setPreferredVoice()/getPreferredVoice()`. Toggles persist in `localStorage` (`kidaura_voice`, `kidaura_music`, `kidaura_voice_name`). Automatic tashkeel is **not** feasible client-side; voice quality is bounded by the OS voices |
| `companion.js` | `Companion`, `KidoraYT` | Assigned to `window` as well (`const` is not a window property). Pages that check `window.Companion` — welcome, tasks, games, safety, stories — stay silent without that assignment. See §4 «Companion widget». `KidoraYT.play(hostId, videoId, {autoplay, skipId})` loads the YouTube IFrame API once (`youtube-nocookie`), autoplays, and resolves on ENDED / skip button / error (6 s guard if the script is blocked). Used by `tasks.php` and `safety.php` |
| `story-player.js` | `StoryPlayer` | `render()` / `narrate()` / `share()` / `exportVideo()` — used by story, grand-story, friends, culture, profile. `opts.animate` adds autoplay; `opts.book` switches to the paper-book layout with narration-synced page turns; a **one-scene story in book mode renders as `renderSingle`** (poster card read in one go by `Companion`); `exportVideo` records a silent 1920×1080 card and continues overflow text on the next frame |
| `games-engine.js` | `GamesEngine` | `run(type, host, title, color, onDone, {category})` → `catch` / `match` / `quiz` / `puzzle` / `hide` / `adventure`. **Content is fetched from `api/game-content.php`, not hardcoded** (a small `FALLBACK` bank exists only so a failed request never shows a broken screen). `game_types()` in `includes/functions.php` is the authoritative slug→label list. Feedback vocabulary is `PRAISE` / `ENCOURAGE` only. Narration is `Companion.say` for every age; the widget is pinned while a game is open |
| `app.js` | — | bootstrap: nav toggle, **all** `.voice-btn`/`.music-btn` toggles, `data-say` buttons (→ `Companion.readAloud`), companion click + swap; the page greeting is spoken by `companion.js` from `KIDAURA_PAGE_LINE` |

**Play style (Sep 2026, updated).** No countdown timers in mission or library games.
`game_is_calm_age()` always returns true; `api/game-content.php` sends `calm: true`.
`quiz` has no timer; `catch` / `match` / `puzzle` / `hide` use the slower calm pacing.
Read-aloud goes through `Companion.say()` (question, prompt, result, ending).
`SoundEngine.speak()` is only the fallback when `Companion` is absent.
Opening a game adds `kidora-game-open`, which pins the companion above the play surface.

### API endpoints
All require `$_SESSION['child_id']` and have no CSRF token.
- `POST api/assess-answer.php` (`question_id`, `option` 1–3) → records the
  `quiz_history` row and returns `{ok, done, next:{id, question, options[]}}`; on the
  last answer sets `last_assessment_at`, clears the session question set, returns
  `{done:true}`. `{ok:false, reload:true}` if the session set is missing.
- `POST api/complete-task.php` (`task_id`) → validates the task is in today's pool and
  not done, appends to `completed_task_ids`, awards points, returns `{ok, points,
  story_line, pair_line, figure{name,title,story_line,youtube_id,…}, game{type,title},
  all_done}`.
- `POST api/play-game.php` → `games_played += 1`, returns `{ok, games_played}`.
- `POST api/swap-companion.php` → toggles `active_character` between `character_1`
  and `character_2`, returns `{ok, active_character}`; client reloads the page.
- `POST api/save-drawing.php` (JSON `{image: data:image/png;base64…, title?}`) →
  `{ok, id, url, title, counted_as_game}`; 401/400/500 with `{ok:false, error}`.
- `GET api/game-content.php?category=<arabic>` → `{ok, age, calm, topic, label, icons,
  quiz, adventure}`. The Arabic category is resolved to a topic through
  `game_topics.categories_json` (unknown → `general`). Active rows for that topic
  are returned in random order; age does not select them. The engine takes the
  first round (`GAME_QUIZ_ROUND = 5`, `GAME_ADVENTURE_SCENES = 4`). **Age still
  comes from the `children` row, never from the query string** (display only).
  `calm` is always true — no timers.

---

## 6. Data model

| Table | Purpose |
|---|---|
| `characters` | slug, name, title, trait, `color`, `move_type`, `image_path`, `audio_path`, `icons_json`, `is_premium`, `sort_order`, **`theme_json`** (`{world, sidekick:{name,icon}, motif}`, Sep 2026) |
| `children` | the user account: credentials, age, parent name/phone, optional `photo_path`, `character_1/2`, `active_character`, `points`, `ring_days`, `last_assessment_at`, `remember_token` / `remember_expires`, **`trial_ends_at`** (null = `created_at` + 7 days) |
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
| `safety_content` | protection lessons: type, title, description, `youtube_id`, `game_type` (engine slug, see §3), `is_premium`, age range |
| `password_resets` | `child_id`, `token_hash` (sha256), `expires_at`, `used_at`, `created_at` — `created_at` is written from PHP (app timezone), never left to the DB default (UTC), otherwise the per-hour limit never triggers |
| `drawings` | `child_id`, `title`, `image_path` (`uploads/drawings/{child}/d_*.png`), `created_at` (also PHP-written) |
| `subscription_plans` / `subscriptions` | plans + one row per child (`pending`/`active`) |
| `institutions` | partner school / org supervisors |
| `wa_log` | every WhatsApp message generated |
| `settings` | `whatsapp_number`, `platform_name`, `story_api_key`, `smtp_host/port/user/pass/from/from_name/tls_name` (key-value) |

Seeded volumes: **8 characters** (v2 set, `settings.characters_version = 2`), **29 tasks**, 36 games (6 mechanics × 6 categories),
12 assessment questions, 29 history figures (every one reachable from a task),
4 safety items, 3 plans, 3 settings, and — for in-game content — 9 topics,
**192 true/false questions** and **78 adventure scenarios**, both age-tagged.

Columns added Aug 2026 (both schemas + `kidora_migrate()`):
`tasks.figure_id` (the mission's linked historical figure) and
`history_figures.category` (matches the task category vocabulary, used as the
fallback when a task has no explicit link).

Column added Sep 2026 (both schemas + `kidora_migrate()`):
`children.photo_path` stores the optional profile image uploaded during public
registration. The public path uses `save_image_upload()` (MIME/content check,
4 MiB cap, JPG/PNG/WebP); profile rendering shows it beside the child's name.

Tables added Sep 2026: `game_topics`, `game_questions`, `game_scenarios`. Existing
databases pick them up through `kidora_migrate()`, which checks for `game_topics` as
the sentinel (all three are created together), executes each `CREATE TABLE` **read
out of the schema file** — so the DDL is never duplicated in PHP — and then calls
`kidora_seed_game_content()`. That function is separate from `kidora_seed()` for
exactly this reason and is safe to re-run: each block seeds only if its table is empty.

`reviewed = 0` marks content authored by tooling rather than approved by a human. It
does **not** hide the row — `active` does that. The admin tab surfaces the pending
count with a per-topic bulk approve.

After the internal review pass, `kidora_content_reviewed()` ships eight topics as
`reviewed = 1` and holds only **safety** (20 questions + 8 scenarios) at `0`. That
topic covers strangers, frightening secrets, unwanted touch, and that an adult's
mistake is never the child's fault — messages a person should approve rather than a
language check. They keep playing while they wait.

Characters (v2, Sep 2026): **spongebob** and **dora** are free; **gumball, ladybug,
spiderman, batman, ben10, conan** are premium. `kidora_migrate_characters_v2()`
(called from `kidora_migrate()`) replaces the old mimo/zizo/finn/nova/lulu/rex rows
on an existing DB and remaps every child's `character_1/2/active_character`
(mimo → dora, everything else → spongebob, second free slot filled), guarded by
`settings.characters_version`. `friends.php` / `includes/demo-content.php` banks were
re-keyed to the new slugs.

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
| المهام (tasks) | add / **edit** / delete / toggle active. Edit = `?tab=tasks&edit_task=ID`, same form prefilled. The YouTube field accepts a pasted URL (watch / shorts / youtu.be / embed / live, with `?si=` etc.) or a bare ID — `youtube_id_from_input()` stores only the 11-char ID and rejects anything else with a flash. This is the live MySQL `tasks` table, not a SQLite file |
| الألعاب (games) | add / delete |
| محتوى الألعاب (gamecontent) | per-topic editor for `game_questions` + `game_scenarios`: add, approve, enable/disable, delete, and bulk-approve a whole topic. Shows which Arabic categories feed each topic |
| أسئلة التحليل (assessment) | `quiz_questions` editor: add (axis autocompletes from existing axes), enable/disable, delete |
| الشخصيات التاريخية (history) | add / **edit** / delete; same URL-or-ID YouTube handling as tasks (`?tab=history&edit_figure=ID`) |
| الاشتراكات (subscriptions) | approve / reject pending requests with direct WhatsApp link; add / **edit** / delete plans (edit = `?tab=subscriptions&edit_plan=ID`, same form prefilled; delete refused while subscriptions reference the plan) |
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
4. ~~**Ring denominator mismatch**: `admin/tabs/users.php` printed `ring_days/10`
   while everything else uses **30**.~~ **RESOLVED Sep 2026.**
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
7. ~~**Orphaned character art** — every `characters.image_path` was `NULL`, so all
   characters rendered as emoji.~~ **RESOLVED Sep 2026** — the v2 set ships with
   placeholder `avatar.svg` files and `image_path` set; real (licensed) art is an
   admin upload. The old `assets/characters/` and `mimo/`, `zizo/` PNGs were deleted.
8. ~~`KIDAURA_ACTIVE_CHARACTER` omits `move`.~~ **RESOLVED Sep 2026** — `move` and
   `theme` are both passed (see §5 wire table).
9. `SoundEngine.playCharacterClip()` (the only consumer of uploaded character audio)
   is exported but **never called**, so admin-uploaded voice files are unused.

### Content not admin-manageable (contradicts the "admin controls everything" claim)
10. `friends.php` L31 `FRIEND_STORY_BANK` and `culture.php` L25 `CULTURE_STORY_BANK`
    are hardcoded JS objects. **Still open** — these are the last two hardcoded
    content banks; `games-engine.js` was moved to the database in Sep 2026 and is the
    worked example to copy.
11. `games2.php`'s 4 games are hardcoded canvas implementations outside the `games`
    table. **Accepted (Sep 2026):** they were rewritten as goal-based educational
    mechanics (see §3 «Other pages»); the word/number banks inside them are small and
    age-levelled, but if they grow they should move to the database like
    `game_questions` did.

### Security
12. Hardcoded plaintext admin credentials in source; no `session_regenerate_id()`,
    no rate limiting, no lockout.
13. **No CSRF token on any form**, admin or child-facing. A logged-in admin's browser
    can be induced to approve subscriptions or delete content cross-site.
14. The legacy `save_upload()` (`includes/functions.php`) still validates by
    **file extension only** — no MIME check, no size limit, and directories are
    created `0777`. Public child registration does not use it: the new
    `save_image_upload()` checks MIME/content and caps files at 4 MiB, but the
    admin character-media upload path remains open.
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
    Narration now goes through `Companion.say` for every age, not only `calm`.
    Questions, adventure prompts, choices and outcomes are now read aloud under `calm`.
30. ~~**Every child saw the whole games library and could generate a daily story, paid
    or not.**~~ Replaced in Sep 2026 by the seven-day full-access trial. After expiry,
    stories and both game libraries stay open; tasks, safety, assessment, drawing,
    and premium companions require a paid plan. See §2 and §3.
31. ~~**The daily story was a static, manually-clicked slideshow.**~~ `StoryPlayer`
    already supported chapter icons, titles and a floating sprite — the grand story
    used them, the daily story passed only `caption` and `grad`. It now passes all
    three and enables autoplay.
32. ~~**Game content was 72 questions and 27 scenarios hardcoded in JavaScript**~~, so
    a topic recycled its 8 questions immediately, `general` was 7/8 duplicated from
    other banks, replays were identical, and nothing was editable. Content is now
    192 questions / 78 scenarios in the database, served in random order. Age labels stay on the rows and do not select which ones play.
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

38. ~~Five history figures — three of them women — had no task pointing at them~~, so
    they appeared only through the random category fallback and girls saw fewer role
    models than the library holds. Five missions were added; all 29 figures are now
    directly reachable, and `tasks` is 29 rows.
39. ~~**Every question a four-year-old could be asked in six of the nine topics
    answered "صح"**~~, so pressing صح always won. Those topics gained concrete false
    statements (a hot snowball, a mouse bigger than an elephant) rather than negated
    good behaviour, which is what confuses that age.
40. ~~Adventure scenarios were all seeded 4-12~~ although several assume school exams,
    weekly planning or using a dictionary. They now carry real age ranges, six
    concrete scenarios were added for the young end, and `game_topic_rows()` treats
    age as a preference — if a topic has too few eligible rows it tops the round up
    from the rest of the topic, so an authoring gap can never open an empty game.
41. ~~`أصرخ عليه وأضربه` was a clickable choice~~ in a values scenario. Hitting is not
    offered as a button in a children's app; the lesson lands without it.
42. ~~Embeds used `youtube.com`~~, which sets tracking cookies before playback. All
    three (task, figure, landing page) now go through `youtube_embed_url()` →
    `youtube-nocookie.com` with related videos restricted to the same channel.

### Found and fixed during the Sep 2026 admin pass

43. ~~**Pasting a YouTube link into the admin crashed the page.**~~ `youtube_id` was
    `VARCHAR(30)` on MySQL and the field was stored verbatim, so a Shorts share link
    (`https://youtube.com/shorts/…?si=…`, 60+ chars) died with
    `SQLSTATE[22001] Data too long for column 'youtube_id'` — and had it fit, the
    embed URL built from it would have been broken anyway. Both admin tabs now go
    through `youtube_id_from_input()`, the column is widened to 64 by the migration,
    and unrecognised input is refused with a message instead of being truncated.
44. ~~**Live databases seeded before Aug 2026 ran a 6-question assessment.**~~ The
    schema-file `INSERT` blocks were removed then, but nothing topped up databases
    that already existed, so production kept showing "سؤال 1 من 6". See §3.
45. ~~**Plans could only be added or deleted, never edited**~~, so changing a price
    meant deleting a plan that active subscriptions pointed at. Edit form added; delete
    is guarded.
46. ~~**Assessment questions and missions had no read-aloud control**~~ although
    safety scenes and the games did. Shared `data-say` button added (see §3).
47. ~~**Existing missions could not have a YouTube video attached from admin.**~~
    Tasks and history figures were add/delete only, so the 29 seeded rows stayed
    `youtube_id = NULL` unless someone wrote SQL. The live site is MySQL, not the
    local `storage/kidora.sqlite` file — that confusion made the data look
    unreachable. Both tabs now have **تعديل** and show the stored ID (or «بدون فيديو»).

**Still open, needs product input:** all 29 missions have `youtube_id = NULL`, so no
mission shows a video yet. `docs/mission-video-shortlist.md` holds three verified
candidates per mission — existence, embeddability, channel, title and duration are
machine-checked, but **nobody has watched them**, and for missions 14, 15 and 23
nothing suitable surfaced after three searches each.

---

### Found and fixed during the Sep 2026 games / mail / drawing pass
Every game was driven to completion in headless Chromium (Playwright) for a
6-year-old and an 11-year-old: all 36 library cards (age-filtered → 30 + 27 cards,
together covering every row), 4 missions + their mini-games per age, safety
(right-only and wrong-only runs), drawing save/gallery/delete, story generation +
book autoplay + video export, the 4 arcade games, and the full reset-mail loop with a
real message delivered by the host Postfix and DKIM-verified. Zero console errors.
Defects that surfaced and were fixed on the way:

- `api/save-drawing.php` used `mb_substr()`; the runtime has no `mbstring` → 500 on
  every save. Replaced with a `preg` UTF-8 truncation. **Do not use `mb_*` anywhere in
  this codebase.**
- `password_resets.created_at` relied on `DEFAULT CURRENT_TIMESTAMP` (UTC) while the
  rate-limit compare used `date()` in `Asia/Gaza` → the 3-per-hour limit could never
  trigger. Both `password_resets` and `drawings` now write `created_at` from PHP.
- The WhatsApp share captions for drawings said «{name} رسم لوحة» (bound masculine
  verb next to the child's name) → «لوحة … من ريشة {name}».
- `includes/footer.php` had a stray Arabic note after `</html>`.
- Legacy `reaction`/`memory` rows: migration renames both `type` and title; verified on
  a copy of a pre-Sep DB.

~~Still open from this pass: the arcade games in `games2.php` are endless score games.~~
Replaced in the Sep 2026 child-experience pass by four goal-based educational games.

### The Sep 2026 child-experience pass (companion-led flow, characters v2)
Product direction from the owner: the platform was too hard for a 6–12-year-old to
drive alone, too quiet, and too "click continue". Everything below was verified
against the running SQLite app over HTTP for a **6-year-old and an 11-year-old**
(register → silent assessment via `api/assess-answer.php` → 4 age-fitting tasks via
`api/complete-task.php` → `game-content` returns `calm` only for the 6-year-old →
`api/play-game.php` → `games2` LEVEL 1 vs 2 → safety lessons filtered by age → story
paywall; plus premium story generation → single-scene render; every inline `<script>`
and every `assets/js/*.js` passes `node --check`). A pre-v2 database was also upgraded
in place through `kidora_migrate()`. No headless browser was available on this
host, so animations and autoplay were reviewed by reading, not by pixel.

- **Characters v2** — mimo/zizo/finn/nova/lulu/rex → spongebob/dora/gumball/ladybug/
  spiderman/batman/ben10/conan with `theme_json` (world, sidekick, motif) and
  placeholder SVG avatars; `kidora_migrate_characters_v2()` remaps existing children.
  ⚠️ These names are third-party IP; the shipped art is deliberately generic.
- **One character at registration**, second free one server-assigned; switch from
  the profile. Registration and the profile both offer age **1–60**. The stored
  age does not filter missions, the game library, safety lessons, or in-game
  questions, and does not enable game timers.
- **`welcome.php`** rebuilt as a full-screen animated greeting with the child's photo;
  the subscription page is no longer the first screen after signup and the paywall
  does not appear after the assessment.
- **Assessment is silent**: sequential questions over AJAX, no progress, no comments,
  no chart for the child; admin Users tab gained an inline analysis panel.
- **Tasks are a state machine**: story → pair line → figure video autoplay → game →
  next task, all narrated by the companion, no reloads (`KidoraYT` for the IFrame
  API). Completion → `games.php?from=tasks` → game of the day → choice modal
  (safety / story). `STORY_MIN_GAMES = 1`.
- **Safety** is a 3-step mission (rule → game → medal) led by the companion; the
  eight gentle engines are unchanged.
- **Daily story is one scene**, grand story is **8 fixed chapters**; `StoryPlayer`
  gained `renderSingle`.
- **Companion**: `companion.js` API, bigger and slower, moods, sidekick bubble, reads
  everything; **music pauses while it speaks**; `cleanSpeech()` strips emoji;
  best-Arabic-voice scoring + a voice picker in the profile.
- **🌈 removed** from every PHP/JS file (owner request).
- **Navbar**: 4 primary links, bigger targets, photo avatar; profile shows the photo
  large with a change form.
- **`games2.php`** → four educational canvas games with real end states (numbers,
  letter tracing, two-basket sorting, theme-icon memory).

Bugs found on the way (fixed): `rtrim($s, '.،')` corrupted UTF-8 in the new story
builder (byte-wise trim on a multibyte delimiter → `json_encode` returned `false` and
an empty `scenes_json` row was written) — use `preg_replace('/…/u')`; `mb_*` used in
the grand story builder although the runtime has no mbstring (see the note above —
**never `mb_*`**); `safety.php` called `is_premium_active()` with the wrong signature.

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
- Public intro media is optional: place `intro.mp4`, `intro.webm`, and optionally
  `intro-poster.webp` in `assets/video/`. If no video exists, `index.php` uses the
  GSAP fallback. `assets/vendor/gsap/` contains the vendored GSAP 3.13.0 and
  ScrollTrigger files; no npm build is required.

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
  them sensibly. Never build an embed URL by hand — use `youtube_embed_url()`, which
  is what keeps playback on `youtube-nocookie.com`. Any path that **stores** a
  `youtube_id` from user input must run it through `youtube_id_from_input()` first;
  the column holds an 11-char ID, never a URL.
- **Read-aloud** is `<button class="btn btn-listen" data-say="…">` — do not write
  per-page `SoundEngine.speak` click handlers; the shared handler in `app.js` also
  handles the muted case.
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
- **The companion narrates; pages don't.** Use `Companion.say/sequence/celebrate/
  guideTo` (they return promises and handle moods, bubble, music pause). Do not add
  raw `SpeechSynthesisUtterance` or `SoundEngine.speak` calls in pages; the one
  exception is a fallback when `window.Companion` is absent.
- **Auto-advance instead of «متابعة».** Flows that chain content (tasks, safety,
  assessment, welcome) are client-side state machines fed by JSON endpoints; the
  child's first tap is the user activation that lets `KidoraYT` autoplay with sound.
  Do not reintroduce full-page POST-redirect steps in the middle of a chain.
- **YouTube playback goes through `KidoraYT.play()`** (IFrame API,
  `youtube-nocookie`), so a video's end can advance the flow. Never a bare `<iframe>`
  in a chained flow.
- **Daily story = one scene, grand story = eight chapters.** `StoryPlayer` picks
  `renderSingle` for a one-scene story in book mode; keep multi-scene support for
  stories already stored.
- **No `mb_*` functions** — the runtime has no mbstring. Use `preg_*` with the `u`
  flag, and never `rtrim()/trim()` with a multibyte character list.
- **No 🌈.** The owner asked for it to be gone from the product; don't add it back.
- **Character IP.** The v2 companions carry third-party names. Keep artwork generic
  until licensed; the admin upload is the path for real art.
