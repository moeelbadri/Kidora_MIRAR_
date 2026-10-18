<?php
if (empty($_SESSION['is_admin'])) { http_response_code(403); exit; }
$selectedVideo = youtube_id_from_input(is_string($_GET['video_id'] ?? null) ? $_GET['video_id'] : '');
$videoFiles = glob(__DIR__ . '/../../assets/videos/*.mp4') ?: [];
sort($videoFiles);
?>
<h2>🎬 مكتبة الفيديوهات</h2>
<?php if (!empty($_SESSION['admin_flash'])): ?>
  <p class="stat-card" role="status"><?php echo h($_SESSION['admin_flash']); ?></p>
<?php unset($_SESSION['admin_flash']); endif; ?>
<p>ارفع نسخة تملك حقوق نشرها إلى مجلد المضيف الدائم. لا تُضاف الفيديوهات إلى Git أو صورة Docker.</p>
<div class="admin-form" id="videoUploadForm">
  <h3>رفع فيديو أو استبداله</h3>
  <p>MP4 · فيديو H.264 / yuv420p · صوت AAC أو بلا صوت · الحد الأقصى 90 MiB. لا يتم تحويل الصيغة تلقائياً.</p>
  <form method="POST" action="upload-video.php" enctype="multipart/form-data" id="videoUpload">
    <?= csrf_field() ?>
    <input type="hidden" name="MAX_FILE_SIZE" value="94371840">
    <div class="row">
      <label>معرّف الفيديو أو رابط المصدر
        <input name="video_id" required dir="ltr" maxlength="500" value="<?php echo h($selectedVideo ?? ''); ?>" placeholder="مثال: B4OELaY0ink" aria-describedby="videoIdHelp">
      </label>
      <label>ملف الفيديو
        <input type="file" name="video" accept=".mp4,video/mp4" required>
      </label>
    </div>
    <p id="videoIdHelp">استخدم نفس المعرّف الموجود في المهمة أو الشخصية. للفيديو الجديد اختر معرّفاً فريداً من 11 حرفاً إنجليزياً أو رقماً أو - أو _، ثم ضعه في حقل الفيديو بالمهمة أو الشخصية.</p>
    <label class="video-upload-check"><input type="checkbox" name="rights_confirmed" value="1" required> أملك حقوق نشر هذا الفيديو على المنصة.</label>
    <label class="video-upload-check"><input type="checkbox" name="replace_existing" value="1"> أوافق على استبدال الفيديو الحالي لهذا المعرّف في جميع المواضع المرتبطة به.</label>
    <button type="submit" class="btn btn-primary" id="videoUploadButton">رفع الفيديو</button>
    <p id="videoUploadStatus" role="status" aria-live="polite"></p>
  </form>
</div>
<h3>الفيديوهات المتاحة (<?php echo count($videoFiles); ?>)</h3>
<?php if (!$videoFiles): ?><p>لا توجد فيديوهات بعد. ارفع أول فيديو من النموذج أعلاه.</p><?php endif; ?>
<div class="admin-card-list video-library">
  <?php foreach ($videoFiles as $videoFile):
      $videoId = pathinfo($videoFile, PATHINFO_FILENAME);
      $videoUrl = local_video_url($videoId);
      if ($videoUrl === null) continue;
  ?>
    <article class="admin-item-card">
      <h4 dir="ltr"><?php echo h($videoId); ?></h4>
      <div class="kidora-player kidora-preview" data-kidora-preview>
        <video playsinline preload="none" aria-label="معاينة <?php echo h($videoId); ?>" data-src="<?php echo h($videoUrl); ?>"></video>
        <button type="button" class="kidora-preview-button" aria-label="معاينة <?php echo h($videoId); ?>">▶ معاينة الفيديو</button>
      </div>
      <p><?php echo number_format(filesize($videoFile) / 1048576, 1); ?> MiB · MP4</p>
      <a class="btn btn-sm btn-gold" href="?tab=videos&amp;video_id=<?php echo h($videoId); ?>#videoUploadForm">استبدال هذا الفيديو</a>
    </article>
  <?php endforeach; ?>
</div>
<script>
document.getElementById('videoUpload').addEventListener('submit', function(event) {
  const file = this.elements.video.files[0];
  if (file && file.size > 94371840) {
    event.preventDefault();
    document.getElementById('videoUploadStatus').textContent = 'حجم الملف أكبر من 90 MiB. اختر ملفاً أصغر.';
    return;
  }
  document.getElementById('videoUploadButton').disabled = true;
  document.getElementById('videoUploadStatus').textContent = 'جارٍ الرفع وفحص الفيديو… انتظر حتى تظهر النتيجة، ولا تغلق الصفحة.';
});
</script>
