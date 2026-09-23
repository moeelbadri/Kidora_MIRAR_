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

/** العمر الذي يختاره الطفل أو ولي الأمر عند التسجيل وفي الملف الشخصي. */
const CHILD_AGE_MIN = 1;
const CHILD_AGE_MAX = 60;

/** تسمية السنة بالعربية بجانب الرقم في قوائم العمر. */
function child_age_label(int $age): string {
    if ($age === 2) return 'سنتان';
    if ($age >= 3 && $age <= 10) return 'سنوات';
    return 'سنة';
}

/** عمر مقبول من النموذج، أو null إن كان خارج 1–60. */
function normalize_child_age($age): ?int {
    $age = (int)$age;
    if ($age < CHILD_AGE_MIN || $age > CHILD_AGE_MAX) return null;
    return $age;
}

/**
 * حدّ عمر المحتوى في لوحة التحكم. القيمة الفارغة أو الصفر تأخذ الافتراضي،
 * وما فوق السقف يُقصّ إلى أقصى عمر يمكن تسجيله.
 */
function clamp_content_age($value, int $fallback): int {
    $n = (int)$value;
    if ($n < CHILD_AGE_MIN) $n = $fallback;
    if ($n > CHILD_AGE_MAX) $n = CHILD_AGE_MAX;
    if ($n < CHILD_AGE_MIN) $n = CHILD_AGE_MIN;
    return $n;
}

/**
 * باكج اليوم: حتى 4 مهام من كل المهام النشطة، بلا فلتر عمر.
 * العمر الذي يختاره الطفل يُحفظ في حسابه ولا يُخفي محتوى.
 * تُثبَّت في daily_progress.task_pool_ids أول مرة.
 * يُستخدم من tasks.php و api/complete-task.php حتى يبقى المصدر واحداً.
 */
