<?php
$sort = $_GET['sort'] ?? 'created_at';
$allowedSort = ['created_at','name','age','points'];
if (!in_array($sort, $allowedSort, true)) $sort = 'created_at';
$users = $pdo->query("SELECT * FROM children ORDER BY {$sort} DESC")->fetchAll();

function admin_user_analysis(PDO $pdo, int $childId): ?array {
    $stmt = $pdo->prepare("SELECT axis, AVG(value) avg_v, COUNT(*) c FROM quiz_history WHERE child_id=? GROUP BY axis");
    $stmt->execute([$childId]);
    $rows = $stmt->fetchAll();
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
  <thead><tr><th>اسم الطفل</th><th>عمر</th><th>ولي الأمر</th><th>واتساب</th><th>البريد</th><th>النقاط</th><th>المغامرة</th><th>الخطة</th><th>التحليل</th></tr></thead>
  <tbody>
    <?php foreach ($users as $u):
      $plan = get_active_plan($pdo, $u['id']);
      $rec = get_subscription_record($pdo, $u['id']);
      $statusBadge = $rec && $rec['status']==='pending' ? ' <span style="color:var(--coral);">⏳</span>' : ($rec && $rec['status']==='active' ? ' <span style="color:var(--mint);">✅</span>' : '');
      $analysis = admin_user_analysis($pdo, $u['id']);
    ?>
    <tr>
      <td><?php echo h($u['name']); ?> <?php echo (int)$plan && $plan['price_ils']>0 ? '<span style="color:#2D6CDF;">✔️</span>' : ''; ?></td>
      <td><?php echo (int)$u['age']; ?></td>
      <td><?php echo h($u['parent_name']); ?></td>
      <td><?php echo h($u['parent_phone']); ?></td>
      <td><?php echo h($u['email']); ?></td>
      <td><?php echo (int)$u['points']; ?></td>
      <td><?php echo (int)$u['ring_days']; ?>/10</td>
      <td><?php echo $rec ? h($rec['name']) : '-'; ?><?php echo $statusBadge; ?></td>
      <td>
        <?php if ($analysis):
          $lines = [];
          foreach ($analysis as $r) { $lines[] = $r['axis'] . ': ' . number_format($r['avg_v'],1) . ' / 3'; }
          $summary = implode("\n", $lines);
        ?>
          <button class="btn btn-sm btn-ghost" onclick="alert(<?php echo json_encode($summary, JSON_UNESCAPED_UNICODE); ?>)">📊 عرض</button>
        <?php else: ?><span style="color:var(--ink-soft);font-size:12px;">لا يوجد بعد</span><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
