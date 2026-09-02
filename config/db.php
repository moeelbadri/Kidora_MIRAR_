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

/** هل الجدول موجود؟ اسم الجدول ثابت في الكود ولا يأتي من المستخدم. */
function kidora_table_exists(PDO $pdo, string $table): bool {
    $sql = DB_DRIVER === 'mysql'
        ? "SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?"
        : "SELECT COUNT(*) c FROM sqlite_master WHERE type = 'table' AND name = ?";
    $st = $pdo->prepare($sql);
    $st->execute([$table]);
    return (int)$st->fetch()['c'] > 0;
}

/**
 * ينفّذ عبارة إنشاء جدول واحد كما هي مكتوبة في ملف الهيكل، حتى يبقى
 * تعريف الجدول في ملف الهيكل وحده بدل تكراره هنا.
 */
function kidora_create_table_from_schema(PDO $pdo, string $table): void {
    $file = __DIR__ . '/../database/' . (DB_DRIVER === 'mysql' ? 'schema.sql' : 'schema_sqlite.sql');
    $sql = @file_get_contents($file);
    if ($sql === false) return;
    $pattern = '/CREATE TABLE IF NOT EXISTS\s+' . preg_quote($table, '/') . '\s*\(.*?\);/is';
    if (preg_match($pattern, $sql, $m)) $pdo->exec($m[0]);
}

/**
 * ترقيات تلقائية للقواعد المُنشأة قبل إضافة أعمدة أو جداول جديدة.
 * آمنة للتكرار: تُضيف الناقص فقط.
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

    // youtube_id كان VARCHAR(30) على MySQL؛ رابط Shorts ملصوق كاملاً أسقط الإدخال
    // بـ«Data too long». المدخل يُطبَّع الآن إلى المعرّف (11 حرفاً)، والعمود يُوسَّع
    // احتياطاً حتى لا يعود الخطأ من أي مسار آخر. SQLite بلا حدّ طول أصلاً.
    if ($isMysql) {
        foreach (['tasks', 'history_figures', 'safety_content'] as $table) {
            $st = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'youtube_id'");
            $col = $st ? $st->fetch() : null;
            if ($col && preg_match('/^varchar\((\d+)\)/i', $col['Type'], $m) && (int)$m[1] < 64) {
                $pdo->exec("ALTER TABLE `{$table}` MODIFY `youtube_id` VARCHAR(64) DEFAULT NULL");
            }
        }
    }

    // قواعد أُنشئت أيام كانت ملفات الهيكل تبذر 6 أسئلة تحليل فقط تعرض «سؤال 1 من 6»؛
    // الجلسة تحتاج 10. يُكمل من بنك البذر بالمحاور الناقصة دون لمس ما عدّله الأدمن.
    require_once __DIR__ . '/../database/seed.php';
    kidora_seed_assessment_questions($pdo, 10);

    // محتوى الألعاب انتقل من games-engine.js إلى القاعدة. الثلاثة تُنشأ معاً،
    // فوجود game_topics كافٍ للحكم — استعلام واحد لكل طلب بدل ثلاثة.
    if (!kidora_table_exists($pdo, 'game_topics')) {
        foreach (['game_topics', 'game_questions', 'game_scenarios'] as $t) {
            kidora_create_table_from_schema($pdo, $t);
        }
        require_once __DIR__ . '/../database/seed.php';
        kidora_seed_game_content($pdo);
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