function daily_task_pool(PDO $pdo, array $child, array $progress): array {
    $pool = json_decode_safe($progress['task_pool_ids'] ?? null, null);
    if ($pool === null || $pool === []) {
        $stmt = $pdo->query("SELECT id FROM tasks WHERE active = 1");
        $eligible = array_column($stmt->fetchAll(), 'id');
        shuffle($eligible);
        $pool = array_slice($eligible, 0, min(4, count($eligible)));
        $pdo->prepare("UPDATE daily_progress SET task_pool_ids = ? WHERE id = ?")->execute([json_encode(array_values($pool)), $progress['id']]);
    }
    return array_map('intval', $pool);
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

/** أرقام موجزة للواجهة العامة — الاستعلامات ثابتة ولا تستقبل مدخلات من المستخدم. */
function public_counts(PDO $pdo): array {
    return [
        'characters' => (int)$pdo->query("SELECT COUNT(*) FROM characters")->fetchColumn(),
        'tasks'      => (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE active = 1")->fetchColumn(),
        'figures'    => (int)$pdo->query("SELECT COUNT(*) FROM history_figures WHERE active = 1")->fetchColumn(),
        'games'      => (int)$pdo->query("SELECT COUNT(*) FROM games")->fetchColumn(),
    ];
}

function character_icons(array $character): array {
    $icons = json_decode($character['icons_json'] ?? '[]', true);
    return is_array($icons) && count($icons) ? $icons : ['✨','⭐','🌟'];
}

/**
 * ثيم عالم الشخصية: world (اسم العالم)، sidekick (name/icon)، motif (زخرفة الخلفية).
 * القيم الافتراضية تجعل أي شخصية يضيفها الأدمن بلا theme_json تعمل بلا كسر.
 */
function character_theme(?array $character): array {
    $t = json_decode($character['theme_json'] ?? '', true);
    $t = is_array($t) ? $t : [];
    $icons = $character ? character_icons($character) : ['✨'];
    return [
        'world'    => (string)($t['world'] ?? ('عالم ' . ($character['name'] ?? 'الرفيق'))),
        'sidekick' => [
            'name' => (string)($t['sidekick']['name'] ?? 'صديقه المقرّب'),
            'icon' => (string)($t['sidekick']['icon'] ?? ($icons[1] ?? '⭐')),
        ],
        'motif'    => (string)($t['motif'] ?? 'stars'),
    ];
}

/**
 * جملة تربط مهمة الطفل بالشخصية ورفيقها («صداقة مثل سبونج بوب وبسيط»).
 * جمل اسمية بلا فعل مسند إلى الطفل (لا جنس مسجّل) — انظر PROJECT-REPORT §8/36.
 */
function companion_pair_line(?array $character, string $category): string {
    if (!$character) return '';
    $theme = character_theme($character);
    $c = $character['name']; $s = $theme['sidekick']['name']; $w = $theme['world'];
    $lines = [
        'اجتماعي'       => "صداقة مثل صداقة {$c} و{$s} في {$w}: يد بيد، والفرح مضاعف 🤝",
        'قيم'           => "قلب طيب مثل قلب {$c} مع {$s}: الصدق والعطاء أجمل ما في {$w} 💛",
        'مهارات حياتية' => "ترتيب ونظام في {$w}… حتى {$c} و{$s} يبدآن يومهما بذلك 🧹",
        'تعلّم'         => "فضول مثل فضول {$c} و{$s}: كل يوم في {$w} اكتشاف جديد 📚",
        'صحة'           => "طاقة وحركة مثل {$c} و{$s} في {$w}: جسم قوي لبطل قوي 🏃",
        'إبداع'         => "خيال مثل خيال {$c} و{$s}: {$w} كلها ألوان وأفكار 🎨",
        'صحة نفسية'     => "نفس عميق مثل ما يفعل {$c} حين يقلق {$s}: الهدوء قوة 🧘",
        'حماية'         => "حذر وشجاعة مثل {$c} و{$s} في {$w}: البطل يحمي نفسه أولاً 🛡️",
        'مسؤولية'       => "وعد يُوفى مثل وعود {$c} لـ{$s}: الثقة تُبنى بالأفعال 🌱",
        'ثقافي'         => "حكاية قديمة تُروى في {$w} كما يرويها {$c} لـ{$s} 🕌",
    ];
    return $lines[$category] ?? "خطوة بطولية تفرح {$c} و{$s} في {$w} ⭐";
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
/** ألعاب اليوم المطلوبة قبل القصة اليومية — لعبة اليوم المقترحة تكفي (سبتمبر 2026) */
const STORY_MIN_GAMES = 1;

/**
 * لعبة اليوم: اختيار ثابت طول اليوم من الألعاب الظاهرة للطفل (يتغيّر يومياً).
 */
function game_of_the_day(array $visibleGames, int $childId): ?array {
    if (!$visibleGames) return null;
    $idx = crc32(today_key() . ':' . $childId) % count($visibleGames);
    return $visibleGames[$idx];
}

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

/**
 * تنسيق رقم الهاتف ليصبح صالحاً لرابط واتساب الدولي مع إضافة المقدمة المناسبة (+972 أو +970)
 */
function normalize_wa_phone(string $phone, string $prefix = '972'): string {
    $clean = preg_replace('/\D/', '', $phone);
    if ($clean === '') return '';

    // إزالة أصفار البداية الدولية 00
    if (str_starts_with($clean, '00')) {
        $clean = substr($clean, 2);
    }

    $prefix = ltrim($prefix, '+');

    // إذا كان الرقم يبدأ بـ 972 أو 970، نحافظ على جسم الرقم ونستبدل المقدمة بالمطلوبة
    if (str_starts_with($clean, '972') || str_starts_with($clean, '970')) {
        $core = substr($clean, 3);
        return $prefix . $core;
    }

    // إذا كان الرقم محلياً يبدأ بـ 0 (مثال: 0599... أو 0569...)
    if (str_starts_with($clean, '0')) {
        return $prefix . substr($clean, 1);
    }

    // إذا كان الرقم 9 أرقام ويبدأ بـ 5 (مثال: 599123456)
    if (strlen($clean) === 9 && str_starts_with($clean, '5')) {
        return $prefix . $clean;
    }

    // إذا كان رقماً دولياً آخر (طوله 10+ أرقام ولا يبدأ بـ 0): نتركه كما هو
    if (strlen($clean) >= 10 && !str_starts_with($clean, '0')) {
        return $clean;
    }

    return $prefix . $clean;
}

function whatsapp_link(PDO $pdo, string $message, string $phone = '', string $prefix = '972'): string {
    $raw = $phone !== '' ? $phone : '';
    if ($raw === '') {
        $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='whatsapp_number'");
        $r = $stmt->fetch();
        $raw = $r ? $r['setting_value'] : '';
    }
    $number = normalize_wa_phone($raw, $prefix);
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

/**
 * يحفظ صورة مرفوعة بعد فحص الحجم وMIME والمحتوى الفعلي للصورة.
 * هذه الدالة مخصّصة لصور الحساب الجديدة ولا تغيّر مسار الرفع القديم.
 */
function save_image_upload(string $inputName, string $destDir): ?string {
    if (empty($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) return null;
    $file = $_FILES[$inputName];
    if ((int)$file['size'] <= 0 || (int)$file['size'] > 4 * 1024 * 1024) return null;
    if (!is_uploaded_file($file['tmp_name'])) return null;

    $mime = null;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime = finfo_file($finfo, $file['tmp_name']) ?: null;
        finfo_close($finfo);
    }
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!$mime || !isset($extensions[$mime]) || @getimagesize($file['tmp_name']) === false) return null;

    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) return null;
    try {
        $filename = 'child_' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
    } catch (Throwable $e) {
        $filename = 'child_' . uniqid('', true) . '.' . $extensions[$mime];
    }
    $destPath = rtrim($destDir, '/') . '/' . $filename;
    return move_uploaded_file($file['tmp_name'], $destPath) ? $destPath : null;
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

/** تسمية قصيرة على شعاع الرسمة؛ الاسم الكامل يبقى في القائمة تحتها */
function behavior_radar_short(string $axis): string {
    static $map = [
        'الثقة بالنفس'       => 'ثقة',
        'المهارات الاجتماعية' => 'اجتماع',
        'الذكاء العاطفي'     => 'مشاعر',
        'الإبداع'            => 'إبداع',
        'التركيز'            => 'تركيز',
        'الأمان الشخصي'      => 'أمان',
        'الاستقلالية'        => 'استقلال',
        'الانتماء الثقافي'   => 'تراث',
        'المثابرة'           => 'مثابرة',
        'التعاون'            => 'تعاون',
        'حل المشكلات'        => 'حل',
        'التعبير عن الذات'   => 'تعبير',
    ];
    if (isset($map[$axis])) return $map[$axis];
    $parts = preg_split('/\s+/u', trim($axis), 2);
    $word = ($parts && $parts[0] !== '') ? $parts[0] : $axis;
    return preg_match('/^(.{1,6})/u', $word, $m) ? $m[1] : $word;
}

/**
 * رسمة عنكبوتية لمحاور التحليل. المقياس ١–٣: الحلقة الداخلية = ١ والخارجية = ٣.
 * تُعاد HTML جاهزة (SVG + قائمة). البطاقة التي تحتويها يجب أن تبقى فاتحة.
 */
function behavior_radar_svg(array $rows): string {
    $pts = [];
    foreach ($rows as $r) {
        $axis = trim((string)($r['axis'] ?? ''));
        if ($axis === '') continue;
        $avg = (float)($r['avg_v'] ?? 0);
        if ($avg < 0) $avg = 0;
        if ($avg > 3) $avg = 3;
        $pts[] = ['axis' => $axis, 'avg' => $avg];
    }
    $n = count($pts);
    if ($n === 0) return '';

    $cx = 180; $cy = 176; $R = 112;
    $rings = '';
    for ($k = 1; $k <= 3; $k++) {
        $rr = $R * $k / 3;
        $rings .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.round($rr, 2).'" class="br-ring"/>';
        $rings .= '<text x="'.($cx + 5).'" y="'.round($cy - $rr - 2, 2).'" class="br-tick">'.$k.'</text>';
    }

    $spokes = '';
    $labels = '';
    $poly = [];
    $legend = '';
    for ($i = 0; $i < $n; $i++) {
        $ang = -M_PI_2 + (2 * M_PI * $i / $n);
        $cos = cos($ang); $sin = sin($ang);
        $x2 = $cx + $R * $cos; $y2 = $cy + $R * $sin;
        $spokes .= '<line x1="'.$cx.'" y1="'.$cy.'" x2="'.round($x2, 2).'" y2="'.round($y2, 2).'" class="br-spoke"/>';
        $pr = $R * ($pts[$i]['avg'] / 3);
        $poly[] = round($cx + $pr * $cos, 2).','.round($cy + $pr * $sin, 2);
        $lx = $cx + ($R + 22) * $cos;
        $ly = $cy + ($R + 18) * $sin;
        $anchor = $cos > 0.35 ? 'start' : ($cos < -0.35 ? 'end' : 'middle');
        $short = behavior_radar_short($pts[$i]['axis']);
        $labels .= '<text x="'.round($lx, 2).'" y="'.round($ly, 2).'" text-anchor="'.$anchor.'" class="br-label">'.h($short).'</text>';
        $legend .= '<li><span>'.h($pts[$i]['axis']).'</span><b>'.h(number_format($pts[$i]['avg'], 1)).' / 3</b></li>';
    }

    return '<div class="behavior-radar">'
        .'<svg viewBox="0 0 360 352" role="img" aria-label="رسمة تحليل السلوك">'
        .$rings.$spokes
        .'<polygon points="'.implode(' ', $poly).'" class="br-shape"/>'
        .$labels
        .'</svg>'
        .'<ul class="behavior-radar-legend">'.$legend.'</ul>'
        .'<p class="behavior-radar-note">كل محور من ١ إلى ٣. الشكل الأكبر يعني مهارة أوضح.</p>'
        .'</div>';
}

/**
 * آليات اللعب المدعومة فعلياً في assets/js/games-engine.js.
 * أي قيمة خارج هذه القائمة ستسقط إلى catch، فاحصر إدخال الأدمن بها.
 */
function game_types(): array {
    return [
        'catch'     => 'التقط الصحيح',
        'match'     => 'مطابقة الأزواج',
        'quiz'      => 'طريق البطل (أسئلة)',
        'puzzle'    => 'البازل',
        'hide'      => 'أين اختبأ صاحبي؟',
        'adventure' => 'مغامرة بالاختيارات',
    ];
}

/** دالة الترتيب العشوائي حسب المحرّك — SQLite تستخدم RANDOM() وMySQL تستخدم RAND() */
function sql_random(): string {
    return DB_DRIVER === 'mysql' ? 'RAND()' : 'RANDOM()';
}

/** كل الألعاب بلا مؤقّت — المحرّك يستخدم دائماً النسخة الهادئة (calm). */
function game_is_calm_age(?int $age): bool {
    return true;
}

/**
 * رابط تضمين فيديو المهمة أو الشخصية.
 *
 * youtube-nocookie.com لا يزرع كوكيز التتبّع قبل التشغيل — وهذا هو الوضع
 * المطلوب في تطبيق يشاهده أطفال. rel=0 لا يُلغي المقترحات (غيّرت يوتيوب ذلك
 * سنة 2018) لكنه يحصرها في القناة نفسها، وهو أقصى ما تسمح به المنصة.
 * playsinline يمنع الفتح بملء الشاشة تلقائياً على أجهزة iOS.
 */
function youtube_embed_url(string $id): string {
    return 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id)
         . '?rel=0&modestbranding=1&playsinline=1';
}

/**
 * يستخرج معرّف الفيديو (11 حرفاً) من أي شكل يلصقه الأدمن: المعرّف نفسه، أو
 * رابط watch?v=، أو shorts/، أو youtu.be/، أو embed/، أو live/ — مع أي
 * معاملات إضافية (?si=… التي تضيفها المشاركة من التطبيق).
 *
 * كان الحقل يُخزَّن كما هو، فرابط Shorts كامل (60+ حرفاً) أسقط الطلب
 * بـ«Data too long for column youtube_id» على MySQL، ولو دخل لكان
 * youtube_embed_url() قد بنى رابطاً معطوباً منه.
 *
 * يُعيد null للفراغ أو لما لا يشبه معرّف يوتيوب — لا يُقتطع الإدخال أبداً.
 */
function youtube_id_from_input(?string $raw): ?string {
    $s = trim((string)$raw);
    if ($s === '') return null;
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $s)) return $s;

    if (!preg_match('~^https?://~i', $s)) $s = 'https://' . $s;
    $u = parse_url($s);
    if (!$u || empty($u['host'])) return null;
    $host = strtolower(preg_replace('/^(www|m|music)\./', '', $u['host']));
    $path = $u['path'] ?? '';

    $candidate = null;
    if ($host === 'youtu.be') {
        $candidate = ltrim($path, '/');
    } elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
        parse_str($u['query'] ?? '', $q);
        if (!empty($q['v'])) {
            $candidate = $q['v'];
        } elseif (preg_match('~^/(?:shorts|embed|live|v)/([^/?#]+)~', $path, $m)) {
            $candidate = $m[1];
        }
    }
    $candidate = $candidate !== null ? explode('/', $candidate)[0] : null;
    return ($candidate !== null && preg_match('/^[A-Za-z0-9_-]{11}$/', $candidate)) ? $candidate : null;
}

