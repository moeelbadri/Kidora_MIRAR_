<?php
/**
 * حقول المهمة من النموذج (إضافة أو تعديل).
 * youtube_id يُطبَّع إلى المعرّف (11 حرفاً) — رابط Shorts كامل يُرفض إن لم يُفهم.
 */
function admin_task_from_post(array $post): array {
    $title = trim($post['title'] ?? '');
    $youtubeRaw = trim($post['youtube_id'] ?? '');
    $youtube = youtube_id_from_input($youtubeRaw);
    return [
        'title'       => $title,
        'description' => trim($post['description'] ?? ''),
        'category'    => trim($post['category'] ?? '') ?: 'عام',
        'age_min'     => (int)($post['age_min'] ?? 0) ?: 4,
        'age_max'     => (int)($post['age_max'] ?? 0) ?: 12,
        // جملة اسمية: سطر القصة يظهر بجوار اسم الطفل، والتطبيق لا يسجّل جنسه
        'story_line'  => trim($post['story_line'] ?? '') ?: ($title !== '' ? "مهمة «{$title}» منجزة بنجاح! ✨" : ''),
        'youtube_raw' => $youtubeRaw,
        'youtube_id'  => $youtube,
        'points'      => (int)($post['points'] ?? 0) ?: 5,
        'game_type'   => array_key_exists($post['game_type'] ?? '', game_types()) ? $post['game_type'] : 'catch',
        'game_title'  => trim($post['game_title'] ?? '') ?: 'لعبة قصيرة',
        'figure_id'   => (int)($post['figure_id'] ?? 0) ?: null,
    ];
}

