<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['is_admin'])) { header('Location: index.php'); exit; }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    if (!kidora_login_allowed($pdo, 'admin', $email)) {
        $error = 'محاولات كثيرة. حاول بعد 15 دقيقة.';
    } elseif ($email === ADMIN_EMAIL && hash_equals(ADMIN_PASSWORD, $pass)) {
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        header('Location: index.php'); exit;
    }
    $error ??= 'بيانات الدخول غير صحيحة';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<script src="../assets/js/security.js?v=<?= filemtime(__DIR__ . '/../assets/js/security.js') ?>"></script>
<title>لوحة تحكم Kidora</title>
<link rel="icon" type="image/png" href="<?php echo h(KIDORA_FAVICON); ?>">
<link rel="shortcut icon" type="image/png" href="<?php echo h(KIDORA_FAVICON); ?>">
<link rel="apple-touch-icon" href="<?php echo h(KIDORA_FAVICON); ?>">
<link href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@700;800&family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/main.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body style="background:radial-gradient(circle at 20% 20%, #2E1A5C, #1B1035 60%);min-height:100vh;">
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo">🛠️ Kidora Admin</div>
    <p class="auth-sub">لوحة تحكم فريق Kidora</p>
    <?php if ($error): ?><div class="auth-error">❌ <?php echo h($error); ?></div><?php endif; ?>
    <form method="POST">
      <?= csrf_field() ?>
      <div class="field"><label>البريد الإلكتروني</label><input type="email" name="email" required></div>
      <div class="field"><label>كلمة المرور</label><input type="password" name="password" required></div>
      <button type="submit" class="btn btn-gold btn-block">دخول لوحة التحكم</button>
    </form>
    <div class="auth-toggle"><a href="../index.php">↩️ رجوع للمنصة</a></div>
  </div>
</div>
</body>
</html>
