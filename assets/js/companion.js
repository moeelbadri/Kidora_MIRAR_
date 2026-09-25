/* ============================================================
   Companion — الرفيق المرشد
   ============================================================ */
const Companion = (function () {
  const MOOD_BADGE = { cheer: "🎉", think: "💭", wave: "👋", point: "👉", talk: "" };
  let widget, bubble, avatar, hideTimer = null, moodTimer = null, queue = Promise.resolve();

  function els() {
    widget = widget || document.getElementById("companionWidget");
    bubble = bubble || document.getElementById("companionBubble");
    avatar = avatar || document.getElementById("companionAvatar");
    return !!avatar;
  }
  function char() { return window.KIDAURA_ACTIVE_CHARACTER || null; }
  function theme() { return (char() && char().theme) || {}; }
  function sidekickHtml() {
    const sk = theme().sidekick;
    if (!sk) return "";
    return `<span class="bubble-sidekick" title="${sk.name || ""}">${sk.icon || "⭐"}</span>`;
  }

  function mood(name, ms) {
    if (!els()) return;
    clearTimeout(moodTimer);
    avatar.className = avatar.className.replace(/\bmood-\S+/g, "").trim();
    const old = avatar.querySelector(".mood-badge"); if (old) old.remove();
    if (name) {
      avatar.classList.add("mood-" + name);
      if (MOOD_BADGE[name]) {
        const b = document.createElement("span"); b.className = "mood-badge"; b.textContent = MOOD_BADGE[name];
        avatar.appendChild(b);
      }
      if (ms) moodTimer = setTimeout(() => mood(null), ms);
    }
  }
  function pin(on) { if (els() && widget) widget.classList.toggle("is-pinned", !!on); }

  function showBubble(text, opts) {
    if (!bubble) return;
    bubble.innerHTML = (opts.sidekick ? sidekickHtml() : "") + escapeHtml(text);
    bubble.style.display = "block";
    clearTimeout(hideTimer);
  }
  function hideBubble(delay) {
    clearTimeout(hideTimer);
    hideTimer = setTimeout(() => { if (bubble) bubble.style.display = "none"; }, delay || 0);
  }
  function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c])); }
  function readTime(text) { return Math.min(14000, 1800 + String(text || "").length * 70); }

  /** يقول جملة: فقاعة + مزاج + صوت */
  function say(text, opts) {
    opts = opts || {};
    return new Promise(resolve => {
      if (!text) { resolve(); return; }
      els();
      if (widget) widget.classList.add("is-talking");
      mood(opts.mood || "talk");
      showBubble(text, opts);
      let finished = false;
      const finish = () => {
        if (finished) return; finished = true;
        if (widget) widget.classList.remove("is-talking");
        mood(opts.keepMood ? opts.mood : null);
        hideBubble(opts.hold != null ? opts.hold : 1400);
        if (typeof opts.onEnd === "function") opts.onEnd();
        resolve();
      };
      const spoke = window.SoundEngine && SoundEngine.speak(text, char(), { onEnd: finish, silentChime: opts.silentChime });
      if (!spoke) setTimeout(finish, readTime(text));
    });
  }

  function readAloud(text, opts) {
    opts = Object.assign({ silentChime: true }, opts || {});
    return new Promise(resolve => {
      if (!text) { resolve(); return; }
      els();
      if (widget) widget.classList.add("is-talking");
      mood("talk");
      const finish = () => { if (widget) widget.classList.remove("is-talking"); mood(null); resolve(); };
      const spoke = window.SoundEngine && SoundEngine.speak(text, char(), { onEnd: finish, silentChime: true });
      if (!spoke) setTimeout(finish, Math.min(readTime(text), opts.maxWait || 4000));
    });
  }

  function sequence(lines) {
    queue = queue.then(async () => {
      for (const l of lines || []) {
        const o = typeof l === "string" ? { text: l } : l;
        await say(o.text, o);
        if (o.pause) await new Promise(r => setTimeout(r, o.pause));
      }
    });
    return queue;
  }

  function celebrate(text, opts) {
    if (window.SoundEngine) SoundEngine.sfx("cheer");
    return say(text, Object.assign({ mood: "cheer", keepMood: true, hold: 2000 }, opts || {})).then(() => mood(null));
  }

  function guideTo(url, text, moodName) {
    return say(text, { mood: moodName || "point", keepMood: true, hold: 300 }).then(() => { location.href = url; });
  }

  function stop() {
    if (window.SoundEngine) SoundEngine.stop();
    if (widget) widget.classList.remove("is-talking");
    mood(null); hideBubble(0);
  }

  /* ============================================================
     bind() — الربط مع الصفحة
     ============================================================ */
  function bind() {
    if (!els()) return;

    avatar.addEventListener("click", () => {
      // 🔓 فتح القفل (مهم لـ iOS)
      if (window.SoundEngine && SoundEngine.unlockAudio) SoundEngine.unlockAudio();
      const c = char();
      const t = theme();
      say(window.KIDAURA_LAST_LINE || (c ? `${c.name} معك دائماً من ${t.world || "عالمه"}! 💛` : "أنا معك دائماً!"), { mood: "wave", sidekick: true });
    });

    // رسالة الصفحة من PHP ($__pageLine)
    if (window.KIDAURA_PAGE_LINE && !window.KIDAURA_SILENT_PAGE) {
      window.KIDAURA_LAST_LINE = window.KIDAURA_PAGE_LINE;

      const isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);

      if (isMobile) {
        // 📱 على الجوال: النطق MUST يحدث SYNCHRONOUS داخل أول لمسة
        const start = () => {
          if (window.SoundEngine && SoundEngine.unlockAudio) SoundEngine.unlockAudio();
          // 🎯 say() مباشرة بدون setTimeout — هذا مفتاح iOS
          say(window.KIDAURA_PAGE_LINE, { mood: "wave" });
          document.removeEventListener("touchstart", start);
          document.removeEventListener("click", start);
        };
        document.addEventListener("touchstart", start, { once: true, passive: true });
        document.addEventListener("click", start, { once: true });
      } else {
        // 💻 على اللاب: نطق عادي بعد لحظة
        setTimeout(() => say(window.KIDAURA_PAGE_LINE, { mood: "wave" }), 600);
      }
    }
  }

  // ✅ استدعاء bind() بعد تحميل الصفحة
  document.addEventListener("DOMContentLoaded", bind);

  // توافق مع الصفحات القديمة
  window.companionSay = function (text, opts) { window.KIDAURA_LAST_LINE = text; return say(text, opts); };

  return { say, readAloud, sequence, celebrate, guideTo, mood, pin, stop, sidekick: () => theme().sidekick || null, theme, character: char };
})();

