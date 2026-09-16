/* ============================================================
   GamesEngine — 6 آليات لعب مختلفة فعلياً، يُعاد استخدامها في
   مكتبة الألعاب (games.php) وفي اللعبة الصغيرة بعد كل مهمة (tasks.php)

   الآليات: catch | match | quiz | puzzle | hide | adventure
     catch     التقط الصحيح — عنصر مطلوب يتساقط بين عناصر أخرى
     match     مطابقة الأزواج — بطاقات تنقلب ثلاثي الأبعاد
     quiz      طريق البطل — الرفيق يتقدّم خطوة مع كل سؤال
     puzzle    البازل — صورة الموضوع مقطّعة، بدّل قطعتين حتى تكتمل
     hide      أين اختبأ صاحبي؟ — الرفيق تحت كوب من أكواب تتحرّك
     adventure مغامرة بالاختيارات

   المحتوى (الأيقونات، بنك صح/خطأ، سيناريوهات المغامرة) لم يبقَ ثوابت
   هنا — يأتي من api/game-content.php حسب تصنيف المهمة أو اللعبة، فيُحرَّر
   من لوحة التحكم ويتوسّع بلا نشر جديد.

   العمر يقرّر شكل اللعب، والخادم هو من يحسمه (لا الرابط):
     10 سنوات وأكثر → مؤقّت في طريق البطل، شبكات أكبر، حركة أسرع.
     أقل من 10       → بلا أي مؤقّت، النص يُقرأ صوتياً، شبكات أصغر وأبطأ.

   لا توجد خسارة في أي لعبة: الطفل يكمل دائماً، والتغذية الراجعة تشجيع فقط
   (لا «خطأ» ولا «غلط»). هذا قرار تربوي لا تفصيل واجهة.

   الاستدعاء: GamesEngine.run(type, host, title, color, onDone, { category })
   ============================================================ */
