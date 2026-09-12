<?php
// استرجاع كلمة المرور — الخطوة الثانية: اختيار كلمة مرور جديدة برمز من البريد
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['child_id'])) { header('Location: ' . BASE_PATH . '/dashboard.php'); exit; }

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error = null;
$reset = null;

if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $st = $pdo->prepare("SELECT r.*, c.name child_name FROM password_resets r JOIN children c ON c.id = r.child_id
                         WHERE r.token_hash = ? AND r.used_at IS NULL LIMIT 1");
    $st->execute([hash('sha256', $token)]);
    $reset = $st->fetch() ?: null;
    if ($reset && strtotime($reset['expires_at']) < time()) {
        $reset = null;
        $error = 'انتهت صلاحية هذا الرابط. اطلب رابطاً جديداً.';
    }
}
if (!$reset && $error === null) {
    $error = 'هذا الرابط غير صالح أو استُخدم من قبل. اطلب رابطاً جديداً.';
}

if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_password'])) {
    $p1 = $_POST['password'] ?? '';
    $p2 = $_POST['confirm_password'] ?? '';
    if (strlen($p1) < 6) {
        $formError = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.';
    } elseif ($p1 !== $p2) {
        $formError = 'كلمتا المرور غير متطابقتين.';
    } else {
        $pdo->prepare("UPDATE children SET password = ? WHERE id = ?")
            ->execute([password_hash($p1, PASSWORD_DEFAULT), $reset['child_id']]);
        // هذا الرمز استُهلك، وكل رمز آخر معلّق لنفس الحساب يسقط معه
        $pdo->prepare("UPDATE password_resets SET used_at = ? WHERE child_id = ? AND used_at IS NULL")
            ->execute([date('Y-m-d H:i:s'), $reset['child_id']]);
        $_SESSION['flash_login'] = 'تم تغيير كلمة المرور بنجاح ✅ سجّل الدخول بكلمتك الجديدة.';
        header('Location: ' . BASE_PATH . '/index.php#auth'); exit;
    }
}

$__pageTitle = 'كلمة مرور جديدة — Kidora';
$__publicNavCompact = true;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/public-nav.php';
require_once __DIR__ . '/includes/public-card.php';
?>
<main class="pc-page">
  <div class="pc-card">
    <div class="pc-icon">🔐</div>
    <h1>كلمة مرور جديدة</h1>
    <?php if (!$reset): ?>
      <div class="pc-msg err">❌ <?php echo h($error); ?></div>
      <p class="pc-links"><a href="<?php echo h(BASE_PATH . '/forgot-password.php'); ?>">طلب رابط جديد</a></p>
    <?php else: ?>
      <p class="pc-lead">لحساب <b><?php echo h($reset['child_name']); ?></b> — اختر كلمة مرور جديدة من 6 أحرف على الأقل.</p>
      <?php if (!empty($formError)): ?><div class="pc-msg err">❌ <?php echo h($formError); ?></div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="token" value="<?php echo h($token); ?>">
        <div class="pc-field"><label for="newPassword">كلمة المرور الجديدة</label><input id="newPassword" type="password" name="password" minlength="6" autocomplete="new-password" required autofocus></div>
        <div class="pc-field"><label for="confirmNew">تأكيد كلمة المرور</label><input id="confirmNew" type="password" name="confirm_password" minlength="6" autocomplete="new-password" required></div>
        <button type="submit" name="set_password" class="pc-btn">احفظ كلمة المرور ✅</button>
      </form>
    <?php endif; ?>
    <p class="pc-links"><a href="<?php echo h(BASE_PATH . '/index.php#auth'); ?>">← الرجوع لتسجيل الدخول</a></p>
  </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
