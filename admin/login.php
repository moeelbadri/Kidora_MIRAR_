<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['is_admin'])) { header('Location: index.php'); exit; }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($email === ADMIN_EMAIL && $pass === ADMIN_PASSWORD) {
        $_SESSION['is_admin'] = true;
        header('Location: index.php'); exit;
    }
    $error = 'بيانات الدخول غير صحيحة';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>لوحة تحكم Kidora</title>
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
      <div class="field"><label>البريد الإلكتروني</label><input type="email" name="email" required></div>
      <div class="field"><label>كلمة المرور</label><input type="password" name="password" required></div>
      <button type="submit" class="btn btn-gold btn-block">دخول لوحة التحكم</button>
    </form>
    <div class="auth-toggle"><a href="../index.php">↩️ رجوع للمنصة</a></div>
  </div>
</div>
</body>
</html>
