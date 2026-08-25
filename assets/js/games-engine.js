/* ============================================================
   GamesEngine — 3 آليات لعب مختلفة فعلياً، يُعاد استخدامها في
   مكتبة الألعاب (games.php) وفي اللعبة الصغيرة بعد كل مهمة (tasks.php)
   ============================================================ */
const GamesEngine = (function () {
  const CATCH_EMOJIS = ["⭐","🌟","✨","💫","🌠"];
  const MATCH_ICONS = ["🍎","🐱","🚗","🌙","🎈","⭐","🎵","🌸"];
  const QUIZ_BANK = [
    {q:"هل الشمس أكبر من الأرض؟", a:true},
    {q:"هل يمكن للأسماك التنفس خارج الماء بسهولة؟", a:false},
    {q:"هل مشاركة الألعاب مع الأصدقاء تصرف جميل؟", a:true},
    {q:"هل من الصواب أن أكذب على أهلي؟", a:false},
    {q:"هل غسل اليدين قبل الأكل مهم لصحتي؟", a:true},
    {q:"هل يجب أن أرمي القمامة بأي مكان؟", a:false},
    {q:"هل مساعدة كبار السن تصرف سيء؟", a:false},
    {q:"هل التعاون مع الأصدقاء يجعل العمل أسهل؟", a:true},
  ];

  function run(type, host, title, color, onDone) {
    if (type === 'match') return runMatch(host, title, color, onDone);
    if (type === 'quiz') return runQuiz(host, title, color, onDone);
    return runCatch(host, title, color, onDone);
  }

  function runCatch(host, title, color, onDone) {
    let caught = 0, total = 6, spawned = 0;
    host.innerHTML = `
      <div class="mini-game-wrap card" style="padding:20px;">
        <h3>${title}</h3>
        <p style="color:var(--ink-soft);">اضغط على العناصر المتساقطة</p>
        <div class="mini-game-area" id="ge_miniArea" style="border-color:${color};"></div>
        <p>التقطت: <b id="ge_catchCount">0</b> / ${total}</p>
      </div>`;
    host.scrollIntoView({behavior:'smooth'});
    const miniArea = document.getElementById('ge_miniArea');
    const spawner = setInterval(() => {
      if (spawned >= total) { clearInterval(spawner); return; }
      spawned++;
      const el = document.createElement('div');
      el.className = 'mini-game-item';
      el.textContent = CATCH_EMOJIS[Math.floor(Math.random()*CATCH_EMOJIS.length)];
      el.style.right = (Math.random()*80) + '%';
      el.style.animationDuration = (2+Math.random()) + 's';
      el.addEventListener('animationend', () => el.remove());
      el.addEventListener('click', () => {
        caught++; document.getElementById('ge_catchCount').textContent = caught; el.remove();
        if (caught >= total) { clearInterval(spawner); finish(host, onDone); }
      });
      miniArea.appendChild(el);
    }, 550);
  }

  function runMatch(host, title, color, onDone) {
    const icons = MATCH_ICONS.slice(0, 6);
    let deck = icons.concat(icons).sort(() => Math.random() - 0.5);
    let opened = [], matched = [], locked = false;
    host.innerHTML = `
      <div class="mini-game-wrap card" style="padding:20px;">
        <h3>${title} 🧠</h3>
        <p style="color:var(--ink-soft);">اقلب بطاقتين واعثر على المتطابقتين</p>
        <div id="ge_matchGrid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;max-width:360px;margin:14px auto;"></div>
        <p>أزواج مكتملة: <b id="ge_matchScore">0</b> / ${icons.length}</p>
      </div>`;
    host.scrollIntoView({behavior:'smooth'});
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
        }, 700);
      }
    }
  }

  function runQuiz(host, title, color, onDone) {
    const questions = QUIZ_BANK.slice().sort(() => Math.random() - 0.5).slice(0, 4);
    let idx = 0, score = 0, timeLeft = 25;
    host.innerHTML = `
      <div class="mini-game-wrap card" style="padding:20px;">
        <h3>${title} ⏱️</h3>
        <p>الوقت المتبقي: <b id="ge_quizTimer" style="color:${color};">${timeLeft}</b> ث | النقاط: <b id="ge_quizScore">0</b></p>
        <div id="ge_quizBody"></div>
      </div>`;
    host.scrollIntoView({behavior:'smooth'});
    const timer = setInterval(() => {
      timeLeft--;
      const t = document.getElementById('ge_quizTimer');
      if (t) t.textContent = timeLeft;
      if (timeLeft <= 0) end();
    }, 1000);
    function render(){
      if (idx >= questions.length) { end(); return; }
      const q = questions[idx];
      document.getElementById('ge_quizBody').innerHTML = `
        <h4 style="margin:14px 0;">${q.q}</h4>
        <div style="display:flex;gap:12px;justify-content:center;">
          <button class="btn btn-primary" data-v="true">✅ صح</button>
          <button class="btn btn-ghost" data-v="false">❌ خطأ</button>
        </div>`;
      document.querySelectorAll('#ge_quizBody [data-v]').forEach(btn => {
        btn.onclick = () => {
          if ((btn.dataset.v === 'true') === q.a) { score++; document.getElementById('ge_quizScore').textContent = score; }
          idx++; render();
        };
      });
    }
    function end(){
      clearInterval(timer);
      const body = document.getElementById('ge_quizBody');
      if (body) body.innerHTML = `<div style="font-size:44px;">🏆</div><p>سجّلت ${score} من ${questions.length}!</p>`;
      finish(host, onDone);
    }
    render();
  }

  function finish(host, onDone) {
    if (typeof onDone === 'function') onDone();
  }

  return { run };
})();
