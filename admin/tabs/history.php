<?php
function admin_figure_from_post(array $post): array {
    $youtubeRaw = trim($post['youtube_id'] ?? '');
    return [
        'name'        => trim($post['name'] ?? ''),
        'title'       => trim($post['title'] ?? ''),
        'description' => trim($post['description'] ?? ''),
        'story_line'  => trim($post['story_line'] ?? ''),
        'category'    => trim($post['category'] ?? ''),
        'youtube_raw' => $youtubeRaw,
        'youtube_id'  => youtube_id_from_input($youtubeRaw),
    ];
}

function admin_figure_save_redirect(int $editId = 0): void {
    header('Location: ?tab=history' . ($editId ? '&edit_figure=' . $editId : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_figure'])) {
    $f = admin_figure_from_post($_POST);
    if ($f['youtube_raw'] !== '' && $f['youtube_id'] === null) {
        $_SESSION['admin_flash'] = 'رابط اليوتيوب غير مفهوم ⚠️ الصق رابط الفيديو كاملاً أو معرّفه (11 حرفاً)';
    } elseif ($f['name'] && $f['description']) {
        $pdo->prepare("INSERT INTO history_figures (name,title,description,youtube_id,story_line,category) VALUES (?,?,?,?,?,?)")
            ->execute([$f['name'],$f['title'],$f['description'],$f['youtube_id'],$f['story_line'],$f['category']]);
        $_SESSION['admin_flash'] = 'تمت الإضافة ✅';
    }
    admin_figure_save_redirect();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_figure_id'])) {
    $id = (int)$_POST['edit_figure_id'];
    $f = admin_figure_from_post($_POST);
    if ($f['youtube_raw'] !== '' && $f['youtube_id'] === null) {
        $_SESSION['admin_flash'] = 'رابط اليوتيوب غير مفهوم ⚠️ الصق رابط الفيديو كاملاً أو معرّفه (11 حرفاً)';
        admin_figure_save_redirect($id);
    }
    if ($f['name'] && $f['description']) {
        $pdo->prepare("UPDATE history_figures SET name=?, title=?, description=?, youtube_id=?, story_line=?, category=? WHERE id=?")
            ->execute([$f['name'],$f['title'],$f['description'],$f['youtube_id'],$f['story_line'],$f['category'],$id]);
        $_SESSION['admin_flash'] = 'تم حفظ تعديل الشخصية ✅';
    } else {
        $_SESSION['admin_flash'] = 'الاسم والوصف مطلوبان ⚠️';
        admin_figure_save_redirect($id);
    }
    admin_figure_save_redirect();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_figure_id'])) {
    $pdo->prepare("DELETE FROM history_figures WHERE id = ?")->execute([(int)$_POST['delete_figure_id']]);
    header('Location: ?tab=history'); exit;
}

$editFigure = null;
if (!empty($_GET['edit_figure'])) {
    $st = $pdo->prepare("SELECT * FROM history_figures WHERE id = ?");
    $st->execute([(int)$_GET['edit_figure']]);
    $editFigure = $st->fetch() ?: null;
}

$figures = $pdo->query("SELECT * FROM history_figures ORDER BY id DESC")->fetchAll();
$form = $editFigure ?: [
    'name' => '', 'title' => '', 'description' => '', 'story_line' => '',
    'category' => '', 'youtube_id' => '',
];
?>
<h2>شخصيات تاريخية عربية وإسلامية</h2>
<p style="color:var(--ink-soft);max-width:720px;">
  البيانات هنا من قاعدة الموقع المباشرة. لتعديل فيديو شخصية موجودة اضغطي «تعديل» والصقي رابط يوتيوب.
  الشخصية المرتبطة بالمهمة مباشرةً (من تبويب المهام) لها الأولوية، وإلا تُختار من نفس تصنيف المهمة.
</p>
<div class="admin-form" id="figureForm">
  <h3 style="margin-top:0;"><?php echo $editFigure ? 'تعديل شخصية: ' . h($editFigure['name']) : 'إضافة شخصية تاريخية'; ?></h3>
  <form method="POST">
    <?php if ($editFigure): ?><input type="hidden" name="edit_figure_id" value="<?php echo (int)$editFigure['id']; ?>"><?php endif; ?>
    <div class="row">
      <input name="name" placeholder="الاسم" required value="<?php echo h((string)$form['name']); ?>">
      <input name="title" placeholder="اللقب/الوصف القصير" value="<?php echo h((string)$form['title']); ?>">
      <input name="category" placeholder="التصنيف (يطابق تصنيف المهمة)" value="<?php echo h((string)$form['category']); ?>">
      <input name="youtube_id" placeholder="الصقي رابط يوتيوب أو المعرّف (اختياري)" value="<?php echo h((string)($form['youtube_id'] ?? '')); ?>">
    </div>
    <div class="row">
      <input name="description" placeholder="وصف تعريفي قصير" style="grid-column:span 2;" required value="<?php echo h((string)$form['description']); ?>">
      <input name="story_line" placeholder="جملة الأثر الإيجابي على الطفل" style="grid-column:span 2;" value="<?php echo h((string)$form['story_line']); ?>">
    </div>
    <?php if ($editFigure): ?>
      <button type="submit" class="btn btn-primary">حفظ التعديل</button>
      <a href="?tab=history" class="btn btn-ghost">إلغاء</a>
    <?php else: ?>
      <button type="submit" name="add_figure" class="btn btn-primary">إضافة</button>
    <?php endif; ?>
  </form>
</div>
<div class="admin-card-list">
  <?php foreach ($figures as $f): ?>
    <div class="admin-item-card" style="<?php echo $editFigure && (int)$editFigure['id'] === (int)$f['id'] ? 'outline:3px solid var(--gold);' : ''; ?>">
      <div style="font-size:32px;">🕌</div>
      <b><?php echo h($f['name']); ?></b>
      <p style="font-size:13px;color:var(--ink-soft);"><?php echo h($f['title']); ?></p>
      <?php if (!empty($f['category'])): ?><span class="pill"><?php echo h($f['category']); ?></span><?php endif; ?>
      <?php if ($f['youtube_id']): ?>
        <p style="font-size:11px;color:var(--mint);">🎬 <?php echo h($f['youtube_id']); ?></p>
      <?php else: ?>
        <p style="font-size:11px;color:var(--ink-soft);">بدون فيديو</p>
      <?php endif; ?>
      <div class="admin-item-actions">
        <a href="?tab=history&edit_figure=<?php echo (int)$f['id']; ?>#figureForm" class="btn btn-sm btn-gold">تعديل</a>
        <form method="POST" onsubmit="return confirm('حذف؟');"><input type="hidden" name="delete_figure_id" value="<?php echo (int)$f['id']; ?>"><button class="btn btn-sm btn-ghost">حذف</button></form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
