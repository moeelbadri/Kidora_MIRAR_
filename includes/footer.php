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

<script src="<?php echo BASE_PATH; ?>/assets/js/app.js"></script>
</body>
</html>
 الشخصية اللي بتتحرك و بتحكي بدي اياها اكبر و بدون اطار دائري و تحرك ايديها و تمها لما تتكلم::
