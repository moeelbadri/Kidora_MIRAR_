<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

/**
 * يُنشئ اتصال PDO حسب DB_DRIVER المحدد في config.php.
 * في وضع SQLite: يُنشئ قاعدة البيانات والجداول والبيانات الأولية
 * تلقائياً عند أول تشغيل، بدون أي إعداد يدوي.
 */
/**
 * أسماء أعمدة جدول معيّن — تُستخدم للترقيات التلقائية.
 * اسم الجدول ثابت في الكود ولا يأتي من المستخدم أبداً.
 */
function kidora_table_columns(PDO $pdo, string $table): array {
    if (DB_DRIVER === 'mysql') {
        return array_column($pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(), 'Field');
    }
    return array_column($pdo->query("PRAGMA table_info({$table})")->fetchAll(), 'name');
}

/**
 * ترقيات تلقائية للقواعد المُنشأة قبل إضافة أعمدة جديدة.
 * آمنة للتكرار: تُضيف العمود فقط إذا كان مفقوداً.
 */
function kidora_migrate(PDO $pdo): void {
    $isMysql = DB_DRIVER === 'mysql';
    $columns = [
        // ربط كل مهمة بشخصية تاريخية ذات صلة بدل الاختيار العشوائي
        'tasks' => ['figure_id' => $isMysql ? 'INT DEFAULT NULL' : 'INTEGER DEFAULT NULL'],
        // تصنيف الشخصية التاريخية — يتيح مطابقة المهمة بالشخصية عند غياب الربط المباشر
        'history_figures' => ['category' => $isMysql ? "VARCHAR(80) DEFAULT ''" : "TEXT DEFAULT ''"],
    ];

    foreach ($columns as $table => $defs) {
        $existing = kidora_table_columns($pdo, $table);
        foreach ($defs as $col => $type) {
            if (!in_array($col, $existing, true)) {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$type}");
            }
        }
    }
}

function kidaura_connect(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    if (DB_DRIVER === 'mysql') {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        kidora_migrate($pdo);
        // schema.sql يُنشئ الجداول فقط؛ البذر مصدره الوحيد seed.php
        if ((int)$pdo->query("SELECT COUNT(*) c FROM characters")->fetch()['c'] === 0) {
            require_once __DIR__ . '/../database/seed.php';
            kidora_seed($pdo);
        }
        return $pdo;
    }

    // ---------------- SQLite (تجربة سريعة محلياً) ----------------
    $storageDir = dirname(SQLITE_PATH);
    if (!is_dir($storageDir)) mkdir($storageDir, 0777, true);

    $isNew = !file_exists(SQLITE_PATH);
    $pdo = new PDO('sqlite:' . SQLITE_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    if ($isNew) {
        $schema = file_get_contents(__DIR__ . '/../database/schema_sqlite.sql');
        $pdo->exec($schema);
        require_once __DIR__ . '/../database/seed.php';
        kidora_seed($pdo);
    } else {
        kidora_migrate($pdo);
    }

    return $pdo;
}

$pdo = kidaura_connect();
