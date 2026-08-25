<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();

$myChars = array_filter([get_character($pdo, $child['character_1']), get_character($pdo, $child['character_2'])]);
$plans = $pdo->query("SELECT * FROM subscription_plans ORDER BY sort_order ASC")->fetchAll();
$activePlan = get_active_plan($pdo, $child['id']);

$__pageTitle = 'أهلاً بك — Kidora';
$__pageLine = "أهلاً {$child['name']}! أنا بكل حماس رح ارافقك بمغامرتك 🎉";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<div class="page-body">
<main class="container" style="padding-top:26px;">

  <div class="welcome-banner" style="flex-direction:column;text-align:center;padding:40px 24px;">
    <div style="font-size:56px;">🎉</div>
    <h2 class="section-title" style="margin:10px 0 6px;">أهلاً وسهلاً <?php echo h($child['name']); ?>!</h2>
    <p style="opacity:.95;max-width:520px;">حسابك جاهز، وشخصياتك بانتظارك ترافقك بكل خطوة. قبل ما نبدأ، هاي نظرة سريعة على خطط الاشتراك المتاحة — تقدر تكمّل بالخطة المجانية أو تفعّل خطة أقوى بأي وقت لاحقاً.</p>
  </div>

  <div class="section">
    <div class="section-head">
      <div class="eyebrow">رفقاء رحلتك</div>
      <h2 class="section-title">شخصياتك</h2>
    </div>
    <div class="characters-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
      <?php foreach ($myChars as $c): ?>
        <div class="character-card" style="--card-color:<?php echo h($c['color']); ?>;">
          <div class="character-media">
            <?php if (!empty($c['image_path'])): ?><img src="<?php echo h($c['image_path']); ?>"><?php else: ?>
              <div class="fallback-emoji" style="background:linear-gradient(150deg, <?php echo h($c['color']); ?>, #fff2);"><?php echo character_icons($c)[0] ?? '✨'; ?></div>
            <?php endif; ?>
            <div class="character-info-overlay"><h3><?php echo h($c['name']); ?></h3><p><?php echo h($c['title']); ?></p><p class="quote">"<?php echo h($c['trait']); ?>"</p></div>
          </div>
          <div class="name"><?php echo h($c['name']); ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="section">
    <div class="section-head">
      <div class="eyebrow">خطط المنصة</div>
      <h2 class="section-title">اختر ما يناسبكم (أو تخطَّ الآن وقرّر لاحقاً)</h2>
    </div>
    <div class="characters-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
      <?php foreach ($plans as $p): $features = json_decode_safe($p['features_json'], []); ?>
        <div class="card" style="padding:22px;">
          <h3 style="margin:0 0 4px;color:var(--ink);"><?php echo h($p['name']); ?></h3>
          <div style="font-family:var(--font-display);font-size:22px;color:var(--coral);margin-bottom:10px;">
            <?php echo (int)$p['price_ils']===0 ? 'مجانية' : (int)$p['price_ils'].' ₪'; ?>
            <span style="font-size:12px;color:var(--ink-soft);font-family:var(--font-body);"><?php echo h($p['billing_cycle']); ?></span>
          </div>
          <ul style="padding-inline-start:16px;color:var(--ink-soft);line-height:1.9;font-size:14px;">
            <?php foreach (array_slice($features,0,3) as $f): ?><li><?php echo h($f); ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:18px;">
      <a href="subscriptions.php" class="btn btn-gold">شاهد التفاصيل الكاملة للخطط</a>
    </div>
  </div>

  <div style="text-align:center;margin:40px 0;">
    <a href="assessment.php" class="btn btn-primary" style="font-size:18px;padding:16px 40px;">ابدأ تحليل شخصيتي 🚀</a>
  </div>
</main>
</div>
<footer class="site-footer">Kidora © 2026</footer>
<script>window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
