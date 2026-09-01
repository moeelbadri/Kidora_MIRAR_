<?php
/* ============================================================
   دوال مساعدة مشتركة — تُستدعى بعد config/db.php في كل صفحة
   ============================================================ */

function require_login(): array {
    if (empty($_SESSION['child_id'])) {
        header('Location: ' . BASE_PATH . '/index.php');
        exit;
    }
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM children WHERE id = ?");
    $stmt->execute([$_SESSION['child_id']]);
    $child = $stmt->fetch();
    if (!$child) {
        session_destroy();
        header('Location: ' . BASE_PATH . '/index.php');
        exit;
    }
    return $child;
}

function today_key(): string {
    return date('Y-m-d');
}

/** يضمن وجود سطر تقدّم لليوم الحالي لهذا الطفل، وينشئه إن لم يكن موجوداً */
function ensure_daily_progress(PDO $pdo, int $childId): array {
    $day = today_key();
    $stmt = $pdo->prepare("SELECT * FROM daily_progress WHERE child_id = ? AND day_key = ?");
    $stmt->execute([$childId, $day]);
    $row = $stmt->fetch();
    if ($row) return $row;

    $ins = $pdo->prepare("INSERT INTO daily_progress (child_id, day_key, completed_task_ids, games_played, quiz_answered, story_generated) VALUES (?,?,?,0,0,0)");
    $ins->execute([$childId, $day, json_encode([])]);

    $stmt->execute([$childId, $day]);
    return $stmt->fetch();
}

function get_character(PDO $pdo, ?int $id): ?array {
    if (!$id) return null;
    $stmt = $pdo->prepare("SELECT * FROM characters WHERE id = ?");
    $stmt->execute([$id]);
    $c = $stmt->fetch();
    return $c ?: null;
}

function all_characters(PDO $pdo): array {
    return $pdo->query("SELECT * FROM characters ORDER BY sort_order ASC, id ASC")->fetchAll();
}

function character_icons(array $character): array {
    $icons = json_decode($character['icons_json'] ?? '[]', true);
    return is_array($icons) && count($icons) ? $icons : ['✨','⭐','🌟'];
}

function active_character(PDO $pdo, array $child): ?array {
    $id = $child['active_character'] ?: $child['character_1'];
    return get_character($pdo, $id);
}

