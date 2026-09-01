<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();
$progress = ensure_daily_progress($pdo, $child['id']);

$taskPool = json_decode_safe($progress['task_pool_ids'], []);
$completedIds = json_decode_safe($progress['completed_task_ids'], []);
$tasksDone = count($taskPool) > 0 && count($completedIds) >= count($taskPool);
$gamesDone = (int)$progress['games_played'] >= FREE_LIBRARY_GAMES;
// القصة اليومية ميزة اشتراك: غير المشترك لا يولّدها أصلاً (لا زر ولا POST)
$isPremium = is_premium_active($pdo, (int)$child['id']);
$ready = $tasksDone && $gamesDone;

// هل تُوجد قصة اليوم بالفعل؟
$todayStory = null;
$st = $pdo->prepare("SELECT * FROM daily_stories WHERE child_id = ? AND DATE(created_at) = ?");
$st->execute([$child['id'], today_key()]);
$todayStory = $st->fetch();

// ---------------- توليد القصة ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_story']) && $isPremium && $ready && !$todayStory) {
    $myChars = array_filter([get_character($pdo, $child['character_1']), get_character($pdo, $child['character_2'])]);
    $names = implode(' و', array_map(fn($c) => $c['name'], $myChars));

    $doneTasks = [];
    foreach ($taskPool as $tid) {
        $t = $pdo->prepare("SELECT * FROM tasks WHERE id = ?"); $t->execute([$tid]); $doneTasks[] = $t->fetch();
    }
    $grads = ['#6C63FF,#FF6FA5','#2EC4B6,#6C63FF','#FF7A50,#FFC93C','#FF6FA5,#FFC93C','#2EC4B6,#241645'];
    // الجمل اسمية بلا فعل مُسنَد إلى اسم الطفل — التطبيق لا يسجّل جنسه،
    // و«استيقظ البطل سلمى» كان يُخاطب البنات بصيغة المذكّر.
    // لكل مشهد أيقونة وعنوان (فصل) يعرضهما StoryPlayer فوق المشهد.
    $scenes = [];
    $scenes[] = [
        'caption' => "صباح مشرق، و{$child['name']} مع الصديقين {$names} على أول الطريق.",
        'grad' => $grads[0], 'icon' => '🌅', 'title' => 'بداية اليوم',
    ];
    foreach ($doneTasks as $i => $t) {
        $scenes[] = [
            'caption' => $t['story_line'],
            'grad'    => $grads[($i + 1) % count($grads)],
            'icon'    => category_icon($t['category']),
            'title'   => $t['title'],
        ];
    }
    $totalPts = array_sum(array_column($doneTasks, 'points'));
    $scenes[] = [
        'caption' => "{$child['name']} في البيت الآن، وفي الجيب {$totalPts} نجمة ✨ من نجوم اليوم!",
        'grad' => $grads[count($grads) - 1], 'icon' => '⭐', 'title' => 'نجوم اليوم',
    ];

    $photoPath = save_upload('photo', __DIR__ . '/uploads/photos', ['jpg','jpeg','png','webp']);
    $photoRel = $photoPath ? 'uploads/photos/' . basename($photoPath) : null;

    $dayIndex = (int)$child['ring_days'] + 1;
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
elseif (!$isPremium)  $__pageLine = "القصة اليومية المتحركة للمشتركين — خلّينا نشوف الاشتراكات مع بعض 💳";
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
    <p class="section-sub">
      <?php if ($isPremium): ?>
        بعد إنجاز مهامك اليومية ولعب الألعاب، يمكنك توليد قصتك المتحركة الخاصة ليوم واحد فقط.
      <?php else: ?>
        القصة اليومية المتحركة ميزة للمشتركين — تُبنى من مهام يومك وتتحرك مشهداً بعد مشهد.
      <?php endif; ?>
    </p>
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
        spriteFace: <?php echo json_encode($__spriteFace, JSON_UNESCAPED_UNICODE); ?>
      }, 'dailyStoryBox', {
        badge: '✅ قصة اليوم جاهزة! عد غداً لقصة جديدة.',
        animate: true
      });
    </script>

  <?php elseif (!$isPremium): ?>
    <div class="card" style="max-width:560px;margin:0 auto;padding:30px;text-align:center;">
      <div style="font-size:44px;">🎬</div>
      <h3 style="color:var(--ink);">قصتك اليومية المتحركة تنتظر الاشتراك</h3>
      <p style="color:var(--ink-soft);line-height:2;">
        كل يوم تُبنى قصة من مهامك أنت: مشاهد متحركة بأيقونة كل مهمة، وصوت
        صاحبك يحكيها، ويمكن تنزيلها كفيديو ومشاركتها مع أهلك.
      </p>
      <p style="color:var(--ink-soft);">ومعها تُفتح مكتبة الألعاب كاملة بدل لعبتين.</p>
      <a class="btn btn-primary btn-block" href="<?php echo BASE_PATH; ?>/subscriptions.php">شوف الاشتراكات 💳</a>
      <a class="btn btn-ghost btn-sm" href="<?php echo BASE_PATH; ?>/games.php" style="margin-top:10px;">رجوع للألعاب 🎮</a>
    </div>

  <?php elseif (!$ready): ?>
    <div class="card" style="max-width:520px;margin:0 auto;padding:30px;text-align:center;">
      <div style="font-size:44px;">🔒</div>
      <h3 style="color:var(--ink);">لسّا ما وصلت لهون!</h3>
      <ul style="text-align:right;color:var(--ink-soft);line-height:2;list-style:none;padding:0;">
        <li><?php echo $tasksDone ? '✅' : '⬜'; ?> كل مهامك اليومية (باكج 4 مهام)</li>
        <li><?php echo $gamesDone ? '✅' : '⬜'; ?> لعبتان من مكتبة الألعاب على الأقل</li>
      </ul>
      <a class="btn btn-primary" href="<?php echo $tasksDone ? 'games.php' : 'tasks.php'; ?>">اذهب <?php echo $tasksDone ? 'للألعاب' : 'لمهامي'; ?></a>
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
  <?php if ($flashToast): ?>
  document.addEventListener('DOMContentLoaded', function(){
    const wrap = document.getElementById('toastWrap');
    const el = document.createElement('div'); el.className='toast'; el.textContent = <?php echo json_encode($flashToast, JSON_UNESCAPED_UNICODE); ?>;
    wrap.appendChild(el); setTimeout(()=>el.remove(), 6000);
  });
  <?php endif; ?>
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
