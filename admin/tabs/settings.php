<?php
require_once __DIR__ . '/../../includes/mailer.php';

/** يكتب قيمة إعداد؛ يُنشئ الصف إن لم يكن موجوداً (الإعدادات الجديدة لا تُبذر في القواعد القديمة) */
function admin_set_setting(PDO $pdo, string $key, string $value): void {
    $upd = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    $upd->execute([$value, $key]);
    if ($upd->rowCount() === 0) {
        $chk = $pdo->prepare("SELECT COUNT(*) c FROM settings WHERE setting_key = ?");
        $chk->execute([$key]);
        if ((int)$chk->fetch()['c'] === 0) {
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?)")->execute([$key, $value]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $wa = trim($_POST['whatsapp_number']); $platform = trim($_POST['platform_name']) ?: 'Kidora'; $apiKey = trim($_POST['story_api_key'] ?? '');
    admin_set_setting($pdo, 'whatsapp_number', $wa);
    admin_set_setting($pdo, 'platform_name', $platform);
    admin_set_setting($pdo, 'story_api_key', $apiKey);
    $_SESSION['admin_flash'] = 'تم حفظ الإعدادات ✅';
    header('Location: ?tab=settings'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_mail'])) {
    foreach (['smtp_host', 'smtp_port', 'smtp_user', 'smtp_from', 'smtp_from_name', 'smtp_tls_name'] as $k) {
        admin_set_setting($pdo, $k, trim($_POST[$k] ?? ''));
    }
    // كلمة المرور تُحفظ فقط إن كُتبت — الحقل الفارغ يعني «أبقِ الحالية»
    if (trim($_POST['smtp_pass'] ?? '') !== '') admin_set_setting($pdo, 'smtp_pass', trim($_POST['smtp_pass']));
    $_SESSION['admin_flash'] = 'تم حفظ إعدادات البريد ✅';
    header('Location: ?tab=settings'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_mail'])) {
    $to = trim($_POST['test_to'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['admin_flash'] = '❌ اكتب بريداً صالحاً للتجربة';
    } else {
        $html = mail_template('رسالة تجريبية', '<p>هذه رسالة تجريبية من لوحة تحكم Kidora للتأكد أن إعدادات البريد تعمل.</p><p>الوقت: ' . h(date('Y-m-d H:i')) . '</p>');
        $err = send_mail($pdo, $to, 'Kidora — رسالة تجريبية', $html, "هذه رسالة تجريبية من لوحة تحكم Kidora.\nالوقت: " . date('Y-m-d H:i'));
        $_SESSION['admin_flash'] = $err === null ? "✅ أُرسلت الرسالة التجريبية إلى {$to}" : "❌ فشل الإرسال — {$err}";
    }
    header('Location: ?tab=settings'); exit;
}

$settings = [];
foreach ($pdo->query("SELECT * FROM settings")->fetchAll() as $row) $settings[$row['setting_key']] = $row['setting_value'];
$mail = mail_config($pdo);
?>
<h2>إعدادات المنصة</h2>
<div class="admin-form" style="max-width:520px;">
  <form method="POST">
    <div class="field"><label>رقم واتساب أعمال المنصة (بصيغة دولية بدون +، مثال: 972592038364)</label>
      <input name="whatsapp_number" value="<?php echo h($settings['whatsapp_number'] ?? ''); ?>"></div>
    <div class="field"><label>اسم المنصة</label><input name="platform_name" value="<?php echo h($settings['platform_name'] ?? 'Kidora'); ?>"></div>
    <div class="field">
      <label>مفتاح API لتوليد القصص بالذكاء الاصطناعي (اختياري)</label>
      <input name="story_api_key" value="<?php echo h($settings['story_api_key'] ?? ''); ?>" placeholder="اتركه فارغاً لاستخدام مولّد القصص المدمج">
      <p style="font-size:12px;color:var(--ink-soft);margin-top:4px;">حالياً تُبنى القصص بمحرك داخلي (مشاهد متحركة + سرد صوتي + تصدير فيديو من المتصفح) لا يحتاج مفتاحاً. عند توفر مفتاح API حقيقي لخدمة توليد فيديو بالذكاء الاصطناعي، يمكن ربطه لاحقاً في story.php لتحسين جودة القصص.</p>
    </div>
    <button type="submit" name="save_settings" class="btn btn-primary">حفظ الإعدادات</button>
  </form>
</div>

<h2 style="margin-top:34px;">البريد الإلكتروني (استرجاع كلمة المرور) 📨</h2>
<div class="admin-form" style="max-width:520px;">
  <?php if ($mail['from_env']): ?>
    <p style="font-size:13px;color:var(--ink-soft);line-height:1.8;">الإعدادات تأتي من متغيّرات البيئة على الخادم (<code>KIDORA_SMTP_*</code>) وهي التي تُطبَّق فعلياً؛ ما يُحفظ هنا يُستخدم فقط إن غابت تلك المتغيّرات.</p>
  <?php endif; ?>
  <p style="font-size:13px;color:var(--ink-soft);line-height:1.8;">المرسِل الحالي: <b><?php echo h($mail['from'] !== '' ? $mail['from'] : 'غير مضبوط'); ?></b> عبر <b><?php echo h($mail['host'] !== '' ? $mail['host'] . ':' . $mail['port'] : '—'); ?></b></p>
  <form method="POST">
    <div class="field"><label>خادم SMTP</label><input name="smtp_host" value="<?php echo h($settings['smtp_host'] ?? ''); ?>" placeholder="مثال: 162.19.244.109"></div>
    <div class="field"><label>المنفذ (465 = TLS ضمني، 587 = STARTTLS)</label><input name="smtp_port" value="<?php echo h($settings['smtp_port'] ?? '465'); ?>"></div>
    <div class="field"><label>اسم المستخدم (بريد الحساب)</label><input name="smtp_user" value="<?php echo h($settings['smtp_user'] ?? ''); ?>" placeholder="no-reply@anivia.site"></div>
    <div class="field"><label>كلمة المرور (اتركها فارغة للإبقاء على الحالية)</label><input name="smtp_pass" type="password" value="" autocomplete="new-password"></div>
    <div class="field"><label>عنوان المرسِل</label><input name="smtp_from" value="<?php echo h($settings['smtp_from'] ?? ''); ?>" placeholder="no-reply@anivia.site"></div>
    <div class="field"><label>اسم المرسِل</label><input name="smtp_from_name" value="<?php echo h($settings['smtp_from_name'] ?? 'Kidora'); ?>"></div>
    <div class="field"><label>اسم شهادة TLS (فقط إن كان الخادم يُخاطَب بعنوان IP)</label><input name="smtp_tls_name" value="<?php echo h($settings['smtp_tls_name'] ?? ''); ?>" placeholder="مثال: mail.ggpanel.site"></div>
    <button type="submit" name="save_mail" class="btn btn-primary">حفظ إعدادات البريد</button>
  </form>
  <form method="POST" style="margin-top:18px;display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
    <div class="field" style="flex:1;min-width:220px;margin:0;"><label>أرسل رسالة تجريبية إلى</label><input name="test_to" type="email" placeholder="بريدك@gmail.com" required></div>
    <button type="submit" name="test_mail" class="btn btn-mint" <?php echo mail_is_configured($pdo) ? '' : 'disabled title="أكمل الإعدادات أولاً"'; ?>>إرسال تجريبي 🚀</button>
  </form>
</div>
