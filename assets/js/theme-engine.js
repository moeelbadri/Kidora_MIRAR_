/* ============================================================
   ThemeEngine — خلفية حيّة تتفاعل بالكامل مع الشخصية النشطة:
   لون + توهّج + أيقونات عائمة بحركة مختلفة فعلياً لكل شخصية
   (اهتزاز/قفز/اندفاع/تحليق/وثب/دبدبة) — تعمل بنفس الروح في
   كل صفحات المنصة، وليست حصراً على صفحة الدخول
   ============================================================ */
const ThemeEngine = (function () {

  const MOVE_ANIM = {
    wiggle: "floatUp",   bounce: "floatBounce", dash: "floatDash",
    float:  "floatDrift", hop:    "floatHop",     stomp: "floatStomp"
  };
  const MOVE_SPEED = { // ثانية تقريبية لعبور الشاشة — حركة كل شخصية بإيقاع مختلف
    wiggle: [11, 20], bounce: [8, 14], dash: [5, 9],
    float: [14, 24], hop: [9, 15], stomp: [10, 16]
  };

  function shadeColor(hex, percent) {
    try {
      let f = parseInt(hex.slice(1), 16), t = percent < 0 ? 0 : 255, p = percent < 0 ? percent * -1 : percent,
        R = f >> 16, G = f >> 8 & 0x00FF, B = f & 0x0000FF;
      return "#" + (0x1000000 + (Math.round((t - R) * p / 100) + R) * 0x10000 +
        (Math.round((t - G) * p / 100) + G) * 0x100 + (Math.round((t - B) * p / 100) + B)).toString(16).slice(1);
    } catch (e) { return hex; }
  }

  // سقف إضاءة ستوبات الخلفية. النصوص في كل المنصة فاتحة (#fff / #D9D0FF)،
  // فلو صارت الخلفية أفتح من هذا الحد يختفي النص أثناء حركة التدرّج.
  const MAX_BG_LUMINANCE = 0.075;

  function toRgb(hex) {
    const f = parseInt(hex.slice(1), 16);
    return [f >> 16 & 0xFF, f >> 8 & 0xFF, f & 0xFF];
  }

  function toHex(rgb) {
    return "#" + rgb.map(v => Math.max(0, Math.min(255, Math.round(v))).toString(16).padStart(2, "0")).join("");
  }

  /** الإضاءة النسبية حسب WCAG — الأصفر أعلى إضاءة من البنفسجي بنفس التشبّع */
  function luminance(rgb) {
    const ch = rgb.map(v => {
      v /= 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * ch[0] + 0.7152 * ch[1] + 0.0722 * ch[2];
  }

  /** يعتّم اللون حتى ينزل تحت سقف الإضاءة، مع الحفاظ على درجته اللونية */
  function darkenToLuminance(hex, max) {
    try {
      let rgb = toRgb(hex);
      for (let i = 0; i < 80 && luminance(rgb) > max; i++) rgb = rgb.map(v => v * 0.97);
      return toHex(rgb);
    } catch (e) { return hex; }
  }

  function updateBackgroundColor(color) {
    const el = document.getElementById("bgGradient");
    if (!el) return;
    // أفتح ستوب يُحسب أولاً ثم يُعتَّم تحت السقف، والباقي أغمق منه —
    // فتبقى الخلفية غامقة بلون الشخصية أياً كان لونها (أصفر/تركواز/بنفسجي).
    const top = darkenToLuminance(shadeColor(color, 18), MAX_BG_LUMINANCE);
    const mid = shadeColor(top, -28);
    const deep = shadeColor(top, -52);
    el.style.backgroundImage = `linear-gradient(-45deg, ${mid}, ${top}, ${deep}, ${mid})`;
    el.style.backgroundSize = "400% 400%";
    el.style.animation = "none";
    setTimeout(() => { el.style.animation = "gradientMove 12s ease infinite"; }, 50);
    // التوهّج والأكسنت يحتفظان باللون الأصلي الزاهي (يستخدمهما الأفاتار
    // وبطاقة الترحيب)، والحماية من فقدان التباين تأتي من طبقة #animated-bg::after
    document.documentElement.style.setProperty("--theme-accent", color);
    document.documentElement.style.setProperty("--theme-glow", shadeColor(color, 35));
  }

  function updateFloatingIcons(icons, move) {
    const container = document.getElementById("floating-icons");
    if (!container) return;
    container.innerHTML = "";
    const list = (icons && icons.length) ? icons : ["✨", "⭐", "🌟"];
    const animName = MOVE_ANIM[move] || "floatUp";
    const speedRange = MOVE_SPEED[move] || [11, 20];
    const count = 20 + Math.floor(Math.random() * 10);
    for (let i = 0; i < count; i++) {
      const span = document.createElement("span");
      span.className = "floating-icon";
      span.textContent = list[i % list.length];
      const dur = speedRange[0] + Math.random() * (speedRange[1] - speedRange[0]);
      span.style.left = Math.random() * 96 + "%";
      span.style.setProperty("--drift", (Math.random() * 120 - 60) + "px");
      span.style.animation = `${animName} ${dur}s linear infinite`;
      span.style.animationDelay = (Math.random() * speedRange[1]) + "s";
      span.style.fontSize = (18 + Math.random() * 38) + "px";
      span.style.opacity = 0.35 + Math.random() * 0.5;
      span.style.filter = Math.random() > 0.6 ? "blur(1px)" : "none";
      container.appendChild(span);
    }
  }

  // زخرفة عالم الشخصية: طبقة إضافية فوق الأيقونات العائمة تُحدَّد بـ theme.motif.
  // كلها شفافة ومعتمة جزئياً وتحت طبقة #animated-bg::after، فلا تمسّ التباين.
  const MOTIFS = {
    bubbles:  { glyphs: ["○", "○", "◌"], anim: "motifRise",  dur: [14, 26], size: [10, 42], opacity: .35 },
    leaves:   { glyphs: ["🍃", "🌿", "🍀"], anim: "motifFall",  dur: [16, 28], size: [16, 30], opacity: .45 },
    confetti: { glyphs: ["▮", "●", "▲", "■"], anim: "motifFall", dur: [9, 18], size: [8, 16], opacity: .55, colorful: true },
    stars:    { glyphs: ["✦", "✧", "·"], anim: "motifTwinkle", dur: [3, 7], size: [8, 22], opacity: .6 },
    webs:     { glyphs: ["🕸️"], anim: "motifTwinkle", dur: [6, 10], size: [40, 90], opacity: .18, corners: true },
    bats:     { glyphs: ["🦇"], anim: "motifFly",   dur: [18, 30], size: [16, 30], opacity: .5 },
    clues:    { glyphs: ["🔍", "❔", "👣"], anim: "motifTwinkle", dur: [5, 9], size: [14, 26], opacity: .35 },
  };
  const CONFETTI_COLORS = ["#FF6B6B", "#4ECDC4", "#FFE66D", "#A8E6CF", "#FF8A5C", "#6C5CE7", "#FD79A8"];

  function updateMotif(motif, color) {
    const bg = document.getElementById("animated-bg");
    if (!bg) return;
    let layer = document.getElementById("theme-motif");
    if (!layer) {
      layer = document.createElement("div");
      layer.id = "theme-motif"; layer.className = "theme-motif-layer";
      const icons = document.getElementById("floating-icons");
      if (icons && icons.parentNode === bg) bg.insertBefore(layer, icons.nextSibling); else bg.appendChild(layer);
    }
    layer.innerHTML = "";
    const m = MOTIFS[motif] || MOTIFS.stars;
    const count = m.corners ? 4 : 14 + Math.floor(Math.random() * 8);
    for (let i = 0; i < count; i++) {
      const s = document.createElement("span");
      s.textContent = m.glyphs[i % m.glyphs.length];
      const dur = m.dur[0] + Math.random() * (m.dur[1] - m.dur[0]);
      if (m.corners) {
        s.style.left = (i % 2 ? 88 : 2) + "%"; s.style.top = (i < 2 ? 8 : 78) + "%";
        s.style.transform = `rotate(${(i * 90) % 360}deg)`;
      } else {
        s.style.left = Math.random() * 96 + "%";
        s.style.top = m.anim === "motifTwinkle" ? (Math.random() * 90 + "%") : "";
      }
      s.style.fontSize = (m.size[0] + Math.random() * (m.size[1] - m.size[0])) + "px";
      s.style.opacity = m.opacity;
      s.style.color = m.colorful ? CONFETTI_COLORS[i % CONFETTI_COLORS.length] : shadeColor(color, 45);
      s.style.setProperty("--drift", (Math.random() * 160 - 80) + "px");
      s.style.animation = `${m.anim} ${dur}s ${m.anim === "motifTwinkle" ? "ease-in-out" : "linear"} infinite`;
      s.style.animationDelay = (-Math.random() * dur) + "s";
      layer.appendChild(s);
    }
    document.documentElement.setAttribute("data-motif", motif || "stars");
  }

  function applyBackground(charData) {
    if (!charData) return;
    updateBackgroundColor(charData.color || "#6C63FF");
    updateFloatingIcons(charData.icons || [], charData.move || "wiggle");
    updateMotif((charData.theme && charData.theme.motif) || "stars", charData.color || "#6C63FF");
  }

  /** معاينة فورية عند الـ hover/الاختيار في صفحة تسجيل الدخول (بدون حفظ سيرفر) */
  function previewCharacter(charData) {
    applyBackground(charData);
    const avatar = document.getElementById("companionAvatar");
    if (avatar) {
      avatar.className = "move-" + (charData.move || "wiggle");
      avatar.innerHTML = charData.image
        ? `<img src="${charData.image}">`
        : (charData.icons && charData.icons[0] ? charData.icons[0] : "✨");
    }
  }

  return { applyBackground, previewCharacter, updateBackgroundColor, updateFloatingIcons, updateMotif, shadeColor };
})();
