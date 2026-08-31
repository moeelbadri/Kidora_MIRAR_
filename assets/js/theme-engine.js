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

  function applyBackground(charData) {
    if (!charData) return;
    updateBackgroundColor(charData.color || "#6C63FF");
    updateFloatingIcons(charData.icons || [], charData.move || "wiggle");
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

  return { applyBackground, previewCharacter, updateBackgroundColor, updateFloatingIcons, shadeColor };
})();
