<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $title = trim($_POST['title']); $desc = trim($_POST['description']); $cat = trim($_POST['category']) ?: 'عام';
    $ageMin = (int)$_POST['age_min']; $ageMax = (int)$_POST['age_max'];
    $story = trim($_POST['story_line']) ?: "أنجز البطل مهمة {$title} بنجاح! ✨";
    $youtube = trim($_POST['youtube_id']);
    $points = (int)($_POST['points'] ?: 5);
    $gameType = in_array($_POST['game_type'] ?? '', ['catch','match','quiz'], true) ? $_POST['game_type'] : 'catch';
    if ($title && $desc) {
        $pdo->prepare("INSERT INTO tasks (title,description,category,age_min,age_max,story_line,youtube_id,points,game_type) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$title,$desc,$cat,$ageMin ?: 4,$ageMax ?: 12,$story,$youtube ?: null,$points,$gameType]);
        $_SESSION['admin_flash'] = 'تمت إضافة المهمة ✅';
    }
    header('Location: ?tab=tasks'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task_id'])) {
    $pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([(int)$_POST['delete_task_id']]);
    header('Location: ?tab=tasks'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_task_id'])) {
    $pdo->prepare("UPDATE tasks SET active = 1 - active WHERE id = ?")->execute([(int)$_POST['toggle_task_id']]);
    header('Location: ?tab=tasks'); exit;
}
$tasks = $pdo->query("SELECT * FROM tasks ORDER BY id DESC")->fetchAll();
?>
<h2>إدارة المهام</h2>
<p style="color:var(--ink-soft);max-width:640px;">كل مهمة = عنوان + وصف يُقرأ بصوت شخصية الطفل + قصة سطرين + فيديو يوتيوب (اختياري) + لعبة قصيرة بعدها. كلّه بجدول واحد.</p>
<div class="admin-form">
  <h3 style="margin-top:0;">إضافة مهمة جديدة (باكج اليوم = 4 مهام تُختار عشوائياً حسب العمر)</h3>
  <form method="POST">
    <div class="row">
      <input name="title" placeholder="عنوان المهمة" required>
      <input name="category" placeholder="التصنيف (تعلّم/صحة/إبداع..)">
      <input name="age_min" type="number" placeholder="أصغر عمر" min="4" max="12">
      <input name="age_max" type="number" placeholder="أكبر عمر" min="4" max="12">
    </div>
    <div class="row">
      <input name="description" placeholder="وصف المهمة (سيُقرأ بصوت الشخصية)" style="grid-column:span 2;" required>
      <input name="story_line" placeholder="قصة السطرين عند الإنجاز" style="grid-column:span 2;">
    </div>
    <div class="row">
      <input name="youtube_id" placeholder="معرّف فيديو يوتيوب متعلّق بالمهمة (اختياري)">
      <input name="points" type="number" value="5" placeholder="النقاط">
      <select name="game_type">
        <option value="catch">لعبة التقاط</option>
        <option value="match">لعبة ذاكرة</option>
        <option value="quiz">سباق أسئلة</option>
      </select>
    </div>
    <button type="submit" name="add_task" class="btn btn-primary">إضافة المهمة</button>
  </form>
</div>
<table class="admin-table">
  <thead><tr><th>العنوان</th><th>التصنيف</th><th>الفئة العمرية</th><th>يوتيوب</th><th>اللعبة</th><th>الحالة</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($tasks as $t): ?>
      <tr>
        <td><?php echo h($t['title']); ?></td><td><?php echo h($t['category']); ?></td><td><?php echo (int)$t['age_min']; ?>-<?php echo (int)$t['age_max']; ?></td>
        <td><?php echo $t['youtube_id'] ? '✅' : '-'; ?></td>
        <td><?php echo h($t['game_type'] ?: 'catch'); ?></td>
        <td><?php echo $t['active'] ? '<span style="color:var(--mint);">مفعّلة</span>' : '<span style="color:var(--coral);">معطّلة</span>'; ?></td>
        <td>
          <form method="POST" style="display:inline;"><input type="hidden" name="toggle_task_id" value="<?php echo (int)$t['id']; ?>"><button class="btn btn-sm btn-ghost">تبديل</button></form>
          <form method="POST" style="display:inline;" onsubmit="return confirm('حذف؟');"><input type="hidden" name="delete_task_id" value="<?php echo (int)$t['id']; ?>"><button class="btn btn-sm btn-ghost">حذف</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
