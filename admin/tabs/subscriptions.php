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
/** حقول الخطة من النموذج (إضافة أو تعديل) — الميزات سطر لكل ميزة أو مفصولة بفاصلة */
function admin_plan_from_post(array $post): array {
    $features = preg_split('/[\n,]+/u', $post['features'] ?? '') ?: [];
    $features = array_values(array_filter(array_map('trim', $features), fn($f) => $f !== ''));
    return [
        'name'      => trim($post['name'] ?? ''),
        'price_ils' => max(0, (int)($post['price_ils'] ?? 0)),
        'cycle'     => trim($post['cycle'] ?? '') ?: 'شهرياً',
        'features'  => json_encode($features, JSON_UNESCAPED_UNICODE),
        'sort'      => (int)($post['sort_order'] ?? 99),
    ];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_plan'])) {
    $p = admin_plan_from_post($_POST);
    if ($p['name'] !== '') {
        $pdo->prepare("INSERT INTO subscription_plans (name, price_ils, billing_cycle, features_json, sort_order) VALUES (?,?,?,?,?)")
            ->execute([$p['name'], $p['price_ils'], $p['cycle'], $p['features'], $p['sort'] ?: 99]);
        $_SESSION['admin_flash'] = 'تمت إضافة الخطة ✅';
    }
    header('Location: ?tab=subscriptions'); exit;
}
// تعديل خطة قائمة: الاسم والسعر والدورة والميزات والترتيب — الاشتراكات المرتبطة
// بها تبقى كما هي (plan_id لا يتغيّر)، فتغيير السعر لا يقطع اشتراكاً مفعّلاً.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_plan_id'])) {
    $p = admin_plan_from_post($_POST);
    if ($p['name'] !== '') {
        $pdo->prepare("UPDATE subscription_plans SET name = ?, price_ils = ?, billing_cycle = ?, features_json = ?, sort_order = ? WHERE id = ?")
            ->execute([$p['name'], $p['price_ils'], $p['cycle'], $p['features'], $p['sort'], (int)$_POST['edit_plan_id']]);
        $_SESSION['admin_flash'] = 'تم حفظ تعديل الخطة ✅';
    } else {
        $_SESSION['admin_flash'] = 'اسم الخطة مطلوب ⚠️';
    }
    header('Location: ?tab=subscriptions'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_plan_id'])) {
    $planId = (int)$_POST['delete_plan_id'];
    $inUse = $pdo->prepare("SELECT COUNT(*) c FROM subscriptions WHERE plan_id = ?");
    $inUse->execute([$planId]);
    if ((int)$inUse->fetch()['c'] > 0) {
        $_SESSION['admin_flash'] = 'لا يمكن حذف خطة عليها اشتراكات — عدّلها بدل حذفها ⚠️';
    } else {
        $pdo->prepare("DELETE FROM subscription_plans WHERE id = ?")->execute([$planId]);
        $_SESSION['admin_flash'] = 'تم حذف الخطة';
    }
    header('Location: ?tab=subscriptions'); exit;
}
$editPlan = null;
if (!empty($_GET['edit_plan'])) {
    $st = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ?");
    $st->execute([(int)$_GET['edit_plan']]);
    $editPlan = $st->fetch() ?: null;
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

<?php
$formPlan = $editPlan ?: ['name' => '', 'price_ils' => '', 'billing_cycle' => '', 'features_json' => '[]', 'sort_order' => ''];
$formFeatures = implode("\n", json_decode_safe($formPlan['features_json'], []));
?>
<div class="admin-form" id="planForm">
  <h3 style="margin-top:0;"><?php echo $editPlan ? 'تعديل خطة: ' . h($editPlan['name']) : 'إضافة خطة جديدة'; ?> (الأسعار بالشيكل ₪)</h3>
  <form method="POST">
    <?php if ($editPlan): ?><input type="hidden" name="edit_plan_id" value="<?php echo (int)$editPlan['id']; ?>"><?php endif; ?>
    <div class="row">
      <input name="name" placeholder="اسم الخطة" required value="<?php echo h((string)$formPlan['name']); ?>">
      <input name="price_ils" type="number" min="0" placeholder="السعر بالشيكل، مثال: 15 — صفر = مجانية" value="<?php echo h((string)$formPlan['price_ils']); ?>">
      <input name="cycle" list="cycleList" placeholder="الدورة (شهرياً / سنوياً / -)" value="<?php echo h((string)$formPlan['billing_cycle']); ?>">
      <datalist id="cycleList"><option value="شهرياً"><option value="سنوياً"><option value="-"></datalist>
      <input name="sort_order" type="number" min="1" placeholder="الترتيب في العرض (1 = الأولى)" value="<?php echo h((string)$formPlan['sort_order']); ?>">
    </div>
    <div class="row">
      <textarea name="features" rows="4" placeholder="الميزات — ميزة في كل سطر" style="grid-column:1 / -1;"><?php echo h($formFeatures); ?></textarea>
    </div>
    <?php if ($editPlan): ?>
      <button type="submit" class="btn btn-primary">حفظ التعديل</button>
      <a href="?tab=subscriptions" class="btn btn-ghost">إلغاء</a>
    <?php else: ?>
      <button type="submit" name="add_plan" class="btn btn-primary">إضافة خطة جديدة</button>
    <?php endif; ?>
  </form>
</div>
<div class="admin-card-list" style="margin-bottom:30px;">
  <?php foreach ($plans as $p): $features = json_decode_safe($p['features_json'], []); ?>
    <div class="admin-item-card" style="<?php echo $editPlan && (int)$editPlan['id'] === (int)$p['id'] ? 'outline:3px solid var(--gold);' : ''; ?>">
      <b><?php echo h($p['name']); ?></b> — <span style="color:var(--coral);font-weight:800;"><?php echo (int)$p['price_ils'] === 0 ? 'مجانية' : (int)$p['price_ils'] . ' ₪'; ?></span>
      <span style="font-size:12px;color:var(--ink-soft);"><?php echo h((string)$p['billing_cycle']); ?> · ترتيب <?php echo (int)$p['sort_order']; ?></span>
      <ul style="font-size:12px;color:var(--ink-soft);padding-inline-start:16px;"><?php foreach ($features as $f) echo '<li>'.h($f).'</li>'; ?></ul>
      <div class="admin-item-actions">
        <a href="?tab=subscriptions&edit_plan=<?php echo (int)$p['id']; ?>#planForm" class="btn btn-sm btn-gold">تعديل</a>
        <form method="POST" onsubmit="return confirm('حذف الخطة؟');"><input type="hidden" name="delete_plan_id" value="<?php echo (int)$p['id']; ?>"><button class="btn btn-sm btn-ghost">حذف</button></form>
      </div>
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
