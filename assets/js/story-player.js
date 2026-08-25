/* ============================================================
   StoryPlayer — مشغّل موحّد لكل أنواع القصص (اليومية، الكبرى،
   قصص الأصدقاء، القصص الثقافية): سلايدشو + سرد صوتي + تنزيل
   فيديو حقيقي (Canvas + MediaRecorder) + مشاركة (Web Share API)
   ============================================================ */
const StoryPlayer = (function () {

  function render(story, containerId, opts = {}) {
    const container = document.getElementById(containerId);
    if (!container) return;
    let idx = 0;

    container.innerHTML = `
      ${opts.badge ? `<p style="text-align:center;color:var(--mint);font-weight:800;">${opts.badge}</p>` : ''}
      <div class="story-player">
        <div class="story-scene" id="${containerId}_scene" style="background:linear-gradient(135deg, ${story.scenes[0].grad});">
          ${story.photo ? `<div class="story-photo-badge"><img src="${story.photo}"></div>` : ''}
          ${story.spriteFace ? `<div class="story-sprite">${story.spriteFace}</div>` : ''}
          <div class="story-scene-caption" id="${containerId}_caption">${story.scenes[0].caption}</div>
        </div>
        <div class="story-controls">
          <button class="btn btn-sm btn-ghost" id="${containerId}_prev">◀ السابق</button>
          <span class="grow" id="${containerId}_counter">1 / ${story.scenes.length}</span>
          <button class="btn btn-sm btn-ghost" id="${containerId}_next">التالي ▶</button>
          <button class="btn btn-sm btn-mint" id="${containerId}_narrate">🔊 اسمع القصة</button>
        </div>
      </div>
      <div class="story-actions">
        <button class="btn btn-primary btn-sm" id="${containerId}_download">⬇️ تنزيل كفيديو</button>
        <button class="btn btn-ghost btn-sm" id="${containerId}_share">🔗 مشاركة</button>
      </div>`;

    function paint() {
      const s = story.scenes[idx];
      document.getElementById(`${containerId}_scene`).style.background = `linear-gradient(135deg, ${s.grad})`;
      document.getElementById(`${containerId}_caption`).textContent = s.caption;
      document.getElementById(`${containerId}_counter`).textContent = `${idx + 1} / ${story.scenes.length}`;
    }
    document.getElementById(`${containerId}_prev`).onclick = () => { idx = Math.max(0, idx - 1); paint(); };
    document.getElementById(`${containerId}_next`).onclick = () => { idx = Math.min(story.scenes.length - 1, idx + 1); paint(); };
    document.getElementById(`${containerId}_narrate`).onclick = () => narrate(story);
    document.getElementById(`${containerId}_download`).onclick = () => exportVideo(story);
    document.getElementById(`${containerId}_share`).onclick = () => share(story);
  }

  function narrate(story) {
    const full = story.scenes.map(s => s.caption).join(". ");
    SoundEngine.speak(full, window.KIDAURA_ACTIVE_CHARACTER);
  }

  function share(story) {
    const text = `شاهدوا قصة "${story.title || 'مغامرة'}" على Kidora! 🎬✨`;
    if (navigator.share) { navigator.share({ title: story.title || "قصة Kidora", text }).catch(() => {}); }
    else { navigator.clipboard?.writeText(text); alert('تم نسخ نص المشاركة 📋'); }
  }

  function wrapText(ctx, text, x, y, maxWidth, lineHeight) {
    const words = text.split(" ");
    let line = "", lines = [];
    words.forEach(w => {
      const test = line + w + " ";
      if (ctx.measureText(test).width > maxWidth && line) { lines.push(line); line = w + " "; }
      else line = test;
    });
    lines.push(line);
    const startY = y - (lines.length - 1) * lineHeight / 2;
    lines.forEach((l, i) => ctx.fillText(l.trim(), x, startY + i * lineHeight));
  }

  function exportVideo(story) {
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
    const perScene = 2200;

    function drawScene(s) {
      const [c1, c2] = s.grad.split(",");
      const grad = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
      grad.addColorStop(0, c1); grad.addColorStop(1, c2 || c1);
      ctx.fillStyle = grad; ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.fillStyle = "rgba(0,0,0,.28)";
      ctx.fillRect(0, canvas.height - 120, canvas.width, 120);
      ctx.fillStyle = "#fff";
      ctx.font = "600 22px Cairo, sans-serif";
      ctx.textAlign = "center";
      wrapText(ctx, s.caption, canvas.width / 2, canvas.height - 60, canvas.width - 80, 30);
      ctx.font = "800 16px Baloo Bhaijaan 2, sans-serif";
      ctx.fillStyle = "#FFC93C";
      ctx.fillText("Kidora ✨", canvas.width / 2, 40);
    }

    recorder.onstop = () => {
      const blob = new Blob(chunks, { type: recorder.mimeType || "video/webm" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url; a.download = `${(story.title || 'kidaura-story').replace(/\s+/g,'_')}.${outExt}`;
      document.body.appendChild(a); a.click(); a.remove();
    };
    recorder.start();
    let i = 0;
    (function next() {
      if (i >= story.scenes.length) { recorder.stop(); return; }
      drawScene(story.scenes[i]); i++;
      setTimeout(next, perScene);
    })();
  }

  return { render, narrate, share, exportVideo };
})();
