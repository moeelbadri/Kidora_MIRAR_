<?php
/**
 * محتوى الألعاب: بنك أسئلة صح/خطأ وسيناريوهات المغامرة، لكل موضوع.
 * كان هذا المحتوى ثوابت داخل assets/js/games-engine.js فلم يكن قابلاً للتحرير.
 *
 * «مُراجَع» علامة إدارية: المحتوى المبذور آلياً يظهر للأطفال لكنه مُعلَّم
 * هنا حتى تعتمده الإدارة. لإخفاء شيء فعلاً استخدم «تعطيل».
 */
$topics = $pdo->query("SELECT * FROM game_topics ORDER BY sort_order, id")->fetchAll();
$topicKeys = array_column($topics, 'topic_key');
$topicLabels = array_column($topics, 'label', 'topic_key');

$sel = $_POST['topic'] ?? $_GET['topic'] ?? '';
if (!in_array($sel, $topicKeys, true)) $sel = $topicKeys[0] ?? 'general';
$redirect = '?tab=gamecontent&topic=' . urlencode($sel);

$clampAge = fn($v, $d) => max(4, min(12, (int)$v ?: $d));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_question'])) {
        $q = trim($_POST['question'] ?? '');
        if ($q !== '') {
            $pdo->prepare("INSERT INTO game_questions (topic_key,question,answer,age_min,age_max,reviewed) VALUES (?,?,?,?,?,1)")
                ->execute([$sel, $q, ($_POST['answer'] ?? '1') === '1' ? 1 : 0, $clampAge($_POST['age_min'] ?? 0, 4), $clampAge($_POST['age_max'] ?? 0, 12)]);
            $_SESSION['admin_flash'] = 'تمت إضافة السؤال ✅';
        }
        header("Location: {$redirect}"); exit;
    }
    if (isset($_POST['add_scenario'])) {
        $prompt = trim($_POST['prompt'] ?? '');
        $goodIdx = ($_POST['good'] ?? '1') === '2' ? 1 : 0;
        $choices = [];
        foreach ([1, 2] as $i) {
            $label = trim($_POST["choice_{$i}"] ?? '');
            $resp  = trim($_POST["response_{$i}"] ?? '');
            if ($label !== '') $choices[] = ['l' => $label, 'g' => ($i - 1) === $goodIdx, 'r' => $resp];
        }
        if ($prompt !== '' && count($choices) === 2) {
            $pdo->prepare("INSERT INTO game_scenarios (topic_key,prompt,choices_json,age_min,age_max,reviewed) VALUES (?,?,?,?,?,1)")
                ->execute([$sel, $prompt, json_encode($choices, JSON_UNESCAPED_UNICODE), $clampAge($_POST['age_min'] ?? 0, 4), $clampAge($_POST['age_max'] ?? 0, 12)]);
            $_SESSION['admin_flash'] = 'تمت إضافة السيناريو ✅';
        } else {
            $_SESSION['admin_flash'] = 'السيناريو يحتاج موقفاً وخيارَين ⚠️';
        }
        header("Location: {$redirect}"); exit;
    }
    // أسماء الجداول ثابتة في الكود، والمعرّفات مُمرَّرة كوسائط
    foreach (['question' => 'game_questions', 'scenario' => 'game_scenarios'] as $kind => $table) {
        if (isset($_POST["approve_{$kind}_id"])) {
            $pdo->prepare("UPDATE {$table} SET reviewed = 1 WHERE id = ?")->execute([(int)$_POST["approve_{$kind}_id"]]);
            header("Location: {$redirect}"); exit;
        }
        if (isset($_POST["toggle_{$kind}_id"])) {
            $pdo->prepare("UPDATE {$table} SET active = CASE active WHEN 1 THEN 0 ELSE 1 END WHERE id = ?")->execute([(int)$_POST["toggle_{$kind}_id"]]);
            header("Location: {$redirect}"); exit;
        }
        if (isset($_POST["delete_{$kind}_id"])) {
            $pdo->prepare("DELETE FROM {$table} WHERE id = ?")->execute([(int)$_POST["delete_{$kind}_id"]]);
            header("Location: {$redirect}"); exit;
        }
    }
    if (isset($_POST['approve_all'])) {
        $pdo->prepare("UPDATE game_questions SET reviewed = 1 WHERE topic_key = ?")->execute([$sel]);
        $pdo->prepare("UPDATE game_scenarios SET reviewed = 1 WHERE topic_key = ?")->execute([$sel]);
        $_SESSION['admin_flash'] = 'تم اعتماد كل محتوى هذا الموضوع ✅';
        header("Location: {$redirect}"); exit;
    }
}

