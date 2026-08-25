/* ============================================================
   SoundEngine — يقرأ النصوص بصوت الشخصية النشطة. إن كان للشخصية
   ملف صوتي مرفوع من الأدمن (ترحيب عام) يشغّله عند الحاجة، وإلا
   يستخدم SpeechSynthesis بنبرة (pitch/rate) خاصة بحركة الشخصية.
   ============================================================ */
const SoundEngine = (function () {
  const MOVE_VOICE = {
    wiggle: { pitch: 1.3, rate: 1.05 }, bounce: { pitch: 1.55, rate: 1.15 },
    dash: { pitch: 1.1, rate: 1.2 }, float: { pitch: 0.9, rate: 0.95 },
    hop: { pitch: 1.4, rate: 1.1 }, stomp: { pitch: 0.7, rate: 0.9 }
  };

  let voiceEnabled = localStorage.getItem("kidaura_voice") !== "off";
  let musicEnabled = localStorage.getItem("kidaura_music") === "on";
  let audioCtx = null, musicTimer = null;
  let cachedArabicVoice = null;

  function pickBestVoice() {
    if (!("speechSynthesis" in window)) return null;
    if (cachedArabicVoice) return cachedArabicVoice;
    const voices = window.speechSynthesis.getVoices() || [];
    if (!voices.length) return null;
    // نفضّل صوتاً أنثوياً عربياً إن وُجد (عادة أدفأ لسرد قصص الأطفال)، ثم أي صوت عربي، ثم الافتراضي
    const female = voices.find(v => /ar/i.test(v.lang) && /female|زينب|salma|hoda|laila/i.test(v.name));
    const anyArabic = voices.find(v => /ar/i.test(v.lang));
    cachedArabicVoice = female || anyArabic || null;
    return cachedArabicVoice;
  }
  if ("speechSynthesis" in window) {
    window.speechSynthesis.onvoiceschanged = () => { cachedArabicVoice = null; pickBestVoice(); };
  }

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

  function speak(text, charData) {
    if (!voiceEnabled) return;
    if (!("speechSynthesis" in window)) return;
    window.speechSynthesis.cancel();
    const move = (charData && charData.move) || (window.KIDAURA_ACTIVE_CHARACTER && window.KIDAURA_ACTIVE_CHARACTER.move) || "wiggle";
    const v = MOVE_VOICE[move] || { pitch: 1, rate: 1 };
    const u = new SpeechSynthesisUtterance(text);
    u.lang = "ar-SA"; u.pitch = v.pitch; u.rate = v.rate; u.volume = 1;
    const voice = pickBestVoice();
    if (voice) u.voice = voice;
    // نغمة تنبيه لطيفة قبل الكلام لإحساس أكثر حيوية (تُشغَّل فقط عند وجود AudioContext متاح)
    playChime();
    window.speechSynthesis.speak(u);
  }

  function playChime() {
    try {
      if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      const o = audioCtx.createOscillator(), g = audioCtx.createGain();
      o.type = "sine"; o.frequency.value = 880;
      g.gain.setValueAtTime(0.0001, audioCtx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.12, audioCtx.currentTime + 0.05);
      g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.25);
      o.connect(g); g.connect(audioCtx.destination);
      o.start(); o.stop(audioCtx.currentTime + 0.3);
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

  function startMusic() {
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    stopMusic();
    const notes = [261.6, 329.6, 392.0, 440.0, 523.3];
    let i = 0;
    const master = audioCtx.createGain();
    master.gain.value = 0.05;
    master.connect(audioCtx.destination);
    musicTimer = setInterval(() => {
      const osc = audioCtx.createOscillator();
      const g = audioCtx.createGain();
      osc.type = "sine";
      osc.frequency.value = notes[i % notes.length];
      g.gain.setValueAtTime(0.0001, audioCtx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.6, audioCtx.currentTime + 0.15);
      g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 1.1);
      osc.connect(g); g.connect(master);
      osc.start(); osc.stop(audioCtx.currentTime + 1.2);
      i++;
    }, 900);
  }
  function stopMusic() {
    if (musicTimer) clearInterval(musicTimer);
    musicTimer = null;
  }

  return { speak, playCharacterClip, isVoiceEnabled, isMusicEnabled, setVoiceEnabled, setMusicEnabled, startMusic, stopMusic };
})();
