<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();
$progress = ensure_daily_progress($pdo, $child['id']);

// ---------------- باكج اليوم: 4 مهام حسب عمر الطفل ----------------
$taskPool = json_decode_safe($progress['task_pool_ids'], null);
if ($taskPool === null) {
    $stmt = $pdo->prepare("SELECT id FROM tasks WHERE active = 1 AND age_min <= ? AND age_max >= ?");
    $stmt->execute([$child['age'], $child['age']]);
    $eligible = array_column($stmt->fetchAll(), 'id');
    shuffle($eligible);
    $taskPool = array_slice($eligible, 0, min(4, count($eligible)));
    $pdo->prepare("UPDATE daily_progress SET task_pool_ids = ? WHERE id = ?")->execute([json_encode($taskPool), $progress['id']]);
}
$completedIds = json_decode_safe($progress['completed_task_ids'], []);

// ---------------- معالجة إنجاز مهمة ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_task_id'])) {
    $taskId = (int)$_POST['complete_task_id'];
    if (in_array($taskId, $taskPool) && !in_array($taskId, $completedIds)) {
        $t = $pdo->prepare("SELECT * FROM tasks WHERE id = ?"); $t->execute([$taskId]); $task = $t->fetch();
        if ($task) {
            $completedIds[] = $taskId;
            $pdo->prepare("UPDATE daily_progress SET completed_task_ids = ? WHERE id = ?")->execute([json_encode($completedIds), $progress['id']]);
            $pdo->prepare("UPDATE children SET points = points + ? WHERE id = ?")->execute([(int)$task['points'], $child['id']]);

            $figure = $pdo->query("SELECT * FROM history_figures WHERE active = 1 ORDER BY RANDOM() LIMIT 1")->fetch();

            $_SESSION['flash_story'] = $task['story_line'];
            $_SESSION['flash_points'] = (int)$task['points'];
            $_SESSION['flash_figure'] = $figure ?: null;
            $_SESSION['flash_game_type'] = $task['game_type'] ?: 'catch';
            $_SESSION['flash_game_title'] = $task['title'];
        }
    }
    header('Location: tasks.php'); exit;
}

$flashStory = $_SESSION['flash_story'] ?? null;
$flashPoints = $_SESSION['flash_points'] ?? null;
$flashFigure = $_SESSION['flash_figure'] ?? null;
$flashGameType = $_SESSION['flash_game_type'] ?? 'catch';
$flashGameTitle = $_SESSION['flash_game_title'] ?? 'لعبة قصيرة';
unset($_SESSION['flash_story'], $_SESSION['flash_points'], $_SESSION['flash_figure'], $_SESSION['flash_game_type'], $_SESSION['flash_game_title']);

$doneCount = count($completedIds);
$allTasksDone = $doneCount >= count($taskPool);

// إذا انتهت الباكج بالكامل ولا يوجد شاشة إنجاز معروضة حالياً → التوجه مباشرة لقسم الحماية
if ($allTasksDone && !$flashStory) {
    header('Location: safety.php'); exit;
}

$currentTask = null;
if (!$allTasksDone && !$flashStory) {
    $currentTaskId = $taskPool[$doneCount];
    $st = $pdo->prepare("SELECT * FROM tasks WHERE id = ?"); $st->execute([$currentTaskId]); $currentTask = $st->fetch();
}