$qs = $pdo->prepare("SELECT * FROM game_questions WHERE topic_key = ? ORDER BY reviewed, age_min, id");
$qs->execute([$sel]);
$questions = $qs->fetchAll();

$ss = $pdo->prepare("SELECT * FROM game_scenarios WHERE topic_key = ? ORDER BY reviewed, sort_order, id");
$ss->execute([$sel]);
$scenarios = $ss->fetchAll();

$pendingQ = count(array_filter($questions, fn($r) => !$r['reviewed']));
$pendingS = count(array_filter($scenarios, fn($r) => !$r['reviewed']));
$totalPending = (int)$pdo->query("SELECT (SELECT COUNT(*) FROM game_questions WHERE reviewed = 0) + (SELECT COUNT(*) FROM game_scenarios WHERE reviewed = 0)")->fetchColumn();
?>
<h2>محتوى الألعاب</h2>
<p style="color:#667;">
  أسئلة صح/خطأ وسيناريوهات المغامرة التي تظهر داخل الألعاب، حسب موضوع المهمة أو اللعبة.
  <?php if ($totalPending > 0): ?>
    <b style="color:#B54708;">في <?php echo $totalPending; ?> عنصراً بانتظار المراجعة في كل المواضيع.</b>
  <?php endif; ?>
</p>

