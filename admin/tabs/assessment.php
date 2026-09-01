<?php
/**
 * أسئلة التحليل اليومي (quiz_questions). كانت في القاعدة من البداية لكن
 * بلا أي واجهة، فلم يكن ممكناً تعديلها إلا بـ SQL.
 *
 * كل سؤال ثلاثة خيارات بقيم 3/2/1 — القيمة تُسجّل في quiz_history وتُبنى
 * عليها محاور النمو في المغامرة الكبرى، فترتيب القيم ليس تجميلياً.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $axis     = trim($_POST['axis'] ?? '');
    $question = trim($_POST['question'] ?? '');
    $opts = [];
    foreach ([1, 2, 3] as $i) {
        $opts[$i] = [
            'text'  => trim($_POST["option_{$i}"] ?? ''),
            'value' => max(1, min(3, (int)($_POST["value_{$i}"] ?? 0))),
            'msg'   => trim($_POST["msg_{$i}"] ?? ''),
        ];
    }
    $complete = $axis !== '' && $question !== '' && !array_filter($opts, fn($o) => $o['text'] === '');
    if ($complete) {
        $pdo->prepare("INSERT INTO quiz_questions
            (axis,question,option_1,option_1_value,option_1_msg,option_2,option_2_value,option_2_msg,option_3,option_3_value,option_3_msg)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$axis, $question,
                $opts[1]['text'], $opts[1]['value'], $opts[1]['msg'],
                $opts[2]['text'], $opts[2]['value'], $opts[2]['msg'],
                $opts[3]['text'], $opts[3]['value'], $opts[3]['msg']]);
        $_SESSION['admin_flash'] = 'تمت إضافة سؤال التحليل ✅';
    } else {
        $_SESSION['admin_flash'] = 'المحور والسؤال والخيارات الثلاثة كلها مطلوبة ⚠️';
    }
    header('Location: ?tab=assessment'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    $pdo->prepare("UPDATE quiz_questions SET active = CASE active WHEN 1 THEN 0 ELSE 1 END WHERE id = ?")->execute([(int)$_POST['toggle_id']]);
    header('Location: ?tab=assessment'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $pdo->prepare("DELETE FROM quiz_questions WHERE id = ?")->execute([(int)$_POST['delete_id']]);
    header('Location: ?tab=assessment'); exit;
}

$questions = $pdo->query("SELECT * FROM quiz_questions ORDER BY axis, id")->fetchAll();
$axes = array_values(array_unique(array_column($questions, 'axis')));
$activeCount = count(array_filter($questions, fn($q) => $q['active']));
?>
<h2>أسئلة التحليل</h2>
<p style="color:#667;">
  الأسئلة التي تظهر للطفل في شاشة التحليل اليومي. المُفعَّل منها
  <b><?php echo $activeCount; ?></b> من <?php echo count($questions); ?>،
  موزّعة على <?php echo count($axes); ?> محوراً.
  المحور هو ما يُقاس عليه نمو الطفل شهرياً، فاستخدم اسم محور موجود إلا إذا أردت محوراً جديداً فعلاً.
</p>

<div class="admin-form">
  <h3 style="margin-top:0;">إضافة سؤال تحليل</h3>
  <form method="POST">
    <div class="row">
      <input name="axis" list="axisList" placeholder="المحور — مثال: الثقة بالنفس" required>
      <datalist id="axisList">
        <?php foreach ($axes as $a): ?><option value="<?php echo h($a); ?>"><?php endforeach; ?>
      </datalist>
    </div>
    <input name="question" placeholder="نص السؤال" required style="width:100%;margin-bottom:10px;">
    <?php foreach ([1 => 3, 2 => 2, 3 => 1] as $i => $defaultValue): ?>
      <div class="row">
        <input name="option_<?php echo $i; ?>" placeholder="الخيار <?php echo $i; ?>" required>
        <select name="value_<?php echo $i; ?>">
          <?php foreach ([3, 2, 1] as $v): ?>
            <option value="<?php echo $v; ?>" <?php echo $v === $defaultValue ? 'selected' : ''; ?>>القيمة <?php echo $v; ?></option>
          <?php endforeach; ?>
        </select>
        <input name="msg_<?php echo $i; ?>" placeholder="رد الرفيق على هذا الخيار">
      </div>
    <?php endforeach; ?>
    <p style="margin:0 0 10px;color:#667;font-size:13px;">
      لا يوجد خيار «خاطئ»: القيمة 1 تعني «يحتاج تدريباً»، ورد الرفيق عليها يجب أن يشجّع لا أن يوبّخ.
    </p>
    <button type="submit" name="add_question" class="btn btn-primary">إضافة السؤال</button>
  </form>
</div>

<table class="admin-table">
  <thead><tr><th>المحور</th><th>السؤال</th><th>الخيارات</th><th>الحالة</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($questions as $q): ?>
      <tr style="<?php echo $q['active'] ? '' : 'opacity:.5;'; ?>">
        <td><?php echo h($q['axis']); ?></td>
        <td><?php echo h($q['question']); ?></td>
        <td style="font-size:13px;line-height:1.9;">
          <?php foreach ([1, 2, 3] as $i): ?>
            <div>
              <b><?php echo (int)$q["option_{$i}_value"]; ?></b> — <?php echo h($q["option_{$i}"]); ?><br>
              <span style="color:#667;"><?php echo h((string)$q["option_{$i}_msg"]); ?></span>
            </div>
          <?php endforeach; ?>
        </td>
        <td><?php echo $q['active'] ? '<span style="color:#1B7F4C;">مُفعَّل</span>' : '<span style="color:#B54708;">معطّل</span>'; ?></td>
        <td style="display:flex;gap:6px;">
          <form method="POST"><input type="hidden" name="toggle_id" value="<?php echo (int)$q['id']; ?>"><button class="btn btn-sm btn-ghost"><?php echo $q['active'] ? 'تعطيل' : 'تشغيل'; ?></button></form>
          <form method="POST" onsubmit="return confirm('حذف؟');"><input type="hidden" name="delete_id" value="<?php echo (int)$q['id']; ?>"><button class="btn btn-sm btn-ghost">حذف</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
