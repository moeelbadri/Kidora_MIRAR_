<?php if (!empty($_SESSION['child_id'])): ?>
  <!-- ============================================================
       الرفيق الدائم — يرافق الطفل بصوته وثيمه في كل الصفحات
       ============================================================ -->
  <div id="companionWidget">
    <div id="companionBubble"></div>
    <div class="companion-col">
      <?php if (count(array_filter([$__headerChild['character_1'] ?? null, $__headerChild['character_2'] ?? null])) > 1): ?>
      <button id="companionSwapBtn" title="بدّل الرفيق">🔄</button>
      <?php endif; ?>
      <button id="companionAvatar" class="move-<?php echo h($__activeChar['move_type'] ?? 'wiggle'); ?>">
        <?php if (!empty($__activeChar['image_path'])): ?>
          <img src="<?php echo BASE_PATH . '/' . h($__activeChar['image_path']); ?>" alt="">
        <?php else: ?>
          <?php echo character_icons($__activeChar)[0] ?? '✨'; ?>
        <?php endif; ?>
      </button>
    </div>
  </div>
  <?php endif; ?>

  <div class="toast-wrap" id="toastWrap"></div>

</div><!-- /.app-wrapper -->

<script src="<?php echo BASE_PATH; ?>/assets/js/app.js?v=20260925"></script>


<script>
/* ============================================================
   ✅ حل رسمي: إصلاح اتجاه فقاعة الرفيق تلقائيًا
   يعمل مع أي ID/class — بلا حاجة لمعرفة اسم الفقاعة
   ============================================================ */
(function () {
  'use strict';

  /* ---------- 1) CSS بالقوة القصوى ---------- */
  var style = document.createElement('style');
  style.setAttribute('data-kidora-fix', 'bubble-rtl');
  style.textContent = `
    /* أي عنصر فقاعة: id/class فيه bubble أو companion أو speech */
    [id*="bubble" i], [id*="Bubble"],
    [class*="bubble" i],
    [id*="speech" i], [class*="speech" i],
    [id*="companion" i][id*="bubble" i],
    [class*="companion" i][class*="bubble" i] {
      direction: rtl !important;
      text-align: right !important;
      unicode-bidi: plaintext !important;
      letter-spacing: 0 !important;
      word-spacing: normal !important;
    }
    /* الأيقونات تبقى سليمة */
    [class*="sidekick" i], [class*="icon" i],
    [class*="emoji" i], [class*="badge" i] {
      direction: ltr !important;
      unicode-bidi: isolate !important;
    }
  `;
  document.head.appendChild(style);

  /* ---------- 2) إصلاح JS مباشر على العناصر ---------- */
  function looksLikeBubble(el) {
    var key = ((el.id || '') + ' ' + (el.className || '')).toLowerCase();
    return /bubble|speech|رفيق|فقاعة/.test(key);
  }

  function fixEl(el) {
    if (!el || el.nodeType !== 1) return;
    if (el.tagName === 'BUTTON' || el.tagName === 'SVG' || el.tagName === 'PATH') return;
    if (!looksLikeBubble(el)) return;

    el.setAttribute('dir', 'rtl');
    el.style.setProperty('direction', 'rtl', 'important');
    el.style.setProperty('text-align', 'right', 'important');
    el.style.setProperty('unicode-bidi', 'plaintext', 'important');

    // أصلح الأبناء النصية (باستثناء الأيقونات)
    el.querySelectorAll('*').forEach(function (child) {
      if (child.tagName === 'BUTTON' || child.tagName === 'SVG') return;
      var ck = ((child.className || '') + '').toLowerCase();
      if (/sidekick|icon|emoji|badge/.test(ck)) {
        child.setAttribute('dir', 'ltr');
        child.style.setProperty('direction', 'ltr', 'important');
        return;
      }
      child.setAttribute('dir', 'rtl');
      child.style.setProperty('direction', 'rtl', 'important');
      child.style.setProperty('text-align', 'right', 'important');
      child.style.setProperty('unicode-bidi', 'plaintext', 'important');
    });
  }

  function scan() {
    document.querySelectorAll('*').forEach(fixEl);
  }

  /* ---------- 3) شغّل عند الإقلاع + راقب أي فقاعة جديدة ---------- */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scan);
  } else {
    scan();
  }

  if (window.MutationObserver) {
    var mo = new MutationObserver(function () { scan(); });
    var start = function () {
      mo.observe(document.documentElement, {
        childList: true,
        subtree: true,
        characterData: true,
        attributes: true,
        attributeFilter: ['class', 'style', 'dir']
      });
    };
    if (document.documentElement) start();
    else document.addEventListener('DOMContentLoaded', start);
  }

  /* للاستدعاء اليدوي من Console */
  window.__fixBubbleNow = scan;
})();
</script>


</body>
</html>
