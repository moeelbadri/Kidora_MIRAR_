<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_figure'])) {
    $name = trim($_POST['name']); $title = trim($_POST['title']); $desc = trim($_POST['description']);
    $youtube = trim($_POST['youtube_id']); $story = trim($_POST['story_line']);
    $category = trim($_POST['category'] ?? '');
    if ($name && $desc) {
        $pdo->prepare("INSERT INTO history_figures (name,title,description,youtube_id,story_line,category) VALUES (?,?,?,?,?,?)")
            ->execute([$name,$title,$desc,$youtube ?: null,$story,$category]);
        $_SESSION['admin_flash'] = 'تمت الإضافة ✅';
    }
    header('Location: ?tab=history'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_figure_id'])) {
    $pdo->prepare("DELETE FROM history_figures WHERE id = ?")->execute([(int)$_POST['delete_figure_id']]);
    header('Location: ?tab=history'); exit;
}
$figures = $pdo->query("SELECT * FROM history_figures ORDER BY id DESC")->fetchAll();
?>
<h2>شخصيات تاريخية عربية وإسلامية</h2>
<p style="color:var(--ink-soft);max-width:640px;">تظهر للطفل بعد إنجاز كل مهمة. الشخصية المرتبطة بالمهمة مباشرةً (من تبويب المهام) لها الأولوية، وإلا تُختار شخصية من نفس تصنيف المهمة — فاضبط التصنيف ليطابق تصنيفات المهام (تعلّم، صحة، قيم، إبداع، اجتماعي، ثقافي، حماية، مسؤولية، مهارات حياتية، صحة نفسية). أضف معرّف فيديو يوتيوب قصير إن توفّر.</p>
<div class="admin-form">
  <h3 style="margin-top:0;">إضافة شخصية تاريخية</h3>
  <form method="POST">
    <div class="row">
      <input name="name" placeholder="الاسم" required>
      <input name="title" placeholder="اللقب/الوصف القصير">
      <input name="category" placeholder="التصنيف (يطابق تصنيف المهمة)">
      <input name="youtube_id" placeholder="معرّف فيديو يوتيوب (اختياري)">
    </div>
    <div class="row">
      <input name="description" placeholder="وصف تعريفي قصير" style="grid-column:span 2;" required>
      <input name="story_line" placeholder="جملة الأثر الإيجابي على الطفل" style="grid-column:span 2;">
    </div>
    <button type="submit" name="add_figure" class="btn btn-primary">إضافة</button>
  </form>
</div>
<div class="admin-card-list">
  <?php foreach ($figures as $f): ?>
    <div class="admin-item-card">
      <div style="font-size:32px;">🕌</div>
      <b><?php echo h($f['name']); ?></b>
      <p style="font-size:13px;color:var(--ink-soft);"><?php echo h($f['title']); ?></p>
      <?php if (!empty($f['category'])): ?><span class="pill"><?php echo h($f['category']); ?></span><?php endif; ?>
      <?php if ($f['youtube_id']): ?><p style="font-size:11px;color:var(--mint);">🎬 فيديو مرفق</p><?php endif; ?>
      <div class="admin-item-actions"><form method="POST" onsubmit="return confirm('حذف؟');"><input type="hidden" name="delete_figure_id" value="<?php echo (int)$f['id']; ?>"><button class="btn btn-sm btn-ghost">حذف</button></form></div>
    </div>
  <?php endforeach; ?>
</div>