const GamesEngine = (function () {

  /* شبكة أمان فقط: إن تعذّر جلب المحتوى (انقطاع/خطأ) لا نُظهر للطفل شاشة
     معطوبة. ليست بنك محتوى — البنك الحقيقي في القاعدة. */
  const FALLBACK = {
    topic: 'general', label: 'عام', calm: false,
    icons: ["⭐","🌙","🎈","🍎","🐱","🌸","🎵","🚗"],
    quiz: [
      { q: "هل غسل اليدين قبل الأكل مهم لصحتي؟", a: true },
      { q: "هل مشاركة الألعاب مع الأصدقاء تصرف جميل؟", a: true },
      { q: "هل أنتظر دوري في الصف؟", a: true },
    ],
    adventure: [
      { t: "في نهاية الطريق طفل يطلب مساعدتك. ماذا تفعل؟",
        c: [{ l: "أساعده قبل أن أكمل", g: true, r: "البطل الحقيقي لا يمرّ دون أن يساعد 💛" },
            { l: "أكمل طريقي بسرعة", g: false, r: "وصلت أولاً لكن بقلب ثقيل 🥲" }] },
    ],
  };

  const PRAISE = ['ممتاز! 🌟', 'رائع! 👏', 'أحسنت! 🎉', 'يا سلام! ✨', 'بطل! 🏅', 'هذا هو! 💪'];
  const ENCOURAGE = ['فكرة جيدة! 💙', 'قريب جداً! 🌟', 'جرّب مرة أخرى 🤗', 'أنت على الطريق الصحيح 🐣'];
  const pick = arr => arr[Math.floor(Math.random() * arr.length)];

  // المحتوى ثابت داخل الجلسة لكل تصنيف، فلا نُعيد الجلب بين لعبة وأخرى
  const cache = new Map();

  function fetchContent(category) {
    const key = category || '';
    if (cache.has(key)) return Promise.resolve(cache.get(key));
    const url = (window.KIDAURA_BASE || '') + '/api/game-content.php?category=' + encodeURIComponent(key);
    return fetch(url, { credentials: 'same-origin' })
      .then(r => r.ok ? r.json() : Promise.reject())
      .then(d => {
        if (!d || !d.ok) return Promise.reject();
        const topic = {
          topic: d.topic, label: d.label, calm: !!d.calm,
          icons: (d.icons && d.icons.length) ? d.icons : FALLBACK.icons,
          quiz: d.quiz || [],
          adventure: d.adventure || [],
        };
        if (!topic.quiz.length) topic.quiz = FALLBACK.quiz;
        if (!topic.adventure.length) topic.adventure = FALLBACK.adventure;
        cache.set(key, topic);
        return topic;
      })
      .catch(() => FALLBACK);
  }

  function shuffled(arr) { return arr.slice().sort(() => Math.random() - 0.5); }

  /** القراءة الصوتية للصغار فقط، وتحترم زرّ كتم الصوت داخل SoundEngine */
  function say(topic, text) {
    if (!topic || !topic.calm || !text) return;
    if (typeof SoundEngine === 'undefined') return;
    SoundEngine.speak(String(text).replace(/[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}\u{FE0F}]/gu, ''), window.KIDAURA_ACTIVE_CHARACTER);
  }

  /** مؤثّر صوتي قصير عبر SoundEngine.sfx (tap→flip، good→match، win→win) */
  function ding(kind) {
    if (typeof SoundEngine === 'undefined' || typeof SoundEngine.sfx !== 'function') return;
    const map = { tap: 'flip', good: 'match', win: 'win', pop: 'pop' };
    try { SoundEngine.sfx(map[kind] || kind); } catch (e) { /* الصوت تحسين لا شرط */ }
  }

  /** الرفيق النشط كأيقونة أو صورة — يُستخدم في «طريق البطل» و«أين اختبأ صاحبي؟» */
  function companionHtml(size) {
    const c = window.KIDAURA_ACTIVE_CHARACTER || {};
    const base = window.KIDAURA_BASE || '';
    if (c.image) return `<img src="${base}/${c.image}" alt="" style="width:${size}px;height:${size}px;object-fit:cover;border-radius:50%;">`;
    const icon = (c.icons && c.icons[0]) || '✨';
    return `<span style="font-size:${Math.round(size * .8)}px;line-height:1;">${icon}</span>`;
  }

  function shell(host, title, subtitle, body, topic) {
    host.innerHTML = `
      <div class="mini-game-wrap card ge-wrap">
        <h3>${title}</h3>
        <p class="ge-sub">${subtitle}</p>
        ${body}
      </div>`;
    host.scrollIntoView({ behavior: 'smooth' });
    say(topic, subtitle);
  }

  function loading(host) {
    host.innerHTML = `
      <div class="mini-game-wrap card" style="padding:30px;text-align:center;">
        <div style="font-size:38px;">🎮</div>
        <p style="color:var(--ink-soft);">جاري تحضير اللعبة...</p>
      </div>`;
    host.scrollIntoView({ behavior: 'smooth' });
  }

  /** انفجار قصاصات ملوّنة داخل اللعبة عند الإنجاز */
  function confetti(container) {
    if (!container) return;
    const colors = ['#FF6B6B', '#4ECDC4', '#FFE66D', '#A8E6CF', '#FF8A5C', '#6C5CE7', '#FD79A8'];
    const layer = document.createElement('div');
    layer.className = 'ge-confetti';
    for (let i = 0; i < 40; i++) {
      const p = document.createElement('i');
      p.style.left = Math.random() * 100 + '%';
      p.style.background = colors[i % colors.length];
      p.style.animationDelay = (Math.random() * .6) + 's';
      p.style.animationDuration = (1.4 + Math.random()) + 's';
      layer.appendChild(p);
    }
    container.appendChild(layer);
    setTimeout(() => layer.remove(), 2600);
  }

  /** شاشة الختام الموحّدة: أيقونة كبيرة + جملة تشجيع + قصاصات */
  function celebrate(host, area, icon, line, topic, onDone) {
    if (area) area.innerHTML = `<div class="ge-end"><div class="ge-end-icon">${icon}</div><p>${line}</p></div>`;
    confetti(host.querySelector('.ge-wrap'));
    ding('win');
    say(topic, line);
    finish(host, onDone);
  }

  function run(type, host, title, color, onDone, opts) {
    loading(host);
    fetchContent(opts && opts.category).then(topic => {
      switch (type) {
        case 'match':     return runMatch(host, title, color, onDone, topic);
        case 'quiz':      return runQuiz(host, title, color, onDone, topic);
        case 'puzzle':    return runPuzzle(host, title, color, onDone, topic);
        case 'hide':      return runHide(host, title, color, onDone, topic);
        case 'adventure': return runAdventure(host, title, color, onDone, topic);
        default:          return runCatch(host, title, color, onDone, topic);
      }
    });
  }

  /* ---------------- 1) التقط الصحيح ---------------- */
  function runCatch(host, title, color, onDone, topic) {
    const TOTAL = topic.calm ? 5 : 6;
    let caught = 0, done = false;
    const icons = topic.icons.slice();
    let target = pick(icons);
    const others = icons.filter(i => i !== target);
    shell(host, title + ' 🎯', `التقط <b class="ge-target" id="ge_target">${target}</b> فقط — ودَع الباقي يمرّ`, `
      <div class="mini-game-area ge-catch-area" id="ge_miniArea" style="border-color:${color};">
        <div class="ge-target-badge" style="border-color:${color};">المطلوب: <span id="ge_targetBadge">${target}</span></div>
      </div>
      <div class="ge-progress" id="ge_catchDots">${'<i></i>'.repeat(TOTAL)}</div>
      <div class="ge-msg" id="ge_catchMsg"></div>`, topic);
    const area = document.getElementById('ge_miniArea');
    const msg = document.getElementById('ge_catchMsg');
    const dots = area.parentElement.querySelectorAll('#ge_catchDots i');
    // للصغار تتساقط العناصر أبطأ وتبقى أطول على الشاشة
    const spawnEvery = topic.calm ? 1000 : 650;
    const fallSecs = topic.calm ? 4 : 2.4;
    say(topic, 'التقط ' + target + ' فقط');

    function retarget() {
      // كل ثلاث التقاطات يتغيّر المطلوب حتى تبقى اللعبة منتبهة لا آلية
      const next = pick(icons.filter(i => i !== target));
      if (!next) return;
      target = next;
      document.getElementById('ge_target').textContent = target;
      document.getElementById('ge_targetBadge').textContent = target;
      msg.textContent = 'المطلوب الآن: ' + target;
      say(topic, 'الآن التقط ' + target);
    }

    const spawner = setInterval(() => {
      if (done) { clearInterval(spawner); return; }
      const isTarget = Math.random() < .5;
      const el = document.createElement('div');
      el.className = 'mini-game-item ge-fall';
      el.textContent = isTarget ? target : (pick(others.filter(i => i !== target)) || '🌙');
      el.dataset.target = isTarget ? '1' : '0';
      el.dataset.icon = el.textContent;
      el.style.right = (5 + Math.random() * 80) + '%';
      el.style.animationDuration = (fallSecs + Math.random()) + 's';
      el.addEventListener('animationend', () => el.remove());
      el.addEventListener('click', () => {
        if (done) return;
        // الحكم بالأيقونة لحظة الضغط: عنصر سقط قبل تغيير المطلوب يظل عادلاً
        if (el.textContent === target) {
          caught++;
          el.classList.add('ge-pop');
          setTimeout(() => el.remove(), 350);
          dots[Math.min(caught, TOTAL) - 1].classList.add('on');
          msg.textContent = pick(PRAISE);
          ding('good');
          if (caught >= TOTAL) {
            done = true; clearInterval(spawner);
            celebrate(host, null, '🏆', `التقطت كل المطلوب! عين صقر وانتباه بطل 🎯`, topic, onDone);
            area.querySelectorAll('.mini-game-item').forEach(x => x.remove());
            msg.textContent = 'التقطت كل المطلوب! 🏆';
          } else if (caught % 3 === 0) {
            retarget();
          }
        } else {
          el.classList.add('ge-sparkle');
          setTimeout(() => el.remove(), 500);
          msg.textContent = `نحن نبحث عن ${target} — ${pick(ENCOURAGE)}`;
        }
      });
      area.appendChild(el);
    }, spawnEvery);
  }

  /* ---------------- 2) مطابقة الأزواج ---------------- */
  function runMatch(host, title, color, onDone, topic) {
    // أزواج أقل للصغار حتى تبقى اللعبة قابلة للإنجاز
    const icons = topic.icons.slice(0, topic.calm ? 4 : 6);
    const deck = shuffled(icons.concat(icons));
    let opened = [], matched = 0, locked = false;
    shell(host, title + ' 🧠', 'اقلب بطاقتين واعثر على المتطابقتين', `
      <div class="ge-match-grid" id="ge_matchGrid" style="--ge-color:${color};"></div>
      <div class="ge-progress" id="ge_matchDots">${'<i></i>'.repeat(icons.length)}</div>
      <div class="ge-msg" id="ge_matchMsg"></div>`, topic);
    const grid = document.getElementById('ge_matchGrid');
    const msg = document.getElementById('ge_matchMsg');
    const dots = grid.parentElement.querySelectorAll('#ge_matchDots i');
    deck.forEach((icon, i) => {
      const card = document.createElement('button');
      card.type = 'button';
      card.className = 'ge-card';
      card.dataset.icon = icon; card.dataset.idx = i;
      card.setAttribute('aria-label', 'بطاقة ' + (i + 1));
      card.innerHTML = `<span class="ge-card-inner"><span class="ge-card-back">❓</span><span class="ge-card-face">${icon}</span></span>`;
      card.onclick = () => flipCard(card);
      grid.appendChild(card);
    });
    function flipCard(card){
      if (locked || card.classList.contains('done') || opened.includes(card)) return;
      card.classList.add('open');
      ding('tap');
      opened.push(card);
      if (opened.length === 2){
        locked = true;
        setTimeout(() => {
          if (opened[0].dataset.icon === opened[1].dataset.icon){
            opened.forEach(c => c.classList.add('done'));
            matched++;
            dots[matched - 1].classList.add('on');
            msg.textContent = pick(PRAISE);
            ding('good');
            if (matched === icons.length){
              celebrate(host, null, '🏆', 'وجدت كل الأزواج! ذاكرة رائعة 🧠', topic, onDone);
              msg.textContent = 'وجدت كل الأزواج! ذاكرة رائعة 🧠🏆';
            }
          } else {
            opened.forEach(c => c.classList.remove('open'));
            msg.textContent = pick(ENCOURAGE);
          }
          opened = []; locked = false;
        }, topic.calm ? 1100 : 750);
      }
    }
  }

  /* ---------------- 3) طريق البطل (أسئلة) ---------------- */
  function runQuiz(host, title, color, onDone, topic) {
    const TOTAL = 5;
    const questions = topic.quiz.slice(0, TOTAL);
    // الصغار: بلا مؤقّت إطلاقاً، والسؤال يُقرأ عليهم بصوت الشخصية
    const timed = !topic.calm;
    let idx = 0, stars = 0, timeLeft = 12 * questions.length, timer = null, finished = false;

    const stations = questions.map((_, i) => `<span class="ge-station" data-i="${i}">${i + 1}</span>`).join('<span class="ge-road"></span>');
    shell(host, title + ' 🛤️', `أسئلة عن ${topic.label} — ${timed ? 'اختر بسرعة وامشِ على الطريق!' : 'خذ وقتك، لا يوجد مؤقّت'}`, `
      <div class="ge-path" style="--ge-color:${color};">
        <div class="ge-walker" id="ge_walker">${companionHtml(44)}</div>
        <div class="ge-stations" id="ge_stations">${stations}<span class="ge-road"></span><span class="ge-station ge-goal">🏁</span></div>
      </div>
      <p class="ge-meta">${timed ? `⏱️ <b id="ge_quizTimer" style="color:${color};">${timeLeft}</b> ث · ` : ''}⭐ <b id="ge_quizScore">0</b></p>
      <div id="ge_quizBody" class="ge-quiz-body"></div>`, topic);

    const walker = document.getElementById('ge_walker');
    const stationEls = Array.from(document.querySelectorAll('#ge_stations .ge-station'));
    function moveWalker(i) {
      const st = stationEls[Math.min(i, stationEls.length - 1)];
      const path = st.closest('.ge-path');
      if (!st || !path) return;
      const r = st.getBoundingClientRect(), pr = path.getBoundingClientRect();
      // RTL: نحسب من الحافة اليمنى حتى يمشي الرفيق من اليمين إلى اليسار
      walker.style.right = (pr.right - r.right + r.width / 2 - 22) + 'px';
      stationEls.forEach((s, k) => s.classList.toggle('done', k < i));
    }
    setTimeout(() => moveWalker(0), 50);

    if (timed) {
      timer = setInterval(() => {
        timeLeft--;
        const t = document.getElementById('ge_quizTimer');
        if (t) t.textContent = timeLeft;
        if (timeLeft <= 0) end();
      }, 1000);
    }

    function render(){
      if (idx >= questions.length) { end(); return; }
      const q = questions[idx];
      document.getElementById('ge_quizBody').innerHTML = `
        <h4 class="ge-question">${q.q}</h4>
        <div class="ge-choices">
          <button type="button" class="btn btn-primary" data-v="true">✅ نعم</button>
          <button type="button" class="btn btn-ghost" data-v="false">🙅 لا</button>
        </div>`;
      say(topic, q.q);
      document.querySelectorAll('#ge_quizBody [data-v]').forEach(btn => {
        btn.onclick = () => {
          if (finished) return;
          const right = (btn.dataset.v === 'true') === !!q.a;
          const answerWord = q.a ? 'نعم ✅' : 'لا 🙅';
          if (right) { stars++; document.getElementById('ge_quizScore').textContent = stars; ding('good'); }
          const line = right
            ? `${pick(PRAISE)} الجواب: ${answerWord}`
            : `${pick(ENCOURAGE)} الجواب هنا: ${answerWord}`;
          document.getElementById('ge_quizBody').innerHTML = `
            <div class="ge-feedback ${right ? 'good' : 'soft'}"><div class="ge-feedback-icon">${right ? '🌟' : '💙'}</div><p>${line}</p></div>`;
          say(topic, line);
          idx++;
          moveWalker(idx);
          setTimeout(render, topic.calm ? 2600 : 1300);
        };
      });
    }
    function end(){
      if (finished) return;
      finished = true;
      if (timer) clearInterval(timer);
      moveWalker(stationEls.length - 1);
      const body = document.getElementById('ge_quizBody');
      const line = stars === questions.length
        ? 'وصلت لنهاية الطريق وجمعت كل النجوم! بطل حقيقي 🌟'
        : `وصلت لنهاية الطريق ومعك ${stars} ${stars === 1 ? 'نجمة' : 'نجوم'} — وكل سؤال علّمنا شيئاً جديداً 💪`;
      celebrate(host, body, '🏆', line, topic, onDone);
    }
    render();
  }

  /* ---------------- 4) البازل ---------------- */
  function runPuzzle(host, title, color, onDone, topic) {
    const N = topic.calm ? 2 : 3;               // 2×2 للصغار، 3×3 لمن فوق
    const icon = pick(topic.icons);
    const SIZE = 300;
    // نرسم صورة الموضوع مرة واحدة على canvas ثم نقصّها بخلفيات متحرّكة
    const cv = document.createElement('canvas');
    cv.width = cv.height = SIZE;
    const ctx = cv.getContext('2d');
    const g = ctx.createLinearGradient(0, 0, SIZE, SIZE);
    g.addColorStop(0, '#FFF3B0'); g.addColorStop(.5, '#FFD6E8'); g.addColorStop(1, '#C9F2FF');
    ctx.fillStyle = g; ctx.fillRect(0, 0, SIZE, SIZE);
    for (let i = 0; i < 14; i++) {
      ctx.beginPath();
      ctx.arc(Math.random() * SIZE, Math.random() * SIZE, 6 + Math.random() * 18, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(255,255,255,${.25 + Math.random() * .4})`; ctx.fill();
    }
    ctx.font = Math.round(SIZE * .6) + 'px "Segoe UI Emoji","Apple Color Emoji","Noto Color Emoji",sans-serif';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillStyle = '#241645';
    ctx.fillText(icon, SIZE / 2, SIZE / 2 + SIZE * .04);
    let dataUrl = '';
    try { dataUrl = cv.toDataURL('image/png'); } catch (e) { dataUrl = ''; }

    // ترتيب مخلوط غير محلول
    const total = N * N;
    let order;
    do { order = shuffled([...Array(total).keys()]); } while (order.every((v, i) => v === i));
    let first = null, locked = false, solved = false;

    shell(host, title + ' 🧩', 'اضغط قطعتين لتبديل مكانهما حتى تكتمل الصورة', `
      <div class="ge-puzzle-row">
        <div class="ge-puzzle" id="ge_puzzle" style="--n:${N};--ge-color:${color};"></div>
        <div class="ge-puzzle-preview" title="الصورة الكاملة"><span>الصورة</span><div style="background-image:url('${dataUrl}');">${dataUrl ? '' : icon}</div></div>
      </div>
      <div class="ge-msg" id="ge_puzzleMsg">قطع في مكانها: <b id="ge_puzzleOk">0</b> / ${total}</div>`, topic);
    const board = document.getElementById('ge_puzzle');
    const msg = document.getElementById('ge_puzzleMsg');

    function draw() {
      board.innerHTML = '';
      let ok = 0;
      order.forEach((piece, pos) => {
        const t = document.createElement('button');
        t.type = 'button';
        t.className = 'ge-piece' + (piece === pos ? ' ok' : '');
        t.dataset.pos = pos; t.dataset.piece = piece;
        t.setAttribute('aria-label', 'قطعة ' + (pos + 1));
        const x = piece % N, y = Math.floor(piece / N);
        t.style.backgroundImage = dataUrl ? `url('${dataUrl}')` : 'none';
        t.style.backgroundSize = `${N * 100}% ${N * 100}%`;
        t.style.backgroundPosition = `${N === 1 ? 0 : (x / (N - 1)) * 100}% ${N === 1 ? 0 : (y / (N - 1)) * 100}%`;
        if (!dataUrl) t.textContent = String(piece + 1);
        if (piece === pos) ok++;
        t.onclick = () => tap(pos, t);
        board.appendChild(t);
      });
      const okEl = document.getElementById('ge_puzzleOk');
      if (okEl) okEl.textContent = ok;
      return ok;
    }
    function tap(pos, el) {
      if (locked || solved) return;
      ding('tap');
      if (first === null) { first = pos; el.classList.add('sel'); return; }
      if (first === pos) { first = null; el.classList.remove('sel'); return; }
      const a = first; first = null;
      [order[a], order[pos]] = [order[pos], order[a]];
      const ok = draw();
      if (ok === total) {
        solved = true; locked = true;
        board.classList.add('solved');
        celebrate(host, null, '🏆', 'اكتملت الصورة! صبر وتركيز بطل 🧩', topic, onDone);
        msg.textContent = 'اكتملت الصورة! صبر وتركيز بطل 🧩🏆';
      } else {
        msg.innerHTML = `${order[pos] === pos || order[a] === a ? pick(PRAISE) : pick(ENCOURAGE)} · قطع في مكانها: <b id="ge_puzzleOk">${ok}</b> / ${total}`;
      }
    }
    draw();
  }

  /* ---------------- 5) أين اختبأ صاحبي؟ ---------------- */
  function runHide(host, title, color, onDone, topic) {
    const CUPS = topic.calm ? 3 : 4;
    const ROUNDS = 3;
    const SWAPS = topic.calm ? 3 : 5;
    const SWAP_MS = topic.calm ? 900 : 550;
    let round = 0, friendAt = 0, busy = true, finished = false;
    // positions[i] = خانة العرض الحالية للكوب i
    const positions = [...Array(CUPS).keys()];
    shell(host, title + ' 🫣', 'راقب الكوب الذي اختبأ تحته صاحبك، ثم اضغط عليه بعد أن تتوقّف الأكواب', `
      <div class="ge-hide" id="ge_hide" style="--cups:${CUPS};--ge-color:${color};"></div>
      <div class="ge-progress" id="ge_hideDots">${'<i></i>'.repeat(ROUNDS)}</div>
      <div class="ge-msg" id="ge_hideMsg"></div>`, topic);
    const stage = document.getElementById('ge_hide');
    const msg = document.getElementById('ge_hideMsg');
    const dots = stage.parentElement.querySelectorAll('#ge_hideDots i');
    const cups = [];
    for (let i = 0; i < CUPS; i++) {
      const c = document.createElement('button');
      c.type = 'button';
      c.className = 'ge-cup';
      c.dataset.cup = i;
      c.setAttribute('aria-label', 'كوب ' + (i + 1));
      c.innerHTML = `<span class="ge-cup-friend">${companionHtml(40)}</span><span class="ge-cup-body">🥤</span>`;
      c.onclick = () => guess(i);
      stage.appendChild(c);
      cups.push(c);
    }
    function place() { cups.forEach((c, i) => c.style.setProperty('--slot', positions[i])); }
    place();

    function startRound() {
      busy = true;
      friendAt = Math.floor(Math.random() * CUPS);
      cups.forEach((c, i) => { c.classList.remove('lift', 'empty', 'found'); c.dataset.has = i === friendAt ? '1' : '0'; });
      msg.textContent = 'انظر… صاحبك يختبئ هنا 👀';
      say(topic, 'انظر أين يختبئ صاحبك');
      cups[friendAt].classList.add('lift');
      setTimeout(() => {
        cups[friendAt].classList.remove('lift');
        let k = 0;
        msg.textContent = 'الأكواب تتحرّك… تابعها بعينيك 👀';
        const iv = setInterval(() => {
          const a = Math.floor(Math.random() * CUPS);
          let b = Math.floor(Math.random() * CUPS);
          if (b === a) b = (a + 1) % CUPS;
          [positions[a], positions[b]] = [positions[b], positions[a]];
          place();
          if (++k >= SWAPS) {
            clearInterval(iv);
            setTimeout(() => { busy = false; msg.textContent = 'أين اختبأ صاحبك؟ اضغط على الكوب 👆'; say(topic, 'أين اختبأ صاحبك؟ اضغط على الكوب'); }, SWAP_MS);
          }
        }, SWAP_MS);
      }, topic.calm ? 1600 : 1100);
    }

    function guess(i) {
      if (busy || finished) return;
      if (i === friendAt) {
        busy = true;
        cups[i].classList.add('lift', 'found');
        round++;
        dots[round - 1].classList.add('on');
        ding('good');
        msg.textContent = pick(PRAISE) + ' وجدت صاحبك!';
        say(topic, 'وجدت صاحبك');
        if (round >= ROUNDS) {
          finished = true;
          setTimeout(() => {
            celebrate(host, null, '🏆', 'وجدت صاحبك في كل مرة! عين لا يفوتها شيء 👀', topic, onDone);
            msg.textContent = 'وجدت صاحبك في كل مرة! 👀🏆';
          }, 700);
        } else {
          setTimeout(startRound, topic.calm ? 1800 : 1200);
        }
      } else {
        // لا خسارة: الكوب يُرفع ليُظهر أنه فارغ، والطفل يجرّب كوباً آخر
        cups[i].classList.add('lift', 'empty');
        msg.textContent = 'ليس هنا… ' + pick(ENCOURAGE);
        say(topic, 'ليس هنا، جرّب كوباً آخر');
        setTimeout(() => cups[i].classList.remove('lift', 'empty'), 700);
      }
    }
    setTimeout(startRound, 400);
  }

  /* ---------------- 6) المغامرة بالاختيارات ---------------- */
  function runAdventure(host, title, color, onDone, topic) {
    // الخادم يُرسل البنك كاملاً مرتّباً عشوائياً، فتختلف المغامرة كل مرة
    const scenes = topic.adventure.slice(0, 4);
    let idx = 0, good = 0;
    shell(host, title + ' 🗺️', `مغامرة عن ${topic.label} — كل قرار يغيّر النهاية`, `
      <div class="ge-adv" id="ge_advArea" style="--ge-color:${color};"></div>
      <div class="ge-progress" id="ge_advDots">${'<i></i>'.repeat(scenes.length)}</div>
      <p class="ge-meta">⭐ <b id="ge_advGood">0</b></p>`, topic);
    const area = document.getElementById('ge_advArea');
    const dots = area.parentElement.querySelectorAll('#ge_advDots i');

    function render(){
      if (idx >= scenes.length) return end();
      const s = scenes[idx];
      area.innerHTML = `
        <div class="ge-adv-scene">${category_emoji(topic)}</div>
        <h4 class="ge-question">${s.t}</h4>
        <div class="ge-choices ge-choices-col">
          ${s.c.map((c, i) => `<button type="button" class="btn btn-ghost" data-i="${i}">${c.l}</button>`).join('')}
        </div>`;
      // الصغار يسمعون الموقف والخيارات، فاللعبة تعمل قبل إتقان القراءة
      say(topic, s.t + '. ' + s.c.map(c => c.l).join('، أو '));
      area.querySelectorAll('[data-i]').forEach(btn => {
        btn.onclick = () => {
          const choice = s.c[+btn.dataset.i];
          if (choice.g) { good++; document.getElementById('ge_advGood').textContent = good; ding('good'); }
          dots[idx].classList.add('on');
          area.innerHTML = `
            <div class="ge-feedback ${choice.g ? 'good' : 'soft'}"><div class="ge-feedback-icon">${choice.g ? '🌟' : '💭'}</div><p>${choice.r}</p></div>`;
          say(topic, choice.r);
          idx++;
          setTimeout(render, topic.calm ? 3200 : 1600);
        };
      });
    }
    function category_emoji(t) {
      return (t.icons && t.icons[idx % t.icons.length]) || '🗺️';
    }
    function end(){
      const perfect = good === scenes.length;
      const line = perfect
        ? 'أنهيت المغامرة بقرارات موفّقة كلها! بطل حقيقي 🌟'
        : `أنهيت المغامرة ومعك ${good} ${good === 1 ? 'نجمة' : 'نجوم'} — وكل مغامرة تعلّمنا شيئاً جديداً 💪`;
      celebrate(host, area, perfect ? '🏆' : '🎒', line, topic, onDone);
    }
    render();
  }

  function finish(host, onDone) {
    if (typeof onDone === 'function') onDone();
  }

  return { run };
})();
