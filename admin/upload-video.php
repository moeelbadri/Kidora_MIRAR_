<?php
require_once __DIR__ . '/includes/guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$stage = null;
$id = null;
try {
    // PHP discards both POST fields and files when post_max_size is exceeded.
    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        throw new RuntimeException('حجم الطلب أكبر من حد الخادم. الحد الأقصى للفيديو 90 MiB.');
    }
    if (!is_string($_POST['csrf_token'] ?? null) || empty($_SESSION['video_csrf'])
        || !hash_equals($_SESSION['video_csrf'], $_POST['csrf_token'])) {
        throw new RuntimeException('انتهت صلاحية النموذج. حدّث الصفحة وحاول مجدداً.');
    }
    $id = youtube_id_from_input(is_string($_POST['video_id'] ?? null) ? $_POST['video_id'] : '');
    if ($id === null) {
        throw new RuntimeException('أدخل رابط المصدر أو معرّفاً من 11 حرفاً: حروف إنجليزية وأرقام وشرطة وشرطة سفلية.');
    }
    if (($_POST['rights_confirmed'] ?? '') !== '1') {
        throw new RuntimeException('يجب تأكيد امتلاك حقوق نشر الفيديو.');
    }
    $file = $_FILES['video'] ?? null;
    if (!is_array($file) || !is_int($file['error'] ?? null) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('لم يكتمل رفع الفيديو. اختر ملفاً بحجم لا يزيد عن 90 MiB وحاول مجدداً.');
    }
    if (!is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('ملف الرفع غير صالح.');
    }
    $size = filesize($file['tmp_name']);
    if ($size === false || $size === 0 || $size > 90 * 1024 * 1024) {
        throw new RuntimeException('يجب أن يكون الملف غير فارغ وحجمه لا يزيد عن 90 MiB.');
    }
    if (!is_string($file['name'] ?? null) || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'mp4'
        || (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) !== 'video/mp4') {
        throw new RuntimeException('يُسمح بملفات MP4 فقط، وليس بتغيير امتداد ملف آخر إلى mp4.');
    }

    // Probe the real streams; neither the extension nor the browser MIME proves H.264.
    $output = [];
    exec('timeout 20s ffprobe -v error -protocol_whitelist file,pipe -show_entries stream=codec_type,codec_name,pix_fmt:format=duration -of json '
        . escapeshellarg($file['tmp_name']) . ' 2>/dev/null', $output, $status);
    $media = json_decode(implode("\n", $output), true);
    if ($status !== 0 || !is_array($media) || (float)($media['format']['duration'] ?? 0) <= 0) {
        throw new RuntimeException('تعذّر فحص الفيديو. استخدم ملف MP4 سليماً وحاول مجدداً.');
    }
    $videos = 0;
    foreach ($media['streams'] ?? [] as $stream) {
        if (($stream['codec_type'] ?? '') === 'video') {
            $videos++;
            if (($stream['codec_name'] ?? '') !== 'h264' || ($stream['pix_fmt'] ?? '') !== 'yuv420p') {
                throw new RuntimeException('الفيديو يجب أن يستخدم H.264 بصيغة ألوان yuv420p. حوّله قبل الرفع.');
            }
        } elseif (($stream['codec_type'] ?? '') !== 'audio' || ($stream['codec_name'] ?? '') !== 'aac') {
            throw new RuntimeException('يجب أن يكون الصوت AAC. يُسمح أيضاً بفيديو بلا صوت، دون مسارات إضافية.');
        }
    }
    if ($videos !== 1) {
        throw new RuntimeException('يجب أن يحتوي الملف على مسار فيديو H.264 واحد.');
    }

    $directory = __DIR__ . '/../assets/videos';
    if (!is_dir($directory) || !is_writable($directory)) {
        throw new RuntimeException('مجلد الفيديوهات غير قابل للكتابة. تحقّق من صلاحيات مجلد المضيف وإعداد الربط.');
    }
    $destination = $directory . '/' . $id . '.mp4';
    $replace = ($_POST['replace_existing'] ?? '') === '1';
    if (file_exists($destination) && !$replace) {
        throw new RuntimeException('هذا المعرّف موجود. فعّل خيار الاستبدال فقط إذا أردت تغيير الفيديو المرتبط به.');
    }
    $stage = tempnam($directory, '.video-upload-');
    $version = file_exists($destination) ? max(time(), filemtime($destination) + 1) : time();
    if ($stage === false || !move_uploaded_file($file['tmp_name'], $stage)
        || !chmod($stage, 0644) || !touch($stage, $version)) {
        throw new RuntimeException('تعذّر حفظ الملف على المضيف. لم يتم تغيير الفيديو الحالي.');
    }
    // Stage on the same filesystem: replacement is atomic; link prevents accidental overwrites.
    $saved = $replace ? rename($stage, $destination) : @link($stage, $destination);
    if (!$saved) {
        throw new RuntimeException('تعذّر نشر الفيديو أو أن المعرّف موجود بالفعل. لم يتم تغيير الفيديو الحالي.');
    }
    $_SESSION['admin_flash'] = 'تم حفظ الفيديو ✅ المعرّف: ' . $id;
} catch (RuntimeException $e) {
    $_SESSION['admin_flash'] = $e->getMessage();
} finally {
    if (is_string($stage) && is_file($stage)) unlink($stage);
}
header('Location: index.php?tab=videos' . ($id !== null ? '&video_id=' . rawurlencode($id) : ''), true, 303);
exit;
