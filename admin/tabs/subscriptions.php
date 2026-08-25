<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_child_id'])) {
    $childId = (int)$_POST['approve_child_id'];
    $pdo->prepare("UPDATE subscriptions SET status='active', activated_at=CURRENT_TIMESTAMP, activated_by='admin' WHERE child_id = ?")->execute([$childId]);
    $_SESSION['admin_flash'] = 'تم ترقية الاشتراك بنجاح ✅';
    header('Location: ?tab=subscriptions'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_child_id'])) {
    $childId = (int)$_POST['reject_child_id'];
    $pdo->prepare("DELETE FROM subscriptions WHERE child_id = ?")->execute([$childId]);
    $_SESSION['admin_flash'] = 'تم رفض/إلغاء الطلب';
    header('Location: ?tab=subscriptions'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_plan'])) {
    $name = trim($_POST['name']); $price = (int)$_POST['price_ils']; $cycle = trim($_POST['cycle']) ?: 'شهرياً';
    $features = array_filter(array_map('trim', explode(',', $_POST['features'] ?? '')));
    if ($name !== '') {
        $pdo->prepare("INSERT INTO subscription_plans (name, price_ils, billing_cycle, features_json, sort_order) VALUES (?,?,?,?,99)")
            ->execute([$name, $price, $cycle, json_encode($features, JSON_UNESCAPED_UNICODE)]);
        $_SESSION['admin_flash'] = 'تمت إضافة الخطة ✅';
    }
    header('Location: ?tab=subscriptions'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_plan_id'])) {
    $pdo->prepare("DELETE FROM subscription_plans WHERE id = ?")->execute([(int)$_POST['delete_plan_id']]);
    header('Location: ?tab=subscriptions'); exit;
}

$pending = $pdo->query("SELECT s.*, c.name child_name, c.parent_name, c.parent_phone, c.email, p.name plan_name, p.price_ils, p.billing_cycle
                         FROM subscriptions s JOIN children c ON c.id=s.child_id JOIN subscription_plans p ON p.id=s.plan_id
                         WHERE s.status='pending' ORDER BY s.requested_at ASC")->fetchAll();
$active = $pdo->query("SELECT s.*, c.name child_name, p.name plan_name FROM subscriptions s JOIN children c ON c.id=s.child_id JOIN subscription_plans p ON p.id=s.plan_id WHERE s.status='active' ORDER BY s.activated_at DESC")->fetchAll();
$plans = $pdo->query("SELECT * FROM subscription_plans ORDER BY sort_order")->fetchAll();
$waNumber = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='whatsapp_number'")->fetch()['setting_value'] ?? '';
?>
<h2>إدارة خطط الاشتراك والترقيات</h2>

<?php if ($pending): ?>
<h3 style="color:var(--coral);">⏳ طلبات بانتظار الترقية (<?php echo count($pending); ?>)</h3>
<p style="color:var(--ink-soft);">تواصل مع ولي الأمر عبر واتساب لإتمام الدفع، ثم اضغط "ترقية" لتفعيل الاشتراك فوراً.</p>
<table class="admin-table" style="margin-bottom:30px;">
  <thead><tr><th>الطفل</th><th>ولي الأمر</th><th>واتساب</th><th>الخطة المطلوبة</th><th>تاريخ الطلب</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($pending as $s):
      $waMsg = rawurlencode("مرحباً {$s['parent_name']}، بخصوص اشتراك {$s['child_name']} في خطة {$s['plan_name']} على Kidora...");
      $waLink = "https://wa.me/" . preg_replace('/\D/','',$s['parent_phone']) . "?text={$waMsg}";
    ?>
    <tr>
      <td><?php echo h($s['child_name']); ?></td><td><?php echo h($s['parent_name']); ?></td>
      <td><a href="<?php echo $waLink; ?>" target="_blank" style="color:var(--mint);"><?php echo h($s['parent_phone']); ?> 📲</a></td>
      <td><?php echo h($s['plan_name']); ?> (<?php echo (int)$s['price_ils']; ?> ₪)</td>
      <td><?php echo date('Y-m-d', strtotime($s['requested_at'])); ?></td>
      <td>
        <form method="POST" style="display:inline;"><input type="hidden" name="approve_child_id" value="<?php echo (int)$s['child_id']; ?>"><button class="btn btn-sm btn-gold">✅ ترقية</button></form>
        <form method="POST" style="display:inline;"><input type="hidden" name="reject_child_id" value="<?php echo (int)$s['child_id']; ?>"><button class="btn btn-sm btn-ghost">رفض</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<div class="admin-form">
  <h3 style="margin-top:0;">إضافة/تعديل خطة (الأسعار بالشيكل ₪)</h3>
  <form method="POST">
    <div class="row">
      <input name="name" placeholder="اسم الخطة" required>
      <input name="price_ils" type="number" placeholder="السعر بالشيكل، مثال: 49">
      <input name="cycle" placeholder="الدورة (شهرياً/-)">
    </div>
    <div class="row"><input name="features" placeholder="الميزات مفصولة بفاصلة ," style="grid-column:span 3;"></div>
    <button type="submit" name="add_plan" class="btn btn-primary">إضافة خطة جديدة</button>
  </form>
</div>
<div class="admin-card-list" style="margin-bottom:30px;">
  <?php foreach ($plans as $p): $features = json_decode_safe($p['features_json'], []); ?>
    <div class="admin-item-card">
      <b><?php echo h($p['name']); ?></b> — <span style="color:var(--coral);font-weight:800;"><?php echo (int)$p['price_ils']; ?> ₪</span>
      <ul style="font-size:12px;color:var(--ink-soft);padding-inline-start:16px;"><?php foreach ($features as $f) echo '<li>'.h($f).'</li>'; ?></ul>
      <div class="admin-item-actions"><form method="POST" onsubmit="return confirm('حذف؟');"><input type="hidden" name="delete_plan_id" value="<?php echo (int)$p['id']; ?>"><button class="btn btn-sm btn-ghost">حذف</button></form></div>
    </div>
  <?php endforeach; ?>
</div>

<h3>المشتركون المفعّلون (<?php echo count($active); ?>)</h3>
<table class="admin-table">
  <thead><tr><th>الطفل</th><th>الخطة</th><th>فُعّلت في</th></tr></thead>
  <tbody>
    <?php foreach ($active as $s): ?>
      <tr><td><?php echo h($s['child_name']); ?></td><td><?php echo h($s['plan_name']); ?></td><td><?php echo $s['activated_at'] ? date('Y-m-d', strtotime($s['activated_at'])) : '-'; ?></td></tr>
    <?php endforeach; ?>
  </tbody>
</table>
