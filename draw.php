<?php
// لوحة الرسم — مساحة حرّة يعبّر فيها الطفل عن نفسه، والرسمة تُحفظ في ملفه
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();
$progress = ensure_daily_progress($pdo, $child['id']);
$activeChar = active_character($pdo, $child);
$stamps = $activeChar ? character_icons($activeChar) : ['✨','⭐','🌟'];

$recent = $pdo->prepare("SELECT * FROM drawings WHERE child_id = ? ORDER BY id DESC LIMIT 6");
$recent->execute([$child['id']]);
$recent = $recent->fetchAll();

$waBase = whatsapp_link($pdo, '', $child['parent_phone'] ?? '');

$__pageTitle = 'لوحتي — Kidora';
$__pageLine = "هون اللوحة إلك يا فنّان! ارسم اللي بخاطرك، وأنا بتفرّج 🎨";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<style>
  .draw-shell{ display:grid; grid-template-columns:1fr; gap:14px; }
  .draw-board{ position:relative; background:#fff; border-radius:var(--radius-lg); box-shadow:var(--shadow-pop); overflow:hidden; border:6px solid #fff; touch-action:none; }
  .draw-board canvas{ display:block; width:100%; height:auto; cursor:crosshair; background:#fff; }
  .draw-toolbar{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:center; padding:12px; border-radius:var(--radius-md); }
  .draw-tool{ width:48px; height:48px; border-radius:14px; border:2px solid #EDE4FF; background:#fff; font-size:22px; cursor:pointer; transition:transform .15s, border-color .15s, background .15s; display:grid; place-items:center; }
  .draw-tool:hover{ transform:translateY(-2px); }
  .draw-tool.on{ border-color:var(--violet); background:#F1EEFF; box-shadow:0 0 0 3px rgba(108,99,255,.2); }
  .draw-tool:disabled{ opacity:.4; cursor:not-allowed; transform:none; }
  .draw-sep{ width:1px; height:34px; background:#EDE4FF; margin:0 4px; }
  .draw-swatch{ width:34px; height:34px; border-radius:50%; border:3px solid #fff; box-shadow:0 0 0 2px #E4DDF7; cursor:pointer; transition:transform .15s; }
  .draw-swatch.on{ transform:scale(1.2); box-shadow:0 0 0 3px var(--violet); }
  .draw-swatch.rainbow{ background:conic-gradient(#ff5e5e,#ffc93c,#4caf6d,#2ec4b6,#6c63ff,#ff6fa5,#ff5e5e); }
  .draw-size{ display:flex; align-items:center; gap:8px; font-weight:800; color:var(--ink-soft); font-size:13px; }
  .draw-size input{ width:120px; accent-color:var(--violet); }
  .draw-size-dot{ width:30px; height:30px; display:grid; place-items:center; }
  .draw-size-dot i{ display:block; border-radius:50%; background:var(--ink); }
  .draw-stamps{ display:flex; flex-wrap:wrap; gap:6px; justify-content:center; }
  .draw-stamp{ width:42px; height:42px; border-radius:12px; border:2px solid #EDE4FF; background:#fff; font-size:22px; cursor:pointer; }
  .draw-stamp.on{ border-color:var(--gold); background:#FFF6D6; }
  .draw-bgs{ display:flex; flex-wrap:wrap; gap:8px; justify-content:center; }
  .draw-bg{ width:56px; height:40px; border-radius:10px; border:3px solid #fff; box-shadow:0 0 0 2px #E4DDF7; cursor:pointer; font-size:18px; display:grid; place-items:center; }
  .draw-bg.on{ box-shadow:0 0 0 3px var(--violet); }
  .draw-actions{ display:flex; flex-wrap:wrap; gap:10px; justify-content:center; margin-top:4px; }
  .draw-hint{ text-align:center; color:#fff; text-shadow:0 2px 8px rgba(0,0,0,.35); font-weight:800; margin:4px 0 0; }
  .draw-gallery{ display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:12px; margin-top:12px; }
  .draw-thumb{ background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 10px 24px rgba(15,10,40,.25); text-align:center; padding-bottom:8px; }
  .draw-thumb img{ width:100%; aspect-ratio:4/3; object-fit:cover; display:block; background:#fff; }
  .draw-thumb small{ display:block; color:var(--ink-soft); font-weight:800; font-size:12px; margin-top:6px; padding:0 6px; }
  .draw-saved{ position:absolute; inset:0; display:none; place-items:center; background:rgba(255,255,255,.88); z-index:3; text-align:center; padding:20px; }
  .draw-saved.show{ display:grid; }
  @media(min-width:900px){ .draw-shell{ grid-template-columns:1fr 230px; align-items:start; } .draw-side{ position:sticky; top:90px; } }
</style>
<div class="page-body">
<main class="container" style="padding-top:26px;">
  <div class="section-head">
    <div class="eyebrow">مساحتك الحرّة</div>
    <h2 class="section-title">🎨 لوحتي</h2>
    <p class="section-sub">ارسم ما تشعر به اليوم: فرحة، حلماً، صديقاً، أو أي شيء يخطر ببالك. لا يوجد رسم «صحيح» أو «خطأ» — كل لوحة هنا رائعة لأنها لك.</p>
  </div>

  <div class="draw-shell">
    <div>
      <div class="card draw-toolbar" id="drawTools">
        <button class="draw-tool on" data-tool="brush" title="فرشاة">🖌️</button>
        <button class="draw-tool" data-tool="marker" title="قلم تلوين عريض">🖍️</button>
        <button class="draw-tool" data-tool="spray" title="رشّاش">💨</button>
        <button class="draw-tool" data-tool="eraser" title="ممحاة">🧽</button>
        <button class="draw-tool" data-tool="stamp" title="طوابع">⭐</button>
        <span class="draw-sep"></span>
        <label class="draw-size">الحجم <input type="range" id="drawSize" min="2" max="40" value="8"><span class="draw-size-dot"><i id="drawSizeDot" style="width:8px;height:8px;"></i></span></label>
        <span class="draw-sep"></span>
        <button class="draw-tool" id="drawUndo" title="تراجع" disabled>↩️</button>
        <button class="draw-tool" id="drawRedo" title="إعادة" disabled>↪️</button>
        <button class="draw-tool" id="drawClear" title="لوحة جديدة">🗑️</button>
      </div>

      <div class="draw-board" id="drawBoard">
        <canvas id="drawCanvas" width="1024" height="768" aria-label="لوحة الرسم"></canvas>
        <div class="draw-saved" id="drawSaved">
          <div>
            <div style="font-size:54px;">🖼️</div>
            <h3 style="color:var(--ink);margin:6px 0;">حُفظت لوحتك في ملفك!</h3>
            <p style="color:var(--ink-soft);" id="drawSavedLine"></p>
            <div class="draw-actions">
              <a class="btn btn-sm btn-mint" id="drawWa" href="#" target="_blank" rel="noopener">📲 شاركها مع أهلك</a>
              <a class="btn btn-sm btn-ghost" href="<?php echo BASE_PATH; ?>/profile.php#drawings">🖼️ معرضي</a>
              <button class="btn btn-sm btn-primary" id="drawAgain">🎨 لوحة جديدة</button>
            </div>
          </div>
        </div>
      </div>

      <div class="draw-actions">
        <input type="text" id="drawTitle" maxlength="60" placeholder="اسم اللوحة (اختياري)" style="flex:1;min-width:180px;max-width:320px;padding:12px 14px;border-radius:14px;border:2px solid #fff;font:inherit;">
        <button class="btn btn-primary" id="drawSave">💾 احفظ في ملفي</button>
        <button class="btn btn-ghost" id="drawDownload">⬇️ نزّل الصورة</button>
      </div>
      <p class="draw-hint" id="drawHint">أول لوحة تحفظها اليوم تُحسب من ألعاب اليوم 🎮</p>
    </div>

    <aside class="draw-side">
      <div class="card" style="padding:14px;">
        <h4 style="margin:0 0 8px;color:var(--ink);">🎨 الألوان</h4>
        <div class="draw-stamps" id="drawPalette"></div>
        <h4 style="margin:14px 0 8px;color:var(--ink);">⭐ طوابع</h4>
        <div class="draw-stamps" id="drawStamps"></div>
        <h4 style="margin:14px 0 8px;color:var(--ink);">🖼️ الخلفية</h4>
        <div class="draw-bgs" id="drawBgs"></div>
      </div>
    </aside>
  </div>

  <?php if ($recent): ?>
    <div class="section" style="padding:26px 0 0;">
      <h3 style="color:#fff;text-shadow:0 2px 8px rgba(0,0,0,.35);">🖼️ آخر لوحاتي <a href="<?php echo BASE_PATH; ?>/profile.php#drawings" style="font-size:13px;color:var(--gold);margin-right:8px;">كل المعرض ←</a></h3>
      <div class="draw-gallery">
        <?php foreach ($recent as $d): ?>
          <a class="draw-thumb" href="<?php echo BASE_PATH . '/' . h($d['image_path']); ?>" target="_blank" rel="noopener">
            <img src="<?php echo BASE_PATH . '/' . h($d['image_path']); ?>" alt="<?php echo h($d['title']); ?>" loading="lazy">
            <small><?php echo h($d['title']); ?></small>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</main>
</div>
<footer class="site-footer">Kidora © 2026</footer>
<script>window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;</script>
<script>
(function () {
  const canvas = document.getElementById('drawCanvas');
  const ctx = canvas.getContext('2d', { willReadFrequently: true });
  const W = canvas.width, H = canvas.height;
  const COLORS = ['#241645','#E5484D','#FF7A50','#FFC93C','#4CAF6D','#2EC4B6','#3B82F6','#6C63FF','#FF6FA5','#8B5A2B','#ffffff','#9CA3AF'];
  const STAMPS = <?php echo json_encode(array_values(array_unique(array_merge($stamps, ['❤️','🌈','🌞','🌸','🦋','🐱','🚀','🎈','🍎','🏠','🌳','⚽']))), JSON_UNESCAPED_UNICODE); ?>;
  const BGS = [
    { key: 'white', label: '⬜', paint: c => { c.fillStyle = '#fff'; c.fillRect(0, 0, W, H); } },
    { key: 'paper', label: '📜', paint: c => { c.fillStyle = '#FFF7E0'; c.fillRect(0, 0, W, H); c.strokeStyle = 'rgba(120,80,20,.12)'; for (let y = 60; y < H; y += 48) { c.beginPath(); c.moveTo(0, y); c.lineTo(W, y); c.stroke(); } } },
    { key: 'sky', label: '☁️', paint: c => { const g = c.createLinearGradient(0, 0, 0, H); g.addColorStop(0, '#9CD3FF'); g.addColorStop(1, '#E8F6FF'); c.fillStyle = g; c.fillRect(0, 0, W, H); c.fillStyle = 'rgba(255,255,255,.9)'; [[200,140],[260,120],[330,150],[720,200],[790,180],[860,210]].forEach(([x,y]) => { c.beginPath(); c.arc(x, y, 46, 0, Math.PI * 2); c.fill(); }); } },
    { key: 'grass', label: '🌿', paint: c => { const g = c.createLinearGradient(0, 0, 0, H); g.addColorStop(0, '#BDE8FF'); g.addColorStop(.62, '#E9F8FF'); g.addColorStop(.62, '#8ED081'); g.addColorStop(1, '#5CB85C'); c.fillStyle = g; c.fillRect(0, 0, W, H); c.fillStyle = '#FFD84D'; c.beginPath(); c.arc(W - 140, 130, 70, 0, Math.PI * 2); c.fill(); } },
    { key: 'sea', label: '🌊', paint: c => { const g = c.createLinearGradient(0, 0, 0, H); g.addColorStop(0, '#FFE9A8'); g.addColorStop(.5, '#FFD1DC'); g.addColorStop(.5, '#2EC4B6'); g.addColorStop(1, '#1B6FA8'); c.fillStyle = g; c.fillRect(0, 0, W, H); c.fillStyle = '#FFF3B0'; c.fillRect(0, H * .82, W, H * .18); } },
    { key: 'night', label: '🌙', paint: c => { const g = c.createLinearGradient(0, 0, 0, H); g.addColorStop(0, '#1B1035'); g.addColorStop(1, '#3A2A75'); c.fillStyle = g; c.fillRect(0, 0, W, H); c.fillStyle = '#fff'; for (let i = 0; i < 90; i++) { c.globalAlpha = .4 + Math.random() * .6; c.beginPath(); c.arc(Math.random() * W, Math.random() * H * .8, 1 + Math.random() * 2.2, 0, Math.PI * 2); c.fill(); } c.globalAlpha = 1; c.fillStyle = '#FFE9A8'; c.beginPath(); c.arc(160, 130, 56, 0, Math.PI * 2); c.fill(); } },
  ];

  let tool = 'brush', color = COLORS[0], size = 8, stamp = STAMPS[0], rainbow = false, hue = 0;
  let drawing = false, last = null, undo = [], redo = [], dirty = false;
  const $ = id => document.getElementById(id);

  function snapshot() {
    undo.push(ctx.getImageData(0, 0, W, H));
    if (undo.length > 25) undo.shift();
    redo = [];
    syncHistoryButtons();
  }
  function syncHistoryButtons() { $('drawUndo').disabled = !undo.length; $('drawRedo').disabled = !redo.length; }

  function paintBg(bg, keepHistory) {
    if (keepHistory) snapshot();
    bg.paint(ctx);
  }

  // ---- الأدوات
  document.querySelectorAll('#drawTools [data-tool]').forEach(b => b.onclick = () => {
    tool = b.dataset.tool;
    document.querySelectorAll('#drawTools [data-tool]').forEach(x => x.classList.toggle('on', x === b));
    canvas.style.cursor = tool === 'stamp' ? 'copy' : tool === 'eraser' ? 'cell' : 'crosshair';
  });
  $('drawSize').oninput = e => { size = +e.target.value; const d = $('drawSizeDot'); d.style.width = d.style.height = Math.min(28, size) + 'px'; };

  const pal = $('drawPalette');
  COLORS.forEach((c, i) => {
    const s = document.createElement('button');
    s.className = 'draw-swatch' + (i === 0 ? ' on' : ''); s.style.background = c; s.title = c; s.dataset.color = c;
    s.onclick = () => { color = c; rainbow = false; pal.querySelectorAll('.draw-swatch').forEach(x => x.classList.toggle('on', x === s)); if (tool === 'eraser') document.querySelector('[data-tool="brush"]').click(); };
    pal.appendChild(s);
  });
  const rb = document.createElement('button');
  rb.className = 'draw-swatch rainbow'; rb.title = 'قوس قزح'; rb.dataset.color = 'rainbow';
  rb.onclick = () => { rainbow = true; pal.querySelectorAll('.draw-swatch').forEach(x => x.classList.toggle('on', x === rb)); if (tool === 'eraser') document.querySelector('[data-tool="brush"]').click(); };
  pal.appendChild(rb);

  const st = $('drawStamps');
  STAMPS.forEach((s, i) => {
    const b = document.createElement('button');
    b.className = 'draw-stamp' + (i === 0 ? ' on' : ''); b.textContent = s; b.dataset.stamp = s;
    b.onclick = () => { stamp = s; st.querySelectorAll('.draw-stamp').forEach(x => x.classList.toggle('on', x === b)); document.querySelector('[data-tool="stamp"]').click(); };
    st.appendChild(b);
  });

  const bgs = $('drawBgs');
  BGS.forEach((bg, i) => {
    const b = document.createElement('button');
    b.className = 'draw-bg' + (i === 0 ? ' on' : ''); b.textContent = bg.label; b.title = bg.key; b.dataset.bg = bg.key;
    b.onclick = () => {
      // تغيير الخلفية بعد الرسم يمسح اللوحة، فنطلب تأكيداً فقط إن كان هناك رسم
      if (dirty && !confirm('تغيير الخلفية يبدأ لوحة جديدة. متابعة؟')) return;
      bgs.querySelectorAll('.draw-bg').forEach(x => x.classList.toggle('on', x === b));
      paintBg(bg, true); dirty = false;
    };
    bgs.appendChild(b);
  });

  // ---- الرسم
  function pos(e) {
    const r = canvas.getBoundingClientRect();
    return { x: (e.clientX - r.left) * (W / r.width), y: (e.clientY - r.top) * (H / r.height) };
  }
  function strokeColor() {
    if (tool === 'eraser') return '#ffffff';
    if (!rainbow) return color;
    hue = (hue + 4) % 360;
    return `hsl(${hue} 90% 55%)`;
  }
  function dot(p) {
    ctx.save();
    if (tool === 'eraser') ctx.globalCompositeOperation = 'destination-out';
    ctx.globalAlpha = tool === 'marker' ? .35 : 1;
    ctx.fillStyle = strokeColor();
    ctx.beginPath(); ctx.arc(p.x, p.y, (tool === 'marker' ? size * 1.6 : size) / 2, 0, Math.PI * 2); ctx.fill();
    ctx.restore();
  }
  function line(a, b) {
    ctx.save();
    if (tool === 'eraser') ctx.globalCompositeOperation = 'destination-out';
    ctx.globalAlpha = tool === 'marker' ? .35 : 1;
    ctx.strokeStyle = strokeColor();
    ctx.lineWidth = tool === 'marker' ? size * 1.6 : size;
    ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
    ctx.restore();
  }
  function spray(p) {
    ctx.save(); ctx.fillStyle = strokeColor();
    for (let i = 0; i < 18; i++) {
      const ang = Math.random() * Math.PI * 2, rad = Math.random() * size * 1.4;
      ctx.beginPath(); ctx.arc(p.x + Math.cos(ang) * rad, p.y + Math.sin(ang) * rad, 1.2, 0, Math.PI * 2); ctx.fill();
    }
    ctx.restore();
  }
  function placeStamp(p) {
    ctx.save();
    ctx.font = Math.max(28, size * 3.2) + 'px "Segoe UI Emoji","Apple Color Emoji","Noto Color Emoji",sans-serif';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(stamp, p.x, p.y);
    ctx.restore();
  }

  canvas.addEventListener('pointerdown', e => {
    e.preventDefault();
    canvas.setPointerCapture(e.pointerId);
    snapshot();
    dirty = true;
    const p = pos(e);
    if (tool === 'stamp') { placeStamp(p); return; }
    drawing = true; last = p;
    if (tool === 'spray') spray(p); else dot(p);
  });
  canvas.addEventListener('pointermove', e => {
    if (!drawing) return;
    e.preventDefault();
    const p = pos(e);
    if (tool === 'spray') spray(p); else line(last, p);
    last = p;
  });
  const stop = () => { drawing = false; last = null; };
  canvas.addEventListener('pointerup', stop);
  canvas.addEventListener('pointercancel', stop);
  canvas.addEventListener('pointerleave', stop);

  // ---- تراجع / إعادة / مسح
  $('drawUndo').onclick = () => { if (!undo.length) return; redo.push(ctx.getImageData(0, 0, W, H)); ctx.putImageData(undo.pop(), 0, 0); syncHistoryButtons(); };
  $('drawRedo').onclick = () => { if (!redo.length) return; undo.push(ctx.getImageData(0, 0, W, H)); ctx.putImageData(redo.pop(), 0, 0); syncHistoryButtons(); };
  $('drawClear').onclick = () => {
    if (dirty && !confirm('تبدأ لوحة جديدة؟ الرسمة الحالية ستُمسح.')) return;
    snapshot();
    const on = bgs.querySelector('.draw-bg.on');
    (BGS.find(b => b.key === (on && on.dataset.bg)) || BGS[0]).paint(ctx);
    dirty = false;
  };

  // ---- حفظ / تنزيل / مشاركة
  const flatten = () => {
    // الممحاة تُفرغ البكسلات؛ نُسطّح على أبيض حتى تخرج PNG بلا شفافية
    const out = document.createElement('canvas'); out.width = W; out.height = H;
    const oc = out.getContext('2d'); oc.fillStyle = '#fff'; oc.fillRect(0, 0, W, H); oc.drawImage(canvas, 0, 0);
    return out.toDataURL('image/png');
  };
  $('drawDownload').onclick = () => {
    const a = document.createElement('a');
    a.href = flatten(); a.download = ((($('drawTitle').value || 'kidora-drawing').trim()).replace(/\s+/g, '_')) + '.png';
    document.body.appendChild(a); a.click(); a.remove();
  };
  $('drawSave').onclick = () => {
    if (!dirty && !undo.length) { window.companionSay && window.companionSay('ارسم شيئاً أولاً، ثم احفظه 🎨'); return; }
    const btn = $('drawSave'); btn.disabled = true; btn.textContent = '⏳ جاري الحفظ…';
    fetch(window.KIDAURA_BASE + '/api/save-drawing.php', {
      method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ image: flatten(), title: $('drawTitle').value.trim() })
    }).then(r => r.json()).then(d => {
      btn.disabled = false; btn.textContent = '💾 احفظ في ملفي';
      if (!d.ok) { alert(d.error || 'تعذّر الحفظ'); return; }
      const abs = location.origin + d.url;
      $('drawSavedLine').textContent = d.counted_as_game ? 'ولأنها أول لوحة اليوم، حُسبت لك لعبة من ألعاب اليوم 🎮' : 'لوحة جديدة في معرضك ✨';
      $('drawWa').href = <?php echo json_encode($waBase); ?> + encodeURIComponent(`🎨 لوحة جديدة من ريشة ${<?php echo json_encode($child['name'], JSON_UNESCAPED_UNICODE); ?>} على Kidora: «${d.title}»\n${abs}`);
      $('drawSaved').classList.add('show');
      window.companionSay && window.companionSay('لوحة رائعة! حفظتها في ملفك 🖼️');
      if (typeof SoundEngine !== 'undefined') SoundEngine.sfx('win');
    }).catch(() => { btn.disabled = false; btn.textContent = '💾 احفظ في ملفي'; alert('تعذّر الاتصال بالخادم'); });
  };
  $('drawAgain').onclick = () => { $('drawSaved').classList.remove('show'); undo = []; redo = []; syncHistoryButtons(); BGS[0].paint(ctx); bgs.querySelectorAll('.draw-bg').forEach((x, i) => x.classList.toggle('on', i === 0)); dirty = false; $('drawTitle').value = ''; };

  BGS[0].paint(ctx);
  window.KidoraDraw = { canvas, isDirty: () => dirty }; // للاختبارات الآلية
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
