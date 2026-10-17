/* واجهة الهبوط العامة — الصور، الكروسيل، المودال، والنموذج */
(function () {
  "use strict";

  const data = window.KIDORA_LANDING || { base: "", characters: [] };
  const $ = (selector, root) => (root || document).querySelector(selector);
  const $$ = (selector, root) => Array.from((root || document).querySelectorAll(selector));
  const reduced = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const hasGsap = typeof window.gsap !== "undefined";

  function toast(message) {
    const wrap = $("#toastWrap");
    if (!wrap) return;
    const item = document.createElement("div");
    item.className = "toast";
    item.textContent = message;
    wrap.appendChild(item);
    window.setTimeout(() => item.remove(), 3200);
  }

  function setupLazyImages() {
    const images = $$("img[data-src]");
    const load = image => { image.src = image.dataset.src; image.removeAttribute("data-src"); };
    if (!window.IntersectionObserver) { images.forEach(load); return; }
    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
      if (entry.isIntersecting) { load(entry.target); observer.unobserve(entry.target); }
    }), { rootMargin: "160px" });
    images.forEach(image => observer.observe(image));
  }

  function renderHeroCharacter(char) {
    const hero = $("#heroCharacter");
    if (!hero || !char) return;
    hero.style.setProperty("--hero-color", char.color || "#6c63ff");
    hero.replaceChildren();
    if (char.image) {
      const image = document.createElement("img");
      image.src = char.image;
      image.alt = "";
      hero.appendChild(image);
    } else {
      hero.textContent = (char.icons && char.icons[0]) || "✨";
    }
  }

  function setupHeroRotation() {
    if (reduced || navigator.connection?.saveData || !data.characters || data.characters.length < 2) return;
    let index = 0;
    window.setInterval(() => {
      const hero = $("#heroCharacter");
      if (!hero) return;
      const bounds = hero.getBoundingClientRect();
      if (document.hidden || bounds.top > innerHeight || bounds.bottom < 0) return;
      index = (index + 1) % data.characters.length;
      const next = data.characters[index];
      if (hasGsap && !reduced) {
        gsap.to(hero, { opacity: 0, scale: .88, duration: .22, onComplete: () => {
          renderHeroCharacter(next);
          gsap.to(hero, { opacity: 1, scale: 1, duration: .5, ease: "back.out(1.5)" });
        }});
      } else renderHeroCharacter(next);
    }, 6500);
  }

  function setupCarousel() {
    const windowEl = $("#characterCarousel");
    const track = $("#characterTrack");
    if (!windowEl || !track) return;
    const cards = $$(".public-character-card", track);
    if (!cards.length) return;
    let index = 0;
    let timer = null;
    let paused = false;

    const visible = () => Math.max(1, Math.floor(windowEl.clientWidth / (cards[0].getBoundingClientRect().width + 18)));
    const maxIndex = () => Math.max(0, cards.length - visible());
    const paint = (animate) => {
      index = Math.min(index, maxIndex());
      const step = cards[0].getBoundingClientRect().width + 18;
      const vars = { x: index * step, duration: animate && !reduced ? .55 : 0, ease: "power3.out" };
      if (hasGsap) gsap.to(track, vars);
      else track.style.transform = `translateX(${index * step}px)`;
    };
    const move = amount => {
      index += amount;
      if (index > maxIndex()) index = 0;
      if (index < 0) index = maxIndex();
      paint(true);
    };
    const restart = () => {
      if (timer) window.clearInterval(timer);
      timer = window.setInterval(() => { if (!paused) move(1); }, 4800);
    };

    $("#charPrev")?.addEventListener("click", () => { move(-1); restart(); });
    $("#charNext")?.addEventListener("click", () => { move(1); restart(); });
    ["mouseenter", "focusin", "touchstart"].forEach(eventName => windowEl.addEventListener(eventName, () => { paused = true; }, { passive: true }));
    ["mouseleave", "focusout", "touchend"].forEach(eventName => windowEl.addEventListener(eventName, () => { paused = false; }, { passive: true }));
    window.addEventListener("resize", () => paint(false));
    paint(false);
    restart();
  }

  let lastModalFocus = null;
  let modalChar = null;

  function setModalText(id, text) {
    const element = $("#" + id);
    if (element) element.textContent = text || "";
  }

  function openModal(char) {
    const modal = $("#characterModal");
    const visual = $("#modalVisual");
    if (!modal || !visual || !char) return;
    modalChar = char;
    lastModalFocus = document.activeElement;
    modal.style.setProperty("--modal-color", char.color || "#6c63ff");
    visual.replaceChildren();
    if (char.image) {
      const image = document.createElement("img");
      image.src = char.image;
      image.alt = char.name || "";
      visual.appendChild(image);
    } else visual.textContent = (char.icons && char.icons[0]) || "✨";
    setModalText("modalCharacterName", char.name);
    setModalText("modalCharacterTitle", char.title);
    setModalText("modalCharacterTrait", char.trait ? `صفة الرفيق: ${char.trait}` : "");
    setModalText("modalCharacterQuote", char.quote ? `«${char.quote}»` : "");
    const icons = $("#modalCharacterIcons");
    if (icons) {
      icons.replaceChildren();
      (char.icons || []).slice(0, 7).forEach(icon => {
        const item = document.createElement("span");
        item.textContent = icon;
        icons.appendChild(item);
      });
    }
    const demoLink = $("#modalDemoLink");
    if (demoLink) demoLink.href = `${data.base || ""}/demo.php?char=${encodeURIComponent(char.slug || "")}`;
    const registerLink = $("#modalRegisterLink");
    if (registerLink) registerLink.dataset.registerChar = String(char.id);
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
    if (window.ThemeEngine) ThemeEngine.previewCharacter(char);
    if (hasGsap && !reduced) gsap.fromTo(".public-modal-card", { opacity: 0, y: 24, scale: .93 }, { opacity: 1, y: 0, scale: 1, duration: .4, ease: "back.out(1.5)" });
    $("#modalClose")?.focus();
  }

  function closeModal() {
    const modal = $("#characterModal");
    if (!modal) return;
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
    if (lastModalFocus && typeof lastModalFocus.focus === "function") lastModalFocus.focus();
    lastModalFocus = null;
    modalChar = null;
  }

  function setupModal() {
    $$(".public-character-card").forEach(card => card.addEventListener("click", () => {
      const char = data.characters.find(item => String(item.id) === card.dataset.charId);
      openModal(char);
    }));
    $("#modalClose")?.addEventListener("click", closeModal);
    $("#characterModal")?.addEventListener("click", event => {
      if (event.target.id === "characterModal") closeModal();
    });
    document.addEventListener("keydown", event => {
      const modal = $("#characterModal");
      if (!modal || !modal.classList.contains("open")) return;
      if (event.key === "Escape") return closeModal();
      if (event.key !== "Tab") return;
      const focusable = $$("button,a,input,select,[tabindex]:not([tabindex='-1'])", modal).filter(item => !item.disabled);
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
  }

  function activateAuth(tab) {
    const isRegister = tab === "register";
    $$(".public-auth-tab").forEach(button => {
      const active = button.dataset.authTab === tab;
      button.classList.toggle("active", active);
      button.setAttribute("aria-selected", active ? "true" : "false");
    });
    const login = $("#loginPanel");
    const register = $("#registerPanel");
    if (login) login.hidden = isRegister;
    if (register) register.hidden = !isRegister;
    if (isRegister) window.setTimeout(() => $("#childName")?.focus(), 80);
  }

  // رفيق واحد فقط عند التسجيل — الرفيق المجاني الثاني يُضاف على الخادم
  let picked = [];
  function selectPicked(id) {
    picked = picked.includes(id) ? [] : [id];
    $$(".public-pick").forEach(card => card.classList.toggle("selected", picked.includes(Number(card.dataset.pickId))));
    const first = $("#character_1");
    if (first) first.value = picked[0] || "";
    const char = data.characters.find(item => Number(item.id) === id);
    if (char && window.ThemeEngine) ThemeEngine.previewCharacter(char);
  }

  function openRegister(charId) {
    closeModal();
    activateAuth("register");
    if (charId) {
      const char = data.characters.find(item => Number(item.id) === Number(charId));
      if (char && !char.is_premium) {
        picked = picked.filter(id => id !== Number(charId));
        selectPicked(Number(charId));
      } else if (char && char.is_premium) {
        toast("هذه الشخصية متاحة للتجربة، ثم تُفتح بعد تفعيل اشتراك مدفوع.");
      }
    }
    $("#auth")?.scrollIntoView({ behavior: reduced ? "auto" : "smooth", block: "start" });
  }

  function setupAuth() {
    const first = Number($("#character_1")?.value || 0);
    picked = [first].filter(Boolean);
    $$(".public-pick").forEach(card => card.classList.toggle("selected", picked.includes(Number(card.dataset.pickId))));
    $$(".public-auth-tab").forEach(button => button.addEventListener("click", () => activateAuth(button.dataset.authTab)));
    document.addEventListener("click", event => {
      const trigger = event.target.closest("[data-open-register]");
      if (!trigger) return;
      event.preventDefault();
      openRegister(Number(trigger.dataset.registerChar || 0));
    });
    $$(".public-pick").forEach(card => card.addEventListener("click", () => {
      if (card.dataset.locked === "1") {
        toast("الشخصيات المدفوعة تُجرّب في الديمو وتُفتح بعد الترقية.");
        return;
      }
      selectPicked(Number(card.dataset.pickId));
    }));
    const form = $("#registerForm");
    form?.addEventListener("submit", event => {
      if (picked.length !== 1) {
        event.preventDefault();
        toast("اختر رفيق المغامرة قبل إنشاء الحساب.");
      }
    });
    const photo = $("#childPhoto");
    const preview = $("#photoPreview");
    photo?.addEventListener("change", () => {
      const file = photo.files && photo.files[0];
      if (!file || !preview) return;
      if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size > 4 * 1024 * 1024) {
        photo.value = "";
        toast("اختر صورة JPG أو PNG أو WebP بحجم أقصى 4 ميجابايت.");
        return;
      }
      const reader = new FileReader();
      reader.onload = () => {
        const image = document.createElement("img");
        image.src = String(reader.result);
        image.alt = "معاينة الصورة";
        preview.replaceChildren(image);
      };
      reader.readAsDataURL(file);
    });
    if (data.openRegister) activateAuth("register");
    if (data.prefillChar) {
      const char = data.characters.find(item => Number(item.id) === Number(data.prefillChar));
      if (char && !char.is_premium && !picked.includes(Number(char.id))) selectPicked(Number(char.id));
    }
  }

  function setupReveals() {
    const features = $$(".public-feature");
    if (hasGsap && window.ScrollTrigger && !reduced) {
      gsap.registerPlugin(ScrollTrigger);
      gsap.to(features, { opacity: 1, y: 0, duration: .65, stagger: .1, ease: "power3.out", scrollTrigger: { trigger: ".public-features", start: "top 82%" } });
      $$(".public-feature-icon").forEach((icon, index) => gsap.to(icon, { y: -7, rotate: index % 2 ? 5 : -5, duration: 1.6 + index * .08, repeat: -1, yoyo: true, ease: "sine.inOut", delay: index * .08 }));
    } else if (window.IntersectionObserver) {
      const observer = new IntersectionObserver(entries => entries.forEach(entry => {
        if (entry.isIntersecting) { entry.target.style.opacity = "1"; entry.target.style.transform = "none"; observer.unobserve(entry.target); }
      }), { threshold: .12 });
      features.forEach(feature => observer.observe(feature));
    } else features.forEach(feature => { feature.style.opacity = "1"; feature.style.transform = "none"; });
  }

  document.addEventListener("DOMContentLoaded", () => {
    setupLazyImages();
    setupCarousel();
    setupModal();
    setupAuth();
    setupReveals();
    setupHeroRotation();
  });
})();
