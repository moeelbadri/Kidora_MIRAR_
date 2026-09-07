/* تجربة الديمو العامة — ثلاث مراحل قصيرة بصوت الشخصية المختارة */
(function () {
  "use strict";

  const data = window.KIDORA_DEMO || { characters: [], stories: {}, guide: {} };
  const $ = id => document.getElementById(id);
  const reduced = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const hasGsap = typeof window.gsap !== "undefined";
  let selected = data.characters.find(char => char.slug === data.selectedSlug) || data.characters[0];
  let childName = "";
  let memoryDone = false;
  let storyRuntime = null;

  function safeColor(value, fallback) {
    return /^#[0-9a-f]{3,8}$/i.test(String(value || "")) ? String(value) : fallback;
  }

  function safeGradient(value) {
    const parts = String(value || "").split(",").map(part => part.trim());
    if (parts.length === 2 && parts.every(part => /^#[0-9a-f]{3,8}$/i.test(part))) return `linear-gradient(135deg,${parts[0]},${parts[1]})`;
    return "linear-gradient(135deg,#6c63ff,#241645)";
  }

  function speak(text, onEnd) {
    if (!window.SoundEngine || !selected) {
      if (typeof onEnd === "function") onEnd();
      return false;
    }
    return SoundEngine.speak(String(text || ""), selected, { onEnd });
  }

  function setAvatar(id, char) {
    const host = $(id);
    if (!host || !char) return;
    host.style.setProperty("--guide-color", safeColor(char.color, "#6c63ff"));
    host.replaceChildren();
    if (char.image) {
      const image = document.createElement("img");
      image.src = char.image;
      image.alt = char.name || "";
      host.appendChild(image);
    } else host.textContent = (char.icons && char.icons[0]) || "✨";
  }

  function updateGuide(message) {
    if (!selected) return;
    ["guideAvatar", "pickGuideAvatar", "memoryGuideAvatar", "nameGuideAvatar", "storyGuideAvatar"].forEach(id => setAvatar(id, selected));
    ["guideName", "pickGuideName", "memoryGuideName", "nameGuideName", "storyGuideName"].forEach(id => {
      const element = $(id);
      if (element) element.textContent = selected.name || "رفيقك";
    });
    if (message !== undefined) {
      const bubble = $("guideBubble");
      if (bubble) bubble.textContent = message;
      const storyBubble = $("storyGuideBubble");
      if (storyBubble) storyBubble.textContent = message;
    }
  }

  function chooseCharacter(char) {
    if (!char) return;
    selected = char;
    updateGuide();
    if (window.ThemeEngine) ThemeEngine.previewCharacter(char);
  }

  function go(stage) {
    const stages = ["demoWelcome", "demoPick", "demoMemory", "demoName", "demoStory", "demoFinale"];
    stages.forEach(id => {
      const element = $(id);
      if (element) element.hidden = id !== stage;
    });
    const progressIndex = { demoWelcome: 0, demoPick: 0, demoMemory: 1, demoName: 1, demoStory: 2, demoFinale: 2 }[stage] || 0;
    document.querySelectorAll("#demoProgress span").forEach((segment, index) => segment.classList.toggle("active", index <= progressIndex));
    const current = $(stage);
    if (current && hasGsap && !reduced) gsap.fromTo(current, { opacity: 0, y: 18 }, { opacity: 1, y: 0, duration: .45, ease: "power3.out" });
    current?.scrollIntoView({ behavior: reduced ? "auto" : "smooth", block: "center" });
  }

  function renderSelectedCard() {
    const host = $("memorySelectedCard");
    if (!host || !selected) return;
    host.style.setProperty("--selected-color", safeColor(selected.color, "#6c63ff"));
    host.replaceChildren();
    const visual = document.createElement("div");
    visual.className = "demo-selected-media";
    if (selected.image) {
      const image = document.createElement("img");
      image.src = selected.image;
      image.alt = selected.name || "";
      visual.appendChild(image);
    } else visual.textContent = (selected.icons && selected.icons[0]) || "✨";
    const name = document.createElement("strong");
    name.textContent = selected.name || "رفيقك";
    host.append(visual, name);
  }

  function renderPick() {
    const grid = $("demoCharacterGrid");
    if (!grid) return;
    grid.replaceChildren();
    data.characters.forEach(char => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "demo-character";
      button.dataset.slug = char.slug || "";
      button.style.setProperty("--char-color", safeColor(char.color, "#6c63ff"));
      button.classList.toggle("selected", selected && selected.slug === char.slug);
      if (char.is_premium) {
        const badge = document.createElement("span");
        badge.className = "demo-premium";
        badge.textContent = "مدفوعة 🔒";
        button.appendChild(badge);
      }
      const media = document.createElement("span");
      media.className = "demo-character-media";
      if (char.image) {
        const image = document.createElement("img");
        image.src = char.image;
        image.alt = char.name || "";
        image.loading = "lazy";
        media.appendChild(image);
      } else media.textContent = (char.icons && char.icons[0]) || "✨";
      const name = document.createElement("strong");
      name.className = "demo-character-name";
      name.textContent = char.name || "";
      const title = document.createElement("small");
      title.className = "demo-character-title";
      title.textContent = char.title || "";
      button.append(media, name, title);
      button.addEventListener("click", () => {
        chooseCharacter(char);
        grid.querySelectorAll(".demo-character").forEach(item => item.classList.toggle("selected", item === button));
        if (window.SoundEngine) SoundEngine.sfx("pop");
        const line = char.quote || `أنا ${char.name || "رفيقك"}، جاهز للمغامرة!`;
        updateGuide(line);
        speak(line);
        if (hasGsap && !reduced) gsap.fromTo(button, { scale: .88 }, { scale: 1, duration: .55, ease: "back.out(2)" });
        window.setTimeout(() => {
          renderSelectedCard();
          buildMemory();
          go("demoMemory");
        }, reduced ? 150 : 650);
      });
      grid.appendChild(button);
    });
  }

  function createParticles() {
    const host = $("demoParticles");
    if (!host || reduced) return;
    host.replaceChildren();
    for (let i = 0; i < 30; i++) {
      const particle = document.createElement("span");
      particle.className = "demo-particle";
      particle.textContent = i % 3 === 0 ? "🌟" : "⭐";
      particle.style.left = `${35 + Math.random() * 30}%`;
      particle.style.top = `${45 + Math.random() * 15}%`;
      particle.style.setProperty("--dx", `${Math.round(Math.random() * 360 - 180)}px`);
      particle.style.setProperty("--dy", `${Math.round(Math.random() * -420 - 80)}px`);
      particle.style.animationDelay = `${Math.random() * .35}s`;
      host.appendChild(particle);
    }
    window.setTimeout(() => host.replaceChildren(), 1900);
  }

  function buildMemory() {
    const grid = $("memoryGrid");
    if (!grid || !selected) return;
    memoryDone = false;
    grid.replaceChildren();
    const icons = [];
    (selected.icons || []).forEach(icon => {
      if (typeof icon !== "string" || icons.includes(icon) || icon === "⭐") return;
      icons.push(icon);
    });
    ["✨", "🌈", "🚀", "💛", "🎈", "🌟"].forEach(icon => {
      if (icons.length < 4 && !icons.includes(icon)) icons.push(icon);
    });
    const deck = icons.slice(0, 4).concat(icons.slice(0, 4)).sort(() => Math.random() - .5);
    let opened = [];
    let matched = 0;
    let moves = 0;
    let locked = false;
    const score = $("memoryScore");
    const moveLabel = $("memoryMoves");
    const message = $("memoryMessage");
    if (score) score.textContent = "0";
    if (moveLabel) moveLabel.textContent = "0";
    if (message) message.textContent = "اقلب بطاقتين متشابهتين.";

    deck.forEach(icon => {
      const card = document.createElement("button");
      card.type = "button";
      card.className = "demo-memory-card";
      card.dataset.icon = icon;
      const inner = document.createElement("span");
      inner.className = "demo-memory-inner";
      const front = document.createElement("span");
      front.className = "demo-memory-face demo-memory-front";
      front.textContent = "؟";
      const back = document.createElement("span");
      back.className = "demo-memory-face demo-memory-back";
      back.style.setProperty("--memory-color", safeColor(selected.color, "#6c63ff"));
      back.textContent = icon;
      inner.append(front, back);
      card.appendChild(inner);
      card.addEventListener("click", () => {
        if (locked || card.classList.contains("is-open") || card.classList.contains("is-matched")) return;
        card.classList.add("is-open");
        opened.push(card);
        if (window.SoundEngine) SoundEngine.sfx("flip");
        if (opened.length !== 2) return;
        locked = true;
        moves++;
        if (moveLabel) moveLabel.textContent = String(moves);
        const pair = opened.slice();
        window.setTimeout(() => {
          const isMatch = pair[0].dataset.icon === pair[1].dataset.icon;
          if (isMatch) {
            pair.forEach(item => item.classList.add("is-matched"));
            matched++;
            if (score) score.textContent = String(matched);
            if (window.SoundEngine) SoundEngine.sfx("match");
            updateGuide("زوج رائع! ذاكرتك لامعة ✨");
            speak("زوج رائع! ذاكرتك لامعة");
            if (matched === 4) {
              memoryDone = true;
              if (message) message.textContent = "أكملت الأزواج الأربعة! 🌟";
              if (window.SoundEngine) SoundEngine.sfx("win");
              createParticles();
              window.setTimeout(() => go("demoName"), reduced ? 100 : 900);
            }
          } else {
            pair.forEach(item => item.classList.remove("is-open"));
            if (window.SoundEngine) SoundEngine.sfx("miss");
            if (message) message.textContent = "محاولة جميلة، ابحث عن الزوج التالي.";
          }
          opened = [];
          locked = false;
        }, reduced ? 350 : 650);
      });
      grid.appendChild(card);
    });
  }

  function replaceName(value) {
    return String(value || "").replaceAll("الاسم", childName);
  }

  function clearNarration() {
    if (!storyRuntime) return;
    storyRuntime.token++;
    if (storyRuntime.timer) window.clearTimeout(storyRuntime.timer);
    storyRuntime.timer = null;
    if ("speechSynthesis" in window) window.speechSynthesis.cancel();
  }

  function showFinale() {
    clearNarration();
    const register = $("demoRegister");
    const finaleText = $("finaleText");
    if (data.loggedIn) {
      if (register) {
        register.href = `${data.base || ""}/dashboard.php`;
        register.textContent = "🚀 إلى لوحتي";
      }
      if (finaleText) finaleText.textContent = `أحسنت يا ${childName}! عد إلى لوحتك لتكمل المهام والقصة اليومية.`;
    } else {
      if (register) register.href = `${data.base || ""}/index.php?register=1&name=${encodeURIComponent(childName)}&char=${encodeURIComponent(selected.id)}#auth`;
      if (finaleText) finaleText.textContent = `قصة ${childName} بدأت هنا. سجّل الآن ليصبح لكل يوم مهمة ورفيق وقصة جديدة.`;
    }
    go("demoFinale");
  }

  function renderStory() {
    const host = $("demoStoryPlayer");
    const rawStory = selected && data.stories[selected.slug];
    if (!host || !rawStory || !rawStory.scenes || !rawStory.scenes.length) return;
    clearNarration();
    const story = {
      title: replaceName(rawStory.title),
      scenes: rawStory.scenes.map(scene => ({
        icon: replaceName(scene.icon),
        title: replaceName(scene.title),
        caption: replaceName(scene.caption),
        grad: scene.grad,
      })),
    };
    const player = document.createElement("div");
    player.className = "demo-story-player";
    const scene = document.createElement("div");
    scene.className = "demo-story-scene";
    const sprite = document.createElement("div");
    sprite.className = "demo-story-sprite";
    sprite.style.setProperty("--story-color", safeColor(selected.color, "#6c63ff"));
    if (selected.image) {
      const image = document.createElement("img");
      image.src = selected.image;
      image.alt = selected.name || "";
      sprite.appendChild(image);
    } else sprite.textContent = (selected.icons && selected.icons[0]) || "✨";
    const chapter = document.createElement("div");
    chapter.className = "demo-story-chapter";
    const chapterIcon = document.createElement("span");
    chapterIcon.className = "demo-story-chapter-icon";
    const chapterTitle = document.createElement("span");
    const caption = document.createElement("div");
    caption.className = "demo-story-caption";
    scene.append(sprite, chapter, caption);
    const controls = document.createElement("div");
    controls.className = "demo-story-controls";
    const pause = document.createElement("button");
    pause.type = "button";
    const listen = document.createElement("button");
    listen.type = "button";
    listen.textContent = "🔊 اسمع الآن";
    const nextButton = document.createElement("button");
    nextButton.type = "button";
    controls.append(pause, listen, nextButton);
    player.append(scene, controls);
    host.replaceChildren(player);

    storyRuntime = { story, index: 0, playing: true, timer: null, token: 0, caption, chapter, scene, pause, listen };
    const paint = withFade => {
      const current = storyRuntime.story.scenes[storyRuntime.index];
      const apply = () => {
        storyRuntime.scene.style.background = safeGradient(current.grad);
        chapterIcon.textContent = current.icon || "✨";
        chapterTitle.textContent = current.title || "";
        chapter.replaceChildren(chapterIcon, chapterTitle);
        caption.textContent = current.caption;
        caption.classList.remove("is-out");
        nextButton.textContent = storyRuntime.index >= storyRuntime.story.scenes.length - 1
          ? "🏁 إنهاء القصة"
          : "التالي ▶";
      };
      if (withFade && !reduced) {
        caption.classList.add("is-out");
        window.setTimeout(apply, 230);
      } else apply();
    };
    const next = () => {
      if (!storyRuntime || !storyRuntime.playing) return;
      if (storyRuntime.index >= storyRuntime.story.scenes.length - 1) return showFinale();
      storyRuntime.index++;
      paint(true);
      window.setTimeout(narrateCurrent, reduced ? 180 : 300);
    };
    const narrateCurrent = () => {
      if (!storyRuntime || !storyRuntime.playing) return;
      const token = ++storyRuntime.token;
      const current = storyRuntime.story.scenes[storyRuntime.index];
      let finished = false;
      const finish = () => {
        if (finished || !storyRuntime || storyRuntime.token !== token || !storyRuntime.playing) return;
        finished = true;
        if (storyRuntime.timer) window.clearTimeout(storyRuntime.timer);
        storyRuntime.timer = null;
        next();
      };
      const canUseSpeech = !!(window.SoundEngine && SoundEngine.isVoiceEnabled() && ("speechSynthesis" in window) && ("SpeechSynthesisUtterance" in window));
      const spoke = canUseSpeech ? speak(current.caption, finish) : false;
      storyRuntime.timer = window.setTimeout(finish, spoke ? Math.max(5200, current.caption.length * 75) : 4500);
    };
    pause.textContent = "⏸ إيقاف";
    pause.addEventListener("click", () => {
      if (!storyRuntime) return;
      if (storyRuntime.playing) {
        storyRuntime.playing = false;
        clearNarration();
        pause.textContent = "▶ متابعة";
      } else {
        storyRuntime.playing = true;
        pause.textContent = "⏸ إيقاف";
        narrateCurrent();
      }
    });
    nextButton.addEventListener("click", () => {
      if (!storyRuntime) return;
      storyRuntime.playing = true;
      clearNarration();
      if (storyRuntime.index >= storyRuntime.story.scenes.length - 1) return showFinale();
      storyRuntime.index++;
      paint(true);
      window.setTimeout(narrateCurrent, reduced ? 0 : 260);
    });
    listen.addEventListener("click", () => {
      if (!storyRuntime) return;
      if (!window.SoundEngine || !SoundEngine.isVoiceEnabled()) {
        updateGuide("الصوت مغلق — اضغط زر 🗣️ في الأعلى لتشغيله.");
        return;
      }
      storyRuntime.playing = true;
      clearNarration();
      narrateCurrent();
    });
    paint(false);
    window.setTimeout(narrateCurrent, reduced ? 100 : 450);
  }

  function setup() {
    if (!selected) return;
    updateGuide();
    renderPick();
    if (window.ThemeEngine) ThemeEngine.previewCharacter(selected);

    $("demoStart")?.addEventListener("click", () => {
      if (window.SoundEngine) SoundEngine.sfx("pop");
      updateGuide("أحسنت! اختر الشخصية الأقرب إلى خيالك.");
      speak(data.lines?.welcome || "أهلاً بك في Kidora!");
      go("demoPick");
    });
    $("demoNameForm")?.addEventListener("submit", event => {
      event.preventDefault();
      const input = $("demoChildName");
      const error = $("demoNameError");
      const value = (input?.value || "").trim();
      if (!value || value.length > 30) {
        if (error) error.textContent = "اكتب اسماً قصيراً من حرف واحد إلى 30 حرفاً.";
        return;
      }
      if (error) error.textContent = "";
      childName = value;
      updateGuide(`هذه قصة ${childName}، بصوت ${selected.name}.`);
      go("demoStory");
      renderStory();
    });
    $("demoTryAgain")?.addEventListener("click", () => {
      clearNarration();
      memoryDone = false;
      renderPick();
      go("demoPick");
    });
  }

  document.addEventListener("DOMContentLoaded", setup);
})();
