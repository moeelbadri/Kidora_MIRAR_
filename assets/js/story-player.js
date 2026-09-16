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
    const words = String(text).split(" ");
    let line = "", lines = [];
    words.forEach(w => {
      const test = line + w + " ";
      if (ctx.measureText(test).width > maxWidth && line) { lines.push(line); line = w + " "; }
      else line = test;
    });
    lines.push(line);
    return lines.map(l => l.trim());
  }
  function wrapText(ctx, text, x, y, maxWidth, lineHeight) {
    const lines = wrapLines(ctx, text, maxWidth);
    const startY = y - (lines.length - 1) * lineHeight / 2;
    lines.forEach((l, i) => ctx.fillText(l, x, startY + i * lineHeight));
  }

  function exportVideo(story, xopts = {}) {
    if (!("MediaRecorder" in window)) { alert('التصدير كفيديو غير مدعوم على هذا المتصفح'); return; }
    const canvas = document.createElement("canvas");
    canvas.width = 640; canvas.height = 360;
    const ctx = canvas.getContext("2d");
    const stream = canvas.captureStream(30);
    let recorder;
    try {
      recorder = new MediaRecorder(stream, { mimeType: "video/mp4" });
    } catch (e) {
      recorder = new MediaRecorder(stream, { mimeType: "video/webm" });
    }
    const outExt = recorder.mimeType && recorder.mimeType.includes("mp4") ? "mp4" : "webm";
    const chunks = [];
    recorder.ondataavailable = e => chunks.push(e.data);
    // القصة ذات المشهد الواحد تبقى على الشاشة بقدر ما يحتاج نصّها للقراءة
    const single = story.scenes.length === 1;
    const perScene = single ? Math.min(20000, Math.max(6000, String(story.scenes[0].caption || '').length * 70)) : (xopts.book ? 3200 : 2200);
    const photoImg = story.photo ? Object.assign(new Image(), { src: story.photo }) : null;

    function drawScene(s) {
      const [c1, c2] = s.grad.split(",");
      const grad = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
      grad.addColorStop(0, c1); grad.addColorStop(1, c2 || c1);
      ctx.fillStyle = grad; ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.textAlign = "center";

      if (s.icon) {
        ctx.font = "72px sans-serif";
        ctx.fillText(s.icon, canvas.width / 2, 150);
      }
      if (s.title) {
        ctx.font = "800 26px Baloo Bhaijaan 2, sans-serif";
        ctx.fillStyle = "#FFC93C";
        ctx.fillText(s.title, canvas.width / 2, s.icon ? 196 : 150);
      }

      ctx.fillStyle = "rgba(0,0,0,.28)";
      ctx.fillRect(0, canvas.height - 120, canvas.width, 120);
      ctx.fillStyle = "#fff";
      ctx.font = "600 22px Cairo, sans-serif";
      wrapText(ctx, s.caption, canvas.width / 2, canvas.height - 60, canvas.width - 80, 30);
      ctx.font = "800 16px Baloo Bhaijaan 2, sans-serif";
      ctx.fillStyle = "#FFC93C";
      ctx.fillText("Kidora ✨", canvas.width / 2, 40);
    }

    /** صفحة الكتاب نفسها: ورق فاتح، لوحة ملوّنة في الأعلى، نص داكن في الأسفل */
    function drawBookScene(s, i) {
      const W = canvas.width, H = canvas.height;
      ctx.fillStyle = "#FFF9EE"; ctx.fillRect(0, 0, W, H);
      // اللوحة
      const [c1, c2] = String(s.grad).split(",");
      const g = ctx.createLinearGradient(0, 0, W, 190);
      g.addColorStop(0, c1); g.addColorStop(1, c2 || c1);
      ctx.fillStyle = g; ctx.fillRect(20, 16, W - 40, 190);
      ctx.fillStyle = "rgba(255,255,255,.18)";
      ctx.beginPath(); ctx.ellipse(W * .3, 206, 260, 60, 0, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = "rgba(255,255,255,.28)";
      ctx.beginPath(); ctx.ellipse(W * .78, 210, 220, 52, 0, 0, Math.PI * 2); ctx.fill();
      ctx.textAlign = "center";
      ctx.font = "84px sans-serif";
      ctx.fillStyle = "#fff";
      ctx.fillText(s.icon || '✨', W / 2, 130);
      // صورة الطفل في إطار دائري يمين اللوحة
      if (photoImg && photoImg.complete && photoImg.naturalWidth) {
        ctx.save(); ctx.beginPath(); ctx.arc(W - 80, 160, 34, 0, Math.PI * 2); ctx.closePath(); ctx.clip();
        ctx.drawImage(photoImg, W - 114, 126, 68, 68); ctx.restore();
        ctx.strokeStyle = "#FFC93C"; ctx.lineWidth = 4; ctx.beginPath(); ctx.arc(W - 80, 160, 34, 0, Math.PI * 2); ctx.stroke();
      }
      // شريط الفصل
      const label = ribbonLabel(story, i);
      ctx.font = "800 15px Baloo Bhaijaan 2, sans-serif";
      const lw = ctx.measureText(label).width + 28;
      ctx.fillStyle = "#FFC93C"; ctx.fillRect(W - 20 - lw, 28, lw, 28);
      ctx.fillStyle = "#241645"; ctx.fillText(label, W - 20 - lw / 2, 47);
      // النص
      ctx.fillStyle = "#241645";
      if (s.title) { ctx.font = "800 22px Baloo Bhaijaan 2, sans-serif"; ctx.fillText(s.title, W / 2, 238); }
      const maxLines = story.scenes.length === 1 ? 7 : 4;
      ctx.font = maxLines > 4 ? "600 14px Cairo, sans-serif" : "600 17px Cairo, sans-serif";
      const lh = maxLines > 4 ? 19 : 24;
      const lines = wrapLines(ctx, s.caption, W - 90).slice(0, maxLines);
      lines.forEach((l, k) => ctx.fillText(l, W / 2, 262 + k * lh));
      if (s.quote) {
        ctx.fillStyle = "#6C63FF"; ctx.font = "700 14px Cairo, sans-serif";
        ctx.fillText(`${s.speaker ? s.speaker + ': ' : ''}«${s.quote}»`, W / 2, H - 14);
      }
      ctx.fillStyle = "#8b7aa8"; ctx.font = "800 12px Baloo Bhaijaan 2, sans-serif";
      ctx.textAlign = "left"; ctx.fillText(`${i + 1} / ${story.scenes.length}`, 24, H - 14);
      ctx.textAlign = "right"; ctx.fillText("Kidora ✨", W - 24, H - 14);
    }

    recorder.onstop = () => {
      const blob = new Blob(chunks, { type: recorder.mimeType || "video/webm" });
      if (typeof xopts.onBlob === 'function') xopts.onBlob(blob);
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url; a.download = `${(story.title || 'kidaura-story').replace(/\s+/g,'_')}.${outExt}`;
      document.body.appendChild(a); a.click(); a.remove();
    };
    recorder.start();
    let i = 0;
    (function next() {
      if (i >= story.scenes.length) { recorder.stop(); return; }
      if (xopts.book) drawBookScene(story.scenes[i], i); else drawScene(story.scenes[i]);
      i++;
      setTimeout(next, perScene);
    })();
  }

  return { render, narrate, share, exportVideo };
})();
