/* ============================================================
   GamesEngine — 6 آليات لعب مختلفة فعلياً، يُعاد استخدامها في
   مكتبة الألعاب (games.php) وفي اللعبة الصغيرة بعد كل مهمة (tasks.php)

   الآليات: catch | match | quiz | reaction | memory | adventure

   المحتوى (الأيقونات، بنك صح/خطأ، سيناريوهات المغامرة) لم يبقَ ثوابت
   هنا — يأتي من api/game-content.php حسب تصنيف المهمة أو اللعبة، فيُحرَّر
   من لوحة التحكم ويتوسّع بلا نشر جديد.

   العمر يقرّر شكل اللعب، والخادم هو من يحسمه (لا الرابط):
     10 سنوات وأكثر → مؤقّتات وسرعة بديهة كما هي.
     أقل من 10       → بلا أي مؤقّت، والنص يُقرأ صوتياً، وسرعة البديهة
                        تُستبدل بمطابقة الأزواج (آلية بلا ضغط وقت).

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
    SoundEngine.speak(String(text).replace(/[🌟💭🏆🎒👀👆🎉⏱️⚡🧩🗺️🧠]/g, ''), window.KIDAURA_ACTIVE_CHARACTER);
  }

  function shell(host, title, subtitle, body, topic) {
    host.innerHTML = `
      <div class="mini-game-wrap card" style="padding:20px;">
        <h3>${title}</h3>
        <p style="color:var(--ink-soft);">${subtitle}</p>
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

  function run(type, host, title, color, onDone, opts) {
    loading(host);
    fetchContent(opts && opts.category).then(topic => {
      // سرعة البديهة تقيس زمن رد الفعل بالمللي ثانية — لا يوجد فيها مؤقّت
      // نُزيله، فالآلية نفسها هي الضغط. للصغار تُستبدل بمطابقة الأزواج،
      // ويُستبدل معها العنوان: «سرعة القفز» يَعِد بلعبة سرعة لن يلعبها.
      let mech = type, name = title;
      if (mech === 'reaction' && topic.calm) { mech = 'match'; name = 'لعبة المطابقة'; }

      switch (mech) {
        case 'match':     return runMatch(host, name, color, onDone, topic);
        case 'quiz':      return runQuiz(host, name, color, onDone, topic);
        case 'reaction':  return runReaction(host, name, color, onDone, topic);
        case 'memory':    return runMemory(host, name, color, onDone, topic);
        case 'adventure': return runAdventure(host, name, color, onDone, topic);
        default:          return runCatch(host, name, color, onDone, topic);
      }
    });
  }

  /* ---------------- 1) التقاط ---------------- */
  function runCatch(host, title, color, onDone, topic) {
    let caught = 0, total = 6, spawned = 0;
    const items = topic.icons;
    shell(host, title, 'اضغط على العناصر المتساقطة', `
      <div class="mini-game-area" id="ge_miniArea" style="border-color:${color};"></div>
      <p>التقطت: <b id="ge_catchCount">0</b> / ${total}</p>`, topic);
    const miniArea = document.getElementById('ge_miniArea');
    // للصغار تتساقط العناصر أبطأ وتبقى أطول على الشاشة
    const spawnEvery = topic.calm ? 900 : 550;
    const fallSecs = topic.calm ? 3.4 : 2;
    const spawner = setInterval(() => {
      if (spawned >= total) { clearInterval(spawner); return; }
      spawned++;
      const el = document.createElement('div');
      el.className = 'mini-game-item';
      el.textContent = items[Math.floor(Math.random()*items.length)];
      el.style.right = (Math.random()*80) + '%';
      el.style.animationDuration = (fallSecs + Math.random()) + 's';
      el.addEventListener('animationend', () => el.remove());
      el.addEventListener('click', () => {
        caught++; document.getElementById('ge_catchCount').textContent = caught; el.remove();
        if (caught >= total) { clearInterval(spawner); finish(host, onDone); }
      });
      miniArea.appendChild(el);
    }, spawnEvery);
  }

  /* ---------------- 2) مطابقة الأزواج ---------------- */
  function runMatch(host, title, color, onDone, topic) {
    // أزواج أقل للصغار حتى تبقى اللعبة قابلة للإنجاز
    const icons = topic.icons.slice(0, topic.calm ? 4 : 6);
    const deck = shuffled(icons.concat(icons));
    let opened = [], matched = [], locked = false;
    shell(host, title + ' 🧠', 'اقلب بطاقتين واعثر على المتطابقتين', `
      <div id="ge_matchGrid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;max-width:360px;margin:14px auto;"></div>
      <p>أزواج مكتملة: <b id="ge_matchScore">0</b> / ${icons.length}</p>`, topic);
    const grid = document.getElementById('ge_matchGrid');
    deck.forEach((icon, i) => {
      const card = document.createElement('button');
      card.className = 'btn btn-ghost';
      card.style.cssText = `height:64px;font-size:26px;border-color:${color};`;
      card.dataset.icon = icon; card.dataset.idx = i;
      card.textContent = '❓';
      card.onclick = () => flipCard(card);
      grid.appendChild(card);
    });
    function flipCard(card){
      if (locked || card.classList.contains('done') || opened.includes(card)) return;
      card.textContent = card.dataset.icon;
      opened.push(card);
      if (opened.length === 2){
        locked = true;
        setTimeout(() => {
          if (opened[0].dataset.icon === opened[1].dataset.icon){
            opened.forEach(c => { c.classList.add('done'); c.style.opacity = .4; });
            matched.push(opened[0].dataset.icon);
            document.getElementById('ge_matchScore').textContent = matched.length;
            if (matched.length === icons.length){ finish(host, onDone); }
          } else {
            opened.forEach(c => c.textContent = '❓');
          }
          opened = []; locked = false;
        }, topic.calm ? 1100 : 700);
      }
    }
  }

  /* ---------------- 3) سباق الأسئلة ---------------- */
  function runQuiz(host, title, color, onDone, topic) {
    const TOTAL = 5;
    const questions = topic.quiz.slice(0, TOTAL);
    // الصغار: بلا مؤقّت إطلاقاً، والسؤال يُقرأ عليهم بصوت الشخصية
    const timed = !topic.calm;
    let idx = 0, score = 0, timeLeft = 30;

    shell(host, title + (timed ? ' ⏱️' : ''), `أسئلة عن ${topic.label} — ${timed ? 'أجب بسرعة!' : 'خذ وقتك، لا يوجد مؤقّت'}`, `
      <p>${timed ? `الوقت المتبقي: <b id="ge_quizTimer" style="color:${color};">${timeLeft}</b> ث | ` : ''}النقاط: <b id="ge_quizScore">0</b></p>
      <div id="ge_quizBody"></div>`, topic);

    const timer = timed ? setInterval(() => {
      timeLeft--;
      const t = document.getElementById('ge_quizTimer');
      if (t) t.textContent = timeLeft;
      if (timeLeft <= 0) end();
    }, 1000) : null;

    function render(){
      if (idx >= questions.length) { end(); return; }
      const q = questions[idx];
      document.getElementById('ge_quizBody').innerHTML = `
        <h4 style="margin:14px 0;">${q.q}</h4>
        <div style="display:flex;gap:12px;justify-content:center;">
          <button class="btn btn-primary" data-v="true">✅ صح</button>
          <button class="btn btn-ghost" data-v="false">❌ خطأ</button>
        </div>`;
      say(topic, q.q);
      document.querySelectorAll('#ge_quizBody [data-v]').forEach(btn => {
        btn.onclick = () => {
          if ((btn.dataset.v === 'true') === q.a) { score++; document.getElementById('ge_quizScore').textContent = score; }
          idx++; render();
        };
      });
    }
    function end(){
      if (timer) clearInterval(timer);
      const body = document.getElementById('ge_quizBody');
      const msg = `سجّلت ${score} من ${questions.length}!`;
      if (body) body.innerHTML = `<div style="font-size:44px;">🏆</div><p>${msg}</p>`;
      say(topic, msg);
      finish(host, onDone);
    }
    render();
  }

  /* ---------------- 4) سرعة البديهة (10 سنوات وأكثر فقط) ---------------- */
  function runReaction(host, title, color, onDone, topic) {
    const ROUNDS = 5;
    let round = 0, times = [], waiting = false, startedAt = 0, timeoutId = null;
    shell(host, title + ' ⚡', 'انتظر حتى تتحوّل الدائرة للأخضر ثم اضغط بأسرع ما يمكن', `
      <button id="ge_reactPad" class="btn" style="width:180px;height:180px;border-radius:50%;font-size:20px;font-weight:800;color:#fff;background:#E5484D;border:none;margin:14px auto;display:flex;align-items:center;justify-content:center;">استعد...</button>
      <p>الجولة: <b id="ge_reactRound">0</b> / ${ROUNDS} | أفضل زمن: <b id="ge_reactBest" style="color:${color};">—</b></p>
      <div id="ge_reactMsg" style="min-height:24px;font-weight:700;"></div>`, topic);
    const pad = document.getElementById('ge_reactPad');
    const msg = document.getElementById('ge_reactMsg');

    function nextRound(){
      if (round >= ROUNDS) return end();
      waiting = false;
      pad.style.background = '#E5484D';
      pad.textContent = 'استعد...';
      timeoutId = setTimeout(() => {
        waiting = true; startedAt = Date.now();
        pad.style.background = '#4CAF6D';
        pad.textContent = 'اضغط الآن!';
      }, 900 + Math.random()*2200);
    }
    pad.onclick = () => {
      if (!waiting) {
        clearTimeout(timeoutId);
        msg.textContent = 'بكّرت شوي! استنى الأخضر ⏳';
        msg.style.color = '#E5484D';
        return nextRound();
      }
      const ms = Date.now() - startedAt;
      waiting = false;
      times.push(ms);
      round++;
      document.getElementById('ge_reactRound').textContent = round;
      document.getElementById('ge_reactBest').textContent = Math.min.apply(null, times) + ' ms';
      msg.textContent = ms + ' ms — ' + (ms < 350 ? 'سرعة بطل! ⚡' : ms < 600 ? 'ممتاز 👏' : 'كمل، رح تتحسّن 💪');
      msg.style.color = 'var(--ink-soft)';
      setTimeout(nextRound, 700);
    };
    function end(){
      const best = Math.min.apply(null, times);
      const avg = Math.round(times.reduce((a,b)=>a+b,0) / times.length);
      pad.style.background = color; pad.textContent = '🏁';
      pad.onclick = null;
      msg.innerHTML = `<div style="font-size:40px;">⚡</div><p>أفضل زمن: <b>${best} ms</b> — المعدل: <b>${avg} ms</b></p>`;
      finish(host, onDone);
    }
    nextRound();
  }

  /* ---------------- 5) ذاكرة التسلسل ---------------- */
  function runMemory(host, title, color, onDone, topic) {
    const pads = topic.icons.slice(0, 4);
    // مستويات أقل وعرض أبطأ للصغار
    const MAX_LEVEL = topic.calm ? 3 : 5;
    const STEP = topic.calm ? 850 : 600;
    let sequence = [], input = [], level = 0, locked = true;
    shell(host, title + ' 🧩', 'شاهد التسلسل جيداً ثم أعِد ترتيبه بنفس الترتيب', `
      <div id="ge_memGrid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;max-width:260px;margin:16px auto;"></div>
      <p>المستوى: <b id="ge_memLevel">0</b> / ${MAX_LEVEL}</p>
      <div id="ge_memMsg" style="min-height:24px;font-weight:700;color:var(--ink-soft);"></div>`, topic);
    const grid = document.getElementById('ge_memGrid');
    const msg = document.getElementById('ge_memMsg');
    const buttons = pads.map((icon, i) => {
      const b = document.createElement('button');
      b.className = 'btn btn-ghost';
      b.style.cssText = `height:76px;font-size:32px;border-color:${color};transition:transform .15s,background .15s;`;
      b.textContent = icon;
      b.onclick = () => press(i);
      grid.appendChild(b);
      return b;
    });

    function flash(i){
      const b = buttons[i];
      b.style.background = color; b.style.transform = 'scale(1.08)';
      setTimeout(() => { b.style.background = ''; b.style.transform = ''; }, 320);
    }
    function playSequence(){
      locked = true;
      msg.textContent = 'انتبه للتسلسل... 👀';
      sequence.forEach((s, k) => setTimeout(() => flash(s), STEP * (k + 1)));
      setTimeout(() => { locked = false; msg.textContent = 'دورك! أعِد التسلسل 👆'; }, STEP * (sequence.length + 1));
    }
    function nextLevel(){
      level++;
      if (level > MAX_LEVEL) return end(true);
      document.getElementById('ge_memLevel').textContent = level;
      input = [];
      sequence.push(Math.floor(Math.random() * pads.length));
      playSequence();
    }
    function press(i){
      if (locked) return;
      flash(i);
      input.push(i);
      const step = input.length - 1;
      if (input[step] !== sequence[step]) return end(false);
      if (input.length === sequence.length) {
        locked = true;
        msg.textContent = 'أحسنت! 🎉';
        setTimeout(nextLevel, 800);
      }
    }
    function end(won){
      locked = true;
      msg.innerHTML = won
        ? `<div style="font-size:40px;">🏆</div><p>أكملت كل المستويات! ذاكرتك قوية جداً</p>`
        : `<div style="font-size:40px;">🧩</div><p>وصلت للمستوى ${level}! ذاكرتك بتقوى كل مرة</p>`;
      finish(host, onDone);
    }
    nextLevel();
  }

  /* ---------------- 6) المغامرة بالاختيارات ---------------- */
  function runAdventure(host, title, color, onDone, topic) {
    // الخادم يُرسل البنك كاملاً مرتّباً عشوائياً، فتختلف المغامرة كل مرة
    const scenes = topic.adventure.slice(0, 4);
    let idx = 0, good = 0;
    shell(host, title + ' 🗺️', `مغامرة عن ${topic.label} — كل قرار يغيّر النهاية`, `
      <div class="mini-game-area" id="ge_advArea" style="height:auto;min-height:200px;display:flex;flex-direction:column;justify-content:center;padding:20px;border-color:${color};"></div>
      <p>المشهد: <b id="ge_advStep">1</b> / ${scenes.length} | قرارات موفّقة: <b id="ge_advGood">0</b></p>`, topic);
    const area = document.getElementById('ge_advArea');

    function render(){
      if (idx >= scenes.length) return end();
      document.getElementById('ge_advStep').textContent = idx + 1;
      const s = scenes[idx];
      area.innerHTML = `
        <h4 style="margin:0 0 16px;line-height:1.9;">${s.t}</h4>
        <div style="display:flex;flex-direction:column;gap:10px;align-items:center;">
          ${s.c.map((c, i) => `<button class="btn btn-ghost" data-i="${i}" style="max-width:340px;border-color:${color};">${c.l}</button>`).join('')}
        </div>`;
      // الصغار يسمعون الموقف والخيارات، فاللعبة تعمل قبل إتقان القراءة
      say(topic, s.t + '. ' + s.c.map(c => c.l).join('، أو '));
      area.querySelectorAll('[data-i]').forEach(btn => {
        btn.onclick = () => {
          const choice = s.c[+btn.dataset.i];
          if (choice.g) { good++; document.getElementById('ge_advGood').textContent = good; }
          area.innerHTML = `
            <div style="font-size:40px;">${choice.g ? '🌟' : '💭'}</div>
            <p style="line-height:1.9;font-weight:700;">${choice.r}</p>`;
          say(topic, choice.r);
          idx++;
          setTimeout(render, topic.calm ? 3200 : 1500);
        };
      });
    }
    function end(){
      const perfect = good === scenes.length;
      const line = perfect
        ? 'أنهيت المغامرة بقرارات موفّقة كلها! بطل حقيقي 🌟'
        : `أنهيت المغامرة بـ ${good} من ${scenes.length} قرارات موفّقة — كل مغامرة تعلّمنا شيئاً جديداً 💪`;
      area.innerHTML = `
        <div style="font-size:46px;">${perfect ? '🏆' : '🎒'}</div>
        <p style="line-height:1.9;font-weight:700;">${line}</p>`;
      say(topic, line);
      finish(host, onDone);
    }
    render();
  }

  function finish(host, onDone) {
    if (typeof onDone === 'function') onDone();
  }

  return { run };
})();
