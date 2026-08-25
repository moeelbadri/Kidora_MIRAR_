<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $wa = trim($_POST['whatsapp_number']); $platform = trim($_POST['platform_name']) ?: 'Kidora'; $apiKey = trim($_POST['story_api_key'] ?? '');
    $upd = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    $upd->execute([$wa, 'whatsapp_number']);
    $upd->execute([$platform, 'platform_name']);
    $upd->execute([$apiKey, 'story_api_key']);
    $_SESSION['admin_flash'] = 'تم حفظ الإعدادات ✅';
    header('Location: ?tab=settings'); exit;
}
$settings = [];
foreach ($pdo->query("SELECT * FROM settings")->fetchAll() as $row) $settings[$row['setting_key']] = $row['setting_value'];
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