<form method="GET" class="admin-form" style="padding:14px;">
  <input type="hidden" name="tab" value="gamecontent">
  <div class="row">
    <select name="topic" onchange="this.form.submit()">
      <?php foreach ($topics as $t): ?>
        <option value="<?php echo h($t['topic_key']); ?>" <?php echo $t['topic_key'] === $sel ? 'selected' : ''; ?>>
          <?php echo h($t['label']); ?> (<?php echo h($t['topic_key']); ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-sm btn-ghost">عرض</button>
  </div>
  <p style="margin:8px 0 0;color:#667;font-size:13px;">
    تصنيفات المهام التي تُخدَّم من هذا الموضوع:
    <?php
      $selTopic = array_values(array_filter($topics, fn($t) => $t['topic_key'] === $sel))[0] ?? null;
      $cats = $selTopic ? json_decode_safe($selTopic['categories_json'], []) : [];
      echo $cats ? h(implode('، ', $cats)) : 'لا شيء (الموضوع الاحتياطي لكل تصنيف غير معروف)';
    ?>
  </p>
</form>

<?php if ($pendingQ + $pendingS > 0): ?>
  <form method="POST" style="margin:12px 0;" onsubmit="return confirm('اعتماد كل محتوى هذا الموضوع؟');">
    <input type="hidden" name="topic" value="<?php echo h($sel); ?>">
    <button name="approve_all" class="btn btn-primary btn-sm">
      ✅ اعتماد كل غير المُراجَع في «<?php echo h($topicLabels[$sel] ?? $sel); ?>» (<?php echo $pendingQ + $pendingS; ?>)
    </button>
  </form>
<?php endif; ?>

<h3>أسئلة صح/خطأ — <?php echo count($questions); ?> سؤالاً</h3>
<div class="admin-form">
  <form method="POST">
    <input type="hidden" name="topic" value="<?php echo h($sel); ?>">
    <div class="row">
      <input name="question" placeholder="نص السؤال (يبدأ عادة بـ «هل...؟»)" required style="flex:3;">
      <select name="answer">
        <option value="1">الجواب: صح</option>
        <option value="0">الجواب: خطأ</option>
      </select>
    </div>
    <div class="row">
      <input name="age_min" type="number" placeholder="أصغر عمر (4)" min="4" max="12">
      <input name="age_max" type="number" placeholder="أكبر عمر (12)" min="4" max="12">
    </div>
    <p style="margin:0 0 10px;color:#667;font-size:13px;">
      صياغة النفي («هل من الصواب أن أكذب؟») تربك عمر 4-6 — اجعل أصغر عمر 7 لمثل هذه الأسئلة.
    </p>
    <button type="submit" name="add_question" class="btn btn-primary">إضافة السؤال</button>
  </form>
</div>
<table class="admin-table">
  <thead><tr><th>السؤال</th><th>الجواب</th><th>العمر</th><th>الحالة</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($questions as $q): ?>
      <tr style="<?php echo $q['active'] ? '' : 'opacity:.5;'; ?>">
        <td><?php echo h($q['question']); ?></td>
        <td><?php echo $q['answer'] ? '✅ صح' : '❌ خطأ'; ?></td>
        <td><?php echo (int)$q['age_min']; ?>-<?php echo (int)$q['age_max']; ?></td>
        <td><?php echo $q['reviewed'] ? '<span style="color:#1B7F4C;">مُراجَع</span>' : '<span style="color:#B54708;">غير مُراجَع</span>'; ?></td>
        <td style="display:flex;gap:6px;">
          <?php if (!$q['reviewed']): ?>
            <form method="POST"><input type="hidden" name="topic" value="<?php echo h($sel); ?>"><input type="hidden" name="approve_question_id" value="<?php echo (int)$q['id']; ?>"><button class="btn btn-sm btn-primary">اعتماد</button></form>
          <?php endif; ?>
          <form method="POST"><input type="hidden" name="topic" value="<?php echo h($sel); ?>"><input type="hidden" name="toggle_question_id" value="<?php echo (int)$q['id']; ?>"><button class="btn btn-sm btn-ghost"><?php echo $q['active'] ? 'تعطيل' : 'تشغيل'; ?></button></form>
          <form method="POST" onsubmit="return confirm('حذف؟');"><input type="hidden" name="topic" value="<?php echo h($sel); ?>"><input type="hidden" name="delete_question_id" value="<?php echo (int)$q['id']; ?>"><button class="btn btn-sm btn-ghost">حذف</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h3 style="margin-top:30px;">سيناريوهات المغامرة — <?php echo count($scenarios); ?> موقفاً</h3>
<div class="admin-form">
  <form method="POST">
    <input type="hidden" name="topic" value="<?php echo h($sel); ?>">
    <input name="prompt" placeholder="الموقف — مثال: وجدت محفظة في الطريق. ماذا تفعل؟" required style="width:100%;margin-bottom:10px;">
    <div class="row">
      <input name="choice_1" placeholder="الخيار الأول" required>
      <input name="response_1" placeholder="نتيجة الخيار الأول" required>
    </div>
    <div class="row">
      <input name="choice_2" placeholder="الخيار الثاني" required>
      <input name="response_2" placeholder="نتيجة الخيار الثاني" required>
    </div>
    <div class="row">
      <select name="good">
        <option value="1">الخيار الصائب: الأول</option>
        <option value="2">الخيار الصائب: الثاني</option>
      </select>
      <input name="age_min" type="number" placeholder="أصغر عمر (4)" min="4" max="12">
      <input name="age_max" type="number" placeholder="أكبر عمر (12)" min="4" max="12">
    </div>
    <p style="margin:0 0 10px;color:#667;font-size:13px;">
      نتيجة الخيار الخاطئ تشرح ما فات ولا تعاقب — الطفل يجرّب مرة أخرى بلا خوف.
    </p>
    <button type="submit" name="add_scenario" class="btn btn-primary">إضافة السيناريو</button>
  </form>
</div>
<table class="admin-table">
  <thead><tr><th>الموقف</th><th>الخيارات</th><th>العمر</th><th>الحالة</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($scenarios as $s): $choices = json_decode_safe($s['choices_json'], []); ?>
      <tr style="<?php echo $s['active'] ? '' : 'opacity:.5;'; ?>">
        <td><?php echo h($s['prompt']); ?></td>
        <td style="font-size:13px;line-height:1.9;">
          <?php foreach ($choices as $c): ?>
            <div><?php echo !empty($c['g']) ? '🌟' : '💭'; ?> <?php echo h($c['l'] ?? ''); ?><br>
              <span style="color:#667;"><?php echo h($c['r'] ?? ''); ?></span></div>
          <?php endforeach; ?>
        </td>
        <td><?php echo (int)$s['age_min']; ?>-<?php echo (int)$s['age_max']; ?></td>
        <td><?php echo $s['reviewed'] ? '<span style="color:#1B7F4C;">مُراجَع</span>' : '<span style="color:#B54708;">غير مُراجَع</span>'; ?></td>
        <td style="display:flex;gap:6px;">
          <?php if (!$s['reviewed']): ?>
            <form method="POST"><input type="hidden" name="topic" value="<?php echo h($sel); ?>"><input type="hidden" name="approve_scenario_id" value="<?php echo (int)$s['id']; ?>"><button class="btn btn-sm btn-primary">اعتماد</button></form>
          <?php endif; ?>
          <form method="POST"><input type="hidden" name="topic" value="<?php echo h($sel); ?>"><input type="hidden" name="toggle_scenario_id" value="<?php echo (int)$s['id']; ?>"><button class="btn btn-sm btn-ghost"><?php echo $s['active'] ? 'تعطيل' : 'تشغيل'; ?></button></form>
          <form method="POST" onsubmit="return confirm('حذف؟');"><input type="hidden" name="topic" value="<?php echo h($sel); ?>"><input type="hidden" name="delete_scenario_id" value="<?php echo (int)$s['id']; ?>"><button class="btn btn-sm btn-ghost">حذف</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
