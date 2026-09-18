<?php
$__publicNavCompact = !empty($__publicNavCompact);
?>
<header class="public-nav" id="publicNav">
  <div class="public-nav-inner">
 <a class="public-brand" href="<?php echo h(BASE_PATH . '/index.php'); ?>" aria-label="Kidora — الصفحة الرئيسية">
  <span class="public-brand-mark" aria-hidden="true">
    <img src="https://i.ibb.co/Nzr63J7/Screenshot-2026-09-17-131732-removebg-preview.png" 
         alt="" 
         style="width:70%;height:70%;object-fit:contain;">
  </span>
  <span>Kidora</span>
</a>

    <?php if (!$__publicNavCompact): ?>
      <nav class="public-nav-links" aria-label="روابط الصفحة">
        <a href="<?php echo h(BASE_PATH . '/index.php#features'); ?>">المميزات</a>
        <a href="<?php echo h(BASE_PATH . '/index.php#characters'); ?>">الشخصيات</a>
        <a href="<?php echo h(BASE_PATH . '/index.php#plans'); ?>">الخطط</a>
      </nav>
    <?php else: ?>
      <nav class="public-nav-links public-nav-links-compact" aria-label="روابط الصفحة">
        <a href="<?php echo h(BASE_PATH . '/index.php'); ?>">الرئيسية</a>
      </nav>
    <?php endif; ?>

    <div class="public-nav-actions">
      <button type="button" class="public-voice-toggle" id="voiceToggle" aria-label="تشغيل أو إيقاف صوت الشخصية">🗣️</button>
      <?php if (!$__publicNavCompact): ?>
        <a class="public-nav-login" href="<?php echo h(BASE_PATH . '/index.php#auth'); ?>">دخول</a>
        <a class="public-nav-demo" href="<?php echo h(BASE_PATH . '/demo.php'); ?>">🎮 جرب الآن</a>
      <?php else: ?>
        <a class="public-nav-demo" href="<?php echo h(BASE_PATH . '/index.php#auth'); ?>">🚀 سجّل الآن</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<style>
  .public-nav{position:sticky;top:0;z-index:100;background:rgba(10,6,26,.82);border-bottom:1px solid rgba(255,255,255,.1);backdrop-filter:blur(18px);box-shadow:0 10px 34px rgba(0,0,0,.18)}
  .public-nav-inner{width:min(1180px,calc(100% - 32px));min-height:72px;margin:0 auto;display:flex;align-items:center;gap:28px}
  .public-brand{display:inline-flex;align-items:center;gap:9px;color:#fff;font-family:var(--font-display);font-size:25px;font-weight:900;letter-spacing:.2px}
  .public-brand-mark{display:grid;place-items:center;width:36px;height:36px;border-radius:13px;color:#241645;background:linear-gradient(135deg,#ffe99a,#ffc93c);box-shadow:0 0 24px rgba(255,201,60,.38);font-size:23px}
  .public-nav-links{display:flex;align-items:center;gap:6px;flex:1}
  .public-nav-links a,.public-nav-login{padding:9px 13px;border-radius:999px;color:#d9d0ff;font-size:14px;font-weight:800;transition:background .2s ease,color .2s ease,transform .2s ease}
  .public-nav-links a:hover,.public-nav-login:hover{background:rgba(255,255,255,.1);color:#fff;transform:translateY(-1px)}
  .public-nav-actions{display:flex;align-items:center;gap:9px;margin-inline-start:auto}
  .public-voice-toggle{width:40px;height:40px;border:1px solid rgba(255,255,255,.16);border-radius:50%;color:#fff;background:rgba(255,255,255,.08);font-size:17px;transition:.2s}
  .public-voice-toggle:hover,.public-voice-toggle.on{background:rgba(255,201,60,.18);border-color:#ffc93c;box-shadow:0 0 18px rgba(255,201,60,.2)}
  .public-nav-demo{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:9px 18px;border-radius:999px;color:#241645;background:linear-gradient(135deg,#ffe99a,#ffc93c);font-size:14px;font-weight:900;box-shadow:0 8px 24px rgba(255,201,60,.24);animation:publicDemoPulse 2.3s ease-in-out infinite}
  .public-nav-demo:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(255,201,60,.34)}
  @keyframes publicDemoPulse{0%,100%{box-shadow:0 8px 24px rgba(255,201,60,.22)}50%{box-shadow:0 8px 30px rgba(255,201,60,.5)}}
  @media(max-width:700px){.public-nav-inner{width:min(100% - 20px,560px);min-height:64px;gap:10px}.public-brand{font-size:21px}.public-brand-mark{width:31px;height:31px;font-size:19px;border-radius:10px}.public-nav-links{display:none}.public-nav-login{display:none}.public-nav-demo{padding:8px 12px;font-size:12px}.public-voice-toggle{width:36px;height:36px;font-size:15px}}
  @media(prefers-reduced-motion:reduce){.public-nav-demo{animation:none}}
</style>
