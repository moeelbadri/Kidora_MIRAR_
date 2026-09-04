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

            $_SESSION['flash_story'] = $task['story_line'];
            $_SESSION['flash_points'] = (int)$task['points'];
            $_SESSION['flash_figure'] = figure_for_task($pdo, $task);
            $_SESSION['flash_game_type'] = $task['game_type'] ?: 'catch';
            $_SESSION['flash_game_title'] = $task['game_title'] ?: $task['title'];
            $_SESSION['flash_game_category'] = $task['category'];
        }
    }
    header('Location: tasks.php'); exit;
}

$flashStory = $_SESSION['flash_story'] ?? null;
$flashPoints = $_SESSION['flash_points'] ?? null;
$flashFigure = $_SESSION['flash_figure'] ?? null;
$flashGameType = $_SESSION['flash_game_type'] ?? 'catch';
$flashGameTitle = $_SESSION['flash_game_title'] ?? 'لعبة قصيرة';
$flashGameCategory = $_SESSION['flash_game_category'] ?? '';
unset($_SESSION['flash_story'], $_SESSION['flash_points'], $_SESSION['flash_figure'], $_SESSION['flash_game_type'], $_SESSION['flash_game_title'], $_SESSION['flash_game_category']);

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
<style>
/* ===== إضافات التحفيز والاحتفال ===== */
.celebration-box {
    background: linear-gradient(135deg, #f9d423, #ff4e50);
    border-radius: 60px 20px 60px 20px;
    padding: 20px 30px;
    margin: 20px auto;
    max-width: 500px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(255, 78, 80, 0.4);
    animation: pulse-glow 1.5s infinite alternate;
    position: relative;
    z-index: 2;
}

.celebration-text h2 {
    color: #fff;
    font-size: 2rem;
    margin: 0 0 6px;
    text-shadow: 0 3px 10px rgba(0,0,0,0.3);
}

.celebration-text p {
    color: #fff;
    font-size: 1.2rem;
    margin: 0 0 6px;
    opacity: 0.9;
}

.celebration-text .stars {
    font-size: 2.5rem;
    letter-spacing: 12px;
    animation: spin-stars 3s linear infinite;
}

@keyframes pulse-glow {
    0% { transform: scale(1); box-shadow: 0 8px 25px rgba(255, 78, 80, 0.4); }
    100% { transform: scale(1.03); box-shadow: 0 15px 40px rgba(255, 78, 80, 0.7); }
}

@keyframes spin-stars {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

#confetti-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 9999;
    overflow: hidden;
}

.confetti-piece {
    position: absolute;
    width: 12px;
    height: 12px;
    border-radius: 4px;
    animation: confetti-fall linear forwards;
}

@keyframes confetti-fall {
    0% {
        opacity: 1;
        transform: translateY(-20px) rotate(0deg) scale(1);
    }
    100% {
        opacity: 0;
        transform: translateY(100vh) rotate(720deg) scale(0.4);
    }
}
</style>
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
      <?php
        // قصة السطرين ثم الشخصية: ما يُعرض هو ما يُقرأ
        $sayDone = $flashStory;
        if ($flashFigure) $sayDone .= ' ' . $flashFigure['name'] . '، ' . $flashFigure['title'] . '. ' . $flashFigure['description'] . ' ' . $flashFigure['story_line'];
      ?>
      <div class="story-line-toast">📖 <?php echo h($flashStory); ?> <?php echo $flashPoints ? "(+{$flashPoints} ⭐)" : ''; ?></div>
      <div style="text-align:center;margin-top:10px;">
        <button type="button" class="btn btn-listen" data-say="<?php echo h($sayDone); ?>">🔊 اسمع القصة والشخصية</button>
      </div>

      <!-- ===== رسالة تحفيزية وتأثيرات احتفالية ===== -->
      <div class="celebration-box">
          <div class="celebration-text">
              <h2>🌟 أحسنت! أنت بطل اليوم! 🌟</h2>
              <p>كل مهمة تنجزها تقربك أكثر من القمة! استمر 💪</p>
              <div class="stars">⭐ ⭐ ⭐ ⭐ ⭐</div>
          </div>
      </div>
      <div id="confetti-container"></div>

      <?php if ($flashFigure): ?>
        <div class="card" style="max-width:560px;margin:20px auto;padding:26px;text-align:center;">
          <!-- «تراثنا» لا «تاريخنا»: المكتبة تضم شخصيات تاريخية وأخرى من الحكايات
               (شهرزاد)، والعنوان يجب أن يصدق على الاثنتين -->
          <div class="eyebrow">تعرّف على شخصية من تراثنا</div>
          <h3 style="color:var(--ink);margin:8px 0 4px;"><?php echo h($flashFigure['name']); ?> — <?php echo h($flashFigure['title']); ?></h3>
          <?php if (!empty($flashFigure['youtube_id'])): ?>
            <div class="task-video-wrap">
              <div style="position:relative;padding-bottom:56.25%;height:0;">
                <iframe src="<?php echo h(youtube_embed_url($flashFigure['youtube_id'])); ?>" style="position:absolute;inset:0;width:100%;height:100%;border:0;" allowfullscreen loading="lazy"></iframe>
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
        <button type="button" class="btn btn-listen" data-say="<?php echo h($currentTask['title'] . '. ' . $currentTask['description']); ?>">🔊 اسمع المهمة</button>
        <?php if (!empty($currentTask['youtube_id'])): ?>
          <div class="task-video-wrap">
            <div style="position:relative;padding-bottom:56.25%;height:0;">
              <iframe src="<?php echo h(youtube_embed_url($currentTask['youtube_id'])); ?>" style="position:absolute;inset:0;width:100%;height:100%;border:0;" allowfullscreen loading="lazy"></iframe>
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
      // إطلاق تأثيرات الاحتفال
      launchConfetti(80);
      
      const btn = document.getElementById('toGameBtn');
      if (btn) btn.onclick = function(){
        btn.style.display = 'none';
        const host = document.getElementById('taskGameHost');
        GamesEngine.run(<?php echo json_encode($flashGameType); ?>, host, <?php echo json_encode($flashGameTitle, JSON_UNESCAPED_UNICODE); ?>, 'var(--coral)', function(){
          fetch(window.KIDAURA_BASE + '/api/play-game.php', {method:'POST'});
          setTimeout(function(){ location.href = 'tasks.php'; }, 1200);
        }, { category: <?php echo json_encode($flashGameCategory, JSON_UNESCAPED_UNICODE); ?> });
      };
    });

    function launchConfetti(count) {
        const container = document.getElementById('confetti-container');
        const colors = ['#FF6B6B', '#4ECDC4', '#FFE66D', '#A8E6CF', '#FF8A5C', '#6C5CE7', '#FD79A8'];
        for (let i = 0; i < count; i++) {
            const piece = document.createElement('div');
            piece.className = 'confetti-piece';
            piece.style.left = Math.random() * 100 + '%';
            piece.style.top = '-10px';
            piece.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            piece.style.width = (Math.random() * 10 + 6) + 'px';
            piece.style.height = (Math.random() * 10 + 6) + 'px';
            piece.style.borderRadius = Math.random() > 0.5 ? '50%' : '4px';
            piece.style.animationDuration = (Math.random() * 2 + 2) + 's';
            piece.style.animationDelay = (Math.random() * 1.5) + 's';
            container.appendChild(piece);
            setTimeout(() => { piece.remove(); }, 4000);
        }
    }
  <?php endif; ?>
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