/**
 * طول الجولة كما يستهلكها GamesEngine: خمسة أسئلة، وأربعة مشاهد مغامرة.
 * القيمتان مرآة لـ TOTAL في runQuiz وslice في runAdventure. المحرّك يقصّ
 * القائمة العشوائية، والعمر لا يدخل في اختيار الصفوف.
 */
const GAME_QUIZ_ROUND      = 5;
const GAME_ADVENTURE_SCENES = 4;

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
 * صفوف محتوى موضوع واحد، مرتّبة عشوائياً، بلا فلتر عمر.
 * المحرّك يأخذ أول جولة فقط. $table ثابت في الكود ومحصور بقائمة،
 * ولا يأتي من المستخدم أبداً.
 */
function game_topic_rows(PDO $pdo, string $table, string $key): array {
    $cols = [
        'game_questions' => 'question, answer',
        'game_scenarios' => 'prompt, choices_json',
    ][$table] ?? null;
    if ($cols === null) return [];

    $rand = sql_random();
    $st = $pdo->prepare("SELECT {$cols} FROM {$table} WHERE topic_key = ? AND active = 1 ORDER BY {$rand}");
    $st->execute([$key]);
    return $st->fetchAll();
}

/**
 * محتوى اللعبة كما يستهلكه GamesEngine: الأيقونات + بنك صح/خطأ +
 * سيناريوهات المغامرة، من كل صفوف الموضوع النشطة وبترتيب عشوائي.
 *
 * أشكال البيانات (q/a للسؤال، t/c/l/g/r للمغامرة) هي نفسها التي كان المحرّك
 * يقرأها من الثوابت، فعقد الاستهلاك لم يتغيّر — تغيّر المصدر فقط.
 * الترتيب العشوائي هو ما يجعل إعادة اللعب مختلفة (كانت متطابقة كل مرة).
 */
