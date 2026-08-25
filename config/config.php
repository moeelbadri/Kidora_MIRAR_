<?php
/* ============================================================
   إعدادات المشروع العامة
   ============================================================
   للتشغيل على استضافة حقيقية: غيّر DB_DRIVER إلى 'mysql' وعبّئ
   بيانات الاتصال، ثم نفّذ database/schema.sql مرة واحدة على
   قاعدة بياناتك عبر phpMyAdmin أو سطر الأوامر.

   للتجربة السريعة محلياً: اترك DB_DRIVER = 'sqlite' ولا داعي
   لأي إعداد إضافي، تُنشأ قاعدة بيانات SQLite تلقائياً في
   storage/kidora.sqlite عند أول تشغيل.
   ============================================================ */

// 'mysql' أو 'sqlite'
define('DB_DRIVER', getenv('KIDAURA_DB_DRIVER') ?: 'sqlite');

// إعدادات MySQL (تُستخدم فقط إذا كان DB_DRIVER = 'mysql')
define('DB_HOST', getenv('KIDAURA_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('KIDAURA_DB_NAME') ?: 'kidora');
define('DB_USER', getenv('KIDAURA_DB_USER') ?: 'root');
define('DB_PASS', getenv('KIDAURA_DB_PASS') ?: '');

// مسار قاعدة بيانات SQLite (تجربة سريعة فقط)
define('SQLITE_PATH', __DIR__ . '/../storage/kidora.sqlite');

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

// بيانات دخول لوحة الإدارة
define('ADMIN_EMAIL', 'admin@kidora.com');
define('ADMIN_PASSWORD', 'admin123');

date_default_timezone_set('Asia/Gaza');
