<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();

$plans = $pdo->query("SELECT * FROM subscription_plans ORDER BY sort_order ASC")->fetchAll();
$subRec = get_subscription_record($pdo, $child['id']);

// ---------------- معالجة طلب اشتراك ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_plan_id'])) {
    $planId = (int)$_POST['request_plan_id'];
    $plan = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ?"); $plan->execute([$planId]); $plan = $plan->fetch();

    if ($plan) {
        if ((int)$plan['price_ils'] === 0) {
            // الخطة المجانية تُفعَّل مباشرة
            $pdo->prepare("DELETE FROM subscriptions WHERE child_id = ?")->execute([$child['id']]);
            $pdo->prepare("INSERT INTO subscriptions (child_id, plan_id, status, activated_at, activated_by) VALUES (?,?,'active',CURRENT_TIMESTAMP,'system')")->execute([$child['id'], $planId]);
            $_SESSION['flash_toast'] = 'تم تفعيل الخطة المجانية 🎉';
        } else {
            $pdo->prepare("DELETE FROM subscriptions WHERE child_id = ?")->execute([$child['id']]);
            $pdo->prepare("INSERT INTO subscriptions (child_id, plan_id, status, requested_at) VALUES (?,?,'pending',CURRENT_TIMESTAMP)")->execute([$child['id'], $planId]);

            $msg = "مرحباً Kidora 👋\nأرغب بالاشتراك بخطة \"{$plan['name']}\" ({$plan['price_ils']} ₪ / {$plan['billing_cycle']}) لطفلي {$child['name']} ({$child['age']} سنوات).\nولي الأمر: {$child['parent_name']} — {$child['parent_phone']}\nالبريد المسجّل: {$child['email']}\nبانتظار التواصل لإتمام الدفع وتفعيل الاشتراك 🙏";
            log_wa($pdo, $child['id'], 'subscription_request', $msg);
            $_SESSION['flash_wa_link'] = whatsapp_link($pdo, $msg);
            $_SESSION['flash_toast'] = 'تم تسجيل طلبك! جاري تحويلك لواتساب لإتمام التواصل 📲';
        }
    }
    header('Location: subscriptions.php' . (isset($_POST['welcome']) ? '?welcome=1' : '')); exit;
}

$subRec = get_subscription_record($pdo, $child['id']);
$activePlan = get_active_plan($pdo, $child['id']);
$flashToast = $_SESSION['flash_toast'] ?? null;
$flashWaLink = $_SESSION['flash_wa_link'] ?? null;
unset($_SESSION['flash_toast'], $_SESSION['flash_wa_link']);

// أول واجهة بعد التسجيل: نفس الصفحة مع ترحيب وزر متابعة حتى لا يعلق الطفل هنا
$isWelcome = isset($_GET['welcome']);

$__pageTitle = 'خطط الاشتراك — Kidora';
$__pageLine = $isWelcome
    ? "أهلاً فيك يا بطل! اختار خطتك، وإذا بدك تبلّش على طول اضغط متابعة 🚀"
    : "اشتراكك المدفوع بيفتحلك شخصيات وألعاب وقصص أكتر بكتير! يلا نشترك سوا 💳";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<div class="page-body">
<main class="container" style="padding-top:26px;">
  <div class="section-head">
    <div class="eyebrow"><?php echo $isWelcome ? 'أهلاً بك في Kidora 🎉' : 'خطط المنصة'; ?></div>
    <h2 class="section-title">اختر خطة اشتراكك (بالشيكل ₪)</h2>
    <p class="section-sub">
      <?php if ($isWelcome): ?>
        تم إنشاء حسابك بنجاح! خطتك المجانية مفعّلة، وتقدر تبدأ مغامرتك فوراً. الاشتراك المدفوع يفتح باقي الشخصيات، مهام وقصص غير محدودة، وتقارير واتساب لولي الأمر.
      <?php else: ?>
        الاشتراك المدفوع يفتح باقي الشخصيات، مهام وقصص غير محدودة، وتقارير واتساب لولي الأمر.
      <?php endif; ?>
    </p>
  </div>

  <?php if ($isWelcome): ?>
    <div style="text-align:center;margin-bottom:22px;">
      <a href="welcome.php" class="btn btn-gold">تخطّي الآن وابدأ مغامرتي 🚀</a>
    </div>
  <?php endif; ?>

  <?php if ($subRec && $subRec['status'] === 'pending'): ?>
    <div class="card" style="padding:22px;margin-bottom:20px;background:var(--cream-2);text-align:center;">
      <p style="font-weight:800;color:var(--coral);">📲 طلبك لخطة "<?php echo h($subRec['name']); ?>" قيد المراجعة! سيتواصل فريق Kidora معكم عبر واتساب لإتمام الدفع، وسيُفعَّل الاشتراك فور موافقة الإدارة.</p>
    </div>
  <?php endif; ?>

  <div class="characters-grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr));">
    <?php foreach ($plans as $p):
        $features = json_decode_safe($p['features_json'], []);
        $isMine = $subRec && (int)$subRec['plan_id'] === (int)$p['id'];
        $isActive = $isMine && $subRec['status'] === 'active';
        $isPending = $isMine && $subRec['status'] === 'pending';
    ?>
      <div class="card" style="padding:26px;text-align:right;<?php echo $isActive ? 'box-shadow:0 0 0 3px var(--gold), var(--shadow-soft);' : ''; ?>">
        <h3 style="margin:0 0 4px;color:var(--ink);"><?php echo h($p['name']); ?></h3>
        <div style="font-family:var(--font-display);font-size:24px;color:var(--coral);margin-bottom:12px;">
          <?php echo (int)$p['price_ils'] === 0 ? 'مجانية' : (int)$p['price_ils'].' ₪'; ?>
          <span style="font-size:13px;color:var(--ink-soft);font-family:var(--font-body);"><?php echo h($p['billing_cycle']); ?></span>
        </div>
        <ul style="padding-inline-start:18px;color:var(--ink-soft);line-height:2;">
          <?php foreach ($features as $f): ?><li><?php echo h($f); ?></li><?php endforeach; ?>
        </ul>
        <form method="POST">
          <input type="hidden" name="request_plan_id" value="<?php echo (int)$p['id']; ?>">
          <?php if ($isWelcome): ?><input type="hidden" name="welcome" value="1"><?php endif; ?>
          <?php if ($isActive): ?>
            <button class="btn btn-ghost btn-block" disabled>خطتك المفعّلة ✅</button>
          <?php elseif ($isPending): ?>
            <button class="btn btn-ghost btn-block" disabled>طلبك قيد المراجعة ⏳</button>
          <?php else: ?>
            <button type="submit" class="btn <?php echo (int)$p['price_ils']===0 ? 'btn-mint' : 'btn-primary'; ?> btn-block">
              <?php echo (int)$p['price_ils']===0 ? 'فعّل الخطة المجانية' : 'اشترك عبر واتساب 📲'; ?>
            </button>
          <?php endif; ?>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</main>
</div>
<footer class="site-footer">Kidora © 2026</footer>
<script>
  window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;
  <?php if ($flashToast): ?>
    document.addEventListener('DOMContentLoaded', function(){
      const wrap = document.getElementById('toastWrap');
      const el = document.createElement('div'); el.className='toast'; el.textContent = <?php echo json_encode($flashToast, JSON_UNESCAPED_UNICODE); ?>;
      wrap.appendChild(el); setTimeout(()=>el.remove(), 4000);
      <?php if ($flashWaLink): ?>
        window.open(<?php echo json_encode($flashWaLink); ?>, '_blank');
      <?php endif; ?>
    });
  <?php endif; ?>
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
