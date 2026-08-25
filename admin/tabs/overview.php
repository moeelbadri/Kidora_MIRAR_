<?php
$totalUsers = (int)$pdo->query("SELECT COUNT(*) c FROM children")->fetch()['c'];
$totalStories = (int)$pdo->query("SELECT COUNT(*) c FROM daily_stories")->fetch()['c'];
$totalGrand = (int)$pdo->query("SELECT COUNT(*) c FROM grand_stories")->fetch()['c'];
$pendingCount = (int)$pdo->query("SELECT COUNT(*) c FROM subscriptions WHERE status='pending'")->fetch()['c'];

$plans = $pdo->query("SELECT * FROM subscription_plans ORDER BY sort_order")->fetchAll();
?>
<h2>نظرة عامة على المنصة</h2>
<div class="admin-grid">
  <div class="stat-card"><b><?php echo $totalUsers; ?></b> إجمالي المستخدمين</div>
  <div class="stat-card"><b><?php echo $totalStories; ?></b> قصة يومية أُنشئت</div>
  <div class="stat-card"><b><?php echo $totalGrand; ?></b> مغامرة كبرى مُنجزة</div>
  <div class="stat-card" style="<?php echo $pendingCount?'border:2px solid var(--coral);':''; ?>"><b style="<?php echo $pendingCount?'color:var(--coral);':''; ?>"><?php echo $pendingCount; ?></b> طلب اشتراك بانتظار الترقية</div>
</div>
<?php if ($pendingCount): ?><a href="?tab=subscriptions" class="btn btn-gold btn-sm" style="margin-bottom:20px;">مراجعة طلبات الاشتراك الآن</a><?php endif; ?>

<h3>توزيع المشتركين المفعّلين حسب الخطة</h3>
<div class="chart-wrap" style="max-width:520px;">
  <?php
  $countStmt = $pdo->prepare("SELECT COUNT(*) c FROM subscriptions WHERE plan_id=? AND status='active'");
  foreach ($plans as $p):
      $countStmt->execute([$p['id']]);
      $count = (int)$countStmt->fetch()['c'];
      $pct = $totalUsers ? ($count/$totalUsers*100) : 0;
  ?>
    <div class="chart-row"><div><?php echo h($p['name']); ?></div><div class="chart-bar-bg"><div class="chart-bar-fg" style="width:<?php echo $pct; ?>%;"></div></div><div><b><?php echo $count; ?></b></div></div>
  <?php endforeach; ?>
</div>
