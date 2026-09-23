/* ============================================================
   StoryPlayer — مشغّل موحّد لكل أنواع القصص (اليومية، الكبرى،
   قصص الأصدقاء، القصص الثقافية): سلايدشو + سرد صوتي + تنزيل
   فيديو حقيقي (Canvas + MediaRecorder) + مشاركة (Web Share API)

   وضعان للعرض:
     الافتراضي  — مشهد متدرّج اللون مع تعليق (القصص الثقافية/الأصدقاء/البروفايل)
     book: true — كتاب مصوّر: صفحة ورقية، شريط الفصل، لوحة مرسومة، فقاعة
                  حوار الرفيق، صورة الطفل، تقليب ثلاثي الأبعاد، وتشغيل
                  تلقائي يتبع نهاية السرد الصوتي (القصة اليومية والكبرى)
   ============================================================ */
const StoryPlayer = (function () {

  // زمن بقاء المشهد في التشغيل التلقائي (بلا صوت)، وزمن تلاشي النص بين مشهدين
  const SCENE_MS = 4500;
  const FADE_MS = 260;
  const TURN_MS = 520;

  const KIND_LABEL = { cover: 'الغلاف', opening: 'البداية', chapter: 'الفصل', obstacle: 'عقبة!', figure: 'من تراثنا', climax: 'الذروة', moral: 'حكمة اليوم', end: 'الخاتمة' };
  const AR_DIGITS = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
  const arNum = n => String(n).replace(/\d/g, d => AR_DIGITS[+d]);
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const stripEmoji = s => String(s || '').replace(/[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}\u{FE0F}]/gu, '');

  /** عنوان شريط الصفحة: «الفصل ٢» للفصول، واسم النوع لغيرها */
  function ribbonLabel(story, idx) {
    const s = story.scenes[idx];
    if (!s.kind) return s.title || '';
    if (s.kind === 'chapter') {
      let n = 0;
      for (let i = 0; i <= idx; i++) if (story.scenes[i].kind === 'chapter') n++;
      return `الفصل ${arNum(n)}`;
    }
    return KIND_LABEL[s.kind] || s.title || '';
  }

  function render(story, containerId, opts = {}) {
    const container = document.getElementById(containerId);
    if (!container || !story || !story.scenes || !story.scenes.length) return;
    // القصة اليومية الجديدة مشهد واحد: لوحة واحدة يقرأها الرفيق دفعة واحدة
    if ((opts.book || opts.single) && story.scenes.length === 1) return renderSingle(story, containerId, container, opts);
    if (opts.book) return renderBook(story, containerId, container, opts);

    // animate = قصة متحركة: تشغيل تلقائي + انتقال بين المشاهد.
    // بدونه يبقى السلوك القديم (تقليب يدوي) للقصص التي لا تحتاج حركة.
    const animate = !!opts.animate;
    let idx = 0, timer = null;

    container.innerHTML = `
      ${opts.badge ? `<p style="text-align:center;color:var(--mint);font-weight:800;">${opts.badge}</p>` : ''}
      <div class="story-player">
        <div class="story-scene" id="${containerId}_scene" style="background:linear-gradient(135deg, ${story.scenes[0].grad});">
          ${story.photo ? `<div class="story-photo-badge"><img src="${story.photo}"></div>` : ''}
          ${story.spriteFace ? `<div class="story-sprite">${story.spriteFace}</div>` : ''}
          <div class="story-chapter" id="${containerId}_chapter"></div>
          <div class="story-scene-caption" id="${containerId}_caption">${story.scenes[0].caption}</div>
        </div>
        <div class="story-controls">
          <button class="btn btn-sm btn-ghost" id="${containerId}_prev">◀ السابق</button>
          <span class="grow" id="${containerId}_counter">1 / ${story.scenes.length}</span>
          <button class="btn btn-sm btn-ghost" id="${containerId}_next">التالي ▶</button>
          ${animate ? `<button class="btn btn-sm btn-primary" id="${containerId}_play">⏸️ إيقاف</button>` : ''}
          <button class="btn btn-sm btn-mint" id="${containerId}_narrate">🔊 اسمع القصة</button>
        </div>
      </div>
      <div class="story-actions">
        <button class="btn btn-primary btn-sm" id="${containerId}_download">⬇️ تنزيل كفيديو</button>
        <button class="btn btn-ghost btn-sm" id="${containerId}_share">🔗 مشاركة</button>
      </div>`;

    const el = suffix => document.getElementById(`${containerId}_${suffix}`);

    function paint(withFade) {
      const caption = el('caption'), chapter = el('chapter');
      const apply = () => {
        const s = story.scenes[idx];
        el('scene').style.background = `linear-gradient(135deg, ${s.grad})`;
        caption.textContent = s.caption;
        el('counter').textContent = `${idx + 1} / ${story.scenes.length}`;
        // العنوان والأيقونة اختياريان — القصص المولّدة قبل الفصول بلا أيّهما
        chapter.innerHTML = (s.icon || s.title)
          ? `${s.icon ? `<div class="story-chapter-icon">${s.icon}</div>` : ''}${s.title ? `<div class="story-chapter-title">${s.title}</div>` : ''}`
          : '';
        caption.classList.remove('is-out');
        chapter.classList.remove('is-out');
      };
      if (!withFade) return apply();
      caption.classList.add('is-out');
      chapter.classList.add('is-out');
      setTimeout(apply, FADE_MS);
    }

    function stopPlay() {
      if (timer) { clearInterval(timer); timer = null; }
      const b = el('play');
      if (b) { b.textContent = '▶ تشغيل'; b.classList.remove('btn-primary'); b.classList.add('btn-ghost'); }
    }
    function startPlay() {
      if (timer) return;
      // الضغط على «تشغيل» في آخر مشهد يعيد القصة من أولها
      if (idx >= story.scenes.length - 1) { idx = 0; paint(true); }
      const b = el('play');
      if (b) { b.textContent = '⏸️ إيقاف'; b.classList.remove('btn-ghost'); b.classList.add('btn-primary'); }
      timer = setInterval(() => {
        if (idx >= story.scenes.length - 1) return stopPlay();
        idx++; paint(true);
      }, SCENE_MS);
    }

    paint(false);
    // التقليب اليدوي يوقف التشغيل التلقائي — الطفل هو من يقود
    el('prev').onclick = () => { stopPlay(); idx = Math.max(0, idx - 1); paint(true); };
    el('next').onclick = () => { stopPlay(); idx = Math.min(story.scenes.length - 1, idx + 1); paint(true); };
    el('narrate').onclick = () => narrate(story);
    el('download').onclick = () => exportVideo(story);
    el('share').onclick = () => share(story);

    if (animate && story.scenes.length > 1) {
      el('play').onclick = () => (timer ? stopPlay() : startPlay());
      setTimeout(startPlay, 900);
    }
  }

  /* ---------------- وضع اللوحة الواحدة (القصة اليومية) ----------------
     لوحة كبيرة واحدة: فنّ متدرّج، صورة الطفل والرفيق، النص كاملاً، وفقاعة
     الرفيق. زر «اقرأ لي» يقرأ اللوحة كلّها بصوت الرفيق (Companion إن وُجد)،
     ويبدأ تلقائياً مع animate. */
  function renderSingle(story, containerId, container, opts) {
    const s = story.scenes[0];
    const c = window.KIDAURA_ACTIVE_CHARACTER || {};
    const companionHtml = c.image
      ? `<img src="${(window.KIDAURA_BASE || '')}/${c.image}" alt="">`
      : `<span>${story.spriteFace || (c.icons && c.icons[0]) || '✨'}</span>`;
    const [g1, g2] = String(s.grad || '#6C63FF,#FF6FA5').split(',');

    container.innerHTML = `
      ${opts.badge ? `<p style="text-align:center;color:var(--mint);font-weight:800;">${opts.badge}</p>` : ''}
      <div class="story-single" id="${containerId}_single" style="--g1:${g1.trim()};--g2:${(g2 || g1).trim()}">
        <div class="single-art">
          <div class="book-art-sky"></div>
          <div class="book-art-hill"></div>
          <div class="book-art-hill two"></div>
          <div class="single-icon">${s.icon || '📖'}</div>
          <div class="book-hero">
            ${story.photo ? `<div class="book-hero-photo"><img src="${story.photo}" alt=""></div>` : `<div class="book-hero-photo book-hero-fallback">🧒</div>`}
            ${story.childName ? `<div class="book-hero-name">${esc(story.childName)}</div>` : ''}
          </div>
          <div class="book-companion">${companionHtml}</div>
        </div>
        <div class="single-text">
          <h3 class="single-title">${esc(s.title || story.title || '')}</h3>
          <p class="single-caption" id="${containerId}_caption">${esc(s.caption || '')}</p>
          ${s.quote ? `<div class="single-quote"><b>${esc(s.speaker || '')}</b>${esc(s.quote)}</div>` : ''}
        </div>
        <div class="story-controls book-controls">
          <button class="btn btn-primary" id="${containerId}_play">▶ اقرأ لي</button>
        </div>
      </div>
      <div class="story-actions">
        <button class="btn btn-primary btn-sm" id="${containerId}_download">⬇️ تنزيل كفيديو</button>
        <button class="btn btn-ghost btn-sm" id="${containerId}_share">🔗 مشاركة</button>
      </div>`;

    const el = suffix => document.getElementById(`${containerId}_${suffix}`);
    const box = el('single');
    let reading = false;
    const text = stripEmoji((s.title ? s.title + '. ' : '') + s.caption + (s.quote ? '. ' + (s.speaker ? s.speaker + ' ' : '') + s.quote : ''));

    function stop() {
      reading = false;
      if (window.Companion) Companion.stop(); else if ('speechSynthesis' in window) window.speechSynthesis.cancel();
      el('play').textContent = '▶ اقرأ لي'; box.classList.remove('is-reading');
    }
    function start() {
      if (reading) return;
      reading = true; el('play').textContent = '⏸️ إيقاف'; box.classList.add('is-reading');
      const done = () => { if (reading) stop(); };
      if (window.Companion) Companion.say(text, { mood: 'talk' }).then(done, done);
      else if (typeof SoundEngine !== 'undefined' && SoundEngine.speak) {
        if (!SoundEngine.speak(text, c, { onEnd: done })) setTimeout(done, Math.min(20000, text.length * 55));
      } else setTimeout(done, Math.min(20000, text.length * 55));
    }
    el('play').onclick = () => (reading ? stop() : start());
    el('download').onclick = () => exportVideo(story, { book: true });
    el('share').onclick = () => share(story);
    if (opts.animate) setTimeout(start, 900);
  }

  /* ---------------- وضع الكتاب المصوّر ---------------- */
  function renderBook(story, containerId, container, opts) {
    const total = story.scenes.length;
    let idx = 0, playing = false, turning = false, playToken = 0, fallbackTimer = null;
    const c = window.KIDAURA_ACTIVE_CHARACTER || {};
    const companionHtml = c.image
      ? `<img src="${(window.KIDAURA_BASE || '')}/${c.image}" alt="">`
      : `<span>${story.spriteFace || (c.icons && c.icons[0]) || '✨'}</span>`;

    container.innerHTML = `
      ${opts.badge ? `<p style="text-align:center;color:var(--mint);font-weight:800;">${opts.badge}</p>` : ''}
      <div class="story-book" id="${containerId}_book">
        <div class="book-page" id="${containerId}_page">
          <div class="book-ribbon" id="${containerId}_ribbon"></div>
          <div class="book-art" id="${containerId}_art">
            <div class="book-art-sky"></div>
            <div class="book-art-hill"></div>
            <div class="book-art-hill two"></div>
            <div class="book-art-icon" id="${containerId}_icon"></div>
            <div class="book-hero">
              ${story.photo ? `<div class="book-hero-photo"><img src="${story.photo}" alt=""></div>` : `<div class="book-hero-photo book-hero-fallback">🧒</div>`}
              ${story.childName ? `<div class="book-hero-name">${esc(story.childName)}</div>` : ''}
            </div>
            <div class="book-companion">${companionHtml}</div>
            <div class="book-bubble" id="${containerId}_bubble" hidden></div>
          </div>
          <div class="book-text">
            <h3 class="book-title" id="${containerId}_title"></h3>
            <p class="book-caption" id="${containerId}_caption"></p>
          </div>
          <div class="book-foot">
            <span class="book-pageno" id="${containerId}_counter"></span>
            <div class="book-dots" id="${containerId}_dots">${story.scenes.map((_, i) => `<i data-i="${i}"></i>`).join('')}</div>
          </div>
        </div>
        <div class="story-controls book-controls">
          <button class="btn btn-sm btn-ghost" id="${containerId}_prev">◀ السابق</button>
          <button class="btn btn-sm btn-primary" id="${containerId}_play">▶ اقرأ لي</button>
          <button class="btn btn-sm btn-ghost" id="${containerId}_next">التالي ▶</button>
        </div>
      </div>
      <div class="story-actions">
        <button class="btn btn-primary btn-sm" id="${containerId}_download">⬇️ تنزيل كفيديو</button>
        <button class="btn btn-ghost btn-sm" id="${containerId}_share">🔗 مشاركة</button>
      </div>`;

    const el = suffix => document.getElementById(`${containerId}_${suffix}`);
    const page = el('page');

    function apply() {
      const s = story.scenes[idx];
      const [g1, g2] = String(s.grad || '#6C63FF,#FF6FA5').split(',');
      el('art').style.setProperty('--g1', g1.trim());
      el('art').style.setProperty('--g2', (g2 || g1).trim());
      el('icon').textContent = s.icon || '✨';
      el('ribbon').textContent = ribbonLabel(story, idx);
      el('title').textContent = s.title || '';
      el('caption').textContent = s.caption || '';
      const bubble = el('bubble');
      if (s.quote) {
        bubble.hidden = false;
        bubble.innerHTML = `<b>${esc(s.speaker || '')}</b>${esc(s.quote)}`;
      } else { bubble.hidden = true; bubble.innerHTML = ''; }
      el('counter').textContent = `${arNum(idx + 1)} / ${arNum(total)}`;
      el('dots').querySelectorAll('i').forEach((d, i) => d.classList.toggle('on', i === idx));
      page.dataset.kind = s.kind || 'chapter';
      page.classList.toggle('is-cover', s.kind === 'cover');
      page.classList.toggle('is-end', s.kind === 'end');
    }

    /** تقليب ثلاثي الأبعاد: الصفحة تدور حتى ٩٠° ثم تُستبدل وتعود */
    function turnTo(newIdx, dir) {
      if (turning || newIdx === idx) return;
      turning = true;
      page.classList.add(dir < 0 ? 'turn-back' : 'turn-out');
      setTimeout(() => {
        idx = newIdx; apply();
        page.classList.remove('turn-out', 'turn-back');
        page.classList.add('turn-in');
        setTimeout(() => { page.classList.remove('turn-in'); turning = false; }, TURN_MS / 2);
      }, TURN_MS / 2);
    }

    function stopPlay() {
      playing = false; playToken++;
      if (fallbackTimer) { clearTimeout(fallbackTimer); fallbackTimer = null; }
      if ('speechSynthesis' in window) window.speechSynthesis.cancel();
      const b = el('play');
      b.textContent = '▶ اقرأ لي'; b.classList.remove('btn-primary'); b.classList.add('btn-ghost');
      page.classList.remove('is-reading');
    }

    /** يقرأ الصفحة بصوت الرفيق، ويقلب للتالية عند انتهاء الصوت (أو بعد مهلة إن كان الصوت مكتوماً) */
    function readPage() {
      if (!playing) return;
      const token = ++playToken;
      const s = story.scenes[idx];
      const text = stripEmoji((s.title ? s.title + '. ' : '') + s.caption + (s.quote ? '. ' + (s.speaker ? s.speaker + ' ' : '') + s.quote : ''));
      page.classList.add('is-reading');
      let spokeAsync = false, ended = false;
      const advance = (delay) => {
        if (ended || token !== playToken || !playing) return;
        ended = true;
        fallbackTimer = setTimeout(() => {
          if (token !== playToken || !playing) return;
          if (idx >= total - 1) { stopPlay(); return; }
          turnTo(idx + 1, 1);
          setTimeout(readPage, TURN_MS + 80);
        }, delay);
      };
      let started = false;
      if (typeof SoundEngine !== 'undefined' && typeof SoundEngine.speak === 'function') {
        started = SoundEngine.speak(text, window.KIDAURA_ACTIVE_CHARACTER, { onEnd: () => { if (spokeAsync) advance(700); } });
      }
      spokeAsync = !!started;
      // الصوت مكتوم/غير مدعوم: إيقاع قراءة بشري تقريبي حسب طول النص
      if (!started) advance(Math.min(9000, Math.max(SCENE_MS, text.length * 55)));
      // حماية من متصفح لا يُطلق onend أبداً
      else fallbackTimer = setTimeout(() => advance(0), Math.min(25000, 4000 + text.length * 90));
    }

    function startPlay() {
      if (playing) return;
      playing = true;
      if (idx >= total - 1) { idx = 0; apply(); }
      const b = el('play');
      b.textContent = '⏸️ إيقاف'; b.classList.remove('btn-ghost'); b.classList.add('btn-primary');
      readPage();
    }

    apply();
    el('prev').onclick = () => { stopPlay(); turnTo(Math.max(0, idx - 1), -1); };
    el('next').onclick = () => { stopPlay(); turnTo(Math.min(total - 1, idx + 1), 1); };
    el('play').onclick = () => (playing ? stopPlay() : startPlay());
    el('dots').querySelectorAll('i').forEach(d => d.onclick = () => { stopPlay(); turnTo(+d.dataset.i, +d.dataset.i > idx ? 1 : -1); });
    el('download').onclick = () => exportVideo(story, { book: true });
    el('share').onclick = () => share(story);
    if (opts.animate && total > 1) setTimeout(startPlay, 900);
  }

  function narrate(story) {
    const full = story.scenes.map(s => s.caption + (s.quote ? '. ' + s.quote : '')).join(". ");
    SoundEngine.speak(stripEmoji(full), window.KIDAURA_ACTIVE_CHARACTER);
  }

  function share(story) {
    const text = `شاهدوا قصة "${story.title || 'مغامرة'}" على Kidora! 🎬✨`;
    if (navigator.share) { navigator.share({ title: story.title || "قصة Kidora", text }).catch(() => {}); }
    else { navigator.clipboard?.writeText(text); alert('تم نسخ نص المشاركة 📋'); }
  }

  function wrapLines(ctx, text, maxWidth) {
    const lines = [];
    let line = '';
    for (const word of String(text || '').trim().split(/\s+/u).filter(Boolean)) {
      const candidate = line ? `${line} ${word}` : word;
      if (ctx.measureText(candidate).width <= maxWidth) { line = candidate; continue; }
      if (line) { lines.push(line); line = ''; }
      // Break a single unusually long token rather than drawing it outside the card.
      for (const char of Array.from(word)) {
        if (ctx.measureText(line + char).width > maxWidth && line) { lines.push(line); line = ''; }
        line += char;
      }
    }
    if (line) lines.push(line);
    return lines;
  }
  function wrapText(ctx, text, x, y, maxWidth, lineHeight) {
    const lines = wrapLines(ctx, text, maxWidth);
    const startY = y - (lines.length - 1) * lineHeight / 2;
    lines.forEach((l, i) => ctx.fillText(l, x, startY + i * lineHeight));
  }

  function roundBox(ctx, x, y, w, h, r) {
    ctx.beginPath(); ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
  }

  function coverCircle(ctx, img, x, y, diameter, color) {
    if (!img || !img.naturalWidth) return false;
    const scale = Math.max(diameter / img.naturalWidth, diameter / img.naturalHeight);
    const sw = diameter / scale, sh = diameter / scale;
    ctx.save(); ctx.beginPath(); ctx.arc(x, y, diameter / 2, 0, Math.PI * 2); ctx.clip();
    ctx.drawImage(img, (img.naturalWidth - sw) / 2, (img.naturalHeight - sh) / 2,
      sw, sh, x - diameter / 2, y - diameter / 2, diameter, diameter);
    ctx.restore();
    ctx.lineWidth = 7; ctx.strokeStyle = color;
    ctx.beginPath(); ctx.arc(x, y, diameter / 2, 0, Math.PI * 2); ctx.stroke();
    return true;
  }

  function loadImage(src) {
    if (!src) return Promise.resolve(null);
    return new Promise(resolve => {
      const img = new Image();
      if (/^https?:\/\//i.test(src) && new URL(src, location.href).origin !== location.origin) img.crossOrigin = 'anonymous';
      img.onload = () => resolve(img);
      img.onerror = () => resolve(null);
      img.src = src;
    });
  }

  function singlePages(ctx, caption) {
    for (const size of [42, 38, 34, 30]) {
      ctx.font = `600 ${size}px Cairo, sans-serif`;
      const lines = wrapLines(ctx, caption, 1480);
      const lineHeight = Math.round(size * 1.65);
      if (lines.length * lineHeight <= 315) return { size, lineHeight, pages: [lines] };
    }
    ctx.font = '600 30px Cairo, sans-serif';
    const lines = wrapLines(ctx, caption, 1480);
    const perPage = Math.floor(315 / 50);
    const pages = [];
    for (let i = 0; i < lines.length; i += perPage) pages.push(lines.slice(i, i + perPage));
    return { size: 30, lineHeight: 50, pages: pages.length ? pages : [[]] };
  }

  /** Replicate the actual daily poster in a wide video frame, without cropping Arabic. */
  function drawSingleVideo(ctx, story, layout, index, photo, companion) {
    const s = story.scenes[0], W = 1920, H = 1080;
    const x = 140, y = 30, w = 1640, h = 1020, artH = 395;
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.direction = 'rtl';
    const bg = ctx.createLinearGradient(0, 0, W, H);
    bg.addColorStop(0, '#1B1035'); bg.addColorStop(1, '#3A2A75');
    ctx.fillStyle = bg; ctx.fillRect(0, 0, W, H);
    ctx.save(); roundBox(ctx, x, y, w, h, 36); ctx.clip();
    ctx.fillStyle = '#FFF9EE'; ctx.fillRect(x, y, w, h);
    const [g1, g2] = String(s.grad || '#6C63FF,#FF6FA5').split(',');
    const gradient = ctx.createLinearGradient(x, y, x + w, y + artH);
    gradient.addColorStop(0, g1.trim()); gradient.addColorStop(1, (g2 || g1).trim());
    ctx.fillStyle = gradient; ctx.fillRect(x, y, w, artH);
    const sky = ctx.createRadialGradient(x + 350, y + 90, 5, x + 350, y + 90, 430);
    sky.addColorStop(0, 'rgba(255,255,255,.35)'); sky.addColorStop(1, 'rgba(255,255,255,0)');
    ctx.fillStyle = sky; ctx.fillRect(x, y, w, artH);
    ctx.save();
    ctx.beginPath(); ctx.rect(x, y, w, artH); ctx.clip();
    ctx.fillStyle = 'rgba(255,255,255,.22)';
    ctx.beginPath(); ctx.ellipse(x + 460, y + artH - 8, 680, 138, 0, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,.3)';
    ctx.beginPath(); ctx.ellipse(x + 1340, y + artH + 4, 720, 158, 0, 0, Math.PI * 2); ctx.fill();
    ctx.restore();
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillStyle = '#fff';
    ctx.font = '134px sans-serif'; ctx.fillText(s.icon || '📖', W / 2, y + 170);
    if (!coverCircle(ctx, photo, x + w - 145, y + artH - 125, 145, '#FFC93C')) {
      ctx.font = '108px sans-serif'; ctx.fillText('🧒', x + w - 145, y + artH - 125);
    }
    if (story.childName) {
      ctx.font = '800 27px Cairo, sans-serif';
      const nameWidth = Math.min(345, ctx.measureText(story.childName).width + 40);
      ctx.fillStyle = 'rgba(255,255,255,.93)';
      roundBox(ctx, x + w - 145 - nameWidth / 2, y + artH - 45, nameWidth, 40, 20); ctx.fill();
      ctx.fillStyle = '#241645'; ctx.fillText(story.childName, x + w - 145, y + artH - 24);
    }
    const face = window.KIDAURA_ACTIVE_CHARACTER || {};
    if (!coverCircle(ctx, companion, x + 150, y + artH - 125, 145, '#fff')) {
      ctx.font = '95px sans-serif'; ctx.fillStyle = '#fff';
      ctx.fillText(story.spriteFace || (face.icons && face.icons[0]) || '✨', x + 150, y + artH - 125);
    }
    ctx.fillStyle = '#241645'; ctx.font = '800 49px "Baloo Bhaijaan 2", sans-serif';
    ctx.fillText(s.title || story.title || '', W / 2, y + artH + 70, w - 110);
    ctx.textAlign = 'right'; ctx.textBaseline = 'top';
    ctx.font = `600 ${layout.size}px Cairo, sans-serif`;
    const textX = x + w - 80, textTop = y + artH + 120;
    for (const [line, i] of layout.pages[index].map((line, i) => [line, i])) {
      ctx.fillText(line, textX, textTop + i * layout.lineHeight, w - 160);
    }
    if (s.quote) {
      const qY = y + h - 150;
      ctx.fillStyle = '#fff'; roundBox(ctx, x + 80, qY, w - 160, 125, 24); ctx.fill();
      ctx.fillStyle = '#6C63FF'; ctx.font = '700 25px Cairo, sans-serif';
      ctx.fillText(s.speaker || '', x + w - 120, qY + 12, w - 240);
      ctx.fillStyle = '#241645'; ctx.font = '700 29px Cairo, sans-serif';
      ctx.fillText(`«${s.quote}»`, x + w - 120, qY + 59, w - 240);
    }
    ctx.restore();
    if (layout.pages.length > 1) {
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.direction = 'rtl';
      ctx.fillStyle = '#fff'; ctx.font = '700 25px Cairo, sans-serif';
      ctx.fillText(`${index + 1} / ${layout.pages.length}`, W / 2, 1060);
    }
  }

  async function exportVideo(story, xopts = {}) {
    if (!("MediaRecorder" in window) || !HTMLCanvasElement.prototype.captureStream) {
      alert('التصدير كفيديو غير مدعوم على هذا المتصفح'); return;
    }
    const canvas = document.createElement('canvas');
    canvas.width = 1920; canvas.height = 1080;
    const ctx = canvas.getContext('2d');
    if (!ctx) { alert('تعذّر إنشاء الفيديو على هذا المتصفح'); return; }
    const single = !!xopts.book && story.scenes.length === 1;
    try {
      if (document.fonts) {
        await Promise.all([document.fonts.load('600 42px Cairo'), document.fonts.load('800 49px "Baloo Bhaijaan 2"')]);
        await document.fonts.ready;
      }
      const face = window.KIDAURA_ACTIVE_CHARACTER || {};
      const faceUrl = face.image && (/^(?:https?:|\/)/i.test(face.image) ? face.image : `${window.KIDAURA_BASE || ''}/${face.image}`);
      const [photoImg, companionImg] = await Promise.all([loadImage(story.photo), loadImage(faceUrl)]);
      const layout = single ? singlePages(ctx, story.scenes[0].caption || '') : null;
      const stream = canvas.captureStream(30);
      let recorder = null;
      for (const mimeType of ['video/mp4', 'video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm']) {
        if (MediaRecorder.isTypeSupported && !MediaRecorder.isTypeSupported(mimeType)) continue;
        try { recorder = new MediaRecorder(stream, { mimeType, videoBitsPerSecond: 8000000 }); break; } catch (e) {}
      }
      if (!recorder) recorder = new MediaRecorder(stream);
      const outExt = recorder.mimeType && recorder.mimeType.includes('mp4') ? 'mp4' : 'webm';
      const chunks = [];
      recorder.ondataavailable = e => { if (e.data.size) chunks.push(e.data); };
      recorder.onerror = () => alert('تعذّر تسجيل الفيديو. جرّب متصفحاً آخر.');
      recorder.onstop = () => {
        stream.getTracks().forEach(track => track.stop());
        const blob = new Blob(chunks, { type: recorder.mimeType || 'video/webm' });
        if (!blob.size) { alert('لم يُنشأ الفيديو. جرّب متصفحاً آخر.'); return; }
        if (typeof xopts.onBlob === 'function') xopts.onBlob(blob);
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = `${(story.title || 'kidora-story').replace(/\s+/g, '_')}.${outExt}`;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 60000);
      };
      if (single) {
        recorder.start();
        for (let i = 0; i < layout.pages.length; i++) {
          const draw = () => drawSingleVideo(ctx, story, layout, i, photoImg, companionImg);
          draw();
          const textLength = layout.pages[i].join(' ').length;
          const duration = Math.min(20000, Math.max(6000, textLength * 70));
          const started = performance.now();
          await new Promise(resolve => {
            const timer = setInterval(() => {
              draw();
              if (performance.now() - started >= duration) { clearInterval(timer); resolve(); }
            }, 100);
          });
        }
        recorder.stop();
        return;
      }
      // القصص متعددة المشاهد تُرسم أيضاً بدقة 1920×1080، وتكمل في إطار تالٍ بدل قصّ النص.
      const drawOther = (s, i, lines) => {
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.direction = 'rtl'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        const [c1, c2] = String(s.grad || '#6C63FF,#FF6FA5').split(',');
        const gradient = ctx.createLinearGradient(0, 0, 1920, 1080);
        gradient.addColorStop(0, c1.trim()); gradient.addColorStop(1, (c2 || c1).trim());
        ctx.fillStyle = xopts.book ? '#FFF9EE' : gradient; ctx.fillRect(0, 0, 1920, 1080);
        if (xopts.book) {
          ctx.fillStyle = gradient; roundBox(ctx, 70, 55, 1780, 510, 30); ctx.fill();
        }
        ctx.fillStyle = '#fff'; ctx.font = '170px sans-serif';
        ctx.fillText(s.icon || story.spriteFace || '✨', 960, xopts.book ? 290 : 315);
        if (photoImg) coverCircle(ctx, photoImg, 1680, 455, 140, '#FFC93C');
        if (xopts.book && companionImg) coverCircle(ctx, companionImg, 235, 455, 140, '#fff');
        ctx.fillStyle = xopts.book ? '#241645' : '#fff';
        ctx.font = '800 68px "Baloo Bhaijaan 2", sans-serif';
        ctx.fillText(s.title || ribbonLabel(story, i), 960, xopts.book ? 635 : 560, 1650);
        ctx.font = '600 48px Cairo, sans-serif';
        lines.forEach((line, n) => ctx.fillText(line, 960, (xopts.book ? 730 : 690) + n * 68, 1590));
      };
      recorder.start();
      for (let i = 0; i < story.scenes.length; i++) {
        ctx.font = '600 48px Cairo, sans-serif';
        const lines = wrapLines(ctx, story.scenes[i].caption || '', 1590);
        const pages = [];
        for (let n = 0; n < lines.length; n += 4) pages.push(lines.slice(n, n + 4));
        if (!pages.length) pages.push([]);
        for (const page of pages) {
          const draw = () => drawOther(story.scenes[i], i, page);
          draw();
          await new Promise(resolve => {
            const timer = setInterval(draw, 100);
            setTimeout(() => { clearInterval(timer); resolve(); }, xopts.book ? 3200 : 2200);
          });
        }
      }
      recorder.stop();
    } catch (e) {
      alert('تعذّر تصدير الفيديو على هذا المتصفح. جرّب متصفحاً آخر.');
    }
  }

  return { render, narrate, share, exportVideo };
})();
