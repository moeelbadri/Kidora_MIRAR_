<?php
/* ============================================================
   إعدادات المشروع العامة
   ============================================================
   للتشغيل على استضافة حقيقية: غيّر DB_DRIVER إلى 'mysql' وعبّئ
   بيانات الاتصال، ثم شغّل php scripts/migrate.php قبل استقبال الطلبات.

   محلياً: اترك DB_DRIVER = 'sqlite' وشغّل أداة الترحيل لإنشاء القاعدة
   خارج جذر الموقع. راجع README لنسخ القواعد والوسائط القديمة.
   ============================================================ */

// 'mysql' أو 'sqlite'
define('DB_DRIVER', getenv('KIDAURA_DB_DRIVER') ?: 'sqlite');

// إعدادات MySQL (تُستخدم فقط إذا كان DB_DRIVER = 'mysql')
define('DB_HOST', getenv('KIDAURA_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('KIDAURA_DB_NAME') ?: 'kidora');
define('DB_USER', getenv('KIDAURA_DB_USER') ?: 'root');
define('DB_PASS', getenv('KIDAURA_DB_PASS') ?: '');

// مسار قاعدة بيانات SQLite (تجربة سريعة فقط)
define('KIDORA_PRIVATE_DIR', getenv('KIDORA_PRIVATE_DIR') ?: dirname(__DIR__, 2) . '/kidora-private');
define('SQLITE_PATH', getenv('KIDORA_SQLITE_PATH') ?: KIDORA_PRIVATE_DIR . '/kidora.sqlite');
define('KIDORA_APP_URL', rtrim(getenv('KIDORA_APP_URL') ?: 'http://localhost:8000', '/'));
define('KIDORA_MEDIA_RETENTION_DAYS', max(1, (int)(getenv('KIDORA_MEDIA_RETENTION_DAYS') ?: 365)));

// المسار الأساسي للمشروع — يُكتشف تلقائياً بحيث يعمل المشروع بشكل صحيح
// سواء كان في جذر الموقع (example.com) أو داخل مجلد فرعي (example.com/kidora)
// لا حاجة لتعديله يدوياً في أغلب الحالات؛ عدّله فقط إن كانت استضافتك
// تستخدم إعادة كتابة روابط (rewrite) غير معتادة.
if (!defined('BASE_PATH')) {
    $__projectRoot = realpath(__DIR__ . '/..');
    $__docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $__base = '';
    if ($__projectRoot && $__docRoot && strpos($__projectRoot, $__docRoot) === 0) {
        $__base = substr($__projectRoot, strlen($__docRoot));
        $__base = str_replace('\\', '/', $__base);
        if ($__base === '/' ) $__base = '';
    }
    define('BASE_PATH', $__base);
}

// أيقونة تبويب المتصفح (شعار Kidora)
define('KIDORA_FAVICON', 'https://i.ibb.co/Nzr63J7/Screenshot-2026-09-17-131732-removebg-preview.png');

// Source IDs are retained for importing authorized copies into assets/videos/{id}.mp4.
define('KIDORA_SHOWCASE_VIDEO_ID', '9yJF1CReRCQ');
define('KIDORA_SAFETY_VIDEO_ID', 'FUC2yrD2Gz8');

// بيانات دخول لوحة الإدارة
define('ADMIN_EMAIL', 'admin@kidora.com');
define('ADMIN_PASSWORD', 'admin123');

date_default_timezone_set('Asia/Gaza');
require_once __DIR__ . '/security.php';
