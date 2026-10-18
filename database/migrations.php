<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';

/** Explicit offline migrations only; never include this from a request. */
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
        // «تذكّرني»: توكن دخول تلقائي (index.php / kidora_remember_login)
        'children' => [
            'photo_path'       => $isMysql ? "VARCHAR(255) DEFAULT NULL" : 'TEXT DEFAULT NULL',
            'remember_token'   => $isMysql ? "VARCHAR(64) DEFAULT NULL" : 'TEXT DEFAULT NULL',
            'remember_expires' => $isMysql ? "DATETIME DEFAULT NULL" : 'DATETIME DEFAULT NULL',
            // null للقواعد القديمة = سبعة أيام من created_at. التسجيل الجديد يكتبها صراحةً.
            'trial_ends_at'     => 'DATETIME DEFAULT NULL',
        ],
        // ثيم عالم الشخصية (اسم العالم، الرفيق، زخرفة الخلفية) — سبتمبر 2026
        'characters' => ['theme_json' => 'TEXT DEFAULT NULL'],
        // قسم الحماية اليومي: كل درس يحمل لعبته الخاصة وقد يكون مدفوعاً
        'safety_content' => [
            'game_type'  => $isMysql ? "VARCHAR(30) DEFAULT 'body'" : "TEXT DEFAULT 'body'",
            'is_premium' => $isMysql ? 'TINYINT(1) DEFAULT 0' : 'INTEGER DEFAULT 0',
        ],
    ];

    foreach ($columns as $table => $defs) {
        $existing = kidora_table_columns($pdo, $table);
        foreach ($defs as $col => $type) {
            if (!in_array($col, $existing, true)) {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$type}");
            }
        }
    }

    // دروس الحماية المزروعة قبل عمود game_type كلها على القيمة الافتراضية 'body'؛
    // نوزّع عليها ألعابها المقصودة بحسب العنوان (نفس خريطة seed.php) مرة واحدة.
    $sfSeed = [
        'سرّي الخاص'            => 'body',
        'الغريب الآمن'          => 'distance',
        'صح أم خطأ: الإنترنت الآمن' => 'quiz',
        'بطل الإنترنت الآمن'     => 'quiz',
        'قول لا بثقة'           => 'scenario',
    ];
    $sfUpd = $pdo->prepare("UPDATE safety_content SET game_type = ? WHERE title = ? AND (game_type IS NULL OR game_type = 'body')");
    foreach ($sfSeed as $title => $gt) {
        if ($gt !== 'body') $sfUpd->execute([$gt, $title]);
    }
    // عناوين بنبرة حكم → عناوين تشجيعية (المنصة لا تقول للطفل «خطأ»)
    $pdo->prepare("UPDATE safety_content SET title = ? WHERE title = ?")->execute(['بطل الإنترنت الآمن', 'صح أم خطأ: الإنترنت الآمن']);
    $pdo->prepare("UPDATE games SET title = ? WHERE title = ?")->execute(['قرارات البطل', 'قرارات صح وخطأ']);

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

    // الشخصيات الجديدة (سبتمبر 2026): تحلّ محلّ ميمو/زيزو/… وتعيد ربط الأطفال بها.
    kidora_migrate_characters_v2($pdo);

    // محتوى الألعاب انتقل من games-engine.js إلى القاعدة. الثلاثة تُنشأ معاً،
    // فوجود game_topics كافٍ للحكم — استعلام واحد لكل طلب بدل ثلاثة.
    if (!kidora_table_exists($pdo, 'game_topics')) {
        foreach (['game_topics', 'game_questions', 'game_scenarios'] as $t) {
            kidora_create_table_from_schema($pdo, $t);
        }
        require_once __DIR__ . '/../database/seed.php';
        kidora_seed_game_content($pdo);
    }

    // استرجاع كلمة المرور ولوحة الرسم — جدولان مستقلان أُضيفا في سبتمبر 2026
    foreach (['password_resets', 'drawings'] as $t) {
        if (!kidora_table_exists($pdo, $t)) kidora_create_table_from_schema($pdo, $t);
    }

    // آليتان استُبدلتا في سبتمبر 2026: سرعة البديهة (reaction) صارت «أين اختبأ
    // صاحبي؟» (hide)، وذاكرة التسلسل (memory) صارت بازل (puzzle). الصفوف القديمة
    // تُرحَّل حتى لا تسقط إلى catch في المحرّك. آمن للتكرار: لا يطابق شيئاً بعد أول مرة.
    $legacy = $pdo->query("SELECT COUNT(*) c FROM games WHERE type IN ('reaction','memory')")->fetch()['c']
            + $pdo->query("SELECT COUNT(*) c FROM tasks WHERE game_type IN ('reaction','memory')")->fetch()['c'];
    if ((int)$legacy > 0) {
        // عناوين البذر القديمة تتبع الآلية الجديدة؛ ما عدّله الأدمن لا يُطابَق فيبقى
        $renameGame = $pdo->prepare("UPDATE games SET title = ? WHERE title = ? AND type IN ('reaction','memory')");
        $renameTask = $pdo->prepare("UPDATE tasks SET game_title = ? WHERE game_title = ? AND game_type IN ('reaction','memory')");
        foreach (kidora_legacy_game_titles() as $old => $new) {
            $renameGame->execute([$new, $old]);
            $renameTask->execute([$new, $old]);
        }
        foreach (['reaction' => 'hide', 'memory' => 'puzzle'] as $old => $new) {
            $pdo->prepare("UPDATE games SET type = ? WHERE type = ?")->execute([$new, $old]);
            $pdo->prepare("UPDATE tasks SET game_type = ? WHERE game_type = ?")->execute([$new, $old]);
        }
    }
}

