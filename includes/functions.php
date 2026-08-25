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

/** يبني مجلد صورة/صوت مخصّص لكل شخصية assets/images/characters/{slug}/ أو assets/audio/characters/{slug}/ */
function character_media_dir(string $kind, string $slug): string {
    $base = $kind === 'audio' ? __DIR__ . '/../assets/audio/characters' : __DIR__ . '/../assets/images/characters';
    $dir = $base . '/' . preg_replace('/[^a-z0-9_-]/i', '', $slug);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    return $dir;
}
