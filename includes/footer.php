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

  <style>
  /* تكبير عملاق */
#companionAvatar {
  width: 40vh !important;
  height: 40vh !important;
  font-size: 25vh !important;
  border: none !important;
  outline: none !important;
  box-shadow: none !important;
  background: transparent !important;
  display: flex;
  align-items: center;
  justify-content: center;
}

#companionAvatar img {
  width: 100% !important;
  height: 100% !important;
  object-fit: contain;
  border: none !important;
  image-rendering: pixelated; /* اختياري للكرتون */
}

#companionWidget {
  width: 45vh !important;
  height: 45vh !important;
}
.companion-col {
  width: 100% !important;
  height: 100% !important;
}

/* حركة المشي العملاقة */
@keyframes walk {
  0% { transform: translateX(0) rotate(0deg) scale(1); }
  25% { transform: translateX(15px) rotate(-4deg) scale(1.05); }
  50% { transform: translateX(0) rotate(0deg) scale(0.95); }
  75% { transform: translateX(-15px) rotate(4deg) scale(1.05); }
  100% { transform: translateX(0) rotate(0deg) scale(1); }
}

.move-walk {
  animation: walk 1.3s infinite ease-in-out !important;
}

</style>

</div><!-- /.app-wrapper -->

<script src="<?php echo BASE_PATH; ?>/assets/js/app.js"></script>
</body>
</html>