function admin_task_save_redirect(int $editId = 0): void {
    header('Location: ?tab=tasks' . ($editId ? '&edit_task=' . $editId : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $t = admin_task_from_post($_POST);
    if ($t['youtube_raw'] !== '' && $t['youtube_id'] === null) {
        $_SESSION['admin_flash'] = 'رابط اليوتيوب غير مفهوم ⚠️ الصق رابط الفيديو كاملاً أو معرّفه (11 حرفاً)';
    } elseif ($t['title'] && $t['description']) {
        $pdo->prepare("INSERT INTO tasks (title,description,category,age_min,age_max,story_line,youtube_id,points,game_type,game_title,figure_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$t['title'],$t['description'],$t['category'],$t['age_min'],$t['age_max'],$t['story_line'],$t['youtube_id'],$t['points'],$t['game_type'],$t['game_title'],$t['figure_id']]);
        $_SESSION['admin_flash'] = 'تمت إضافة المهمة ✅';
    }
    admin_task_save_redirect();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_task_id'])) {
    $id = (int)$_POST['edit_task_id'];
    $t = admin_task_from_post($_POST);
    if ($t['youtube_raw'] !== '' && $t['youtube_id'] === null) {
        $_SESSION['admin_flash'] = 'رابط اليوتيوب غير مفهوم ⚠️ الصق رابط الفيديو كاملاً أو معرّفه (11 حرفاً)';
        admin_task_save_redirect($id);
    }
    if ($t['title'] && $t['description']) {
        $pdo->prepare("UPDATE tasks SET title=?, description=?, category=?, age_min=?, age_max=?, story_line=?, youtube_id=?, points=?, game_type=?, game_title=?, figure_id=? WHERE id=?")
            ->execute([$t['title'],$t['description'],$t['category'],$t['age_min'],$t['age_max'],$t['story_line'],$t['youtube_id'],$t['points'],$t['game_type'],$t['game_title'],$t['figure_id'],$id]);
        $_SESSION['admin_flash'] = 'تم حفظ تعديل المهمة ✅';
    } else {
        $_SESSION['admin_flash'] = 'العنوان والوصف مطلوبان ⚠️';
        admin_task_save_redirect($id);
    }
    admin_task_save_redirect();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task_id'])) {
    $pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([(int)$_POST['delete_task_id']]);
    header('Location: ?tab=tasks'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_task_id'])) {
    $pdo->prepare("UPDATE tasks SET active = 1 - active WHERE id = ?")->execute([(int)$_POST['toggle_task_id']]);
    header('Location: ?tab=tasks'); exit;
}

$editTask = null;
if (!empty($_GET['edit_task'])) {
    $st = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $st->execute([(int)$_GET['edit_task']]);
    $editTask = $st->fetch() ?: null;
}

$tasks = $pdo->query("SELECT t.*, f.name figure_name FROM tasks t LEFT JOIN history_figures f ON f.id = t.figure_id ORDER BY t.id DESC")->fetchAll();
$figures = $pdo->query("SELECT id, name, category FROM history_figures WHERE active = 1 ORDER BY name")->fetchAll();
$form = $editTask ?: [
    'title' => '', 'description' => '', 'category' => '', 'age_min' => '', 'age_max' => '',
    'story_line' => '', 'youtube_id' => '', 'points' => 5, 'game_type' => 'catch',
    'game_title' => '', 'figure_id' => '',
];
$missingVideo = count(array_filter($tasks, fn($row) => empty($row['youtube_id'])));
?>
<h2>إدارة المهام</h2>
<p style="color:var(--ink-soft);max-width:720px;">
  هذه هي مهام الموقع المباشرة — ليست ملف SQLite. عدّلي أي مهمة موجودة من زر «تعديل»
  والصقي رابط يوتيوب (watch أو Shorts أو youtu.be). يُحفظ المعرّف فقط، فيظهر الفيديو للطفل داخل باكج المهمة.
</p>
<p style="color:var(--ink-soft);max-width:720px;">
  باكج المهمة = عنوان + وصف يُقرأ بصوت الشخصية + فيديو + شخصية تاريخية + لعبة قصيرة.
  إن تركتِ الشخصية فارغة تُختار من نفس تصنيف المهمة تلقائياً.
  <?php if ($missingVideo): ?>
    <b style="color:var(--coral);"><?php echo $missingVideo; ?> مهمة بلا فيديو.</b>
  <?php endif; ?>
</p>
<div class="admin-form" id="taskForm">
  <h3 style="margin-top:0;"><?php echo $editTask ? 'تعديل مهمة: ' . h($editTask['title']) : 'إضافة مهمة جديدة'; ?></h3>
  <form method="POST">
    <?php if ($editTask): ?><input type="hidden" name="edit_task_id" value="<?php echo (int)$editTask['id']; ?>"><?php endif; ?>
    <div class="row">
      <input name="title" placeholder="عنوان المهمة" required value="<?php echo h((string)$form['title']); ?>">
      <input name="category" placeholder="التصنيف (تعلّم/صحة/إبداع..)" value="<?php echo h((string)$form['category']); ?>">
      <input name="age_min" type="number" placeholder="أصغر عمر" min="4" max="12" value="<?php echo h((string)$form['age_min']); ?>">
      <input name="age_max" type="number" placeholder="أكبر عمر" min="4" max="12" value="<?php echo h((string)$form['age_max']); ?>">
    </div>
    <div class="row">
      <input name="description" placeholder="وصف المهمة (سيُقرأ بصوت الشخصية)" style="grid-column:span 2;" required value="<?php echo h((string)$form['description']); ?>">
      <input name="story_line" placeholder="قصة السطرين عند الإنجاز" style="grid-column:span 2;" value="<?php echo h((string)$form['story_line']); ?>">
    </div>
    <div class="row">
      <input name="youtube_id" placeholder="الصقي رابط يوتيوب أو المعرّف — مثال: https://youtube.com/shorts/…" value="<?php echo h((string)($form['youtube_id'] ?? '')); ?>">
      <input name="points" type="number" placeholder="النقاط" value="<?php echo h((string)$form['points']); ?>">
      <select name="game_type">
        <?php foreach (game_types() as $slug => $label): ?>
          <option value="<?php echo h($slug); ?>" <?php echo ($form['game_type'] ?? '') === $slug ? 'selected' : ''; ?>><?php echo h($label); ?></option>
        <?php endforeach; ?>
      </select>
      <input name="game_title" placeholder="اسم اللعبة المعروض" value="<?php echo h((string)$form['game_title']); ?>">
    </div>
    <div class="row">
      <select name="figure_id" style="grid-column:span 2;">
        <option value="">الشخصية التاريخية — تلقائي حسب تصنيف المهمة</option>
        <?php foreach ($figures as $f): ?>
          <option value="<?php echo (int)$f['id']; ?>" <?php echo (int)($form['figure_id'] ?? 0) === (int)$f['id'] ? 'selected' : ''; ?>><?php echo h($f['name']); ?><?php echo $f['category'] ? ' — ' . h($f['category']) : ''; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($editTask): ?>
      <button type="submit" class="btn btn-primary">حفظ التعديل</button>
      <a href="?tab=tasks" class="btn btn-ghost">إلغاء</a>
    <?php else: ?>
      <button type="submit" name="add_task" class="btn btn-primary">إضافة المهمة</button>
    <?php endif; ?>
  </form>
</div>
<table class="admin-table">
  <thead><tr><th>العنوان</th><th>التصنيف</th><th>الفئة العمرية</th><th>يوتيوب</th><th>الشخصية التاريخية</th><th>اللعبة</th><th>الحالة</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($tasks as $t): ?>
      <tr style="<?php echo $editTask && (int)$editTask['id'] === (int)$t['id'] ? 'outline:3px solid var(--gold);outline-offset:-3px;' : ''; ?>">
        <td><?php echo h($t['title']); ?></td><td><?php echo h($t['category']); ?></td><td><?php echo (int)$t['age_min']; ?>-<?php echo (int)$t['age_max']; ?></td>
        <td><?php echo $t['youtube_id'] ? '<code style="font-size:12px;">'.h($t['youtube_id']).'</code>' : '<span style="color:var(--ink-soft);">بدون فيديو</span>'; ?></td>
        <td><?php echo $t['figure_name'] ? h($t['figure_name']) : '<span style="color:var(--ink-soft);">تلقائي</span>'; ?></td>
        <td><?php echo h(game_types()[$t['game_type']] ?? ($t['game_type'] ?: 'catch')); ?></td>
        <td><?php echo $t['active'] ? '<span style="color:var(--mint);">مفعّلة</span>' : '<span style="color:var(--coral);">معطّلة</span>'; ?></td>
        <td>
          <a href="?tab=tasks&edit_task=<?php echo (int)$t['id']; ?>#taskForm" class="btn btn-sm btn-gold">تعديل</a>
          <form method="POST" style="display:inline;"><input type="hidden" name="toggle_task_id" value="<?php echo (int)$t['id']; ?>"><button class="btn btn-sm btn-ghost">تبديل</button></form>
          <form method="POST" style="display:inline;" onsubmit="return confirm('حذف؟');"><input type="hidden" name="delete_task_id" value="<?php echo (int)$t['id']; ?>"><button class="btn btn-sm btn-ghost">حذف</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
