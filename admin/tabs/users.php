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
      <td><?php echo (int)$u['ring_days']; ?>/30</td>
      <td><?php echo $rec ? h($rec['name']) : '-'; ?><?php echo $statusBadge; ?></td>
      <td>
        <?php if ($analysis): ?>
          <button class="btn btn-sm btn-ghost" type="button" onclick="document.getElementById('an-<?php echo (int)$u['id']; ?>').classList.toggle('hidden')">📊 عرض</button>
        <?php else: ?><span style="color:var(--ink-soft);font-size:12px;">لا يوجد بعد</span><?php endif; ?>
      </td>
    </tr>
    <?php if ($analysis): $total = array_sum(array_column($analysis, 'c')); ?>
    <tr id="an-<?php echo (int)$u['id']; ?>" class="hidden">
      <td colspan="9" style="background:#F6F2FF;padding:14px 18px;">
        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
          <b>تحليل سلوك <?php echo h($u['name']); ?></b>
          <span style="color:var(--ink-soft);font-size:12px;"><?php echo (int)$total; ?> إجابة · آخر تحليل: <?php echo $u['last_assessment_at'] ? h(date('Y-m-d', strtotime($u['last_assessment_at']))) : '—'; ?></span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px 18px;">
          <?php foreach ($analysis as $r): $pct = round(((float)$r['avg_v'] / 3) * 100); ?>
            <div style="display:grid;grid-template-columns:1fr auto;gap:4px 10px;align-items:center;font-size:13px;">
              <span><?php echo h($r['axis']); ?> <small style="color:var(--ink-soft);">(<?php echo (int)$r['c']; ?>)</small></span>
              <b style="color:<?php echo $pct >= 75 ? '#2E7D4F' : ($pct >= 50 ? '#B7791F' : '#C0392B'); ?>;"><?php echo number_format($r['avg_v'], 1); ?> / 3</b>
              <div style="grid-column:1/-1;height:8px;border-radius:999px;background:#E6E0F5;overflow:hidden;"><div style="width:<?php echo $pct; ?>%;height:100%;background:linear-gradient(90deg,#6C63FF,#2EC4B6);"></div></div>
            </div>
          <?php endforeach; ?>
        </div>
      </td>
    </tr>
    <?php endif; ?>
    <?php endforeach; ?>
  </tbody>
</table>