$__pageTitle = 'مهامي اليومية — Kidora';
$__pageLine = $flashStory ? null : "يلا نبدأ هالمهمة سوا! 💪";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<div class="page-body">
<main class="container" style="padding-top:26px;">
  <div class="section-head">
    <div class="eyebrow">أهم شغلة! باكج اليوم 4 مهام</div>
    <h2 class="section-title">مهامي اليومية</h2>
    <p class="section-sub">أنجز مهامك، اكسب نقاط، وتعرّف بعد كل مهمة على بطل عربي أو إسلامي ملهم قبل أن تنتقل للتالية.</p>
  </div>

  <div class="task-stage">
    <div class="task-progress-dots">
      <?php foreach ($taskPool as $i => $tid): ?>
        <span class="<?php echo $i < $doneCount ? 'done' : ($i === $doneCount ? 'current' : ''); ?>"></span>
      <?php endforeach; ?>
    </div>

    <?php if ($flashStory): ?>
      <!-- شاشة إنجاز المهمة: قصة السطر + شخصية تاريخية ملهمة -->
      <div class="story-line-toast">📖 <?php echo h($flashStory); ?> <?php echo $flashPoints ? "(+{$flashPoints} ⭐)" : ''; ?></div>

      <?php if ($flashFigure): ?>
        <div class="card" style="max-width:560px;margin:20px auto;padding:26px;text-align:center;">
          <div class="eyebrow">تعرّف على بطل من تاريخنا</div>
          <h3 style="color:var(--ink);margin:8px 0 4px;"><?php echo h($flashFigure['name']); ?> — <?php echo h($flashFigure['title']); ?></h3>
          <?php if (!empty($flashFigure['youtube_id'])): ?>
            <div class="task-video-wrap">
              <div style="position:relative;padding-bottom:56.25%;height:0;">
                <iframe src="https://www.youtube.com/embed/<?php echo h($flashFigure['youtube_id']); ?>" style="position:absolute;inset:0;width:100%;height:100%;border:0;" allowfullscreen loading="lazy"></iframe>
              </div>
            </div>
          <?php else: ?>
            <div style="font-size:50px;margin:10px 0;">🕌</div>
          <?php endif; ?>
          <p style="color:var(--ink-soft);line-height:1.8;"><?php echo h($flashFigure['description']); ?></p>
          <p style="color:var(--violet);font-weight:700;">✨ <?php echo h($flashFigure['story_line']); ?></p>
        </div>
      <?php endif; ?>

      <div style="text-align:center;margin-top:10px;">
        <button class="btn btn-primary" id="toGameBtn">العب لعبة قصيرة قبل المهمة التالية 🎮</button>
      </div>
      <div id="taskGameHost" style="margin-top:18px;"></div>

    <?php elseif ($currentTask): ?>
      <div class="card task-card">
        <span class="pill task-cat"><?php echo h($currentTask['category']); ?></span>
        <h3><?php echo h($currentTask['title']); ?></h3>
        <p><?php echo h($currentTask['description']); ?></p>
        <?php if (!empty($currentTask['youtube_id'])): ?>
          <div class="task-video-wrap">
            <div style="position:relative;padding-bottom:56.25%;height:0;">
              <iframe src="https://www.youtube.com/embed/<?php echo h($currentTask['youtube_id']); ?>" style="position:absolute;inset:0;width:100%;height:100%;border:0;" allowfullscreen loading="lazy"></iframe>
            </div>
          </div>
        <?php endif; ?>
        <form method="POST" style="margin-top:22px;">
          <input type="hidden" name="complete_task_id" value="<?php echo (int)$currentTask['id']; ?>">
          <button type="submit" class="btn btn-primary">أنجزت المهمة ✅ (+<?php echo (int)$currentTask['points']; ?>)</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</main>
</div>
<footer class="site-footer">Kidora © 2026</footer>
<script>
  window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;
  <?php if ($currentTask): ?>
    document.addEventListener('DOMContentLoaded', function(){
      setTimeout(function(){
        SoundEngine.speak(<?php echo json_encode($currentTask['title'].'. '.$currentTask['description'], JSON_UNESCAPED_UNICODE); ?>, window.KIDAURA_ACTIVE_CHARACTER);
      }, 700);
    });
  <?php endif; ?>
  <?php if ($flashStory): ?>
    document.addEventListener('DOMContentLoaded', function(){
      const btn = document.getElementById('toGameBtn');
      if (btn) btn.onclick = function(){
        btn.style.display = 'none';
        const host = document.getElementById('taskGameHost');
        GamesEngine.run(<?php echo json_encode($flashGameType); ?>, host, <?php echo json_encode($flashGameTitle, JSON_UNESCAPED_UNICODE); ?>, 'var(--coral)', function(){
          fetch(window.KIDAURA_BASE + '/api/play-game.php', {method:'POST'});
          setTimeout(function(){ location.href = 'tasks.php'; }, 1200);
        });
      };
    });
  <?php endif; ?>
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