function game_content_for(PDO $pdo, ?string $category): array {
    $topic = game_topic_for_category($pdo, $category);
    if (!$topic) return ['topic' => 'general', 'label' => 'عام', 'icons' => [], 'quiz' => [], 'adventure' => []];

    $key = $topic['topic_key'];

    $quiz = array_map(
        fn($r) => ['q' => $r['question'], 'a' => (bool)(int)$r['answer']],
        game_topic_rows($pdo, 'game_questions', $key)
    );

    $adventure = [];
    foreach (game_topic_rows($pdo, 'game_scenarios', $key) as $r) {
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
 * المغامرة الكبرى: ثمانية فصول ثابتة تحكي شهر الطفل الحقيقي كرحلة واحدة
 * متماسكة (بداية ← طريق ← كنز ← رفاق ← عقبة ← دفتر ← قمة ← خاتمة)، لا كقائمة
 * إحصاءات. كل فصل موجود دائماً حتى لو غاب مصدره؛ عندئذ يُروى بصيغة عامة
 * تحافظ على تسلسل الحكاية. كل مشهد: kind + icon + title + caption + grad
 * (+ speaker/quote).
 *
 * الجمل اسمية حول اسم الطفل عمداً — لا حقل جنس في children.
 */
function grand_story_scenes(array $child, array $stories, array $sum, string $companions): array {
    $name = $child['name'];
    $comp = trim(explode(' و', $companions)[0] ?? '') ?: 'الرفيق';
    $grads = ['#1B1035,#6C63FF','#6C63FF,#FF6FA5','#2EC4B6,#6C63FF','#FF7A50,#FFC93C','#FF6FA5,#FFC93C','#2EC4B6,#241645','#3A2A75,#FF7A50','#FFC93C,#FF7A50'];
    $scenes = [];
    $add = function (string $kind, string $icon, string $title, string $caption, ?string $speaker = null, ?string $quote = null) use (&$scenes, $grads) {
        $sc = ['kind' => $kind, 'icon' => $icon, 'title' => $title, 'caption' => $caption, 'grad' => $grads[count($scenes) % count($grads)]];
        if ($speaker !== null && $quote !== null) { $sc['speaker'] = $speaker; $sc['quote'] = $quote; }
        $scenes[] = $sc;
    };
    $days  = max(1, (int)($sum['days'] ?? count($stories)));
    $tasks = (int)($sum['tasks_done'] ?? 0);

    // 1) البداية
    $add('cover', '🌟', 'البداية', "قبل ثلاثين يوماً، حقيبة صغيرة وخريطة بيضاء… وخطوة أولى لـ{$name} مع {$companions}. لم يكن أحد يعرف إلى أين يقود الطريق، لكن الجميع كان مستعداً.", $comp, "كل رحلة كبيرة تبدأ بخطوة صغيرة… وهذه خطوتك يا {$name}!");

    // 2) الطريق: المهام
    $add('chapter', '✅', 'الطريق', $tasks > 0
        ? "في كل صباح من {$days} يوماً، محطة جديدة على الخريطة: {$tasks} مهمة مُنجزة واحدة تلو الأخرى — بعضها سهل كنسمة، وبعضها احتاج نفساً عميقاً. ومع كل مهمة، خطّ جديد يُرسم على الخريطة."
        : "في كل صباح من {$days} يوماً، محطة جديدة على الخريطة، وخطوة أثبت من التي قبلها. الخريطة البيضاء بدأت تمتلئ بالخطوط والألوان.");

    // 3) الكنز: أقوى مجال
    $cats = $sum['categories'] ?? [];
    $topCat = array_key_first($cats);
    $add('chapter', $topCat ? category_icon($topCat) : '💎', 'الكنز المخفي', $topCat
        ? "وفي منتصف الرحلة، اكتشاف: هناك شيء يبرع فيه {$name} أكثر من غيره — «{$topCat}»، {$cats[$topCat]} مهمة في هذا المجال وحده. هذا هو الكنز الذي لا يُشترى: معرفة ما نُحسنه."
        : "وفي منتصف الرحلة، اكتشاف صغير: كل يوم يكشف لـ{$name} شيئاً جديداً يُحسنه. هذا هو الكنز الذي لا يُشترى: معرفة ما نُحبّ وما نتقن.",
        $comp, "كنت أعرف أن هناك كنزاً… لكن لم أتوقع أنه بداخلك!");

    // 4) رفاق من التاريخ
    $figs = $sum['figures'] ?? [];
    if ($figs) {
        $shown = array_slice($figs, 0, 3);
        $more = count($figs) - count($shown);
        $list = implode('، ', $shown) . ($more > 0 ? " و{$more} آخرين" : '');
        $add('figure', '🕌', 'رفاق من التاريخ', "على جانب الطريق، حكايات قديمة انضمت إلى الرحلة: {$list}. كل واحد منهم مشى ذات يوم طريقاً شبيهاً، وترك إشارة لمن يأتي بعده… واليوم يمشي {$name} على الإشارات نفسها.");
    } else {
        $add('figure', '🕌', 'رفاق من التاريخ', "على جانب الطريق، حكايات قديمة تهمس من بين الصفحات: أبطال مشوا قبلنا وتركوا إشارات لمن يأتي بعدهم. واليوم يمشي {$name} على الإشارات نفسها.");
    }

    // 5) العقبة
    $games = (int)($sum['games_played'] ?? 0);
    $add('obstacle', '🌩️', 'العقبة', "ولا رحلة بلا عاصفة. جاءت أيام ثقيلة: صوت يقول «خلّيها لبكرة»، وحقيبة تبدو أثقل. لكن الأبطال لا يعودون من منتصف الطريق" . ($games > 0 ? " — بل يتدرّبون: {$games} لعبة درّبت العقل على التركيز والصبر والسرعة." : " — بل يأخذون نفساً عميقاً… ويكملون."),
        $comp, "الغيوم تمرّ يا {$name}. نفس عميق… وخطوة.");

    // 6) دفتر الرحلة: لقطات من اليوميات
    $highlights = [];
    foreach ($stories as $st) {
        foreach (json_decode_safe($st['scenes_json'], []) as $one) {
            if (!empty($one['caption']) && ($one['kind'] ?? '') !== 'cover') $highlights[] = (string)$one['caption'];
        }
    }
    if ($highlights) {
        $pick = [];
        $step = max(1, (int)floor(count($highlights) / 3));
        for ($k = 0; $k < 3 && ($k * $step) < count($highlights); $k++) {
            $h = $highlights[$k * $step];
            // بلا mbstring (غير مضمونة على كل الاستضافات): القصّ عبر preg بوحدة u
            $pick[] = preg_replace('/^(.{108}).{3,}$/us', '$1…', $h);
        }
        $add('chapter', '📖', 'من دفتر الرحلة', "أوراق من دفتر الأيام: " . implode(' ▪ ', $pick));
    } else {
        $add('chapter', '📖', 'من دفتر الرحلة', "دفتر الرحلة امتلأ بصفحات صغيرة: صباحات مشمسة، ومطر خفيف على النافذة، ونجوم صغيرة تُجمع كل مساء. كل صفحة باسم {$name}.");
    }

    // 7) القمة: النجوم والتقدّم
    $pts = (int)$child['points'];
    $growth = $sum['growth'] ?? null;
    $axis = $sum['best_axis']['axis'] ?? null;
    $climax = "وعند قمة الجبل، السماء مليئة: {$pts} نجمة في رصيد {$name} ✨";
    if ($growth) $climax .= " وتحليل السلوك ارتفع من {$growth['from']} إلى {$growth['to']} من 3 — تقدّم يُرى بالأرقام.";
    if ($axis) $climax .= " وأقوى ما يميّز البطل اليوم: «{$axis}».";
    if (!$growth && !$axis) $climax .= " وليست النجوم وحدها ما جُمع: شهر كامل من الشجاعة والصبر والمحاولة.";
    $add('climax', '⭐', 'القمة', $climax, $comp, "كل نجمة هناك تعرف اسمك يا {$name}!");

    // 8) الخاتمة… والبداية
    $add('end', '🏆', 'النهاية… والبداية', "وهكذا انتهت ثلاثون يوماً من النمو والشجاعة والتعلّم. الخريطة البيضاء صارت ملوّنة، والحقيبة الصغيرة صارت مليئة بالحكايات. لكن الأبطال يعرفون السرّ: كل نهاية هي أول الطريق. المغامرة القادمة تبدأ غداً!", $comp, "أحسنت يا {$name}. أراك على أول الطريق… غداً!");

    return $scenes;
}


/* ============================================================
   نظام "تذكرني" — دخول تلقائي بدون إيميل وكلمة مرور
   ============================================================ */

/**
 * يسجّل الدخول التلقائي: يخزّن توكن آمن في الداتابيس + كوكي
 */

function kidora_remember_login(PDO $pdo, int $childId): void {
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 90);
    
    // خزّن في الداتابيس (نفس الكود)
    $stmt = $pdo->prepare("UPDATE children SET remember_token = ?, remember_expires = ? WHERE id = ?");
    $stmt->execute([$token, $expires, $childId]);
    
    // ✅ الصيغة القديمة — تشتغل على كل إصدارات PHP
    $secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $expireTs = time() + 60 * 60 * 24 * 90;
    
    setcookie(
        'kidora_remember',    // name
        $token,               // value
        $expireTs,            // expires
        '/',                  // path
        '',                   // domain
        $secure,              // secure
        true                  // httponly
    );
}

/**
 * يمسح التوكن (عند الخروج)
 */
function kidora_clear_remember(PDO $pdo, ?int $childId = null): void {
    if ($childId) {
        $pdo->prepare("UPDATE children SET remember_token = NULL, remember_expires = NULL WHERE id = ?")
            ->execute([$childId]);
    }
    setcookie('kidora_remember', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

/**
 * يحاول الدخول التلقائي من الكوكي
 * @return array|null بيانات الطفل لو نجح، أو null
 */
function kidora_try_auto_login(PDO $pdo): ?array {
    $token = $_COOKIE['kidora_remember'] ?? '';
    
    // تحقق من صيغة التوكن (64 hex)
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    
    // ابحث في الداتابيس
    // NOW() غير موجودة في SQLite — الوقت يُمرَّر من PHP (توقيت التطبيق) ليعمل على المحرّكين
    $stmt = $pdo->prepare("SELECT * FROM children WHERE remember_token = ? AND remember_expires > ? LIMIT 1");
    $stmt->execute([$token, date('Y-m-d H:i:s')]);
    $child = $stmt->fetch();
    
    if (!$child) {
        // توكن غلط أو منتهي → امسح الكوكي بهدوء
        setcookie('kidora_remember', '', [
            'expires' => time() - 3600,
            'path' => '/'
        ]);
        return null;
    }
    
    // ✅ نجح! جدّد التوكن (rotation) وابدأ الجلسة
    kidora_remember_login($pdo, (int)$child['id']);
    
    session_regenerate_id(true);
    $_SESSION['child_id']   = $child['id'];
    $_SESSION['child_name'] = $child['name'];
    
    return $child;
}


// 


/**
 * القصة اليومية: مشهد واحد فقط — لوحة واحدة تحكي يوم الطفل الحقيقي
 * (مهام اليوم المنجزة عبر story_line، الشخصية التراثية المرتبطة بالمهمة،
 * النجوم، وحكمة قصيرة) في فقرة سردية واحدة يقرأها الرفيق دفعة واحدة.
 *
 * العشوائية مثبَّتة باليوم والطفل: إعادة تحميل الصفحة لا تغيّر القصة.
 * الجمل اسمية حول اسم الطفل عمداً — لا حقل جنس في children.
 *
 * المشهد: kind='single' + icon + title + caption + grad + speaker + quote.
 * StoryPlayer يعرض القصة ذات المشهد الواحد كلوحة واحدة (وضع single).
 * القصص القديمة متعددة المشاهد ما زالت تُعرض بوضع الكتاب.
 */
function daily_story_scenes(PDO $pdo, array $child, array $doneTasks, array $companions, int $dayIndex): array {
    $name = $child['name'];
    $doneTasks = array_values(array_filter($doneTasks));
    $compNames = array_map(fn($c) => trim((string)$c['name']), $companions);
    $comp1 = $compNames[0] ?? 'الرفيق';

    mt_srand(crc32($child['id'] . '|' . today_key()));
    $pickOne = fn(array $arr) => $arr[mt_rand(0, count($arr) - 1)];

    $openings = [
        "في صباحٍ هادئ ونسمة تحمل رائحة الخبز الطازج، بدأ يوم {$name} و{$comp1} ينتظر عند الباب.",
        "الشمس تطلّ من خلف الغيوم والعصافير تتسابق على الشرفة — يوم جديد لـ{$name} و{$comp1} يلوّح: اليوم مختلف!",
        "قطرات مطر خفيفة على الزجاج ودفء في البيت، وفي قلب {$name} حماس، و{$comp1} يقفز فوق الوسادة.",
        "صباح مشمس وسماء زرقاء بلا حدود، حقيبة صغيرة وابتسامة أكبر منها — هكذا بدأ يوم {$name}.",
    ];

    // مهام اليوم في جملة واحدة متدفّقة
    $lines = [];
    foreach ($doneTasks as $t) {
        $l = rtrim(str_replace("\n", ' ', (string)($t['story_line'] ?: $t['title'])));
        // rtrim() يعمل بالبايت ويكسر الحرف الأخير مع «،» متعددة البايتات — نستخدم preg بوحدة u
        if ($l !== '') $lines[] = preg_replace('/[.،\s]+$/u', '', $l);
    }
    $journey = '';
    if ($lines) {
        $joiners = ['أولاً، ', 'ثم ', 'وبعدها ', 'وقبل الغروب ', 'وأخيراً '];
        $parts = [];
        foreach ($lines as $i => $l) $parts[] = $joiners[min($i, count($joiners) - 1)] . $l;
        $journey = ' ' . implode('، ', $parts) . '.';
    }

    // شخصية من تراثنا مرتبطة بمهمة اليوم لا عشوائية
    $figureLine = '';
    foreach ($doneTasks as $t) {
        if ($f = figure_for_task($pdo, $t)) {
            $figureLine = " وعلى جانب الطريق حكاية قديمة تهمس: {$f['name']}، {$f['title']} — وفي قلب {$name} اليوم شيء من تلك الروح.";
            break;
        }
    }

    $totalPts = (int)array_sum(array_column($doneTasks, 'points'));
    $cats = array_count_values(array_map(fn($t) => (string)$t['category'], $doneTasks));
    arsort($cats);
    $topCat = array_key_first($cats) ?: '';
    $morals = [
        'مهارات حياتية' => 'من يرتّب الأشياء الصغيرة يرتّب الأحلام الكبيرة',
        'تعلّم'         => 'كل كلمة نتعلّمها نافذة جديدة على العالم',
        'صحة'           => 'الجسد القوي بيت للقلب الشجاع',
        'إبداع'         => 'الخيال جناحان ومن يستخدمهما يطير',
        'قيم'           => 'القلب الطيب أقوى من أي قوة في العالم',
        'صحة نفسية'     => 'الهدوء قوة لا يعرفها إلا الشجعان',
        'حماية'         => 'الشجاعة أن تقول «لا» حين يجب',
        'مسؤولية'       => 'الوعد الصغير الذي نفي به يصنع إنساناً كبيراً',
        'اجتماعي'       => 'الفرح الذي نتقاسمه يكبر',
        'ثقافي'         => 'من عرف جذوره لم تُسقطه أي ريح',
    ];
    $moral = $morals[$topCat] ?? 'كل يوم نحاول فيه هو يوم ننتصر فيه';

    $caption = $pickOne($openings) . $journey . $figureLine
        . " وعند الغروب، {$totalPts} نجمة تلمع في جيب {$name} ✨ وحكمة اليوم: {$moral} 🌙";

    $quote = $pickOne([
        "هذا ما أسمّيه شجاعة! نلتقي غداً على أول الطريق.",
        "لم أشكّ لحظة في قدرتك يا {$name}. الغد ينتظرنا!",
        "السماء مليئة بالنجوم الليلة… وكل نجمة تعرف اسمك!",
    ]);

    mt_srand(); // إعادة البذرة العشوائية للحالة الطبيعية لبقية الطلب
    return [[
        'kind'    => 'single',
        'icon'    => $doneTasks ? category_icon((string)$doneTasks[0]['category']) : '📖',
        'title'   => "مغامرة {$name} — اليوم {$dayIndex}",
        'caption' => $caption,
        'grad'    => $pickOne(['#6C63FF,#FF6FA5', '#2EC4B6,#6C63FF', '#FF7A50,#FFC93C', '#FF6FA5,#FFC93C', '#3A2A75,#FF7A50']),
        'speaker' => $comp1,
        'quote'   => $quote,
    ]];
}

/** يبني مجلد صورة/صوت مخصّص لكل شخصية assets/images/characters/{slug}/ أو assets/audio/characters/{slug}/ */
function character_media_dir(string $kind, string $slug): string {
    $base = $kind === 'audio' ? __DIR__ . '/../assets/audio/characters' : __DIR__ . '/../assets/images/characters';
    $dir = $base . '/' . preg_replace('/[^a-z0-9_-]/i', '', $slug);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    return $dir;
}


/* ============================================================
   يتفحص الكوكي بدون ما يسجّل الدخول
   عشان نعرض "متابعة كـ ريمان"
   ============================================================ */
function kidora_peek_remember(PDO $pdo): ?array {
    $token = $_COOKIE['kidora_remember'] ?? '';
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) return null;

    $stmt = $pdo->prepare("SELECT * FROM children WHERE remember_token = ? AND remember_expires > ? LIMIT 1");
    $stmt->execute([$token, date('Y-m-d H:i:s')]);
    $child = $stmt->fetch();
    if (!$child) return null;

    // نجيب الشخصية الأولى عشان اللون والأيقونة
    if (!empty($child['character_1'])) {
        $c = get_character($pdo, (int)$child['character_1']);
        if ($c) {
            $child['_char_color'] = $c['color'] ?? '#6C63FF';
            $child['_char_icon']  = character_icons($c)[0] ?? '✨';
        }
    }
    return $child;
}