/* ============================================================
   KidoraYT — يوتيوب IFrame API
   ============================================================ */
const KidoraYT = (function () {
  let ready = null, current = null;
  function load() {
    if (ready) return ready;
    ready = new Promise(res => {
      if (window.YT && YT.Player) return res();
      const prev = window.onYouTubeIframeAPIReady;
      window.onYouTubeIframeAPIReady = () => { if (prev) prev(); res(); };
      const s = document.createElement("script"); s.src = "https://www.youtube.com/iframe_api"; document.head.appendChild(s);
      setTimeout(res, 6000);
    });
    return ready;
  }
  function destroy() { if (current) { try { current.destroy(); } catch (e) {} current = null; } }
  function play(hostId, videoId, opts) {
    opts = opts || {};
    return new Promise(async res => {
      await load();
      const host = document.getElementById(hostId);
      if (!host || !(window.YT && YT.Player)) return res();
      let finished = false; const done = () => { if (!finished) { finished = true; res(); } };
      const skip = opts.skipId && document.getElementById(opts.skipId); if (skip) skip.onclick = done;
      destroy();
      current = new YT.Player(hostId, {
        videoId, host: "https://www.youtube-nocookie.com",
        playerVars: { rel: 0, modestbranding: 1, playsinline: 1, autoplay: opts.autoplay ? 1 : 0 },
        events: {
          onReady: e => { if (opts.autoplay) { try { e.target.playVideo(); } catch (err) {} } },
          onStateChange: e => { if (e.data === YT.PlayerState.ENDED) done(); },
          onError: done,
        },
      });
    });
  }
  return { play, destroy, load };
})();
