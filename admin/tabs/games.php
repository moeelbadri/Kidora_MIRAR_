<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_game'])) {
    $title = trim($_POST['title']); $type = $_POST['type']; $cat = trim($_POST['category']) ?: 'تربوي';
    $ageMin = (int)$_POST['age_min'] ?: 4; $ageMax = (int)$_POST['age_max'] ?: 12;
    if ($title) {
        $pdo->prepare("INSERT INTO games (title,type,category,age_min,age_max) VALUES (?,?,?,?,?)")->execute([$title,$type,$cat,$ageMin,$ageMax]);
        $_SESSION['admin_flash'] = 'تمت إضافة اللعبة ✅';
    }
    header('Location: ?tab=games'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_game_id'])) {
    $pdo->prepare("DELETE FROM games WHERE id = ?")->execute([(int)$_POST['delete_game_id']]);
    header('Location: ?tab=games'); exit;
}
$games = $pdo->query("SELECT * FROM games ORDER BY category, id DESC")->fetchAll();
?>
<h2>إدارة الألعاب</h2>
<div class="admin-form">
  <h3 style="margin-top:0;">إضافة لعبة جديدة</h3>
  <form method="POST">
    <div class="row">
      <input name="title" placeholder="اسم اللعبة" required>
      <select name="type"><option value="catch">التقاط</option><option value="match">مطابقة</option><option value="jump">قفز</option></select>
      <select name="category">
        <option value="تربوي">تربوي</option><option value="علمي">علمي</option><option value="اجتماعي">اجتماعي</option>
        <option value="سلوكي">سلوكي</option><option value="ثقافي">ثقافي</option><option value="صحي">صحي</option>
      </select>
    </div>
    <div class="row">
      <input name="age_min" type="number" placeholder="أصغر عمر" min="4" max="12">
      <input name="age_max" type="number" placeholder="أكبر عمر" min="4" max="12">
    </div>
    <button type="submit" name="add_game" class="btn btn-primary">إضافة اللعبة</button>
  </form>
</div>
<table class="admin-table">
  <thead><tr><th>الاسم</th><th>النوع</th><th>المجال</th><th>الفئة العمرية</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($games as $g): ?>
      <tr><td><?php echo h($g['title']); ?></td><td><?php echo h($g['type']); ?></td><td><?php echo h($g['category']); ?></td><td><?php echo (int)$g['age_min']; ?>-<?php echo (int)$g['age_max']; ?></td>
      <td><form method="POST" onsubmit="return confirm('حذف؟');"><input type="hidden" name="delete_game_id" value="<?php echo (int)$g['id']; ?>"><button class="btn btn-sm btn-ghost">حذف</button></form></td></tr>
    <?php endforeach; ?>
  </tbody>
</table>
