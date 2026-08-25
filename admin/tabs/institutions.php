<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_inst'])) {
    $name = trim($_POST['name']); $email = trim($_POST['email']);
    if ($name && $email) {
        $pdo->prepare("INSERT INTO institutions (name, email) VALUES (?,?)")->execute([$name, $email]);
        $_SESSION['admin_flash'] = 'تمت إضافة المؤسسة ✅';
    }
    header('Location: ?tab=institutions'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_inst_id'])) {
    $pdo->prepare("DELETE FROM institutions WHERE id = ?")->execute([(int)$_POST['delete_inst_id']]);
    header('Location: ?tab=institutions'); exit;
}
$institutions = $pdo->query("SELECT * FROM institutions ORDER BY id DESC")->fetchAll();
?>
<h2>إدارة المؤسسات المشرفة</h2>
<div class="admin-form">
  <h3 style="margin-top:0;">إضافة مؤسسة كأدمن مشرف</h3>
  <form method="POST">
    <div class="row"><input name="name" placeholder="اسم المؤسسة" required><input name="email" type="email" placeholder="البريد الإلكتروني للمشرف" required></div>
    <button type="submit" name="add_inst" class="btn btn-primary">إضافة المؤسسة</button>
  </form>
</div>
<table class="admin-table">
  <thead><tr><th>اسم المؤسسة</th><th>البريد الإلكتروني</th><th>تاريخ الإضافة</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($institutions as $i): ?>
      <tr><td><?php echo h($i['name']); ?></td><td><?php echo h($i['email']); ?></td><td><?php echo date('Y-m-d', strtotime($i['created_at'])); ?></td>
      <td><form method="POST" onsubmit="return confirm('حذف؟');"><input type="hidden" name="delete_inst_id" value="<?php echo (int)$i['id']; ?>"><button class="btn btn-sm btn-ghost">حذف</button></form></td></tr>
    <?php endforeach; ?>
  </tbody>
</table>