/** خطة الاشتراك الحالية (نشطة فقط)، أو null إن لم تكن مفعّلة */
function get_active_plan(PDO $pdo, int $childId): ?array {
    $stmt = $pdo->prepare("SELECT s.*, p.name, p.price_ils, p.billing_cycle, p.features_json
                            FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id
                            WHERE s.child_id = ? AND s.status = 'active'");
    $stmt->execute([$childId]);
    $r = $stmt->fetch();
    return $r ?: null;
}
function get_subscription_record(PDO $pdo, int $childId): ?array {
    $stmt = $pdo->prepare("SELECT s.*, p.name, p.price_ils, p.billing_cycle FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id WHERE s.child_id = ?");
    $stmt->execute([$childId]);
    $r = $stmt->fetch();
    return $r ?: null;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

/** هل خطة الطفل الحالية مدفوعة ومفعّلة (وليست الخطة المجانية)؟ يُستخدم لعرض وسام التوثيق وفتح الشخصيات الحصرية */
function is_premium_active(PDO $pdo, int $childId): bool {
    $plan = get_active_plan($pdo, $childId);
    return $plan && (int)$plan['price_ils'] > 0;
}

/**
 * عدد ألعاب المكتبة المتاحة بلا اشتراك.
 * اللعبة الصغيرة التي تلي كل مهمة ليست منها — هي جزء من باكج المهمة ومجانية دائماً.
 */
const FREE_LIBRARY_GAMES = 2;

/**
 * ألعاب المكتبة التي يراها الطفل فعلاً.
 * غير المشترك يرى FREE_LIBRARY_GAMES فقط، ويُفضَّل أن تكون بآليات مختلفة
 * حتى تُظهر العيّنة تنوّع المكتبة لا آلية واحدة مكرّرة.
 */
function visible_library_games(array $games, bool $premium): array {
    if ($premium) return $games;

    $picked = $seenTypes = $pickedIds = [];
    foreach ($games as $g) {
        if (count($picked) >= FREE_LIBRARY_GAMES) break;
        if (in_array($g['type'], $seenTypes, true)) continue;
        $seenTypes[] = $g['type'];
        $pickedIds[] = $g['id'];
        $picked[] = $g;
    }
    // مكتبة بآلية واحدة فقط: أكمل العدد بالترتيب
    foreach ($games as $g) {
        if (count($picked) >= FREE_LIBRARY_GAMES) break;
        if (!in_array($g['id'], $pickedIds, true)) { $pickedIds[] = $g['id']; $picked[] = $g; }
    }
    return $picked;
}

/** الشخصيات المتاحة للاختيار: المجانية دائماً + الحصرية فقط إن كان الاشتراك مفعّلاً */
function selectable_characters(PDO $pdo, bool $premiumUnlocked): array {
    $all = all_characters($pdo);
    if ($premiumUnlocked) return $all;
    return array_values(array_filter($all, fn($c) => !$c['is_premium']));
}

function whatsapp_link(PDO $pdo, string $message, string $phone = ''): string {
    $number = $phone !== '' ? preg_replace('/\D/', '', $phone) : '';
    if ($number === '') {
        $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='whatsapp_number'");
        $r = $stmt->fetch();
        $number = $r ? preg_replace('/\D/', '', $r['setting_value']) : '';
    }
    return "https://wa.me/{$number}?text=" . rawurlencode($message);
}

function log_wa(PDO $pdo, int $childId, string $type, string $message): void {
    $stmt = $pdo->prepare("INSERT INTO wa_log (child_id, type, message) VALUES (?,?,?)");
    $stmt->execute([$childId, $type, $message]);
}

/** يحفظ ملف صورة/صوت مرفوع في مجلد الوجهة، ويُعيد المسار النسبي أو null */
function save_upload(string $inputName, string $destDir, array $allowedExt): ?string {
    if (empty($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($_FILES[$inputName]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) return null;
    if (!is_dir($destDir)) mkdir($destDir, 0777, true);
    $filename = uniqid('u_') . '.' . $ext;
    $destPath = rtrim($destDir, '/') . '/' . $filename;
    if (!move_uploaded_file($_FILES[$inputName]['tmp_name'], $destPath)) return null;
    return $destPath;
}

function json_decode_safe(?string $s, $default = []) {
    if (!$s) return $default;
    $d = json_decode($s, true);
    return $d === null ? $default : $d;
}

/** هل حان وقت تحليل السلوك الجديد؟ (أول مرة أو كل 10 أيام فقط) */
function needs_assessment(array $child): bool {
    if (empty($child['last_assessment_at'])) return true;
    $last = strtotime($child['last_assessment_at']);
    return (time() - $last) >= (10 * 86400);
}

/** ملخص محاور التحليل الحقيقية لطفل معيّن — يُستخدم بالبروفايل ولوحة الأدمن */
function assessment_axis_summary(PDO $pdo, int $childId): array {
    $stmt = $pdo->prepare("SELECT axis, AVG(value) avg_v, COUNT(*) c FROM quiz_history WHERE child_id = ? GROUP BY axis");
    $stmt->execute([$childId]);
    return $stmt->fetchAll();
}

/**
 * آليات اللعب المدعومة فعلياً في assets/js/games-engine.js.
 * أي قيمة خارج هذه القائمة ستسقط إلى catch، فاحصر إدخال الأدمن بها.
 */
function game_types(): array {
    return [
        'catch'     => 'لعبة التقاط',
        'match'     => 'مطابقة الأزواج',
        'quiz'      => 'سباق أسئلة',
        'reaction'  => 'سرعة البديهة',
        'memory'    => 'ذاكرة التسلسل',
        'adventure' => 'مغامرة بالاختيارات',
    ];
}

/** دالة الترتيب العشوائي حسب المحرّك — SQLite تستخدم RANDOM() وMySQL تستخدم RAND() */
function sql_random(): string {
    return DB_DRIVER === 'mysql' ? 'RAND()' : 'RANDOM()';
}

/**
 * سنّ التحوّل بين نسختَي اللعب. من هذا العمر وما فوق: مؤقّتات وسرعة بديهة.
 * تحته: بلا مؤقّت، والنص يُقرأ صوتياً، وسرعة البديهة تُستبدل بآلية بلا ضغط وقت.
 */
const GAME_TIMER_MIN_AGE = 10;

/** هل يلعب هذا العمر النسخة الهادئة (بلا مؤقّت + قراءة صوتية)؟ */
function game_is_calm_age(?int $age): bool {
    return $age !== null && $age < GAME_TIMER_MIN_AGE;
}

/**
 * موضوع المحتوى المناسب لتصنيف مهمة أو لعبة (بالعربية).
 * الخريطة نفسها تعيش في game_topics.categories_json فتُحرَّر من لوحة التحكم.
 * المواضيع تسعة صفوف فقط، فالمطابقة في PHP أبسط وأكثر توافقاً من JSON في SQL.
 */
function game_topic_for_category(PDO $pdo, ?string $category): ?array {
    $topics = $pdo->query("SELECT * FROM game_topics WHERE active = 1 ORDER BY sort_order, id")->fetchAll();
    if (!$topics) return null;

    $needle = trim((string)$category);
    $fallback = null;
    foreach ($topics as $t) {
        if ($t['topic_key'] === 'general') $fallback = $t;
        if ($needle === '') continue;
        if ($t['topic_key'] === $needle) return $t;
        foreach (json_decode_safe($t['categories_json'], []) as $c) {
            if (trim((string)$c) === $needle) return $t;
        }
    }
    return $fallback ?: $topics[0];
}

/**
 * محتوى اللعبة كما يستهلكه GamesEngine: الأيقونات + بنك صح/خطأ +
 * سيناريوهات المغامرة، مفلترة بعمر الطفل ومرتّبة عشوائياً.
 *
 * أشكال البيانات (q/a للسؤال، t/c/l/g/r للمغامرة) هي نفسها التي كان المحرّك
 * يقرأها من الثوابت، فعقد الاستهلاك لم يتغيّر — تغيّر المصدر فقط.
 * الترتيب العشوائي هو ما يجعل إعادة اللعب مختلفة (كانت متطابقة كل مرة).
 */
function game_content_for(PDO $pdo, ?string $category, ?int $age = null): array {
    $topic = game_topic_for_category($pdo, $category);
    if (!$topic) return ['topic' => 'general', 'label' => 'عام', 'icons' => [], 'quiz' => [], 'adventure' => []];

    $key  = $topic['topic_key'];
    $age  = $age !== null ? max(1, min(18, (int)$age)) : null;
    // غياب العمر يعني «كل المحتوى» — تستخدمه لوحة التحكم للعرض والتحرير
    $ageSql = $age !== null ? ' AND age_min <= :age AND age_max >= :age' : '';
    $bind   = $age !== null ? ['age' => $age] : [];
    $rand   = sql_random();

    $qs = $pdo->prepare("SELECT question, answer FROM game_questions WHERE topic_key = :k AND active = 1{$ageSql} ORDER BY {$rand}");
    $qs->execute(['k' => $key] + $bind);
    $quiz = array_map(
        fn($r) => ['q' => $r['question'], 'a' => (bool)(int)$r['answer']],
        $qs->fetchAll()
    );

    $ss = $pdo->prepare("SELECT prompt, choices_json FROM game_scenarios WHERE topic_key = :k AND active = 1{$ageSql} ORDER BY {$rand}");
    $ss->execute(['k' => $key] + $bind);
    $adventure = [];
    foreach ($ss->fetchAll() as $r) {
        $choices = json_decode_safe($r['choices_json'], []);
        if ($choices) $adventure[] = ['t' => $r['prompt'], 'c' => $choices];
    }

    return [
        'topic'     => $key,
        'label'     => $topic['label'],
        'icons'     => json_decode_safe($topic['icons_json'], []),
        'quiz'      => $quiz,
        'adventure' => $adventure,
    ];
}

/**
 * الشخصية التاريخية التي تُعرض بعد إنجاز مهمة معيّنة.
 * الأولوية: الربط المباشر (tasks.figure_id) ← تطابق التصنيف ← أي شخصية نشطة.
 * الغرض أن تكون الشخصية ذات صلة فعلية بالمهمة بدل اختيارها عشوائياً.
 */
function figure_for_task(PDO $pdo, array $task): ?array {
    if (!empty($task['figure_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM history_figures WHERE id = ? AND active = 1");
        $stmt->execute([(int)$task['figure_id']]);
        if ($f = $stmt->fetch()) return $f;
    }
    if (!empty($task['category'])) {
        $stmt = $pdo->prepare("SELECT * FROM history_figures WHERE active = 1 AND category = ? ORDER BY " . sql_random() . " LIMIT 1");
        $stmt->execute([$task['category']]);
        if ($f = $stmt->fetch()) return $f;
    }
    $f = $pdo->query("SELECT * FROM history_figures WHERE active = 1 ORDER BY " . sql_random() . " LIMIT 1")->fetch();
    return $f ?: null;
}

/** أيقونة تعبيرية لكل تصنيف مهمة — تُستخدم في مشاهد المغامرة الكبرى */
function category_icon(string $category): string {
    $map = [
        'مهارات حياتية' => '🧹', 'تعلّم' => '📚', 'صحة' => '🏃', 'إبداع' => '🎨',
        'قيم' => '💛', 'صحة نفسية' => '🧘', 'حماية' => '🛡️', 'مسؤولية' => '🌱',
        'اجتماعي' => '🤝', 'ثقافي' => '🕌',
    ];
    return $map[$category] ?? '⭐';
}

/**
 * ملخّص إنجاز الطفل خلال فترة — الأساس الذي تُبنى عليه المغامرة الكبرى.
 * يجمع المهام المنجزة وتصنيفاتها، الألعاب، الشخصيات التاريخية التي قابلها،
 * وتطوّر تحليل السلوك بين أول جلسة وآخر جلسة.
 */
function child_achievement_summary(PDO $pdo, int $childId, string $fromDay, string $toDay): array {
    $sum = [
        'days' => 0, 'tasks_done' => 0, 'games_played' => 0,
        'categories' => [], 'figures' => [], 'best_axis' => null, 'growth' => null,
    ];

    $stmt = $pdo->prepare("SELECT completed_task_ids, games_played FROM daily_progress WHERE child_id = ? AND day_key BETWEEN ? AND ?");
    $stmt->execute([$childId, $fromDay, $toDay]);
    $taskIds = [];
    foreach ($stmt->fetchAll() as $row) {
        $sum['days']++;
        $sum['games_played'] += (int)$row['games_played'];
        foreach (json_decode_safe($row['completed_task_ids'], []) as $tid) $taskIds[] = (int)$tid;
    }
    $sum['tasks_done'] = count($taskIds);

    if ($taskIds) {
        $unique = array_values(array_unique($taskIds));
        $ph = implode(',', array_fill(0, count($unique), '?'));
        $q = $pdo->prepare("SELECT t.id, t.category, f.name figure_name
                             FROM tasks t LEFT JOIN history_figures f ON f.id = t.figure_id
                             WHERE t.id IN ($ph)");
        $q->execute($unique);
        $byId = [];
        foreach ($q->fetchAll() as $r) $byId[(int)$r['id']] = $r;

        $figures = [];
        foreach ($taskIds as $tid) {
            if (!isset($byId[$tid])) continue;
            $cat = $byId[$tid]['category'] ?: 'عام';
            $sum['categories'][$cat] = ($sum['categories'][$cat] ?? 0) + 1;
            if (!empty($byId[$tid]['figure_name'])) $figures[$byId[$tid]['figure_name']] = true;
        }
        arsort($sum['categories']);
        $sum['figures'] = array_keys($figures);
    }

    $best = $pdo->prepare("SELECT axis, AVG(value) avg_v FROM quiz_history WHERE child_id = ? GROUP BY axis ORDER BY avg_v DESC LIMIT 1");
    $best->execute([$childId]);
    $sum['best_axis'] = $best->fetch() ?: null;

    // تطوّر التحليل: متوسط أول جلسة مقابل متوسط آخر جلسة
    $span = $pdo->prepare("SELECT MIN(DATE(created_at)) first_day, MAX(DATE(created_at)) last_day FROM quiz_history WHERE child_id = ?");
    $span->execute([$childId]);
    $span = $span->fetch();
    if ($span && $span['first_day'] && $span['first_day'] !== $span['last_day']) {
        $avg = $pdo->prepare("SELECT AVG(value) v FROM quiz_history WHERE child_id = ? AND DATE(created_at) = ?");
        $avg->execute([$childId, $span['first_day']]);
        $from = (float)$avg->fetch()['v'];
        $avg->execute([$childId, $span['last_day']]);
        $to = (float)$avg->fetch()['v'];
        if ($to > $from) $sum['growth'] = ['from' => round($from, 1), 'to' => round($to, 1)];
    }

    return $sum;
}

/**
 * يبني مشاهد المغامرة الكبرى من إنجاز الطفل الحقيقي خلال الشهر،
 * لا من مجرد دمج القصص اليومية. كل مشهد: caption + grad + icon + title.
 */
function grand_story_scenes(array $child, array $stories, array $sum, string $companions): array {
    $name = $child['name'];
    $grads = ['#1B1035,#6C63FF','#6C63FF,#FF6FA5','#2EC4B6,#6C63FF','#FF7A50,#FFC93C','#FF6FA5,#FFC93C','#2EC4B6,#241645','#3A2A75,#FF7A50'];
    $i = 0;
    $scenes = [];
    $add = function (string $icon, string $title, string $caption) use (&$scenes, &$i, $grads) {
        $scenes[] = ['caption' => $caption, 'grad' => $grads[$i++ % count($grads)], 'icon' => $icon, 'title' => $title];
    };

    // لا يوجد حقل جنس للطفل، فالصياغة اسمية ومحايدة بدل أفعال تحتاج مطابقة
    $add('🌟', 'المغامرة الكبرى', "ثلاثون يوماً... هذه رحلة {$name} من أول مهمة إلى آخر نجمة.");
    $add('🌱', 'البداية', "قبل ثلاثين يوماً بدأت الرحلة مع {$companions}، ولم تكن النهاية معروفة بعد.");
    $add('✅', 'المهام', $sum['tasks_done'] . " مهمة مُنجزة على مدى " . max(1, $sum['days']) . " يوماً — واحدة تلو الأخرى، بلا استسلام.");

    $top = array_slice($sum['categories'], 0, 2, true);
    foreach ($top as $cat => $count) {
        $add(category_icon($cat), "تألّق في {$cat}", "أكثر مجال تألّق فيه {$name}: «{$cat}» — {$count} مهمة فيه وحده.");
    }

    if ($sum['figures']) {
        $shown = array_slice($sum['figures'], 0, 4);
        $more = count($sum['figures']) - count($shown);
        $list = implode('، ', $shown) . ($more > 0 ? " و{$more} آخرين" : '');
        $add('🕌', 'أبطال التاريخ', count($sum['figures']) . " من أبطال تاريخنا رافقوا الرحلة: {$list}.");
    }

    if ($sum['games_played'] > 0) {
        $add('🎮', 'الألعاب', $sum['games_played'] . " لعبة خلال الشهر، وكل واحدة درّبت العقل على شيء جديد.");
    }

    $add('⭐', 'النجوم', (int)$child['points'] . " نجمة في رصيد {$name} حتى الآن ✨");

    if ($sum['growth']) {
        $add('📈', 'التقدّم', "تحليل السلوك ارتفع من {$sum['growth']['from']} إلى {$sum['growth']['to']} من 3 — تقدّم حقيقي يُرى بالأرقام.");
    }
    if ($sum['best_axis']) {
        $add('🏅', 'أقوى محور', "أقوى محور اليوم: «" . $sum['best_axis']['axis'] . "» — صار العلامة المميّزة لـ{$name}.");
    }

    // لقطات من القصص اليومية نفسها حتى تبقى الرحلة محسوسة لا أرقاماً فقط
    $highlights = [];
    foreach ($stories as $s) {
        $sc = json_decode_safe($s['scenes_json'], []);
        foreach ($sc as $one) {
            if (!empty($one['caption'])) $highlights[] = $one['caption'];
        }
    }
    if ($highlights) {
        $step = max(1, (int)floor(count($highlights) / 3));
        for ($k = 0; $k < 3 && ($k * $step) < count($highlights); $k++) {
            $add('📖', 'من يوميّاته', $highlights[$k * $step]);
        }
    }

    $add('🏆', 'النهاية... والبداية', "وهكذا انتهت ثلاثون يوماً من النمو والشجاعة والتعلّم. المغامرة القادمة تبدأ غداً!");

    return $scenes;
}

/** يبني مجلد صورة/صوت مخصّص لكل شخصية assets/images/characters/{slug}/ أو assets/audio/characters/{slug}/ */
function character_media_dir(string $kind, string $slug): string {
    $base = $kind === 'audio' ? __DIR__ . '/../assets/audio/characters' : __DIR__ . '/../assets/images/characters';
    $dir = $base . '/' . preg_replace('/[^a-z0-9_-]/i', '', $slug);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    return $dir;
}
