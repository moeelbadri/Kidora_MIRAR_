<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $title = trim($_POST['title']); $desc = trim($_POST['description']); $cat = trim($_POST['category']) ?: 'عام';
    $ageMin = (int)$_POST['age_min']; $ageMax = (int)$_POST['age_max'];
    // جملة اسمية: سطر القصة يظهر بجوار اسم الطفل، والتطبيق لا يسجّل جنسه
    $story = trim($_POST['story_line']) ?: "مهمة «{$title}» منجزة بنجاح! ✨";
    $youtube = trim($_POST['youtube_id']);
    $points = (int)($_POST['points'] ?: 5);
    $gameType = array_key_exists($_POST['game_type'] ?? '', game_types()) ? $_POST['game_type'] : 'catch';
    $gameTitle = trim($_POST['game_title'] ?? '');
    $figureId = (int)($_POST['figure_id'] ?? 0) ?: null;
    if ($title && $desc) {
        $pdo->prepare("INSERT INTO tasks (title,description,category,age_min,age_max,story_line,youtube_id,points,game_type,game_title,figure_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$title,$desc,$cat,$ageMin ?: 4,$ageMax ?: 12,$story,$youtube ?: null,$points,$gameType,$gameTitle ?: 'لعبة قصيرة',$figureId]);
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
$tasks = $pdo->query("SELECT t.*, f.name figure_name FROM tasks t LEFT JOIN history_figures f ON f.id = t.figure_id ORDER BY t.id DESC")->fetchAll();
$figures = $pdo->query("SELECT id, name, category FROM history_figures WHERE active = 1 ORDER BY name")->fetchAll();
?>
<h2>إدارة المهام</h2>
<p style="color:var(--ink-soft);max-width:640px;">باكج المهمة الواحدة = عنوان + وصف يُقرأ بصوت شخصية الطفل + فيديو يوتيوب عن المهمة + شخصية تاريخية ذات صلة + لعبة قصيرة من نفس المجال. اربط الشخصية التاريخية يدوياً؛ إن تركتها فارغة تُختار شخصية من نفس تصنيف المهمة تلقائياً.</p>
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
      <input name="youtube_id" placeholder="معرّف فيديو يوتيوب عن المهمة (اختياري)">
      <input name="points" type="number" value="5" placeholder="النقاط">
      <select name="game_type">
        <?php foreach (game_types() as $slug => $label): ?>
          <option value="<?php echo h($slug); ?>"><?php echo h($label); ?></option>
        <?php endforeach; ?>
      </select>
      <input name="game_title" placeholder="اسم اللعبة المعروض">
    </div>
    <div class="row">
      <select name="figure_id" style="grid-column:span 2;">
        <option value="">الشخصية التاريخية — تلقائي حسب تصنيف المهمة</option>
        <?php foreach ($figures as $f): ?>
          <option value="<?php echo (int)$f['id']; ?>"><?php echo h($f['name']); ?><?php echo $f['category'] ? ' — ' . h($f['category']) : ''; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" name="add_task" class="btn btn-primary">إضافة المهمة</button>
  </form>
</div>
<table class="admin-table">
  <thead><tr><th>العنوان</th><th>التصنيف</th><th>الفئة العمرية</th><th>يوتيوب</th><th>الشخصية التاريخية</th><th>اللعبة</th><th>الحالة</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($tasks as $t): ?>
      <tr>
        <td><?php echo h($t['title']); ?></td><td><?php echo h($t['category']); ?></td><td><?php echo (int)$t['age_min']; ?>-<?php echo (int)$t['age_max']; ?></td>
        <td><?php echo $t['youtube_id'] ? '✅' : '-'; ?></td>
        <td><?php echo $t['figure_name'] ? h($t['figure_name']) : '<span style="color:var(--ink-soft);">تلقائي</span>'; ?></td>
        <td><?php echo h(game_types()[$t['game_type']] ?? ($t['game_type'] ?: 'catch')); ?></td>
        <td><?php echo $t['active'] ? '<span style="color:var(--mint);">مفعّلة</span>' : '<span style="color:var(--coral);">معطّلة</span>'; ?></td>
        <td>
          <form method="POST" style="display:inline;"><input type="hidden" name="toggle_task_id" value="<?php echo (int)$t['id']; ?>"><button class="btn btn-sm btn-ghost">تبديل</button></form>
          <form method="POST" style="display:inline;" onsubmit="return confirm('حذف؟');"><input type="hidden" name="delete_task_id" value="<?php echo (int)$t['id']; ?>"><button class="btn btn-sm btn-ghost">حذف</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
