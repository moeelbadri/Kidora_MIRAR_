<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();
$progress = ensure_daily_progress($pdo, $child['id']);

$completedIds = json_decode_safe($progress['completed_task_ids'], []);
$gamesDone = (int)$progress['games_played'] >= STORY_MIN_GAMES;
// القصص والألعاب تبقى متاحة بعد انتهاء التجربة. لعبة واحدة تكفي لفتح قصة اليوم؛
// المهام المدفوعة تثري النص أثناء التجربة/الاشتراك لكنها ليست شرطاً للتوليد.
$ready = $gamesDone;

// هل تُوجد قصة اليوم بالفعل؟
$todayStory = null;
$st = $pdo->prepare("SELECT * FROM daily_stories WHERE child_id = ? AND DATE(created_at) = ?");
$st->execute([$child['id'], today_key()]);
$todayStory = $st->fetch();

// ---------------- توليد القصة ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_story']) && $ready && !$todayStory) {
    $myChars = array_values(array_filter([get_character($pdo, $child['character_1']), get_character($pdo, $child['character_2'])]));

    $doneTasks = [];
    $tq = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    foreach ($completedIds as $tid) { $tq->execute([(int)$tid]); if ($row = $tq->fetch()) $doneTasks[] = $row; }

    $dayIndex = (int)$child['ring_days'] + 1;
    // الحكاية الكاملة (غلاف، افتتاحية، فصول، عقبة، شخصية تراثية، حكمة، خاتمة)
    // تُبنى في daily_story_scenes() — الصياغة محايدة جنسياً حول اسم الطفل.
    $scenes = daily_story_scenes($pdo, $child, $doneTasks, $myChars, $dayIndex);

    $photoPath = save_image_upload('photo', __DIR__ . '/uploads/photos');
    $photoRel = $photoPath ? 'uploads/photos/' . basename($photoPath) : null;
    $ins = $pdo->prepare("INSERT INTO daily_stories (child_id, day_index, title, scenes_json, photo_path) VALUES (?,?,?,?,?)");
    $ins->execute([$child['id'], $dayIndex, "مغامرة {$child['name']} — اليوم {$dayIndex}", json_encode($scenes, JSON_UNESCAPED_UNICODE), $photoRel]);

    $pdo->prepare("UPDATE children SET ring_days = ring_days + 1, points = points + 10 WHERE id = ?")->execute([$child['id']]);

    if ($dayIndex % 30 === 0) {
        $msg = "مرحباً! 🌟 طفلك {$child['name']} أكمل للتو 30 يوماً من المهام والقصص على منصة Kidora، وحصل على مغامرته الكبرى الأولى! رصيده الآن " . ((int)$child['points']+10) . " نقطة.";
        log_wa($pdo, $child['id'], 'progress_update', $msg);
        $_SESSION['flash_wa_link'] = whatsapp_link($pdo, $msg, $child['parent_phone']);
        $_SESSION['flash_toast'] = '🎊 وصلت 30 قصة! مغامرتك الكبرى جاهزة للدمج';
    }
    header('Location: story.php'); exit;
}

$flashToast = $_SESSION['flash_toast'] ?? null;
$flashWaLink = $_SESSION['flash_wa_link'] ?? null;
unset($_SESSION['flash_toast'], $_SESSION['flash_wa_link']);

$st = $pdo->prepare("SELECT * FROM daily_stories WHERE child_id = ? AND DATE(created_at) = ?");
$st->execute([$child['id'], today_key()]);
$todayStory = $st->fetch();

$__pageTitle = 'قصتي اليومية — Kidora';
if ($todayStory)      $__pageLine = "قصتك جاهزة! افتخر فيها 🎬";
elseif ($ready)       $__pageLine = "حان وقت صنع قصتك الخاصة اليوم! ✨";
else                  $__pageLine = "لسّا في شوي باقي قبل ما توصل لقصتك 🔒";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<div class="page-body">
<main class="container" style="padding-top:26px;">
  <div class="section-head">
    <div class="eyebrow">مكافأة اليوم</div>
    <h2 class="section-title">قصتي الخاصة اليوم</h2>
    <p class="section-sub">بعد لعبة واحدة، تُبنى لك لوحة قصة يومية يقرأها رفيقك بصوته. وإذا أنجزت مهاماً اليوم تدخل مغامراتها في الحكاية أيضاً.</p>
  </div>

  <?php if ($todayStory): ?>
    <?php
      // وجه الرفيق يظهر داخل المشهد. $__activeChar يضبطه header.php أعلاه.
      $__spriteFace = '🌟';
      $__icons = json_decode_safe($__activeChar['icons_json'] ?? '', []);
      if ($__icons) $__spriteFace = $__icons[0];
    ?>
    <div id="dailyStoryBox"></div>
    <script>
      StoryPlayer.render({
        title: <?php echo json_encode($todayStory['title'], JSON_UNESCAPED_UNICODE); ?>,
        scenes: <?php echo $todayStory['scenes_json']; ?>,
        photo: <?php echo json_encode($todayStory['photo_path'] ? BASE_PATH.'/'.$todayStory['photo_path'] : null); ?>,
        spriteFace: <?php echo json_encode($__spriteFace, JSON_UNESCAPED_UNICODE); ?>,
        childName: <?php echo json_encode($child['name'], JSON_UNESCAPED_UNICODE); ?>
      }, 'dailyStoryBox', {
        badge: '✅ قصة اليوم جاهزة! عد غداً لقصة جديدة.',
        animate: true,
        book: true
      });
    </script>

  <?php elseif (!$ready): ?>
    <div class="card" style="max-width:520px;margin:0 auto;padding:30px;text-align:center;">
      <div style="font-size:44px;">🔒</div>
      <h3 style="color:var(--ink);">لسّا ما وصلت لهون!</h3>
      <ul style="text-align:right;color:var(--ink-soft);line-height:2;list-style:none;padding:0;">
        <li><?php echo $gamesDone ? '✅' : '⬜'; ?> لعبة اليوم من قسم الألعاب</li>
      </ul>
      <a class="btn btn-primary" href="games.php">اذهب للألعاب</a>
    </div>

  <?php else: ?>
    <div class="card" style="max-width:560px;margin:0 auto;padding:30px;text-align:center;">
      <div style="font-size:44px;">🎬</div>
      <h3 style="color:var(--ink);">حان وقت صنع قصتك الخاصة اليوم!</h3>
      <p style="color:var(--ink-soft);">ارفع صورتك ليظهر بطل القصة بوجهك الحقيقي.</p>
      <form method="POST" enctype="multipart/form-data">
        <input type="file" name="photo" accept="image/*" style="margin:14px 0;">
        <button type="submit" name="generate_story" class="btn btn-primary btn-block">أنشئ قصتي الآن ✨</button>
      </form>
    </div>
  <?php endif; ?>
</main>
</div>
<footer class="site-footer">Kidora © 2026</footer>
<script>
  window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;
  <?php if ($todayStory): ?>
  try { localStorage.setItem('kidora_done_story_' + new Date().toISOString().slice(0,10), '1'); } catch(e){}
  <?php endif; ?>
  <?php if ($flashToast): ?>
  document.addEventListener('DOMContentLoaded', function(){
    const wrap = document.getElementById('toastWrap');
    const el = document.createElement('div'); el.className='toast'; el.textContent = <?php echo json_encode($flashToast, JSON_UNESCAPED_UNICODE); ?>;
    wrap.appendChild(el); setTimeout(()=>el.remove(), 6000);
  });
  <?php endif; ?>
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
