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

  function updateBackgroundColor(color) {
    const el = document.getElementById("bgGradient");
    if (!el) return;
    const c1 = color, c2 = shadeColor(color, 35), c3 = shadeColor(color, -35);
    el.style.background = `linear-gradient(-45deg, ${c1}, ${c2}, ${c3}, ${c1})`;
    el.style.backgroundSize = "400% 400%";
    el.style.animation = "none";
    setTimeout(() => { el.style.animation = "gradientMove 12s ease infinite"; }, 50);
    document.documentElement.style.setProperty("--theme-accent", color);
    document.documentElement.style.setProperty("--theme-glow", c2);
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
