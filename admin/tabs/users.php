<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trial_child_id'])) {
    $childId = (int)$_POST['trial_child_id'];
    $action = (string)($_POST['trial_action'] ?? 'set');
    if ($action === 'default') {
        $pdo->prepare("UPDATE children SET trial_ends_at = NULL WHERE id = ?")->execute([$childId]);
        $_SESSION['admin_flash'] = 'عادت نهاية التجربة إلى سبعة أيام من تاريخ إنشاء الحساب.';
    } else {
        $raw = trim((string)($_POST['trial_ends_at'] ?? ''));
        $end = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $raw);
        $errors = DateTimeImmutable::getLastErrors();
        if ($end && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            $pdo->prepare("UPDATE children SET trial_ends_at = ? WHERE id = ?")
                ->execute([$end->format('Y-m-d H:i:s'), $childId]);
            $_SESSION['admin_flash'] = 'تم حفظ نهاية التجربة المجانية.';
        } else {
            $_SESSION['admin_flash'] = 'تاريخ نهاية التجربة غير صالح.';
        }
    }
    header('Location: ?tab=users'); exit;
}

$sort = $_GET['sort'] ?? 'created_at';
$allowedSort = ['created_at','name','age','points'];
if (!in_array($sort, $allowedSort, true)) $sort = 'created_at';
$users = $pdo->query("SELECT * FROM children ORDER BY {$sort} DESC")->fetchAll();

function admin_user_analysis(PDO $pdo, int $childId): ?array {
    $rows = assessment_axis_summary($pdo, $childId);
    return $rows ?: null;
}
?>
<h2>جدول المستخدمين</h2>
<div style="margin-bottom:12px;display:flex;gap:8px;">
  <a href="?tab=users&sort=name" class="btn btn-sm btn-ghost">فرز بالاسم</a>
  <a href="?tab=users&sort=age" class="btn btn-sm btn-ghost">فرز بالعمر</a>
  <a href="?tab=users&sort=points" class="btn btn-sm btn-ghost">فرز بالنقاط</a>
</div>
<table class="admin-table">
  <thead><tr><th>اسم الطفل</th><th>عمر</th><th>ولي الأمر</th><th>واتساب</th><th>البريد</th><th>النقاط</th><th>المغامرة</th><th>الخطة</th><th>التجربة</th><th>التحليل</th></tr></thead>
  <tbody>
    <?php foreach ($users as $u):
      $plan = get_active_plan($pdo, $u['id']);
      $rec = get_subscription_record($pdo, $u['id']);
      $statusBadge = $rec && $rec['status']==='pending' ? ' <span style="color:var(--coral);">⏳</span>' : ($rec && $rec['status']==='active' ? ' <span style="color:var(--mint);">✅</span>' : '');
      $analysis = admin_user_analysis($pdo, $u['id']);
      $trialEnd = trial_ends_at_for($u);
      $tier = access_tier($pdo, $u);
      $trialInput = $trialEnd ? $trialEnd->format('Y-m-d\TH:i') : '';
    ?>
    <tr>
      <td><?php echo h($u['name']); ?> <?php echo (int)$plan && $plan['price_ils']>0 ? '<span style="color:#2D6CDF;">✔️</span>' : ''; ?></td>
      <td><?php echo (int)$u['age']; ?></td>
      <td><?php echo h($u['parent_name']); ?></td>
      <td><?php echo h($u['parent_phone']); ?></td>
      <td><?php echo h($u['email']); ?></td>
      <td><?php echo (int)$u['points']; ?></td>
      <td><?php echo (int)$u['ring_days']; ?>/30</td>
      <td><?php echo $rec ? h($rec['name']) : '-'; ?><?php echo $statusBadge; ?></td>
      <td style="min-width:230px;">
        <div style="font-size:12px;margin-bottom:6px;color:<?php echo $tier === 'trial' ? 'var(--mint)' : ($tier === 'paid' ? '#2D6CDF' : 'var(--coral)'); ?>;">
          <?php echo $tier === 'paid' ? 'مشترك — الوصول الكامل مفعّل' : ($tier === 'trial' ? 'تجربة سارية' : 'التجربة منتهية'); ?>
        </div>
        <form method="POST" style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;">
          <input type="hidden" name="trial_child_id" value="<?php echo (int)$u['id']; ?>">
          <input type="datetime-local" name="trial_ends_at" value="<?php echo h($trialInput); ?>" style="max-width:170px;padding:6px;">
          <button class="btn btn-sm btn-gold" name="trial_action" value="set">حفظ</button>
          <button class="btn btn-sm btn-ghost" name="trial_action" value="default" title="سبعة أيام من إنشاء الحساب">الافتراضي</button>
        </form>
      </td>
      <td>
        <?php if ($analysis): ?>
          <button class="btn btn-sm btn-ghost" type="button" onclick="document.getElementById('an-<?php echo (int)$u['id']; ?>').classList.toggle('hidden')">📊 عرض</button>
        <?php else: ?><span style="color:var(--ink-soft);font-size:12px;">لا يوجد بعد</span><?php endif; ?>
      </td>
    </tr>
    <?php if ($analysis): $total = array_sum(array_column($analysis, 'c')); ?>
    <tr id="an-<?php echo (int)$u['id']; ?>" class="hidden">
      <td colspan="10" style="background:#F6F2FF;padding:14px 18px;">
        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
          <b>تحليل سلوك <?php echo h($u['name']); ?></b>
          <span style="color:var(--ink-soft);font-size:12px;"><?php echo (int)$total; ?> إجابة · آخر تحليل: <?php echo $u['last_assessment_at'] ? h(date('Y-m-d', strtotime($u['last_assessment_at']))) : '—'; ?></span>
        </div>
        <div style="background:#fff;border-radius:16px;padding:12px 14px;">
          <?php echo behavior_radar_svg($analysis); ?>
        </div>
      </td>
    </tr>
    <?php endif; ?>
    <?php endforeach; ?>
  </tbody>
</table>
