/* ============================================================
   GamesEngine — 6 آليات لعب مختلفة فعلياً، يُعاد استخدامها في
   مكتبة الألعاب (games.php) وفي اللعبة الصغيرة بعد كل مهمة (tasks.php)

   الآليات: catch | match | quiz | reaction | memory | adventure

   محتوى كل لعبة يتبع "موضوع" مشتق من تصنيف المهمة أو اللعبة، حتى تكون
   اللعبة التي تلي المهمة ذات صلة فعلية بها لا مجرد عنوان مختلف.
   الاستدعاء: GamesEngine.run(type, host, title, color, onDone, { category })
   ============================================================ */
const GamesEngine = (function () {

  /* ---------------- ربط تصنيفات المهام والألعاب بموضوع محتوى ---------------- */
  const TOPIC_BY_CATEGORY = {
    'تعلّم': 'learn', 'تعلم': 'learn', 'تربوي': 'learn', 'علمي': 'learn',
    'صحة': 'health', 'صحي': 'health', 'صحة نفسية': 'health',
    'قيم': 'values', 'سلوكي': 'values',
    'اجتماعي': 'social',
    'إبداع': 'creative', 'ابداع': 'creative',
    'ثقافي': 'culture',
    'حماية': 'safety',
    'مهارات حياتية': 'life', 'مسؤولية': 'life',
  };

  const TOPICS = {
    learn: {
      label: 'التعلّم',
      icons: ["📚","✏️","🔤","🔢","🧪","🔬","🌍","💡"],
      quiz: [
        {q:"هل القراءة كل يوم تزيد معرفتي؟", a:true},
        {q:"هل الشمس أكبر من الأرض؟", a:true},
        {q:"هل يمكن للأسماك التنفس خارج الماء بسهولة؟", a:false},
        {q:"هل السؤال عن شيء لا أفهمه تصرف ذكي؟", a:true},
        {q:"هل الخطأ أثناء التعلّم شيء يجب أن أخاف منه؟", a:false},
        {q:"هل الماء يتجمّد عندما يبرد كثيراً؟", a:true},
        {q:"هل القمر يضيء بنفسه مثل الشمس؟", a:false},
        {q:"هل التمرين على المسألة يجعلها أسهل؟", a:true},
      ],
      adventure: [
        {t:"وصلت إلى مكتبة قديمة وبابها مغلق بلغز. ماذا تفعل؟",
         c:[{l:"أقرأ اللغز بهدوء وأفكر",g:true,r:"فتح الباب! التفكير الهادئ مفتاح كل لغز 🔍"},
            {l:"أطرق الباب بقوة",g:false,r:"لم يُفتح... اللغز يحتاج عقلاً لا يداً 🚪"}]},
        {t:"داخل المكتبة كلمة لا تعرف معناها. ماذا تفعل؟",
         c:[{l:"أبحث عنها في المعجم",g:true,r:"كلمة جديدة في جعبتك! 📖"},
            {l:"أتجاوزها وأكمل",g:false,r:"بقي في القصة جزء غامض... 🌫️"}]},
        {t:"أمامك مسألة صعبة تحرس الكنز. ماذا تفعل؟",
         c:[{l:"أقسّمها خطوة خطوة",g:true,r:"حُلّت! الخطوات الصغيرة تهزم الصعب 🔢"},
            {l:"أخمّن إجابة بسرعة",g:false,r:"التخمين أضاع عليك المحاولة 🎲"}]},
      ],
    },
    health: {
      label: 'الصحة',
      icons: ["🏃","💧","🍎","🥕","😴","🦷","🧘","☀️"],
      quiz: [
        {q:"هل غسل اليدين قبل الأكل مهم لصحتي؟", a:true},
        {q:"هل شرب الماء يفيد جسمي؟", a:true},
        {q:"هل النوم مبكراً يعطيني طاقة لليوم التالي؟", a:true},
        {q:"هل أكل الحلويات طوال اليوم صحي؟", a:false},
        {q:"هل الرياضة تقوّي قلبي وعضلاتي؟", a:true},
        {q:"هل تنظيف الأسنان مرة واحدة في الشهر كافٍ؟", a:false},
        {q:"هل التنفس بعمق يساعدني على الهدوء عند الغضب؟", a:true},
        {q:"هل الجلوس أمام الشاشة ساعات طويلة مفيد لعيني؟", a:false},
      ],
      adventure: [
        {t:"تبدأ رحلة الجبل وأنت متعب. ما أول خطوة؟",
         c:[{l:"أشرب ماء وأتمدد قليلاً",g:true,r:"جسمك جاهز الآن! 💧"},
            {l:"أركض فوراً بأقصى سرعة",g:false,r:"تعبت بسرعة ولم تكمل 😮‍💨"}]},
        {t:"في منتصف الطريق تشعر بالغضب من التعب. ماذا تفعل؟",
         c:[{l:"أتنفس بعمق عشر مرات",g:true,r:"هدأ قلبك وعادت قوتك 🧘"},
            {l:"أصرخ وأرمي حقيبتي",g:false,r:"زاد تعبك ولم يتغيّر الطريق 🎒"}]},
        {t:"وصلت القمة ووجدت طاولة طعام. ماذا تختار؟",
         c:[{l:"فاكهة وماء",g:true,r:"طاقة حقيقية لبطل حقيقي 🍎"},
            {l:"حلويات فقط",g:false,r:"طاقة سريعة اختفت بسرعة 🍬"}]},
      ],
    },
    values: {
      label: 'القيم',
      icons: ["💛","🤝","🌟","🕊️","🎁","🤍","⭐","😊"],
      quiz: [
        {q:"هل مشاركة الألعاب مع الأصدقاء تصرف جميل؟", a:true},
        {q:"هل من الصواب أن أكذب على أهلي؟", a:false},
        {q:"هل مساعدة كبار السن تصرف سيء؟", a:false},
        {q:"هل الاعتذار عند الخطأ شجاعة؟", a:true},
        {q:"هل قول «شكراً» يُسعد من ساعدني؟", a:true},
        {q:"هل السخرية من شكل أحد تصرف مقبول؟", a:false},
        {q:"هل الأمانة تعني أن أُرجع ما ليس لي؟", a:true},
        {q:"هل الوفاء بالوعد مهم؟", a:true},
      ],
      adventure: [
        {t:"وجدت محفظة في الطريق. ماذا تفعل؟",
         c:[{l:"أسلّمها لأهلي أو للمعلم",g:true,r:"الأمانة أضاءت طريقك ✨"},
            {l:"آخذها لنفسي",g:false,r:"شعرت بثقل في قلبك 😔"}]},
        {t:"كسرت شيئاً في البيت بالخطأ ولم يرك أحد. ماذا تفعل؟",
         c:[{l:"أخبر أهلي وأعتذر",g:true,r:"شجاعتك أكبر من الخطأ 🤍"},
            {l:"أخفيه وأصمت",g:false,r:"بقي السر يزعجك طول اليوم 🌫️"}]},
        {t:"صديقك نسي طعامه اليوم. ماذا تفعل؟",
         c:[{l:"أشاركه طعامي",g:true,r:"كسبت وسام اللطف الذهبي 💛"},
            {l:"آكل وحدي",g:false,r:"مرّ اليوم بلا ابتسامة 🥲"}]},
      ],
    },
    social: {
      label: 'الأصدقاء',
      icons: ["🤝","👫","🎲","💬","😄","🫂","🎈","👋"],
      quiz: [
        {q:"هل التعاون مع الأصدقاء يجعل العمل أسهل؟", a:true},
        {q:"هل الاستماع لصديقي عندما يتكلم احترام له؟", a:true},
        {q:"هل مقاطعة الآخرين أثناء الكلام تصرف مهذب؟", a:false},
        {q:"هل دعوة طفل يجلس وحده للعب معنا تصرف جميل؟", a:true},
        {q:"هل الفوز في اللعب أهم من صداقتي؟", a:false},
        {q:"هل الابتسامة تساعدني على تكوين صداقات؟", a:true},
        {q:"هل من الجيد أن أشكر من يلعب معي؟", a:true},
        {q:"هل إخفاء لعبتي عن صديقي يُسمّى مشاركة؟", a:false},
      ],
      adventure: [
        {t:"في الملعب طفل جديد يجلس وحده. ماذا تفعل؟",
         c:[{l:"أدعوه للعب معنا",g:true,r:"كسبت صديقاً جديداً 👋"},
            {l:"أكمل لعبي وأتجاهله",g:false,r:"بقي وحيداً... وبقيت اللعبة ناقصة 🎈"}]},
        {t:"اختلف صديقان في فريقك على القواعد. ماذا تفعل؟",
         c:[{l:"أقترح أن نسمع الاثنين ثم نتفق",g:true,r:"عاد الفريق أقوى من قبل 🤝"},
            {l:"أنحاز لصديقي المفضّل",g:false,r:"انقسم الفريق وتوقفت اللعبة ⚡"}]},
        {t:"فزت في اللعبة وصديقك حزين. ماذا تفعل؟",
         c:[{l:"أشكره على اللعب وأمدح محاولته",g:true,r:"الفوز الحقيقي صداقة تدوم 🌟"},
            {l:"أضحك على خسارته",g:false,r:"فزت باللعبة وخسرت الصديق 💔"}]},
      ],
    },
    creative: {
      label: 'الإبداع',
      icons: ["🎨","🖌️","🌈","✂️","🧩","🎭","🎵","✨"],
      quiz: [
        {q:"هل لكل مشكلة أكثر من حل ممكن؟", a:true},
        {q:"هل الخيال يساعدني على اختراع أشياء جديدة؟", a:true},
        {q:"هل الرسم يجب أن يكون مثالياً وإلا فهو فاشل؟", a:false},
        {q:"هل تجربة فكرة جديدة تستحق المحاولة؟", a:true},
        {q:"هل خلط الأزرق مع الأصفر يعطي اللون الأخضر؟", a:true},
        {q:"هل الإبداع موهبة لا يمكن تطويرها بالتمرين؟", a:false},
        {q:"هل يمكن صنع لعبة ممتعة من أشياء بسيطة في البيت؟", a:true},
        {q:"هل تقليد الآخرين دائماً أفضل من ابتكار أسلوبي؟", a:false},
      ],
      adventure: [
        {t:"طُلب منك صنع هدية ولا يوجد مال. ماذا تفعل؟",
         c:[{l:"أصنعها بيدي من أشياء عندي",g:true,r:"هدية لا يملك أحد مثلها 🎁"},
            {l:"أعتذر ولا أحضر شيئاً",g:false,r:"ضاعت فرصة جميلة ✂️"}]},
        {t:"رسمتك خرجت مختلفة عمّا تخيّلت. ماذا تفعل؟",
         c:[{l:"أضيف عليها وأحوّلها لشيء جديد",g:true,r:"صار الخطأ أجمل جزء في اللوحة 🌈"},
            {l:"أمزّقها وأتوقف",g:false,r:"اختفت فكرة كانت ستكون رائعة 🖌️"}]},
        {t:"أصدقاؤك ملّوا من الألعاب المعتادة. ماذا تفعل؟",
         c:[{l:"أخترع لعبة بقواعد جديدة",g:true,r:"صرت صانع المرح اليوم 🎭"},
            {l:"أقول لا يوجد شيء نلعبه",g:false,r:"جلس الجميع بلا حماس 😐"}]},
      ],
    },
    culture: {
      label: 'التراث',
      icons: ["🕌","📜","🌙","🧭","⭐","🏛️","📖","🪔"],
      quiz: [
        {q:"هل ابن بطوطة رحّالة عربي مشهور؟", a:true},
        {q:"هل الخوارزمي هو من وضع أسس علم الجبر؟", a:true},
        {q:"هل اللغة العربية تُكتب من اليسار إلى اليمين؟", a:false},
        {q:"هل ابن سينا كان طبيباً وعالماً؟", a:true},
        {q:"هل «ألف ليلة وليلة» مجموعة حكايات مشهورة؟", a:true},
        {q:"هل التراث شيء قديم لا فائدة منه اليوم؟", a:false},
        {q:"هل حاول عباس بن فرناس الطيران قبل مئات السنين؟", a:true},
        {q:"هل الاعتزاز بلغتي وتراثي شيء جميل؟", a:true},
      ],
      adventure: [
        {t:"تقف أمام مكتبة بغداد القديمة. بم تبدأ؟",
         c:[{l:"أسأل العالِم عن أنفع كتاب",g:true,r:"دلّك على كنز المعرفة 📜"},
            {l:"أتجوّل بلا هدف",g:false,r:"مرّ الوقت ولم تحمل شيئاً 🌫️"}]},
        {t:"وجدت مخطوطة قديمة ممزّقة. ماذا تفعل؟",
         c:[{l:"أسلّمها لمن يحفظها ويرمّمها",g:true,r:"أنقذت حكاية عمرها مئات السنين 🪔"},
            {l:"أطويها في جيبي",g:false,r:"تلفت أكثر بين يديك 📄"}]},
        {t:"طلب منك أن تروي حكاية من تراثنا. ماذا تختار؟",
         c:[{l:"أروي قصة بطل تعلّمت منها",g:true,r:"أشعلت في الجميع حب التراث 🌙"},
            {l:"أعتذر لأني لا أحفظ شيئاً",g:false,r:"صمت المجلس... 🤐"}]},
      ],
    },
    safety: {
      label: 'الحماية',
      icons: ["🛡️","🚦","🚨","🔒","🧯","✋","📵","👮"],
      quiz: [
        {q:"هل أخبر أهلي إذا أزعجني أحد؟", a:true},
        {q:"هل أعطي معلوماتي الشخصية لشخص غريب على الإنترنت؟", a:false},
        {q:"هل من حقي أن أقول «لا» لأي لمسة تزعجني؟", a:true},
        {q:"هل أذهب مع شخص غريب إذا قال إن أهلي أرسلوه؟", a:false},
        {q:"هل أعبر الشارع من ممر المشاة؟", a:true},
        {q:"هل أحتفظ بسرّ يخيفني ولا أخبر أهلي به؟", a:false},
        {q:"هل كلمة السر يجب أن تبقى بيني وبين أهلي فقط؟", a:true},
        {q:"هل ألعب بالكهرباء أو النار إذا لم يرني أحد؟", a:false},
      ],
      adventure: [
        {t:"شخص لا تعرفه ينادي عليك من سيارته. ماذا تفعل؟",
         c:[{l:"أبتعد فوراً وأخبر أهلي",g:true,r:"تصرّفت كبطل يحمي نفسه 🛡️"},
            {l:"أقترب لأسمع ما يريد",g:false,r:"قرار خطير... الابتعاد دائماً أأمن 🚨"}]},
        {t:"وصلتك رسالة على الإنترنت تطلب صورتك وعنوانك. ماذا تفعل؟",
         c:[{l:"لا أرد وأخبر أهلي",g:true,r:"أغلقت الباب في وجه الخطر 🔒"},
            {l:"أرسل المعلومات",g:false,r:"معلوماتك صارت عند شخص مجهول 📵"}]},
        {t:"طلب منك أحدهم أن تكتم سراً عن أهلك وأنت خائف. ماذا تفعل؟",
         c:[{l:"أخبر أهلي مهما كان السر",g:true,r:"لا سرّ يعلو على أمانك ✋"},
            {l:"أكتمه لأني وعدت",g:false,r:"السرّ المخيف يجب ألا يبقى سراً 💙"}]},
      ],
    },
    life: {
      label: 'مهارات الحياة',
      icons: ["🧹","🧺","🪥","🌱","🍽️","⏰","🧴","🧊"],
      quiz: [
        {q:"هل ترتيب غرفتي مسؤوليتي أنا؟", a:true},
        {q:"هل أترك ألعابي على الأرض بعد اللعب؟", a:false},
        {q:"هل سقاية النبتة بانتظام تساعدها على النمو؟", a:true},
        {q:"هل أساعد في ترتيب طاولة الطعام؟", a:true},
        {q:"هل رمي القمامة في أي مكان تصرف صحيح؟", a:false},
        {q:"هل تنظيم وقتي يساعدني على إنجاز أكثر؟", a:true},
        {q:"هل الاعتناء بأغراضي يجعلها تدوم أطول؟", a:true},
        {q:"هل أنتظر دائماً من يرتّب مكاني بدلاً عني؟", a:false},
      ],
      adventure: [
        {t:"غرفتك فوضى وأصدقاؤك سيصلون بعد ساعة. ماذا تفعل؟",
         c:[{l:"أرتّب قطعة قطعة من الآن",g:true,r:"وصل أصدقاؤك لغرفة تلمع ✨"},
            {l:"أؤجّل حتى يصلوا",g:false,r:"وصلوا ولم تجد مكاناً للجلوس 🧺"}]},
        {t:"نبتتك بدأت تذبل. ماذا تفعل؟",
         c:[{l:"أسقيها وأضعها قرب الشمس",g:true,r:"عادت خضراء بفضل عنايتك 🌱"},
            {l:"أنتظر لعلها تتحسّن",g:false,r:"ذبلت أكثر... العناية لا تنتظر 🥀"}]},
        {t:"أمامك واجب ولعبة جديدة. بم تبدأ؟",
         c:[{l:"أنهي الواجب ثم ألعب مرتاحاً",g:true,r:"لعبت بلا قلق ⏰"},
            {l:"ألعب أولاً والواجب لاحقاً",g:false,r:"جاء الليل والواجب لم ينتهِ 😴"}]},
      ],
    },
    general: {
      label: 'عام',
      icons: ["🍎","🐱","🚗","🌙","🎈","⭐","🎵","🌸"],
      quiz: [
        {q:"هل الشمس أكبر من الأرض؟", a:true},
        {q:"هل يمكن للأسماك التنفس خارج الماء بسهولة؟", a:false},
        {q:"هل مشاركة الألعاب مع الأصدقاء تصرف جميل؟", a:true},
        {q:"هل من الصواب أن أكذب على أهلي؟", a:false},
        {q:"هل غسل اليدين قبل الأكل مهم لصحتي؟", a:true},
        {q:"هل يجب أن أرمي القمامة في أي مكان؟", a:false},
        {q:"هل مساعدة كبار السن تصرف سيء؟", a:false},
        {q:"هل التعاون مع الأصدقاء يجعل العمل أسهل؟", a:true},
      ],
      adventure: [
        {t:"أمامك طريقان: واحد مضيء وواحد مظلم. ماذا تختار؟",
         c:[{l:"أسأل عن الطريق أولاً",g:true,r:"السؤال وفّر عليك الضياع 🧭"},
            {l:"أدخل المظلم بلا تفكير",g:false,r:"تهت قليلاً ثم عدت 🌫️"}]},
        {t:"وجدت صندوقاً مقفلاً ومفتاحين. ماذا تفعل؟",
         c:[{l:"أجرّب بهدوء وأنتبه للعلامات",g:true,r:"انفتح الصندوق! ⭐"},
            {l:"أكسر القفل بحجر",g:false,r:"تضرّر ما بداخله 🪨"}]},
        {t:"في نهاية الطريق طفل يطلب مساعدتك. ماذا تفعل؟",
         c:[{l:"أساعده قبل أن أكمل",g:true,r:"البطل الحقيقي لا يمرّ دون أن يساعد 💛"},
            {l:"أكمل طريقي بسرعة",g:false,r:"وصلت أولاً لكن بقلب ثقيل 🥲"}]},
      ],
    },
  };

  function topicFor(category) {
    if (!category) return TOPICS.general;
    return TOPICS[TOPIC_BY_CATEGORY[String(category).trim()]] || TOPICS.general;
  }

  function shuffled(arr) { return arr.slice().sort(() => Math.random() - 0.5); }

  function shell(host, title, subtitle, body) {
    host.innerHTML = `
      <div class="mini-game-wrap card" style="padding:20px;">
        <h3>${title}</h3>
        <p style="color:var(--ink-soft);">${subtitle}</p>
        ${body}
      </div>`;
    host.scrollIntoView({behavior:'smooth'});
  }

  function run(type, host, title, color, onDone, opts) {
    const topic = topicFor(opts && opts.category);
    switch (type) {
      case 'match':     return runMatch(host, title, color, onDone, topic);
      case 'quiz':      return runQuiz(host, title, color, onDone, topic);
      case 'reaction':  return runReaction(host, title, color, onDone, topic);
      case 'memory':    return runMemory(host, title, color, onDone, topic);
      case 'adventure': return runAdventure(host, title, color, onDone, topic);
      default:          return runCatch(host, title, color, onDone, topic);
    }
  }

  /* ---------------- 1) التقاط ---------------- */
  function runCatch(host, title, color, onDone, topic) {
    let caught = 0, total = 6, spawned = 0;
    const items = topic.icons;
    shell(host, title, 'اضغط على العناصر المتساقطة', `
      <div class="mini-game-area" id="ge_miniArea" style="border-color:${color};"></div>
      <p>التقطت: <b id="ge_catchCount">0</b> / ${total}</p>`);
    const miniArea = document.getElementById('ge_miniArea');
    const spawner = setInterval(() => {
      if (spawned >= total) { clearInterval(spawner); return; }
      spawned++;
      const el = document.createElement('div');
      el.className = 'mini-game-item';
      el.textContent = items[Math.floor(Math.random()*items.length)];
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

  /* ---------------- 2) مطابقة الأزواج ---------------- */
  function runMatch(host, title, color, onDone, topic) {
    const icons = topic.icons.slice(0, 6);
    const deck = shuffled(icons.concat(icons));
    let opened = [], matched = [], locked = false;
    shell(host, title + ' 🧠', 'اقلب بطاقتين واعثر على المتطابقتين', `
      <div id="ge_matchGrid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;max-width:360px;margin:14px auto;"></div>
      <p>أزواج مكتملة: <b id="ge_matchScore">0</b> / ${icons.length}</p>`);
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

  /* ---------------- 3) سباق الأسئلة ---------------- */
  function runQuiz(host, title, color, onDone, topic) {
    const questions = shuffled(topic.quiz).slice(0, 4);
    let idx = 0, score = 0, timeLeft = 25;
    shell(host, title + ' ⏱️', `أسئلة عن ${topic.label} — أجب بسرعة!`, `
      <p>الوقت المتبقي: <b id="ge_quizTimer" style="color:${color};">${timeLeft}</b> ث | النقاط: <b id="ge_quizScore">0</b></p>
      <div id="ge_quizBody"></div>`);
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

  /* ---------------- 4) سرعة البديهة ---------------- */
  function runReaction(host, title, color, onDone, topic) {
    const ROUNDS = 5;
    let round = 0, times = [], waiting = false, startedAt = 0, timeoutId = null;
    shell(host, title + ' ⚡', 'انتظر حتى تتحوّل الدائرة للأخضر ثم اضغط بأسرع ما يمكن', `
      <button id="ge_reactPad" class="btn" style="width:180px;height:180px;border-radius:50%;font-size:20px;font-weight:800;color:#fff;background:#E5484D;border:none;margin:14px auto;display:flex;align-items:center;justify-content:center;">استعد...</button>
      <p>الجولة: <b id="ge_reactRound">0</b> / ${ROUNDS} | أفضل زمن: <b id="ge_reactBest" style="color:${color};">—</b></p>
      <div id="ge_reactMsg" style="min-height:24px;font-weight:700;"></div>`);
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
    const MAX_LEVEL = 5;
    let sequence = [], input = [], level = 0, locked = true;
    shell(host, title + ' 🧩', 'شاهد التسلسل جيداً ثم أعِد ترتيبه بنفس الترتيب', `
      <div id="ge_memGrid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;max-width:260px;margin:16px auto;"></div>
      <p>المستوى: <b id="ge_memLevel">0</b> / ${MAX_LEVEL}</p>
      <div id="ge_memMsg" style="min-height:24px;font-weight:700;color:var(--ink-soft);"></div>`);
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
      sequence.forEach((s, k) => setTimeout(() => flash(s), 600 * (k + 1)));
      setTimeout(() => { locked = false; msg.textContent = 'دورك! أعِد التسلسل 👆'; }, 600 * (sequence.length + 1));
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
    const scenes = topic.adventure;
    let idx = 0, good = 0;
    shell(host, title + ' 🗺️', `مغامرة عن ${topic.label} — كل قرار يغيّر النهاية`, `
      <div class="mini-game-area" id="ge_advArea" style="height:auto;min-height:200px;display:flex;flex-direction:column;justify-content:center;padding:20px;border-color:${color};"></div>
      <p>المشهد: <b id="ge_advStep">1</b> / ${scenes.length} | قرارات موفّقة: <b id="ge_advGood">0</b></p>`);
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
      area.querySelectorAll('[data-i]').forEach(btn => {
        btn.onclick = () => {
          const choice = s.c[+btn.dataset.i];
          if (choice.g) { good++; document.getElementById('ge_advGood').textContent = good; }
          area.innerHTML = `
            <div style="font-size:40px;">${choice.g ? '🌟' : '💭'}</div>
            <p style="line-height:1.9;font-weight:700;">${choice.r}</p>`;
          idx++;
          setTimeout(render, 1500);
        };
      });
    }
    function end(){
      const perfect = good === scenes.length;
      area.innerHTML = `
        <div style="font-size:46px;">${perfect ? '🏆' : '🎒'}</div>
        <p style="line-height:1.9;font-weight:700;">
          ${perfect
            ? 'أنهيت المغامرة بقرارات موفّقة كلها! بطل حقيقي 🌟'
            : `أنهيت المغامرة بـ ${good} من ${scenes.length} قرارات موفّقة — كل مغامرة تعلّمنا شيئاً جديداً 💪`}
        </p>`;
      finish(host, onDone);
    }
    render();
  }

  function finish(host, onDone) {
    if (typeof onDone === 'function') onDone();
  }

  return { run, topicFor };
})();
