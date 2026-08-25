<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();

$dueNow = needs_assessment($child);

// جلسة أسئلة التحليل الحالية (10 أسئلة، تبقى نفسها حتى تكتمل حتى لو غادر الصفحة)
if ($dueNow) {
    if (empty($_SESSION['assess_qids']) || ($_SESSION['assess_child_id'] ?? 0) != $child['id']) {
        $ids = array_column($pdo->query("SELECT id FROM quiz_questions WHERE active = 1")->fetchAll(), 'id');
        shuffle($ids);
        $_SESSION['assess_qids'] = array_slice($ids, 0, min(10, count($ids)));
        $_SESSION['assess_answered'] = [];
        $_SESSION['assess_child_id'] = $child['id'];
    }
}
$qSet = $_SESSION['assess_qids'] ?? [];
$answered = $_SESSION['assess_answered'] ?? [];

// ---------------- معالجة إجابة ----------------
$flashMsg = null;
if ($dueNow && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer_question_id'])) {
    $qid = (int)$_POST['answer_question_id'];
    $opt = (int)($_POST['option'] ?? 0);
    if (in_array($qid, $qSet) && !in_array($qid, $answered) && in_array($opt, [1,2,3])) {
        $q = $pdo->prepare("SELECT * FROM quiz_questions WHERE id = ?"); $q->execute([$qid]); $question = $q->fetch();
        if ($question) {
            $value = (int)$question["option_{$opt}_value"];
            $msg = $question["option_{$opt}_msg"];
            $pdo->prepare("INSERT INTO quiz_history (child_id, axis, value) VALUES (?,?,?)")->execute([$child['id'], $question['axis'], $value]);
            $_SESSION['assess_answered'][] = $qid;
            $_SESSION['flash_quiz_msg'] = $msg;
        }
    }
    if (count($_SESSION['assess_answered']) >= count($qSet)) {
        $pdo->prepare("UPDATE children SET last_assessment_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$child['id']]);
        $_SESSION['assess_just_finished'] = true;
    }
    header('Location: assessment.php'); exit;
}
$flashMsg = $_SESSION['flash_quiz_msg'] ?? null;
unset($_SESSION['flash_quiz_msg']);
$answered = $_SESSION['assess_answered'] ?? [];
$justFinished = !empty($_SESSION['assess_just_finished']);
if ($justFinished) unset($_SESSION['assess_just_finished']);
$doneNow = $dueNow && count($answered) >= count($qSet) && count($qSet) > 0;

$currentQuestion = null;
if ($dueNow && !$doneNow && !$flashMsg) {
    $remaining = array_values(array_diff($qSet, $answered));
    if ($remaining) {
        $st = $pdo->prepare("SELECT * FROM quiz_questions WHERE id = ?"); $st->execute([$remaining[0]]); $currentQuestion = $st->fetch();
    }
}

$axisRows = assessment_axis_summary($pdo, $child['id']);
$activePlan = get_active_plan($pdo, $child['id']);
$showSubCTA = ($doneNow || !$dueNow) && count($axisRows) > 0 && (!$activePlan || (int)$activePlan['price_ils'] === 0);

$nextDueLabel = null;
if (!empty($child['last_assessment_at'])) {
    $nextTs = strtotime($child['last_assessment_at']) + 10*86400;
    if ($nextTs > time()) $nextDueLabel = date('Y-m-d', $nextTs);
}

$__pageTitle = 'تحليل شخصيتي — Kidora';
$__pageLine = $dueNow ? "جاوب بصدق، أنا جنبك بكل خطوة 🌟" : "خلّينا نلعب ونتقدّم لحد التحليل الجاي 🚀";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<div class="page-body">
<main class="container" style="padding-top:26px;">
  <div class="section-head">
    <div class="eyebrow">لعبة اكتشاف الذات — كل 10 أيام</div>
    <h2 class="section-title">تحليل شخصيتي</h2>
    <p class="section-sub">10 أسئلة قصيرة على شكل لعبة تساعدنا نفهم شخصيتك أكثر، تظهر مرة كل 10 أيام فقط. إجابتك تُبنى كمخطط تقدّم حقيقي وليس نسبة مئوية!</p>
  </div>

  <?php if ($flashMsg): ?>
    <div class="quiz-card card quiz-flash-pop">
      <div class="quiz-confetti">🎉✨🌟💫🎊</div>
      <div style="font-size:52px;">🌟</div>
      <h3><?php echo h($flashMsg); ?></h3>
      <a href="assessment.php" class="btn btn-primary" style="margin-top:12px;">التالي</a>
    </div>

  <?php elseif ($dueNow && !$doneNow && $currentQuestion): ?>
    <div class="task-progress-dots">
      <?php foreach ($qSet as $i => $qid): ?><span class="<?php echo in_array($qid,$answered) ? 'done' : (($qid===$currentQuestion['id']) ? 'current' : ''); ?>"></span><?php endforeach; ?>
    </div>
    <p style="text-align:center;color:#D9D0FF;font-weight:700;">سؤال <?php echo count($answered)+1; ?> من <?php echo count($qSet); ?></p>
    <div class="quiz-card card quiz-live">
      <div class="quiz-reaction-avatar" id="quizAvatar">🤔</div>
      <div class="quiz-axis"><?php echo h($currentQuestion['axis']); ?></div>
      <h3><?php echo h($currentQuestion['question']); ?></h3>
      <form method="POST" class="quiz-options">
        <input type="hidden" name="answer_question_id" value="<?php echo (int)$currentQuestion['id']; ?>">
        <?php for ($i = 1; $i <= 3; $i++): ?>
          <button type="submit" name="option" value="<?php echo $i; ?>" class="quiz-opt" onmouseover="bounceAvatar()"><?php echo h($currentQuestion["option_{$i}"]); ?></button>
        <?php endfor; ?>
      </form>
    </div>

  <?php elseif ($justFinished || ($dueNow && $doneNow)): ?>
    <div class="quiz-card card quiz-flash-pop">
      <div class="quiz-confetti">🎊🏆✨🎉🌟</div>
      <div style="font-size:56px;">🏆</div>
      <h3>أنجزت تحليل شخصيتك كاملاً!</h3>
      <p style="color:var(--ink-soft);">رائع! سجّلنا كل إجاباتك، وسيُرسل والداك تقرير تقدّمك، وسيظهر التحليل التالي بعد 10 أيام.</p>
      <a href="tasks.php" class="btn btn-primary" style="margin-top:10px;">يلا لمهامي اليومية 💪</a>
    </div>

  <?php else: ?>
    <div class="quiz-card card">
      <div style="font-size:52px;">🗓️</div>
      <h3>التحليل التالي بعد كم يوم</h3>
      <p style="color:var(--ink-soft);">تحليل شخصيتك يظهر كل 10 أيام فقط عشان نراقب تقدّمك الحقيقي بدون إزعاج.<?php echo $nextDueLabel ? " التحليل الجاي بتاريخ <b>{$nextDueLabel}</b>." : ""; ?></p>
      <a href="tasks.php" class="btn btn-primary">يلا لمهامي اليومية 💪</a>
    </div>
  <?php endif; ?>

  <?php if ($showSubCTA): ?>
    <div class="card" style="max-width:560px;margin:22px auto 0;padding:22px;background:var(--cream-2);text-align:center;">
      <p style="font-weight:800;color:var(--coral);font-size:17px;">بناءً على تحليل طفلك، نقترح ترقية اشتراكه لفتح المزيد من الشخصيات والقصص والألعاب 💡</p>
      <a href="subscriptions.php" class="btn btn-gold" style="margin-top:10px;">شاهد خطط الاشتراك المقترحة</a>
    </div>
  <?php endif; ?>

  <div class="section">
    <h3 style="text-align:center;color:#fff;text-shadow:0 2px 8px rgba(0,0,0,.35);">مخطط تقدّمك عبر الزمن</h3>
    <div class="card" style="padding:26px;max-width:560px;margin:16px auto;">
      <div class="chart-wrap">
        <?php if (!$axisRows): ?>
          <p style="color:var(--ink-soft);text-align:center;">أجب عن التحليل ليظهر لك أول رسمة لمخططك 📊</p>
        <?php else: foreach ($axisRows as $row): $pct = ((float)$row['avg_v'] / 3) * 100; ?>
          <div class="chart-row">
            <div><?php echo h($row['axis']); ?></div>
            <div class="chart-bar-bg"><div class="chart-bar-fg" style="width:<?php echo $pct; ?>%;"></div></div>
            <div style="font-weight:800;color:var(--violet);"><?php echo number_format($row['avg_v'],1); ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</main>
</div>
<footer class="site-footer">Kidora © 2026</footer>
<script>window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;</script>
<script>
  function bounceAvatar(){
    const el = document.getElementById('quizAvatar');
    if (!el) return;
    const faces = ['🤔','😊','🧐','😄'];
    el.textContent = faces[Math.floor(Math.random()*faces.length)];
    el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump');
  }
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