function kidora_run_migrations(PDO $pdo): void {
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version INTEGER PRIMARY KEY, applied_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
    $done = array_map('intval', $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
    if (!in_array(1, $done, true)) {
        $fresh = !kidora_table_exists($pdo, 'children');
        if ($fresh) $pdo->exec(file_get_contents(__DIR__ . (DB_DRIVER === 'mysql' ? '/schema.sql' : '/schema_sqlite.sql')));
        kidora_migrate($pdo);
        require_once __DIR__ . '/seed.php';
        if ($fresh || (int)$pdo->query('SELECT COUNT(*) FROM characters')->fetchColumn() === 0) kidora_seed($pdo);
        $pdo->exec('INSERT INTO schema_migrations (version) VALUES (1)');
    }
    if (!in_array(2, $done, true)) {
        foreach (['auth_version' => 'INTEGER NOT NULL DEFAULT 0', 'privacy_consent_at' => 'DATETIME DEFAULT NULL'] as $col => $def) {
            if (!in_array($col, kidora_table_columns($pdo, 'children'), true)) $pdo->exec("ALTER TABLE children ADD COLUMN {$col} {$def}");
        }
        if (!in_array('story_day', kidora_table_columns($pdo, 'daily_stories'), true)) {
            $pdo->exec('ALTER TABLE daily_stories ADD COLUMN story_day ' . (DB_DRIVER === 'mysql' ? 'DATE' : 'TEXT') . ' DEFAULT NULL');
        }
        kidora_create_table_from_schema($pdo, 'auth_attempts');
        // Assign only the earliest historical story on a day; never discard duplicates.
        $pdo->beginTransaction();
        try {
            $rows = $pdo->query('SELECT id, child_id, DATE(created_at) AS day FROM daily_stories ORDER BY created_at, id')->fetchAll();
            $pdo->exec('UPDATE daily_stories SET story_day = NULL');
            $seen = [];
            $update = $pdo->prepare('UPDATE daily_stories SET story_day = ? WHERE id = ?');
            foreach ($rows as $row) {
                $key = $row['child_id'] . ':' . $row['day'];
                if ($row['day'] !== null && !isset($seen[$key])) {
                    $update->execute([$row['day'], $row['id']]);
                    $seen[$key] = true;
                }
            }
            $pdo->exec('UPDATE children SET remember_token = NULL, remember_expires = NULL');
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        if (DB_DRIVER === 'mysql') {
            $indexes = $pdo->query("SHOW INDEX FROM daily_stories WHERE Key_name = 'daily_stories_child_day'")->fetchAll();
            if (!$indexes) $pdo->exec('CREATE UNIQUE INDEX daily_stories_child_day ON daily_stories(child_id, story_day)');
        } else {
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS daily_stories_child_day ON daily_stories(child_id, story_day)');
        }
        $pdo->exec('INSERT INTO schema_migrations (version) VALUES (2)');
    }
}
