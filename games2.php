<?php
/**
 * ألعاب الذكاء — أربع ألعاب تعليمية على Canvas بهدف واضح (لا نقاط لا نهائية):
 *   numbers  صيّاد الأرقام   — حساب: التقط الفقاعة التي تُكمل المعادلة (10 التقاطات)
 *   trace    تتبّع الحروف    — لغة/حركة دقيقة: ارسم الحرف العربي بإصبعك فوق شكله (6 حروف)
 *   sort     فرّز بذكاء      — تصنيف: اسحب كل شيء إلى سلّته (صحي/غير صحي، يُعاد تدويره/لا) (10 أشياء)
 *   memory   إيقاع الذاكرة   — ذاكرة: كرّر تسلسل أيقونات عالم رفيقك المضيئة (6 جولات)
 *
 * كل لعبة: لمس فقط (لا لوحة مفاتيح)، بلا مؤقّت، الرفيق يقرأ السؤال ويحتفل.
 * الصعوبة قرار خادم من عمر الطفل (LEVEL 1: 6–8 سنوات، LEVEL 2: 9–12).
 * الفوز يُسجَّل عبر api/play-game.php (games_played + 1) ويُعلَّم kidora_done_game_*.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();
$progress = ensure_daily_progress($pdo, $child['id']);

$level = ((int)$child['age'] >= 9) ? 2 : 1;

$__pageTitle = 'ألعاب الذكاء — Kidora';
$__pageLine  = 'أربع ألعاب تدرّب العقل: حساب، وحروف، وذاكرة، ومنطق. اختر واحدة وسأقرأ لك كل سؤال.';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<style>
.eg-hero{ text-align:center; padding:2rem 1rem 1rem; }
.eg-hero h1{ margin:0; font-family:var(--font-display); font-size:clamp(30px,6vw,46px); color:#fff; }
.eg-hero h1 span{ background-image:linear-gradient(135deg,#fff,#ffc93c); -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent; }
.eg-hero p{ color:#d9d0ff; font-size:1.1rem; margin:.4rem 0 0; }
.eg-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:20px; margin-top:20px; }
.eg-card{ position:relative; background:rgba(255,255,255,.06); border:2px solid rgba(255,255,255,.1); border-radius:28px; padding:26px 20px 22px; text-align:center; cursor:pointer; transition:.25s; min-height:250px; }
.eg-card:hover{ transform:translateY(-6px); border-color:var(--c); background:rgba(255,255,255,.1); box-shadow:0 18px 44px color-mix(in srgb,var(--c) 35%,transparent); }
.eg-card .ic{ font-size:4.2rem; line-height:1; display:inline-block; animation:companionFloat 3s ease-in-out infinite; }
.eg-card h3{ color:#fff; margin:12px 0 4px; font-size:1.4rem; }
.eg-card p{ color:#c9bfe6; font-size:.95rem; line-height:1.7; margin:0 0 12px; min-height:2.6em; }
.eg-card .skill{ display:inline-block; background:color-mix(in srgb,var(--c) 30%,transparent); color:#fff; border:1px solid var(--c); font-weight:900; font-size:.8rem; padding:.25rem .8rem; border-radius:30px; margin-bottom:12px; }
.eg-play{ display:block; width:100%; min-height:56px; border:none; border-radius:60px; background:var(--c); color:#1a1040; font-weight:900; font-size:1.1rem; cursor:pointer; font-family:inherit; transition:.2s; }
.eg-play:hover{ transform:scale(1.03); }

.eg-modal{ position:fixed; inset:0; background:rgba(5,3,20,.9); backdrop-filter:blur(10px); display:none; align-items:center; justify-content:center; z-index:99999; padding:14px; }
.eg-modal.open{ display:flex; }
.eg-box{ background:#140c33; border-radius:34px; max-width:860px; width:100%; padding:16px; border:1px solid rgba(255,255,255,.1); position:relative; }
.eg-close{ position:absolute; top:12px; left:14px; background:rgba(255,255,255,.08); border:none; color:#fff; font-size:26px; width:50px; height:50px; border-radius:50%; cursor:pointer; z-index:10; }
.eg-top{ display:flex; align-items:center; justify-content:space-between; gap:10px; padding:6px 64px 12px 12px; color:#fff; font-weight:900; }
.eg-top .t{ font-size:1.25rem; }
.eg-prog{ display:flex; gap:6px; }
.eg-prog i{ width:14px; height:14px; border-radius:50%; background:rgba(255,255,255,.15); display:block; transition:.3s; }
.eg-prog i.on{ background:#ffc93c; box-shadow:0 0 12px #ffc93c; transform:scale(1.15); }
.eg-q{ text-align:center; color:#fff; font-size:clamp(1.3rem,4vw,2rem); font-weight:900; min-height:2.6rem; padding:0 12px 10px; direction:rtl; }
.eg-canvas-wrap{ background:#0b0722; border-radius:24px; overflow:hidden; width:100%; aspect-ratio:16/9; min-height:320px; position:relative; }
.eg-canvas-wrap canvas{ display:block; width:100%!important; height:100%!important; touch-action:none; }
.eg-fb{ min-height:2.6rem; text-align:center; color:#ffe99a; font-weight:800; font-size:1.05rem; padding:10px 8px 0; }
.eg-ctrl{ display:flex; justify-content:center; gap:12px; padding-top:12px; flex-wrap:wrap; }
.eg-btn{ min-height:54px; padding:0 28px; border-radius:60px; font-weight:900; font-size:1.05rem; cursor:pointer; font-family:inherit; border:2px solid rgba(255,255,255,.14); background:rgba(255,255,255,.07); color:#fff; }
.eg-btn.gold{ background:linear-gradient(135deg,#ffe99a,#ffc93c); color:#241645; border-color:#ffc93c; }
.eg-win{ position:absolute; inset:0; display:none; place-items:center; background:rgba(11,7,34,.9); text-align:center; color:#fff; padding:20px; }
.eg-win.open{ display:grid; }
.eg-win .medal{ font-size:5rem; animation:egPop .8s cubic-bezier(.34,1.56,.64,1) both; }
@keyframes egPop{ from{ transform:scale(.2) rotate(-20deg); opacity:0 } to{ transform:scale(1) rotate(0); opacity:1 } }
.eg-win h3{ margin:.3rem 0; font-family:var(--font-display); font-size:2rem; }
.eg-win p{ color:#d9d0ff; margin:0 0 14px; }
@media(max-width:640px){ .eg-canvas-wrap{ aspect-ratio:4/5; min-height:300px; } .eg-top{ padding-right:56px; } }
</style>

<main class="container" style="padding:10px 15px 50px;">
  <div class="eg-hero">
    <h1>🧠 ألعاب <span>الذكاء</span></h1>
    <p>كل لعبة لها هدف واضح — أكملها واحصل على وسامك</p>
  </div>

  <div class="eg-grid">
    <div class="eg-card" style="--c:#ffc93c" onclick="EG.open('numbers')">
      <div class="ic">🔢</div><h3>صيّاد الأرقام</h3>
      <span class="skill">حساب</span>
      <p>التقط الفقاعة التي تُكمل المعادلة قبل أن تطير!</p>
      <button class="eg-play" type="button">▶ العب</button>
    </div>
    <div class="eg-card" style="--c:#2ec4b6" onclick="EG.open('trace')">
      <div class="ic">✍️</div><h3>تتبّع الحروف</h3>
      <span class="skill">لغة</span>
      <p>ارسم الحرف بإصبعك فوق شكله حتى يضيء كله.</p>
      <button class="eg-play" type="button">▶ العب</button>
    </div>
    <div class="eg-card" style="--c:#6c63ff" onclick="EG.open('sort')">
      <div class="ic">🧺</div><h3>فرّز بذكاء</h3>
      <span class="skill">تصنيف</span>
      <p>اسحب كل شيء إلى سلّته الصحيحة: صحي أو لا؟ يُعاد تدويره أو لا؟</p>
      <button class="eg-play" type="button">▶ العب</button>
    </div>
    <div class="eg-card" style="--c:#ff6fa5" onclick="EG.open('memory')">
      <div class="ic">🧩</div><h3>إيقاع الذاكرة</h3>
      <span class="skill">ذاكرة</span>
      <p>راقب أيقونات عالم رفيقك التي تضيء ثم كرّرها بنفس الترتيب.</p>
      <button class="eg-play" type="button">▶ العب</button>
    </div>
  </div>
</main>

<div class="eg-modal" id="egModal" role="dialog" aria-modal="true">
  <div class="eg-box">
    <button class="eg-close" type="button" onclick="EG.close()" aria-label="إغلاق">✕</button>
    <div class="eg-top"><span class="t" id="egTitle"></span><div class="eg-prog" id="egProg"></div></div>
    <div class="eg-q" id="egQ"></div>
    <div class="eg-canvas-wrap">
      <canvas id="egCanvas"></canvas>
      <div class="eg-win" id="egWin">
        <div>
          <div class="medal">🏅</div>
          <h3>أحسنت يا <?php echo h($child['name']); ?>!</h3>
          <p id="egWinMsg"></p>
          <div class="eg-ctrl">
            <button class="eg-btn gold" type="button" onclick="EG.restart()">🔄 مرة أخرى</button>
            <button class="eg-btn" type="button" onclick="EG.close()">🧠 لعبة أخرى</button>
            <a class="eg-btn" href="<?php echo BASE_PATH; ?>/dashboard.php" style="display:inline-flex;align-items:center;text-decoration:none;">🏠 الرئيسية</a>
          </div>
        </div>
      </div>
    </div>
    <div class="eg-fb" id="egFb"></div>
    <div class="eg-ctrl">
      <button class="eg-btn" type="button" id="egRepeat">🔊 أعد السؤال</button>
      <button class="eg-btn gold" type="button" onclick="EG.restart()">🔄 إعادة</button>
    </div>
  </div>
</div>

<footer class="site-footer">Kidora © 2026</footer>
<script>window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;</script>
<script>
const EG = (function () {
  const LEVEL = <?php echo (int)$level; ?>;         // 1: 6–8 سنوات، 2: 9–12 — قرار خادم
  const NAME  = <?php echo json_encode($child['name'], JSON_UNESCAPED_UNICODE); ?>;
  const modal = document.getElementById('egModal'), canvas = document.getElementById('egCanvas'), ctx = canvas.getContext('2d');
  const $ = id => document.getElementById(id);
  let game = null, key = null, anim = null, W = 0, H = 0, dpr = 1, lastQ = '';

  /* ---------- أدوات مشتركة ---------- */
  const rnd = (a, b) => a + Math.floor(Math.random() * (b - a + 1));
  const shuffle = a => { for (let i = a.length - 1; i > 0; i--) { const j = rnd(0, i); [a[i], a[j]] = [a[j], a[i]]; } return a; };
  const ar = n => String(n).replace(/\d/g, d => '٠١٢٣٤٥٦٧٨٩'[+d]);
  function say(t, mood) { if (window.Companion) return Companion.say(t, { mood: mood || 'talk' }); return Promise.resolve(); }
  function ask(t) { lastQ = t; $('egQ').textContent = t; return say(t); }
  function fb(t, good) { $('egFb').textContent = t; $('egFb').style.color = good ? '#8ff5e6' : '#ffe99a'; }
  const PRAISE = ['أحسنت!', 'ممتاز!', 'رائع!', 'صحيح تماماً!', 'عقل ذكي!'];
  const GENTLE = ['قريب! جرّب مرة أخرى.', 'فكرة جيدة… حاول من جديد.', 'ركّز جيداً وجرّب ثانية.'];
  const praise = () => PRAISE[rnd(0, PRAISE.length - 1)], gentle = () => GENTLE[rnd(0, GENTLE.length - 1)];
  function setProg(n, total) { $('egProg').innerHTML = Array.from({ length: total }, (_, i) => `<i class="${i < n ? 'on' : ''}"></i>`).join(''); }
  function resize() {
    const r = canvas.parentElement.getBoundingClientRect(); dpr = Math.min(2, window.devicePixelRatio || 1);
    W = r.width; H = r.height; canvas.width = W * dpr; canvas.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }
  function pos(e) { const r = canvas.getBoundingClientRect(); return { x: (e.clientX - r.left) * (W / r.width), y: (e.clientY - r.top) * (H / r.height) }; }
  function bg(c1, c2) { const g = ctx.createLinearGradient(0, 0, 0, H); g.addColorStop(0, c1); g.addColorStop(1, c2); ctx.fillStyle = g; ctx.fillRect(0, 0, W, H); }
  function bubble(x, y, r, color, text, font) {
    ctx.save(); ctx.shadowColor = color; ctx.shadowBlur = 18; ctx.fillStyle = color; ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2); ctx.fill(); ctx.shadowBlur = 0;
    ctx.fillStyle = 'rgba(255,255,255,.35)'; ctx.beginPath(); ctx.arc(x - r * .35, y - r * .35, r * .25, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = '#1a1040'; ctx.font = font || `900 ${Math.round(r)}px Cairo, sans-serif`; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(text, x, y + 2); ctx.restore();
  }
  function pop(x, y, color) { game.fx.push({ x, y, r: 6, a: 1, color }); }
  function drawFx() { game.fx = game.fx.filter(f => f.a > 0); game.fx.forEach(f => { f.r += 3; f.a -= .05; ctx.strokeStyle = f.color; ctx.globalAlpha = Math.max(0, f.a); ctx.lineWidth = 4; ctx.beginPath(); ctx.arc(f.x, f.y, f.r, 0, Math.PI * 2); ctx.stroke(); ctx.globalAlpha = 1; }); }
  const PALETTE = ['#ffc93c', '#2ec4b6', '#ff6fa5', '#6c63ff', '#ff7a50', '#8ff5e6'];

  /* ============================================================
     1) صيّاد الأرقام — الحساب
     ============================================================ */
  const Numbers = {
    title: '🔢 صيّاد الأرقام', total: 10,
    init() { this.score = 0; this.fx = []; this.newRound(); },
    newRound() {
      let a, b, op, ans;
      if (LEVEL === 1) { op = Math.random() < .6 ? '+' : '−'; a = rnd(1, 9); b = rnd(1, 9); if (op === '−' && b > a) [a, b] = [b, a]; ans = op === '+' ? a + b : a - b; }
      else { const r = Math.random(); if (r < .4) { op = '×'; a = rnd(2, 9); b = rnd(2, 9); ans = a * b; } else if (r < .7) { op = '+'; a = rnd(10, 60); b = rnd(5, 40); ans = a + b; } else { op = '−'; a = rnd(20, 90); b = rnd(5, a - 1); ans = a - b; } }
      this.q = `${ar(a)} ${op} ${ar(b)} = ؟`; this.ans = ans;
      const set = new Set([ans]); while (set.size < 4) set.add(Math.max(0, ans + rnd(-6, 6) * (LEVEL === 2 && op === '×' ? 2 : 1)));
      const opts = shuffle([...set]); const lane = W / (opts.length + 1);
      this.bubbles = opts.map((v, i) => ({ v, x: lane * (i + 1) + rnd(-10, 10), y: H + 40 + i * 50, r: Math.min(44, lane * .38), sp: (LEVEL === 1 ? .35 : .55) + Math.random() * .25, color: PALETTE[i % PALETTE.length] }));
      ask(`كم يساوي ${a} ${op === '+' ? 'زائد' : op === '−' ? 'ناقص' : 'ضرب'} ${b}؟ التقط الفقاعة الصحيحة.`);
    },
    tap(p) {
      const hit = this.bubbles.find(b => Math.hypot(b.x - p.x, b.y - p.y) <= b.r + 10);
      if (!hit) return;
      if (hit.v === this.ans) { this.score++; setProg(this.score, this.total); pop(hit.x, hit.y, '#8ff5e6'); fb(praise(), true); say(praise(), 'cheer'); if (this.score >= this.total) return win(this, 'أكملت عشر معادلات صحيحة!'); setTimeout(() => this.newRound(), 700); this.bubbles = []; }
      else { pop(hit.x, hit.y, '#ffe99a'); fb(gentle(), false); hit.y += 40; }
    },
    update() { this.bubbles.forEach(b => { b.y -= b.sp; if (b.y < -b.r) b.y = H + b.r; }); },
    draw() {
      bg('#1b1035', '#2b1d5e');
      ctx.fillStyle = 'rgba(255,255,255,.08)'; ctx.font = `900 ${Math.min(72, W / 8)}px Cairo, sans-serif`; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(this.q, W / 2, H * .18);
      this.bubbles.forEach(b => bubble(b.x, b.y, b.r, b.color, ar(b.v))); drawFx();
    }
  };

  /* ============================================================
     2) تتبّع الحروف — الحرف يُرسم كقناع على كانفاس خفي، والطفل يمرّ بإصبعه
        فوقه؛ نحسب نسبة خلايا القناع التي لمسها. لا مؤقّت، لا «خطأ»:
        الخروج عن الحرف لا يُحتسب ولا يُعاقَب.
     ============================================================ */
  const LETTERS1 = [['ا','أرنب','🐇'],['ب','بطة','🦆'],['ت','تفاحة','🍎'],['د','دب','🐻'],['ر','رمان','🍅'],['س','سمكة','🐟'],['ع','عنب','🍇'],['ق','قمر','🌙'],['م','موز','🍌'],['ن','نجمة','⭐']];
  const LETTERS2 = [['ج','جمل','🐪'],['ح','حوت','🐋'],['خ','خروف','🐑'],['ص','صقر','🦅'],['ض','ضفدع','🐸'],['ط','طائرة','✈️'],['ظ','ظرف','✉️'],['غ','غزال','🦌'],['ف','فراشة','🦋'],['ك','كتاب','📖'],['ه','هلال','🌙'],['ي','يد','✋']];
  const Trace = {
    title: '✍️ تتبّع الحروف', total: 6, need: LEVEL === 1 ? .62 : .75,
    init() { this.score = 0; this.fx = []; this.pool = shuffle([...(LEVEL === 1 ? LETTERS1 : LETTERS2)]); this.drawing = false; this.newRound(); },
    newRound() {
      const [ch, word, emoji] = this.pool.pop(); this.ch = ch; this.word = word; this.emoji = emoji; this.done = false;
      this.strokes = []; this.cur = null; this.buildMask(); this.covered = new Set(); this.pct = 0;
      ask(`حرف ${ch}، مثل ${word} ${emoji}. ارسم الحرف بإصبعك فوق شكله.`);
      $('egQ').textContent = `${emoji}  ${word}  —  حرف «${ch}»`;
    },
    /* قناع الحرف بخلايا 8px: يُعاد بناؤه عند تغيّر المقاس */
    buildMask() {
      this.cell = 8; this.fs = Math.min(H * .78, W * .6);
      const off = document.createElement('canvas'); off.width = Math.ceil(W); off.height = Math.ceil(H); const o = off.getContext('2d');
      o.fillStyle = '#fff'; o.font = `900 ${this.fs}px "Baloo Bhaijaan 2", Cairo, sans-serif`; o.textAlign = 'center'; o.textBaseline = 'middle'; o.fillText(this.ch, W / 2, H * .5);
      const d = o.getImageData(0, 0, off.width, off.height).data; const cols = Math.ceil(W / this.cell), rows = Math.ceil(H / this.cell);
      this.mask = new Set(); this.maskW = off.width;
      for (let cy = 0; cy < rows; cy++) for (let cx = 0; cx < cols; cx++) {
        let hit = 0; for (let y = 0; y < this.cell; y += 2) for (let x = 0; x < this.cell; x += 2) { const px = cx * this.cell + x, py = cy * this.cell + y; if (px < off.width && py < off.height && d[(py * off.width + px) * 4 + 3] > 128) hit++; }
        if (hit >= 4) this.mask.add(cy * cols + cx);
      }
      this.cols = cols;
    },
    paint(p) {
      if (this.done) return;
      if (this.cur) this.cur.push(p);
      const r = this.cell * 2.2; // سماحة الإصبع
      for (let dy = -r; dy <= r; dy += this.cell) for (let dx = -r; dx <= r; dx += this.cell) {
        const k = Math.floor((p.y + dy) / this.cell) * this.cols + Math.floor((p.x + dx) / this.cell);
        if (this.mask.has(k)) this.covered.add(k);
      }
      this.pct = this.covered.size / Math.max(1, this.mask.size);
      if (this.pct >= this.need) this.finishLetter();
    },
    finishLetter() {
      this.done = true; this.score++; setProg(this.score, this.total); pop(W / 2, H / 2, '#8ff5e6');
      fb(`${praise()} حرف ${this.ch} — ${this.word}`, true); say(`${praise()} ${this.ch}، ${this.word}.`, 'cheer');
      if (this.score >= this.total) return win(this, 'رسمت ستة حروف بيدك!');
      setTimeout(() => this.newRound(), 1100);
    },
    down(p) { if (this.done) return; this.drawing = true; this.cur = [p]; this.strokes.push(this.cur); this.paint(p); },
    move(p) { if (this.drawing) this.paint(p); },
    up() { this.drawing = false; this.cur = null; if (!this.done && this.pct > 0 && this.pct < this.need) fb(`أكمل الحرف… ${Math.round(this.pct * 100)}٪`, true); },
    tap() {},
    update() {},
    draw() {
      bg('#231448', '#160a30');
      // الحرف الدليل (خافت) ثم الجزء الملوّن الذي غُطّي
      ctx.save(); ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.font = `900 ${this.fs}px "Baloo Bhaijaan 2", Cairo, sans-serif`;
      ctx.lineWidth = 3; ctx.strokeStyle = 'rgba(255,255,255,.35)'; ctx.setLineDash([10, 8]); ctx.strokeText(this.ch, W / 2, H * .5); ctx.setLineDash([]);
      ctx.fillStyle = 'rgba(255,255,255,.08)'; ctx.fillText(this.ch, W / 2, H * .5); ctx.restore();
      ctx.fillStyle = 'rgba(46,196,182,.55)';
      this.covered.forEach(k => { const cx = k % this.cols, cy = Math.floor(k / this.cols); ctx.fillRect(cx * this.cell, cy * this.cell, this.cell, this.cell); });
      // خطوط الإصبع
      ctx.strokeStyle = '#ffc93c'; ctx.lineWidth = 14; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.globalAlpha = .9;
      this.strokes.forEach(st => { if (st.length < 2) return; ctx.beginPath(); ctx.moveTo(st[0].x, st[0].y); st.forEach(q => ctx.lineTo(q.x, q.y)); ctx.stroke(); }); ctx.globalAlpha = 1;
      // شريط التغطية
      ctx.fillStyle = 'rgba(255,255,255,.12)'; ctx.fillRect(W * .2, H - 26, W * .6, 12); ctx.fillStyle = '#2ec4b6'; ctx.fillRect(W * .2, H - 26, W * .6 * Math.min(1, this.pct / this.need), 12);
      ctx.fillStyle = '#fff'; ctx.font = '900 44px sans-serif'; ctx.textAlign = 'right'; ctx.textBaseline = 'top'; ctx.fillText(this.emoji, W - 16, 12);
      drawFx();
    }
  };

  /* ============================================================
     3) إيقاع الذاكرة — تسلسل يطول كل جولة من أيقونات عالم الرفيق
     ============================================================ */
  const THEME_ICONS = ((window.KIDAURA_ACTIVE_CHARACTER || {}).icons || []).filter(Boolean);
  const SHAPES = [0, 1, 2, 3, 4, 5].map(i => [THEME_ICONS[i] || ['●', '■', '▲', '★', '♥', '◆'][i], PALETTE[i]]);
  const Memory = {
    title: '🧩 إيقاع الذاكرة', total: 6,
    init() { this.score = 0; this.fx = []; this.seq = []; this.lit = -1; this.input = []; this.busy = false; this.n = LEVEL === 1 ? 4 : 6; this.newRound(); },
    cells() { const cols = this.n === 4 ? 2 : 3, rows = this.n / cols; const s = Math.min(W / (cols + 1), H / (rows + .8)); const ox = (W - cols * s) / 2, oy = (H - rows * s) / 2; return Array.from({ length: this.n }, (_, i) => ({ x: ox + (i % cols) * s + s / 2, y: oy + Math.floor(i / cols) * s + s / 2, r: s * .38 })); },
    async newRound() {
      this.seq.push(rnd(0, this.n - 1)); this.input = []; this.busy = true;
      await ask(`الجولة ${ar(this.seq.length)}. راقب الأشكال التي تضيء.`);
      for (const i of this.seq) { this.lit = i; await new Promise(r => setTimeout(r, LEVEL === 1 ? 650 : 480)); this.lit = -1; await new Promise(r => setTimeout(r, 220)); }
      this.busy = false; fb('الآن كرّر الترتيب 👇', true); say('الآن دورك. كرّر الترتيب.');
    },
    tap(p) {
      if (this.busy) return;
      const cs = this.cells(); const i = cs.findIndex(c => Math.hypot(c.x - p.x, c.y - p.y) <= c.r + 12); if (i < 0) return;
      this.lit = i; setTimeout(() => { if (this.lit === i) this.lit = -1; }, 220); pop(cs[i].x, cs[i].y, SHAPES[i][1]);
      this.input.push(i);
      const k = this.input.length - 1;
      if (this.input[k] !== this.seq[k]) { fb(gentle() + ' سأعرض التسلسل مرة أخرى.', false); say('لا بأس، شاهد التسلسل مرة أخرى.'); this.seq.pop(); this.busy = true; setTimeout(() => this.newRound(), 900); return; }
      if (this.input.length === this.seq.length) { this.score++; setProg(this.score, this.total); fb(praise(), true); say(praise(), 'cheer'); if (this.score >= this.total) return win(this, 'حفظت تسلسلاً من ستة أشكال!'); this.busy = true; setTimeout(() => this.newRound(), 900); }
    },
    update() {},
    draw() {
      bg('#2a1050', '#160a30');
      this.cells().forEach((c, i) => {
        const on = this.lit === i; ctx.save(); ctx.globalAlpha = on ? 1 : .45; ctx.shadowColor = SHAPES[i][1]; ctx.shadowBlur = on ? 40 : 0;
        ctx.fillStyle = 'rgba(255,255,255,.08)'; ctx.beginPath(); ctx.roundRect(c.x - c.r, c.y - c.r, c.r * 2, c.r * 2, 22); ctx.fill();
        ctx.fillStyle = SHAPES[i][1]; ctx.font = `900 ${Math.round(c.r * 1.2)}px sans-serif`; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(SHAPES[i][0], c.x, c.y + 4); ctx.restore();
      });
      drawFx();
    }
  };

  /* ============================================================
     4) فرّز بذكاء — سلّتان؛ يظهر شيء واحد في المنتصف ويُسحب (أو تُضغط السلّة)
        إلى مكانه. المجموعات: صحي/غير صحي، يُعاد تدويره/لا.
     ============================================================ */
  const SORT_SETS = [
    { q: 'صحي أم غير صحي؟', a: ['🥗 صحي', '#2ec4b6'], b: ['🍭 غير صحي', '#ff6fa5'],
      items: [['🍎', 0], ['🥕', 0], ['🥦', 0], ['💧', 0], ['🍌', 0], ['🥛', 0], ['🍟', 1], ['🍭', 1], ['🥤', 1], ['🍩', 1], ['🍫', 1], ['🍬', 1]] },
    { q: 'يُعاد تدويره أم لا؟', a: ['♻️ يُعاد تدويره', '#2ec4b6'], b: ['🗑️ نفايات', '#6c63ff'],
      items: [['📰', 0], ['🍾', 0], ['🥫', 0], ['📦', 0], ['🧴', 0], ['📄', 0], ['🍌', 1], ['🍎', 1], ['🧻', 1], ['🍕', 1], ['🥚', 1], ['🌽', 1]] },
  ];
  const Sort = {
    title: '🧺 فرّز بذكاء', total: 10,
    init() { this.score = 0; this.fx = []; this.set = SORT_SETS[rnd(0, SORT_SETS.length - 1)]; this.queue = shuffle([...this.set.items]).slice(0, this.total); this.drag = null; this.next(); },
    baskets() { const bw = Math.min(W * .34, 260), bh = Math.min(H * .34, 150); return [{ x: W * .04, y: H - bh - 12, w: bw, h: bh, k: 0 }, { x: W - bw - W * .04, y: H - bh - 12, w: bw, h: bh, k: 1 }]; },
    next() {
      if (!this.queue.length) return;
      const [icon, k] = this.queue[0]; this.item = { icon, k, x: W / 2, y: H * .3, r: Math.min(56, W / 9) };
      ask(`${this.set.q} أين نضع ${icon}؟ اسحبه إلى سلّته.`); $('egQ').textContent = `${this.set.q}  ${icon}`;
    },
    drop(k) {
      const it = this.item; if (!it) return;
      if (k === it.k) { this.score++; this.queue.shift(); setProg(this.score, this.total); pop(it.x, it.y, '#8ff5e6'); fb(praise(), true); say(praise(), 'cheer'); this.item = null;
        if (this.score >= this.total) return win(this, 'فرزت عشرة أشياء في سلّتها الصحيحة!'); setTimeout(() => this.next(), 700); }
      else { pop(it.x, it.y, '#ffe99a'); fb(`${gentle()} فكّر: ${it.icon} — ${this.set.q}`, false); it.x = W / 2; it.y = H * .3; }
    },
    inBasket(p) { return this.baskets().find(b => p.x >= b.x && p.x <= b.x + b.w && p.y >= b.y && p.y <= b.y + b.h); },
    down(p) { const it = this.item; if (!it) return; if (Math.hypot(it.x - p.x, it.y - p.y) <= it.r + 14) { this.drag = { dx: it.x - p.x, dy: it.y - p.y }; return; } const b = this.inBasket(p); if (b) this.drop(b.k); },
    move(p) { if (this.drag && this.item) { this.item.x = p.x + this.drag.dx; this.item.y = p.y + this.drag.dy; } },
    up(p) { if (!this.drag) return; this.drag = null; const b = this.inBasket(p); if (b) this.drop(b.k); else if (this.item) { this.item.x = W / 2; this.item.y = H * .3; } },
    tap() {},
    update() {},
    draw() {
      bg('#1b1035', '#2a1b4e');
      this.baskets().forEach(b => {
        const lab = b.k === 0 ? this.set.a : this.set.b;
        ctx.save(); ctx.fillStyle = lab[1]; ctx.globalAlpha = .28; ctx.beginPath(); ctx.roundRect(b.x, b.y, b.w, b.h, 22); ctx.fill(); ctx.globalAlpha = 1;
        ctx.strokeStyle = lab[1]; ctx.lineWidth = 4; ctx.setLineDash([12, 8]); ctx.beginPath(); ctx.roundRect(b.x, b.y, b.w, b.h, 22); ctx.stroke(); ctx.setLineDash([]);
        ctx.fillStyle = '#fff'; ctx.font = `900 ${Math.min(24, b.w / 9)}px Cairo, sans-serif`; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(lab[0], b.x + b.w / 2, b.y + b.h / 2); ctx.restore();
      });
      const it = this.item;
      if (it) { ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.5)'; ctx.shadowBlur = 20; ctx.fillStyle = 'rgba(255,255,255,.12)'; ctx.beginPath(); ctx.arc(it.x, it.y, it.r, 0, Math.PI * 2); ctx.fill(); ctx.shadowBlur = 0;
        ctx.font = `${Math.round(it.r * 1.3)}px sans-serif`; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(it.icon, it.x, it.y + 4); ctx.restore(); }
      ctx.fillStyle = 'rgba(255,255,255,.55)'; ctx.font = '800 15px Cairo, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'top'; ctx.fillText('اسحب الشيء إلى السلّة، أو اضغط السلّة', W / 2, 10);
      drawFx();
    }
  };

  /* ============================================================
     الفوز → api/play-game.php + علامة اليوم + احتفال الرفيق
     ============================================================ */
  function win(g, msg) {
    g.won = true; $('egWinMsg').textContent = msg; $('egWin').classList.add('open');
    try { localStorage.setItem('kidora_done_game_' + new Date().toISOString().slice(0, 10), '1'); } catch (e) {}
    fetch(window.KIDAURA_BASE + '/api/play-game.php', { method: 'POST' }).catch(() => {});
    if (window.Companion) Companion.celebrate(`مبروك يا ${NAME}! ${msg} حصلت على وسام الذكاء.`);
  }

  /* ---------- الحلقة ---------- */
  const GAMES = { numbers: Numbers, trace: Trace, sort: Sort, memory: Memory };
  function loop() { if (!game) return; game.update(); game.draw(); anim = requestAnimationFrame(loop); }
  function open(k) {
    key = k; game = GAMES[k]; if (!game) return;
    modal.classList.add('open'); resize(); $('egTitle').textContent = game.title; restart();
  }
  function restart() {
    if (!game) return; if (anim) cancelAnimationFrame(anim);
    $('egWin').classList.remove('open'); fb(''); setProg(0, game.total); game.won = false; game.init(); loop();
  }
  function close() {
    if (anim) cancelAnimationFrame(anim); anim = null; game = null; modal.classList.remove('open');
    if (window.Companion) Companion.stop();
  }
  canvas.addEventListener('pointerdown', e => { if (!game || game.won) return; e.preventDefault(); canvas.setPointerCapture(e.pointerId); if (game.down) game.down(pos(e)); else game.tap(pos(e)); });
  canvas.addEventListener('pointermove', e => { if (game && !game.won && game.move) { e.preventDefault(); game.move(pos(e)); } });
  canvas.addEventListener('pointerup', e => { if (game && !game.won && game.up) game.up(pos(e)); });
  canvas.addEventListener('pointercancel', e => { if (game && game.up) game.up(pos(e)); });
  $('egRepeat').onclick = () => lastQ && say(lastQ);
  window.addEventListener('resize', () => { if (game) { resize(); if (game.buildMask) { game.buildMask(); game.covered = new Set(); game.strokes = []; game.pct = 0; } } });
  modal.addEventListener('click', e => { if (e.target === modal) close(); });
  if (!CanvasRenderingContext2D.prototype.roundRect) {
    CanvasRenderingContext2D.prototype.roundRect = function (x, y, w, h, r) { r = Math.min(r, w / 2, h / 2); this.moveTo(x + r, y); this.arcTo(x + w, y, x + w, y + h, r); this.arcTo(x + w, y + h, x, y + h, r); this.arcTo(x, y + h, x, y, r); this.arcTo(x, y, x + w, y, r); return this; };
  }
  return { open, close, restart };
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
