/* ============================================================
   SoundEngine — صوت الرفيق المرشد.
   - speak(): يقرأ نصاً نظيفاً (بلا إيموجي ولا رموز) بأفضل صوت عربي متاح
     على جهاز الطفل، ويوقف الموسيقى الخلفية أثناء الكلام ثم يعيدها.
   - sfx(): نغمات قصيرة للتفاعل. startMusic()/stopMusic(): موسيقى خلفية هادئة.
   - الصوت المفضّل يُحفظ في localStorage (kidaura_voice_name) ويختاره الطفل
     من البروفايل.
   ============================================================ */
const SoundEngine = (function () {
  let voiceEnabled = localStorage.getItem("kidaura_voice") !== "off";
  let musicEnabled = localStorage.getItem("kidaura_music") === "on";
  let preferredVoiceName = localStorage.getItem("kidaura_voice_name") || "";
  let audioCtx = null, musicTimer = null, musicPausedForSpeech = false;
  let cachedVoice = null;
  const listeners = { start: [], end: [] };

  // ---------- تنظيف النص قبل النطق ----------
  // الإيموجي والرموز التصويرية والأسهم والنقاط الزخرفية تُحذف؛ الفواصل تصبح وقفات.
  const EMOJI_RE = /[\p{Extended_Pictographic}\p{Emoji_Presentation}\u{FE0F}\u{200D}\u{20E3}\u{E0020}-\u{E007F}]/gu;
  const SYMBOL_RE = /[\u2190-\u21FF\u2300-\u23FF\u2500-\u27BF\u2B00-\u2BFF\u{1F000}-\u{1FAFF}•▪▮●▲■○◌✦✧·]/gu;
  function cleanSpeech(text) {
    return String(text || "")
      .replace(EMOJI_RE, " ")
      .replace(SYMBOL_RE, " ")
      .replace(/[*_#>`~|]/g, " ")
      .replace(/\s*\n+\s*/g, ". ")
      .replace(/\s{2,}/g, " ")
      .replace(/\s+([،,.؟!:])/g, "$1")
      .replace(/([،,.؟!:])\1+/g, "$1")
      .trim();
  }

  // ---------- اختيار الصوت ----------
  function arabicVoices() {
    if (!("speechSynthesis" in window)) return [];
    return (window.speechSynthesis.getVoices() || []).filter(v => /^ar([-_]|$)/i.test(v.lang));
  }
  function voiceScore(v) {
    const n = (v.name || "").toLowerCase();
    let s = 0;
    if (/natural|neural|premium|enhanced|wavenet/.test(n)) s += 40;
    if (/online/.test(n)) s += 20;
    if (/google/.test(n)) s += 25;
    if (/microsoft/.test(n)) s += 15;
    if (/salma|hoda|zariyah|laila|amany|female|زينب|سلمى|هدى/.test(n)) s += 10; // نبرة أدفأ لسرد الأطفال
    if (/^ar-(sa|eg|ae|jo|ps|lb|kw|qa|ma|dz|tn|iq|sy)/i.test(v.lang)) s += 5;
    if (v.localService === false) s += 3;
    return s;
  }
  function pickBestVoice() {
    if (cachedVoice) return cachedVoice;
    const list = arabicVoices();
    if (!list.length) return null;
    if (preferredVoiceName) {
      const pref = list.find(v => v.name === preferredVoiceName);
      if (pref) { cachedVoice = pref; return pref; }
    }
    cachedVoice = list.slice().sort((a, b) => voiceScore(b) - voiceScore(a))[0] || null;
    return cachedVoice;
  }
  if ("speechSynthesis" in window) {
    window.speechSynthesis.onvoiceschanged = () => { cachedVoice = null; pickBestVoice(); };
  }
  function listVoices() { return arabicVoices().map(v => ({ name: v.name, lang: v.lang })); }
  function setPreferredVoice(name) {
    preferredVoiceName = name || "";
    cachedVoice = null;
    if (name) localStorage.setItem("kidaura_voice_name", name); else localStorage.removeItem("kidaura_voice_name");
  }
  function getPreferredVoice() { return preferredVoiceName; }

  function isVoiceEnabled() { return voiceEnabled; }
  function isMusicEnabled() { return musicEnabled; }
  function setVoiceEnabled(v) {
    voiceEnabled = v;
    localStorage.setItem("kidaura_voice", v ? "on" : "off");
    if (!v && "speechSynthesis" in window) window.speechSynthesis.cancel();
  }
  function setMusicEnabled(v) {
    musicEnabled = v;
    localStorage.setItem("kidaura_music", v ? "on" : "off");
    if (v) startMusic(); else stopMusic();
  }

  function on(evt, fn) { if (listeners[evt]) listeners[evt].push(fn); }
  function emit(evt, payload) { (listeners[evt] || []).forEach(fn => { try { fn(payload); } catch (e) {} }); }

  // ---------- الكلام ----------
  let speaking = false;
  function isSpeaking() { return speaking; }
  function stop() {
    if ("speechSynthesis" in window) window.speechSynthesis.cancel();
  }

  function speak(text, charData, opts) {
    opts = opts || {};
    const clean = cleanSpeech(text);
    let ended = false;
    const done = () => {
      if (ended) return;
      ended = true;
      speaking = false;
      if (musicPausedForSpeech && musicEnabled) { musicPausedForSpeech = false; startMusic(true); }
      emit("end", clean);
      if (typeof opts.onEnd === "function") opts.onEnd();
    };
    if (!voiceEnabled || !clean) { done(); return false; }
    if (!("speechSynthesis" in window) || !("SpeechSynthesisUtterance" in window)) { done(); return false; }

    window.speechSynthesis.cancel();
    // لا تداخل بين الموسيقى وصوت الرفيق: تتوقف الآن وتعود بعد آخر كلمة
    if (musicTimer) { stopMusic(); musicPausedForSpeech = true; }

    const u = new SpeechSynthesisUtterance(clean);
    u.lang = "ar-SA";
    u.rate = opts.rate || 0.95;   // إيقاع سرد هادئ يناسب 6–12 سنة
    u.pitch = opts.pitch || 1.05;
    u.volume = 1;
    const voice = pickBestVoice();
    if (voice) { u.voice = voice; u.lang = voice.lang; }
    u.onstart = () => { speaking = true; emit("start", clean); };
    u.onend = done;
    u.onerror = done;
    if (!opts.silentChime) playChime();
    window.speechSynthesis.speak(u);
    // حارس: بعض المتصفحات لا تطلق onend إذا أُلغي الكلام من الخارج
    const est = Math.min(60000, 1500 + clean.length * 95);
    setTimeout(() => { if (!ended && !window.speechSynthesis.speaking) done(); }, est);
    return true;
  }

  // ---------- مؤثرات ----------
  function ctx() {
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    if (audioCtx.state === "suspended") audioCtx.resume().catch(() => {});
    return audioCtx;
  }
  function sfx(name) {
    const sounds = {
      flip:  [{ f: 520, d: .07, v: .06 }],
      match: [{ f: 660, d: .1, v: .08 }, { f: 880, d: .14, v: .07, delay: .07 }],
      miss:  [{ f: 220, d: .16, v: .05 }],
      win:   [{ f: 523, d: .12, v: .07 }, { f: 659, d: .12, v: .07, delay: .1 }, { f: 784, d: .2, v: .08, delay: .2 }],
      pop:   [{ f: 740, d: .08, v: .05 }],
      cheer: [{ f: 523, d: .1, v: .07 }, { f: 659, d: .1, v: .07, delay: .08 }, { f: 784, d: .1, v: .07, delay: .16 }, { f: 1046, d: .25, v: .09, delay: .24 }],
      step:  [{ f: 392, d: .09, v: .05 }, { f: 523, d: .12, v: .06, delay: .1 }]
    };
    const notes = sounds[name];
    if (!notes) return;
    try {
      const ac = ctx();
      notes.forEach(note => {
        const start = ac.currentTime + (note.delay || 0);
        const osc = ac.createOscillator();
        const gain = ac.createGain();
        osc.type = "sine";
        osc.frequency.value = note.f;
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(note.v, start + .015);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + note.d);
        osc.connect(gain); gain.connect(ac.destination);
        osc.start(start); osc.stop(start + note.d + .03);
      });
    } catch (e) {}
  }

  function playChime() {
    try {
      const ac = ctx();
      const o = ac.createOscillator(), g = ac.createGain();
      o.type = "sine"; o.frequency.value = 880;
      g.gain.setValueAtTime(0.0001, ac.currentTime);
      g.gain.exponentialRampToValueAtTime(0.08, ac.currentTime + 0.05);
      g.gain.exponentialRampToValueAtTime(0.0001, ac.currentTime + 0.22);
      o.connect(g); g.connect(ac.destination);
      o.start(); o.stop(ac.currentTime + 0.25);
    } catch (e) {}
  }

  /** يشغّل الملف الصوتي المرفوع للشخصية إن وُجد (ترحيب/تعريف)، وإلا ينطق الاسم */
  function playCharacterClip(charData) {
    if (!voiceEnabled) return;
    if (charData && charData.audio) {
      const audio = new Audio(window.KIDAURA_BASE + "/" + charData.audio);
      audio.play().catch(() => speak(charData.name || "", charData));
    } else if (charData) {
      speak(charData.name || "أنا هنا!", charData);
    }
  }

  // ---------- موسيقى خلفية ----------
  function startMusic(resume) {
    if (!resume && speaking) { musicPausedForSpeech = true; return; } // تنتظر انتهاء الكلام
    let ac;
    try { ac = ctx(); } catch (e) { return; }
    stopMusic();
    const notes = [261.6, 329.6, 392.0, 440.0, 523.3];
    let i = 0;
    const master = ac.createGain();
    master.gain.value = 0.05;
    master.connect(ac.destination);
    musicTimer = setInterval(() => {
      const osc = ac.createOscillator();
      const g = ac.createGain();
      osc.type = "sine";
      osc.frequency.value = notes[i % notes.length];
      g.gain.setValueAtTime(0.0001, ac.currentTime);
      g.gain.exponentialRampToValueAtTime(0.6, ac.currentTime + 0.15);
      g.gain.exponentialRampToValueAtTime(0.0001, ac.currentTime + 1.1);
      osc.connect(g); g.connect(master);
      osc.start(); osc.stop(ac.currentTime + 1.2);
      i++;
    }, 900);
  }
  function stopMusic() {
    if (musicTimer) clearInterval(musicTimer);
    musicTimer = null;
  }

  return {
    speak, stop, isSpeaking, cleanSpeech, sfx, playCharacterClip, on,
    isVoiceEnabled, isMusicEnabled, setVoiceEnabled, setMusicEnabled, startMusic, stopMusic,
    listVoices, setPreferredVoice, getPreferredVoice
  };
})();
