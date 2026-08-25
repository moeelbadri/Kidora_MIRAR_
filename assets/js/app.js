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

  // ---------- زر الصوت (منفصل عن الموسيقى) ----------
  const voiceBtn = document.getElementById("voiceToggle");
  if (voiceBtn) {
    const refreshVoiceBtn = () => {
      const on = SoundEngine.isVoiceEnabled();
      voiceBtn.classList.toggle("on", on);
      voiceBtn.textContent = on ? "🗣️" : "🔈";
    };
    refreshVoiceBtn();
    voiceBtn.addEventListener("click", () => {
      SoundEngine.setVoiceEnabled(!SoundEngine.isVoiceEnabled());
      refreshVoiceBtn();
    });
  }

  // ---------- زر الموسيقى ----------
  const musicBtn = document.getElementById("musicToggle");
  if (musicBtn) {
    const refreshMusicBtn = () => {
      const on = SoundEngine.isMusicEnabled();
      musicBtn.classList.toggle("on", on);
      musicBtn.textContent = on ? "🔊" : "🔇";
    };
    refreshMusicBtn();
    if (SoundEngine.isMusicEnabled()) SoundEngine.startMusic();
    musicBtn.addEventListener("click", () => {
      SoundEngine.setMusicEnabled(!SoundEngine.isMusicEnabled());
      refreshMusicBtn();
    });
  }

  // ---------- الرفيق الدائم ----------
  const bubble = document.getElementById("companionBubble");
  const avatar = document.getElementById("companionAvatar");
  const swapBtn = document.getElementById("companionSwapBtn");
  let companionTimeout = null;

  window.companionSay = function (text) {
    if (bubble) {
      bubble.textContent = text;
      bubble.style.display = "block";
      clearTimeout(companionTimeout);
      companionTimeout = setTimeout(() => { bubble.style.display = "none"; }, 5200);
    }
    if (window.KIDAURA_ACTIVE_CHARACTER) SoundEngine.speak(text, window.KIDAURA_ACTIVE_CHARACTER);
  };

  if (avatar) {
    avatar.addEventListener("click", () => {
      window.companionSay(window.KIDAURA_LAST_LINE || (window.KIDAURA_ACTIVE_CHARACTER ? window.KIDAURA_ACTIVE_CHARACTER.name + "! أنا معك دايماً 💛" : "أنا معك دايماً!"));
    });
  }

  if (swapBtn) {
    swapBtn.addEventListener("click", () => {
      fetch(window.KIDAURA_BASE + "/api/swap-companion.php", { method: "POST" })
        .then(r => r.json())
        .then(data => {
          if (data.ok) location.reload();
        });
    });
  }

  // رسالة ترحيب تلقائية من الرفيق عند دخول أي صفحة (إن حُدّدت عبر PHP)
  if (window.KIDAURA_PAGE_LINE) {
    setTimeout(() => window.companionSay(window.KIDAURA_PAGE_LINE), 500);
  }
});
