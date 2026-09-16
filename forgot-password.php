<?php
// استرجاع كلمة المرور — الخطوة الأولى: طلب رابط عبر بريد ولي الأمر
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

if (!empty($_SESSION['child_id'])) { header('Location: ' . BASE_PATH . '/dashboard.php'); exit; }

/** الرمز صالح لهذه المدة (بالدقائق) */
const PASSWORD_RESET_TTL_MIN = 60;
/** أقصى عدد طلبات لنفس البريد خلال ساعة — يمنع إغراق صندوق ولي الأمر */
const PASSWORD_RESET_MAX_PER_HOUR = 3;

$flash = $_SESSION['flash_reset'] ?? null;
unset($_SESSION['flash_reset']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $email = trim($_POST['email'] ?? '');
    // الرد واحد دائماً حتى لا يكشف النموذج أي بريد مسجّل وأيّه لا
    $neutral = ['type' => 'ok', 'text' => 'إذا كان هذا البريد مسجّلاً عندنا فستصلك رسالة فيها رابط تعيين كلمة مرور جديدة خلال دقائق. تحقّق من مجلد الرسائل غير المرغوبة أيضاً.'];

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_reset'] = ['type' => 'err', 'text' => 'اكتب البريد الإلكتروني الذي سُجّل به الحساب.'];
        header('Location: forgot-password.php'); exit;
    }
    if (!mail_is_configured($pdo)) {
        $_SESSION['flash_reset'] = ['type' => 'err', 'text' => 'خدمة البريد غير مفعّلة حالياً. تواصل مع المنصة عبر واتساب لاسترجاع الحساب.'];
        header('Location: forgot-password.php'); exit;
    }

    $st = $pdo->prepare("SELECT id, name, email FROM children WHERE email = ?");
    $st->execute([$email]);
    $child = $st->fetch();

    if ($child) {
        $cnt = $pdo->prepare("SELECT COUNT(*) c FROM password_resets WHERE child_id = ? AND created_at >= ?");
        $cnt->execute([$child['id'], date('Y-m-d H:i:s', time() - 3600)]);
        if ((int)$cnt->fetch()['c'] < PASSWORD_RESET_MAX_PER_HOUR) {
            $token = bin2hex(random_bytes(32));
            // created_at يُكتب من PHP (توقيت التطبيق) لا من DEFAULT CURRENT_TIMESTAMP (UTC في SQLite/MySQL)،
            // وإلا اختلف عن مقارنة حدّ المحاولات أعلاه بثلاث ساعات ولم يعمل الحدّ أبداً.
            $ins = $pdo->prepare("INSERT INTO password_resets (child_id, token_hash, expires_at, created_at) VALUES (?,?,?,?)");
            $ins->execute([$child['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + PASSWORD_RESET_TTL_MIN * 60), date('Y-m-d H:i:s')]);

            // خلف Cloudflare → Traefik يصل الطلب إلى الحاوية بـ http، وقد لا يُمرَّر X-Forwarded-Proto؛
            // نقرأ CF-Visitor أيضاً، وأي مضيف غير محلي يُعامل كـ https حتى لا يصل رابط http في البريد.
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $isLocal = preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/', $host) === 1;
            $cfVisitor = json_decode($_SERVER['HTTP_CF_VISITOR'] ?? '', true);
            $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
                || (($cfVisitor['scheme'] ?? '') === 'https')
                || !$isLocal) ? 'https' : 'http';
            $link = "{$scheme}://{$host}" . BASE_PATH . '/reset-password.php?token=' . $token;

            $body = '<p dir="rtl" style="direction:rtl;text-align:right;margin:0 0 12px;">مرحباً،</p>'
                . '<p dir="rtl" style="direction:rtl;text-align:right;margin:0 0 12px;">وصلنا طلب لتعيين كلمة مرور جديدة لحساب <b>' . h($child['name']) . '</b> على منصة Kidora.</p>'
                . '<p dir="rtl" style="direction:rtl;text-align:right;margin:0 0 12px;">اضغط الزر التالي لاختيار كلمة مرور جديدة. الرابط صالح لمدة <b>' . PASSWORD_RESET_TTL_MIN . ' دقيقة</b> ولمرة واحدة.</p>'
                . '<p dir="rtl" style="direction:rtl;text-align:right;font-size:12px;color:#8b7aa8;word-break:break-all;margin:0 0 12px;">أو انسخ الرابط: ' . h($link) . '</p>'
                . '<p dir="rtl" style="direction:rtl;text-align:right;margin:0 0 12px;">إن لم تطلب ذلك فتجاهل هذه الرسالة، ولن يتغيّر شيء في الحساب.</p>';
            $html = mail_template('تعيين كلمة مرور جديدة', $body, $link, 'تعيين كلمة المرور 🔑');
            $text = "مرحباً،\nوصلنا طلب لتعيين كلمة مرور جديدة لحساب {$child['name']} على منصة Kidora.\n"
                . "افتح الرابط التالي (صالح " . PASSWORD_RESET_TTL_MIN . " دقيقة ولمرة واحدة):\n{$link}\n\n"
                . "إن لم تطلب ذلك فتجاهل هذه الرسالة.";

            $err = send_mail($pdo, $child['email'], 'Kidora — تعيين كلمة مرور جديدة', $html, $text);
            if ($err !== null) {
                error_log('[kidora] password reset mail failed for child ' . $child['id'] . ': ' . $err);
                $_SESSION['flash_reset'] = ['type' => 'err', 'text' => 'تعذّر إرسال الرسالة الآن. حاول بعد قليل أو تواصل مع المنصة عبر واتساب.'];
                header('Location: forgot-password.php'); exit;
            }
        }
    }

    $_SESSION['flash_reset'] = $neutral;
    header('Location: forgot-password.php'); exit;
}

$__pageTitle = 'استرجاع كلمة المرور — Kidora';
$__publicNavCompact = true;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/public-nav.php';
require_once __DIR__ . '/includes/public-card.php';
?>
<main class="pc-page">
  <div class="pc-card">
    <div class="pc-icon">🔑</div>
    <h1>نسيت كلمة المرور؟</h1>
    <p class="pc-lead">اكتب بريد ولي الأمر المسجّل، ونرسل إليه رابطاً لاختيار كلمة مرور جديدة.</p>
    <?php if ($flash): ?>
      <div class="pc-msg <?php echo $flash['type'] === 'ok' ? 'ok' : 'err'; ?>"><?php echo $flash['type'] === 'ok' ? '📬 ' : '❌ '; echo h($flash['text']); ?></div>
    <?php endif; ?>
    <?php if (!$flash || $flash['type'] !== 'ok'): ?>
    <form method="POST">
      <div class="pc-field"><label for="resetEmail">البريد الإلكتروني</label><input id="resetEmail" type="email" name="email" autocomplete="email" required autofocus></div>
      <button type="submit" name="request_reset" class="pc-btn">أرسل رابط الاسترجاع 📨</button>
    </form>
    <?php endif; ?>
    <p class="pc-links"><a href="<?php echo h(BASE_PATH . '/index.php#auth'); ?>">← الرجوع لتسجيل الدخول</a></p>
  </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
