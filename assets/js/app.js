/* ============================================================
   app.js — يُحمَّل في تذييل كل صفحة، يُفعّل: قائمة الجوال،
   أزرار الصوت/الموسيقى، والرفيق الدائم (فقاعة + تبديل)
   ============================================================ */
document.addEventListener("DOMContentLoaded", function () {

  // ---------- قائمة الجوال ----------
  const navToggle = document.getElementById("navToggle");
  const navLinks = document.getElementById("navLinks");
  if (navToggle && navLinks) {
    navToggle.addEventListener("click", () => navLinks.classList.toggle("open"));
  }

  // ---------- أزرار الصوت والموسيقى (الهيدر + سايدبار الجوال معاً) ----------
  const voiceBtns = document.querySelectorAll(".voice-btn, #voiceToggle");
  const musicBtns = document.querySelectorAll(".music-btn, #musicToggle");
  const refreshVoice = () => { const on = SoundEngine.isVoiceEnabled(); voiceBtns.forEach(b => { b.classList.toggle("on", on); b.textContent = on ? "🗣️" : "🔈"; }); };
  const refreshMusic = () => { const on = SoundEngine.isMusicEnabled(); musicBtns.forEach(b => { b.classList.toggle("on", on); b.textContent = on ? "🔊" : "🔇"; }); };
  refreshVoice(); refreshMusic();
  if (SoundEngine.isMusicEnabled()) SoundEngine.startMusic();
  voiceBtns.forEach(b => b.addEventListener("click", () => {
    if (SoundEngine.unlockAudio) SoundEngine.unlockAudio();
    const nowOn = !SoundEngine.isVoiceEnabled();
    SoundEngine.setVoiceEnabled(nowOn);
    refreshVoice();
    if (nowOn && b.classList.contains("public-voice-toggle")) {
      const line = "أنا معك! هل تسمعني الآن؟";
      if (window.Companion) Companion.say(line, { mood: "wave" });
      else SoundEngine.speak(line, window.KIDAURA_ACTIVE_CHARACTER);
    }
  }));
  musicBtns.forEach(b => b.addEventListener("click", () => { SoundEngine.setMusicEnabled(!SoundEngine.isMusicEnabled()); refreshMusic(); }));

  // ---------- الرفيق الدائم (المنطق في companion.js) ----------
  const swapBtn = document.getElementById("companionSwapBtn");
  if (swapBtn) {
    swapBtn.addEventListener("click", () => {
      fetch(window.KIDAURA_BASE + "/api/swap-companion.php", { method: "POST" })
        .then(r => r.json())
        .then(data => {
          if (data.ok) location.reload();
        });
    });
  }

  // ---------- أزرار «اسمع» ----------
  // أي زر يحمل data-say يقرأ نصّه بصوت الرفيق (مثل مشاهد قسم الحماية).
  // إن كان الصوت مغلقاً من زر 🗣️ لا نتجاوز اختيار الطفل، بل نخبره أين يفتحه —
  // بدل زرٍ يُضغط ولا يحدث شيء.
  document.addEventListener("click", function (e) {
    const btn = e.target.closest("[data-say]");
    if (!btn) return;
    const text = btn.getAttribute("data-say") || "";
    if (!text.trim()) return;
    if (!SoundEngine.isVoiceEnabled()) {
      const wrap = document.getElementById("toastWrap");
      if (wrap) {
        const el = document.createElement("div"); el.className = "toast";
        el.textContent = "الصوت مغلق — اضغط 🔈 في الأعلى لتشغيله";
        wrap.appendChild(el); setTimeout(() => el.remove(), 3500);
      }
      return;
    }
    btn.classList.add("is-speaking");
    const done = () => btn.classList.remove("is-speaking");
    if (window.Companion) Companion.readAloud(text).then(done);
    else { SoundEngine.speak(text, window.KIDAURA_ACTIVE_CHARACTER); setTimeout(done, 1200); }
  });
});
