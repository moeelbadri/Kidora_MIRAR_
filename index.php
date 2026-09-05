<?php
// index.php - Landing Page + Login/Register (نسخة بنفسجية غامقة)
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// تسجيل الخروج
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// إذا كان الطفل مسجلاً دخوله، حوّله إلى لوحة التحكم
if (!empty($_SESSION['child_id'])) {
    $chk = $pdo->prepare("SELECT * FROM children WHERE id = ?");
    $chk->execute([$_SESSION['child_id']]);
    $chkChild = $chk->fetch();
    header('Location: ' . ($chkChild && needs_assessment($chkChild) ? 'welcome.php' : 'dashboard.php'));
    exit;
}

// جلب البيانات للـ Landing
$characters = all_characters($pdo);
$plans = $pdo->query("SELECT * FROM subscription_plans ORDER BY sort_order ASC")->fetchAll();

// متغيرات الـ Login/Register
$loginError = null;
$registerError = null;

// ============================================================
// معالجة تسجيل الدخول
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $loginError = 'الرجاء ملء جميع الحقول.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, password FROM children WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['child_id'] = $user['id'];
            $_SESSION['child_name'] = $user['name'];
            $fullUser = $pdo->prepare("SELECT * FROM children WHERE id = ?");
            $fullUser->execute([$user['id']]);
            $fullUser = $fullUser->fetch();
            header('Location: ' . (needs_assessment($fullUser) ? 'welcome.php' : 'dashboard.php'));
            exit;
        } else {
            $loginError = 'البريد الإلكتروني أو كلمة المرور غير صحيحة.';
        }
    }
}

// ============================================================
// معالجة التسجيل — الشخصيتان أولاً، ثم بيانات الحساب
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = trim($_POST['child_name'] ?? '');
    $age = (int)($_POST['child_age'] ?? 0);
    $parentName = trim($_POST['parent_name'] ?? '');
    $parentPhone = trim($_POST['parent_phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $char1 = (int)($_POST['character_1'] ?? 0);
    $char2 = (int)($_POST['character_2'] ?? 0);

    if (!$char1 || !$char2 || $char1 === $char2) {
        $registerError = 'الرجاء اختيار شخصيتين مختلفتين أولاً.';
    } elseif (empty($name) || !$age || empty($parentName) || empty($parentPhone) || empty($email) || empty($password) || empty($confirm)) {
        $registerError = 'الرجاء ملء جميع الحقول المطلوبة.';
    } elseif ($password !== $confirm) {
        $registerError = 'كلمة المرور غير متطابقة مع تأكيدها.';
    } elseif (strlen($password) < 6) {
        $registerError = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.';
    } else {
        $chk = $pdo->prepare("SELECT COUNT(*) c FROM characters WHERE id IN (?,?) AND is_premium = 1");
        $chk->execute([$char1, $char2]);
        if ((int)$chk->fetch()['c'] > 0) {
            $registerError = 'إحدى الشخصيتين المختارتين مدفوعة ولا يمكن اختيارها قبل تفعيل الاشتراك.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM children WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $registerError = 'هذا البريد الإلكتروني مسجّل مسبقاً.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $ins = $pdo->prepare("INSERT INTO children (name, email, password, age, parent_name, parent_phone, character_1, character_2, active_character)
                                       VALUES (?,?,?,?,?,?,?,?,?)");
                $ins->execute([$name, $email, $hashed, $age, $parentName, $parentPhone, $char1, $char2, $char1]);
                $childId = (int)$pdo->lastInsertId();

                $freePlan = $pdo->query("SELECT id FROM subscription_plans ORDER BY sort_order ASC LIMIT 1")->fetch();
                if ($freePlan) {
                    $subIns = $pdo->prepare("INSERT INTO subscriptions (child_id, plan_id, status, activated_at, activated_by) VALUES (?,?,'active',CURRENT_TIMESTAMP,'system')");
                    $subIns->execute([$childId, $freePlan['id']]);
                }

                $_SESSION['child_id'] = $childId;
                $_SESSION['child_name'] = $name;
                header('Location: subscriptions.php?welcome=1');
                exit;
            }
        }
    }
}

// ============================================================
// تحضير بيانات الشخصيات لـ JS (للتسجيل)
// ============================================================
$charDataForJS = array_map(function($c){
    return [
        'id' => (int)$c['id'],
        'color' => $c['color'],
        'move' => $c['move_type'],
        'icons' => character_icons($c),
        'image' => $c['image_path'],
        'name' => $c['name'],
        'is_premium' => (bool)$c['is_premium']
    ];
}, $characters);

// تحضير الشخصيات للكاروسيل
$carouselChars = [];
foreach ($characters as $c) {
    $carouselChars[] = [
        'name' => $c['name'],
        'image' => $c['image_path'],
        'icon' => character_icons($c)[0] ?? '✨',
        'color' => $c['color'] ?? '#a78bfa',
        'trait' => $c['trait'] ?? 'مميز'
    ];
}

$__pageTitle = 'Kidora — منصة التعلم بالمغامرة';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

<style>
    /* ============================================================
       الأنماط العامة – خلفية متدرجة بنفسجية غامقة
       ============================================================ */
    :root {
        --bg-primary: #0a061a;
        --bg-secondary: #140a2a;
        --bg-card: rgba(255,255,255,0.04);
        --bg-card-hover: rgba(255,255,255,0.06);
        --text-primary: #f1f5f9;
        --text-secondary: #c4b5d4;
        --text-muted: #b9abd4;
        --primary: #a78bfa;
        --primary-dark: #7c3aed;
        --primary-glow: rgba(167,139,250,0.15);
        --purple: #a78bfa;
        --purple-dark: #7c3aed;
        --border-light: rgba(255,255,255,0.04);
        --shadow-heavy: 0 30px 80px rgba(0,0,0,0.8);
        --shadow-soft: 0 10px 40px rgba(0,0,0,0.5);
        --radius-xl: 32px;
        --radius-lg: 24px;
        --radius-md: 16px;
        --transition: 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    body {
        background: var(--bg-primary);
        background-image:
            radial-gradient(ellipse at 20% 30%, rgba(167,139,250,0.12), transparent 50%),
            radial-gradient(ellipse at 80% 70%, rgba(124,58,237,0.08), transparent 50%),
            radial-gradient(ellipse at 50% 50%, rgba(139,92,246,0.05), transparent 60%);
        color: var(--text-primary);
        font-family: 'Segoe UI', 'Tajawal', system-ui, sans-serif;
        line-height: 1.6;
        min-height: 100vh;
    }

    .landing-page {
        padding: 0 20px 40px;
        max-width: 1200px;
        margin: 0 auto;
    }

    /* ============================================================
       القسم الرئيسي (Hero) – متجاوب بالكامل
       ============================================================ */
    .hero-section {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        padding: 20px 0 30px;
        gap: 24px;
        min-height: auto;
    }
    .hero-content {
        flex: 1 1 400px;
        order: 1;
    }
    .hero-badge {
        display: inline-block;
        background: var(--primary-glow);
        color: var(--primary);
        padding: 4px 14px;
        border-radius: 40px;
        font-weight: 700;
        font-size: 12px;
        border: 1px solid rgba(167,139,250,0.15);
        letter-spacing: 0.5px;
        margin-bottom: 12px;
        backdrop-filter: blur(4px);
    }
    .hero-content h1 {
        font-size: 52px;
        font-weight: 900;
        line-height: 1.1;
        margin-bottom: 8px;
        letter-spacing: -1px;
        text-shadow: 0 2px 14px rgba(10,6,26,.55);
    }
    .hero-content h1 .highlight {
        background-image: linear-gradient(135deg, #ffffff, #c4b5fd);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .hero-subtitle {
        font-size: 24px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 10px;
        text-shadow: 0 2px 14px rgba(10,6,26,.55);
    }
    .hero-description {
        font-size: 17px;
        color: #d9d0ff;
        max-width: 520px;
        line-height: 1.8;
        margin-bottom: 24px;
        text-shadow: 0 1px 10px rgba(10,6,26,.5);
    }
    .hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }
    .hero-actions .btn-large {
        padding: 14px 32px;
        font-size: 18px;
        font-weight: 800;
        border-radius: 60px;
    }

    /* ===== القسم البصري (الشخصية والإحصائيات) ===== */
    .hero-visual {
        flex: 1 1 300px;
        text-align: center;
        background: var(--bg-card);
        backdrop-filter: blur(12px);
        border-radius: var(--radius-xl);
        padding: 24px 16px 20px;
        border: 1px solid var(--border-light);
        position: relative;
        box-shadow: var(--shadow-soft);
        order: 2;
        min-height: 300px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        perspective: 1000px;
    }
    .hero-visual .glow-ring {
        position: absolute;
        inset: -2px;
        border-radius: var(--radius-xl);
        background: radial-gradient(circle at 30% 40%, rgba(167,139,250,0.08), transparent 60%);
        pointer-events: none;
        z-index: 0;
    }

    /* ===== كاروسيل الشخصيات 3D ===== */
    .hero-carousel {
        position: relative;
        width: 100%;
        max-width: 180px;
        height: 180px;
        margin: 0 auto 8px;
        z-index: 1;
        transform-style: preserve-3d;
        transition: transform 0.1s ease-out;
        cursor: grab;
    }
    .hero-carousel:active {
        cursor: grabbing;
    }
    .hero-carousel-item {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) scale(0.5);
        opacity: 0;
        transition: all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        width: 140px;
        height: 140px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 80px;
        border-radius: 0;
        filter: drop-shadow(0 10px 30px rgba(0,0,0,0.5));
        pointer-events: none;
    }
    .hero-carousel-item.active {
        transform: translate(-50%, -50%) scale(1) rotateY(0deg);
        opacity: 1;
        z-index: 10;
        filter: drop-shadow(0 20px 40px rgba(167,139,250,0.4));
    }
    .hero-carousel-item img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        border-radius: 0;
        filter: drop-shadow(0 10px 30px rgba(0,0,0,0.3));
    }
    .hero-carousel-item .char-emoji {
        font-size: 100px;
        line-height: 1;
        filter: drop-shadow(0 10px 30px rgba(0,0,0,0.3));
    }
    .hero-char-name {
        position: relative;
        z-index: 2;
        font-weight: 800;
        font-size: 18px;
        color: var(--text-primary);
        margin-top: 0;
        background: rgba(0,0,0,0.3);
        padding: 4px 16px;
        border-radius: 40px;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.05);
        display: inline-block;
        transition: all 0.5s;
        min-height: 34px;
        line-height: 1.4;
    }
    .hero-char-name span {
        color: var(--primary);
    }

    /* ===== الإحصائيات ===== */
    .hero-stats {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-top: 12px;
        position: relative;
        z-index: 1;
        flex-wrap: wrap;
    }
    .hero-stats .stat {
        text-align: center;
        background: rgba(255,255,255,0.03);
        padding: 4px 14px;
        border-radius: 40px;
        border: 1px solid rgba(255,255,255,0.03);
        backdrop-filter: blur(4px);
        min-width: 60px;
    }
    .hero-stats .stat .number {
        font-size: 24px;
        font-weight: 900;
        color: var(--primary);
        display: block;
        line-height: 1.2;
    }
    .hero-stats .stat .label {
        font-size: 11px;
        color: var(--text-muted);
        font-weight: 600;
        display: block;
        line-height: 1.3;
    }

    /* ============================================================
       باقي الأقسام
       ============================================================ */
    .section-head {
        text-align: center;
        margin: 40px 0 24px;
    }
    .eyebrow {
        color: var(--primary);
        font-weight: 700;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 4px;
    }
    .section-title {
        font-size: 30px;
        font-weight: 900;
        color: var(--text-primary);
        margin: 6px 0 8px;
    }
    .section-sub {
        color: var(--text-secondary);
        font-size: 16px;
        max-width: 600px;
        margin: 0 auto;
        line-height: 1.7;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 10px;
    }
    .feature-card {
        background: var(--bg-card);
        backdrop-filter: blur(8px);
        border-radius: var(--radius-lg);
        padding: 24px 16px 20px;
        text-align: center;
        border: 1px solid var(--border-light);
        transition: var(--transition);
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    }
    .feature-card:hover {
        transform: translateY(-8px);
        border-color: rgba(167,139,250,0.2);
        background: var(--bg-card-hover);
        box-shadow: 0 16px 50px rgba(0,0,0,0.4);
    }
    .feature-icon {
        font-size: 40px;
        margin-bottom: 12px;
    }
    .feature-card h3 {
        font-size: 18px;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 6px;
    }
    .feature-card p {
        color: var(--text-secondary);
        font-size: 14px;
        line-height: 1.7;
        margin: 0;
    }

    .video-wrapper {
        max-width: 800px;
        margin: 0 auto;
        border-radius: var(--radius-xl);
        overflow: hidden;
        aspect-ratio: 16/9;
        background: #000;
        box-shadow: var(--shadow-heavy);
        border: 1px solid var(--border-light);
    }
    .video-wrapper iframe {
        width: 100%;
        height: 100%;
        border: 0;
    }

    /* ============================================================
       الشخصيات – سكرول أفقي حيوي
       ============================================================ */
    .characters-scroll-wrapper {
        position: relative;
        width: 100%;
        overflow-x: auto;
        overflow-y: visible;
        padding: 16px 0 24px;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
        scroll-snap-type: x mandatory;
        mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
        -webkit-mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
    }
    .characters-track {
        display: flex;
        gap: 20px;
        padding: 8px 16px;
        width: max-content;
        animation: autoScroll 30s linear infinite;
    }
    .characters-track:hover {
        animation-play-state: paused;
    }
    .characters-scroll-wrapper:has(.character-card-enhanced:hover) .characters-track {
        animation-play-state: paused;
    }
    @keyframes autoScroll {
        0% { transform: translateX(0); }
        100% { transform: translateX(-50%); }
    }
    .character-card-enhanced {
        flex: 0 0 180px;
        scroll-snap-align: start;
        border-radius: 24px;
        padding: 16px 12px 14px;
        text-align: center;
        position: relative;
        transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        border: 1px solid rgba(255,255,255,0.06);
        box-shadow: 0 8px 30px rgba(0,0,0,0.5);
        overflow: hidden;
        cursor: default;
        backdrop-filter: blur(4px);
        transform: scale(0.95);
        opacity: 0.8;
    }
    .character-card-enhanced:hover {
        transform: scale(1.05) translateY(-12px);
        opacity: 1;
        box-shadow: 0 20px 50px rgba(0,0,0,0.6), 0 0 40px var(--char-glow);
        border-color: var(--char-color);
        z-index: 10;
    }
    .character-card-enhanced .char-glow-ring {
        position: absolute;
        inset: -2px;
        border-radius: 24px;
        background: radial-gradient(circle at 30% 20%, var(--char-color), transparent 70%);
        opacity: 0;
        transition: opacity 0.4s;
        pointer-events: none;
    }
    .character-card-enhanced:hover .char-glow-ring {
        opacity: 0.6;
    }
    .char-media {
        aspect-ratio: 1/1;
        border-radius: 50%;
        overflow: hidden;
        margin: 0 auto 10px;
        width: 80px;
        height: 80px;
        background: rgba(0,0,0,0.3);
        border: 3px solid rgba(255,255,255,0.08);
        transition: all 0.4s;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    .character-card-enhanced:hover .char-media {
        border-color: var(--char-color);
        box-shadow: 0 0 30px var(--char-glow);
    }
    .char-media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.4s;
    }
    .character-card-enhanced:hover .char-media img {
        transform: scale(1.08);
    }
    .char-emoji {
        font-size: 40px;
    }
    .char-info .name {
        font-weight: 900;
        font-size: 17px;
        color: #fff;
        margin-bottom: 2px;
        text-shadow: 0 2px 8px rgba(0,0,0,0.6);
    }
    .char-info .title {
        font-size: 12px;
        color: #c4b5d4;
        margin-bottom: 4px;
        line-height: 1.3;
    }
    .char-trait {
        display: inline-block;
        background: rgba(255,255,255,0.05);
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 11px;
        color: var(--char-color);
        border: 1px solid rgba(255,255,255,0.03);
        font-weight: 600;
    }
    .char-hover-reveal {
        max-height: 0;
        opacity: 0;
        overflow: hidden;
        transition: all 0.5s ease;
        margin-top: 0;
    }
    .character-card-enhanced:hover .char-hover-reveal {
        max-height: 100px;
        opacity: 1;
        margin-top: 10px;
    }
    .char-hover-reveal blockquote {
        font-size: 12px;
        font-style: italic;
        color: #d9d0ff;
        border-right: 3px solid var(--char-color);
        padding-right: 10px;
        margin: 0;
        line-height: 1.6;
        background: rgba(0,0,0,0.2);
        border-radius: 12px;
        padding: 8px 12px;
    }

    /* ============================================================
       خطط الاشتراك
       ============================================================ */
    .plans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 10px;
    }
    .plan-card {
        background: var(--bg-card);
        backdrop-filter: blur(8px);
        border-radius: var(--radius-lg);
        padding: 22px 16px;
        text-align: center;
        border: 1px solid var(--border-light);
        transition: var(--transition);
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    }
    .plan-card:hover {
        transform: translateY(-6px);
        border-color: rgba(167,139,250,0.15);
        box-shadow: 0 16px 40px rgba(0,0,0,0.3);
    }
    .plan-card h3 {
        font-size: 18px;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 4px;
    }
    .plan-card .price {
        font-size: 24px;
        font-weight: 900;
        color: var(--primary);
        margin-bottom: 14px;
    }
    .plan-card ul {
        list-style: none;
        padding: 0;
        margin: 0 0 14px;
        text-align: right;
    }
    .plan-card ul li {
        color: var(--text-secondary);
        font-size: 13px;
        padding: 4px 0;
        border-bottom: 1px solid rgba(255,255,255,0.03);
    }
    .plan-card ul li:last-child {
        border-bottom: none;
    }

    .ai-section {
        background: linear-gradient(145deg, rgba(167,139,250,0.03), rgba(124,58,237,0.03));
        border-radius: var(--radius-xl);
        padding: 32px 20px;
        border: 1px solid var(--border-light);
        text-align: center;
        margin: 32px 0 16px;
        box-shadow: var(--shadow-soft);
    }
    .ai-section .ai-icon {
        font-size: 50px;
        animation: pulse 2.5s ease-in-out infinite;
    }
    @keyframes pulse {
        0%,100% { transform: scale(1); opacity: 0.8; }
        50% { transform: scale(1.08); opacity: 1; }
    }
    .ai-section h2 {
        font-size: 24px;
        font-weight: 900;
        color: var(--text-primary);
        margin: 10px 0 6px;
    }
    .ai-section p {
        color: var(--text-secondary);
        font-size: 15px;
        max-width: 550px;
        margin: 0 auto 14px;
        line-height: 1.8;
    }
    .ai-preview {
        background: rgba(255,255,255,0.02);
        border-radius: var(--radius-lg);
        padding: 20px;
        max-width: 550px;
        margin: 0 auto;
        border: 1px dashed rgba(167,139,250,0.15);
        text-align: right;
    }
    .ai-preview .story-title {
        font-weight: 800;
        color: var(--primary);
        font-size: 18px;
        margin-bottom: 4px;
    }
    .ai-preview .story-snippet {
        color: var(--text-secondary);
        font-size: 14px;
        line-height: 1.8;
    }
    .ai-preview .story-tag {
        display: inline-block;
        background: var(--primary-glow);
        color: var(--primary);
        font-size: 11px;
        padding: 4px 14px;
        border-radius: 30px;
        margin-top: 10px;
        font-weight: 600;
    }

    /* ============================================================
       نموذج تسجيل الدخول / إنشاء حساب
       ============================================================ */
    .auth-section {
        margin-top: 40px;
        border-top: 1px solid var(--border-light);
        padding-top: 32px;
    }
    .auth-wrap {
        display: flex;
        justify-content: center;
        align-items: flex-start;
        padding: 6px 0 20px;
    }
    .auth-card {
        max-width: 600px;
        width: 100%;
        background: var(--bg-card);
        backdrop-filter: blur(16px);
        border-radius: var(--radius-xl);
        padding: 28px 24px;
        border: 1px solid var(--border-light);
        box-shadow: var(--shadow-heavy);
    }
    .auth-logo {
        font-size: 28px;
        font-weight: 900;
        color: var(--primary);
        text-align: center;
        margin-bottom: 4px;
    }
    .auth-sub {
        text-align: center;
        color: var(--text-secondary);
        font-size: 15px;
        margin-bottom: 20px;
        line-height: 1.6;
    }
    .auth-tabs {
        display: flex;
        gap: 6px;
        margin-bottom: 20px;
        background: rgba(255,255,255,0.03);
        border-radius: 60px;
        padding: 4px;
        border: 1px solid rgba(255,255,255,0.03);
    }
    .auth-tab {
        flex: 1;
        padding: 8px;
        border: none;
        background: transparent;
        color: var(--text-secondary);
        font-weight: 700;
        border-radius: 40px;
        cursor: pointer;
        transition: 0.3s;
        font-size: 14px;
    }
    .auth-tab.active {
        background: linear-gradient(135deg, var(--primary), var(--purple-dark));
        color: #fff;
        box-shadow: 0 4px 15px rgba(167,139,250,0.2);
    }
    .auth-form {
        margin-top: 8px;
    }
    .auth-form.hidden {
        display: none;
    }
    .field {
        margin-bottom: 14px;
    }
    .field label {
        display: block;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
        font-size: 13px;
    }
    .field input, .field select {
        width: 100%;
        padding: 10px 12px;
        background: rgba(255,255,255,0.04);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-md);
        color: var(--text-primary);
        font-size: 15px;
        transition: 0.3s;
        font-family: inherit;
    }
    .field input:focus, .field select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(167,139,250,0.08);
        background: rgba(255,255,255,0.06);
    }
    .field select option {
        background: #1e293b;
        color: var(--text-primary);
    }
    .auth-error {
        background: rgba(239,68,68,0.08);
        border: 1px solid rgba(239,68,68,0.15);
        border-radius: var(--radius-md);
        padding: 10px 14px;
        color: #f87171;
        margin-bottom: 14px;
        font-weight: 600;
        font-size: 14px;
    }
    .btn-block {
        width: 100%;
        justify-content: center;
        padding: 12px;
        font-size: 16px;
        margin-top: 4px;
        border-radius: 60px;
    }
    .auth-toggle {
        text-align: center;
        margin-top: 16px;
        color: var(--text-secondary);
        font-size: 13px;
    }
    .auth-toggle a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 700;
        transition: 0.2s;
    }
    .auth-toggle a:hover {
        text-decoration: underline;
    }

    .two-char-note {
        text-align: center;
        font-weight: 700;
        color: var(--primary);
        background: rgba(167,139,250,0.06);
        border-radius: 40px;
        padding: 6px 10px;
        margin-bottom: 14px;
        border: 1px solid rgba(167,139,250,0.05);
        font-size: 14px;
    }
    .characters-grid.pickable-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 10px;
        margin-bottom: 14px;
    }
    .character-card.pickable {
        cursor: pointer;
        border: 2px solid transparent;
        transition: 0.3s;
        position: relative;
        background: var(--bg-card);
        border-radius: var(--radius-md);
        padding: 10px 8px;
        text-align: center;
    }
    .character-card.pickable:hover {
        border-color: rgba(167,139,250,0.2);
        background: var(--bg-card-hover);
    }
    .character-card.pickable.selected {
        border-color: var(--primary);
        box-shadow: 0 0 30px rgba(167,139,250,0.08);
    }
    .character-card.pickable.locked {
        opacity: 0.4;
        pointer-events: none;
        filter: grayscale(0.6);
    }
    .character-card.pickable .mini-char-badge {
        position: absolute;
        top: 4px;
        left: 4px;
        background: rgba(15,23,42,0.8);
        color: var(--primary);
        font-size: 9px;
        padding: 2px 8px;
        border-radius: 30px;
        z-index: 2;
    }
    .character-card.pickable .char-media {
        border-radius: var(--radius-md);
        font-size: 32px;
        border: none;
        background: rgba(255,255,255,0.02);
        margin-bottom: 4px;
    }
    .character-card.pickable .name {
        font-size: 12px;
    }

    .btn-primary-gradient {
        background: linear-gradient(135deg, var(--primary), var(--purple-dark));
        border: none;
        color: #fff;
        font-weight: 800;
        padding: 10px 24px;
        border-radius: 60px;
        cursor: pointer;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        font-size: 15px;
        box-shadow: 0 8px 30px rgba(167,139,250,0.2);
    }
    .btn-primary-gradient:hover {
        transform: scale(1.04);
        box-shadow: 0 12px 40px rgba(167,139,250,0.3);
    }
    .btn-primary-gradient.btn-large {
        padding: 14px 32px;
        font-size: 18px;
    }
    .btn-outline {
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--border-light);
        color: var(--text-primary);
        padding: 10px 24px;
        border-radius: 60px;
        cursor: pointer;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        font-size: 15px;
        font-weight: 700;
    }
    .btn-outline:hover {
        background: rgba(255,255,255,0.06);
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
    }

    .landing-footer {
        text-align: center;
        padding: 32px 0 16px;
        color: var(--text-muted);
        font-size: 13px;
        border-top: 1px solid var(--border-light);
        margin-top: 32px;
    }

    /* ============================================================
       استجابة محسّنة للجوال (هنا الحل الأساسي)
       ============================================================ */
    @media (max-width: 992px) {
        .hero-content h1 { font-size: 44px; }
        .hero-subtitle { font-size: 22px; }
        .hero-visual { min-height: 280px; }
    }

    @media (max-width: 768px) {
        .hero-section {
            flex-direction: column;
            padding: 12px 0 20px;
            gap: 16px;
        }
        .hero-content {
            order: 2;
            flex: none;
            width: 100%;
            text-align: center;
        }
        .hero-visual {
            order: 1;
            flex: none;
            width: 100%;
            min-height: 240px;
            padding: 16px 12px;
            border-radius: 24px;
        }
        .hero-content h1 { font-size: 36px; }
        .hero-subtitle { font-size: 20px; }
        .hero-description {
            font-size: 15px;
            max-width: 100%;
            margin-left: auto;
            margin-right: auto;
        }
        .hero-actions {
            justify-content: center;
        }
        .hero-actions .btn-large {
            padding: 12px 24px;
            font-size: 16px;
        }
        .hero-carousel {
            max-width: 140px;
            height: 140px;
        }
        .hero-carousel-item {
            width: 110px;
            height: 110px;
            font-size: 60px;
        }
        .hero-carousel-item .char-emoji {
            font-size: 80px;
        }
        .hero-char-name {
            font-size: 16px;
            min-height: 30px;
            padding: 2px 14px;
        }
        .hero-stats {
            gap: 12px;
        }
        .hero-stats .stat {
            padding: 4px 12px;
            min-width: 50px;
        }
        .hero-stats .stat .number {
            font-size: 20px;
        }
        .hero-stats .stat .label {
            font-size: 10px;
        }

        .section-title { font-size: 26px; }
        .section-sub { font-size: 15px; }
        .features-grid { grid-template-columns: 1fr 1fr; gap: 14px; }
        .feature-card { padding: 20px 14px; }
        .feature-icon { font-size: 34px; }
        .feature-card h3 { font-size: 16px; }
        .feature-card p { font-size: 13px; }
        .character-card-enhanced { flex: 0 0 150px; padding: 12px 10px; }
        .char-media { width: 70px; height: 70px; }
        .char-info .name { font-size: 15px; }
        .char-info .title { font-size: 11px; }
        .plans-grid { grid-template-columns: 1fr 1fr; gap: 14px; }
        .plan-card { padding: 18px 14px; }
        .plan-card h3 { font-size: 16px; }
        .plan-card .price { font-size: 20px; }
        .auth-card { padding: 20px 16px; }
        .auth-logo { font-size: 24px; }
        .auth-sub { font-size: 14px; }
        .characters-grid.pickable-grid { grid-template-columns: repeat(3, 1fr); }
        .hero-actions .btn-large { width: 100%; justify-content: center; }
        .hero-actions { flex-direction: column; width: 100%; }
        .btn-primary-gradient.btn-large { width: 100%; justify-content: center; }
    }

    @media (max-width: 480px) {
        .hero-content h1 { font-size: 28px; }
        .hero-subtitle { font-size: 17px; }
        .hero-description { font-size: 14px; }
        .hero-visual { min-height: 200px; padding: 12px 8px; border-radius: 20px; }
        .hero-carousel { max-width: 100px; height: 100px; }
        .hero-carousel-item { width: 80px; height: 80px; font-size: 40px; }
        .hero-carousel-item .char-emoji { font-size: 60px; }
        .hero-char-name { font-size: 13px; min-height: 24px; padding: 2px 10px; }
        .hero-stats { gap: 8px; }
        .hero-stats .stat { padding: 2px 8px; min-width: 40px; }
        .hero-stats .stat .number { font-size: 16px; }
        .hero-stats .stat .label { font-size: 9px; }
        .hero-actions .btn-large { padding: 10px 16px; font-size: 14px; }
        .section-title { font-size: 22px; }
        .section-sub { font-size: 14px; }
        .features-grid { grid-template-columns: 1fr; }
        .plans-grid { grid-template-columns: 1fr; }
        .characters-grid.pickable-grid { grid-template-columns: repeat(3, 1fr); }
        .auth-card { padding: 14px 10px; }
        .auth-logo { font-size: 20px; }
        .field input, .field select { font-size: 14px; padding: 8px 10px; }
        .character-card-enhanced { flex: 0 0 120px; padding: 8px 6px; }
        .char-media { width: 60px; height: 60px; }
        .char-info .name { font-size: 13px; }
        .char-info .title { font-size: 10px; }
        .char-hover-reveal blockquote { font-size: 11px; padding: 4px 8px; }
        .btn-primary-gradient { font-size: 13px; padding: 8px 18px; }
        .btn-outline { font-size: 13px; padding: 8px 18px; }
        .characters-track { gap: 12px; padding: 4px 8px; }
        .characters-scroll-wrapper { padding: 10px 0 16px; }
    }

/* ============================================================
   RESPONSIVE PATCH — Kidora
   يحافظ على التصميم الحالي ويعالج مشاكل الجوال/التابلت
   ============================================================ */

/* منع أي عنصر من التسبب بتمرير أفقي */
html,
body {
    width: 100%;
    max-width: 100%;
    overflow-x: hidden;
}

*,
*::before,
*::after {
    box-sizing: border-box;
}

img,
iframe,
video {
    max-width: 100%;
}

/* الحاوية الرئيسية */
.landing-page {
    width: min(1200px, 100%);
    padding-inline: clamp(12px, 3vw, 20px);
}

/* تحسين الـ Hero على الشاشات المتوسطة */
@media (max-width: 1100px) {
    .hero-section {
        gap: 30px;
    }

    .hero-content,
    .hero-visual {
        flex-basis: calc(50% - 15px);
        min-width: 0;
    }

    .hero-content h1 {
        font-size: clamp(38px, 5vw, 48px);
    }

    .hero-description {
        font-size: 16px;
    }

    .features-grid,
    .plans-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

/* التابلت والجوال */
@media (max-width: 768px) {
    .landing-page {
        padding: 0 14px 28px;
    }

    .hero-section {
        display: flex;
        flex-direction: column;
        width: 100%;
        gap: 20px;
        padding-top: 18px;
    }

    .hero-content,
    .hero-visual {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        flex: none;
    }

    .hero-content {
        text-align: center;
        order: 2;
    }

    .hero-visual {
        order: 1;
        min-height: 250px;
        padding: 18px 12px;
        overflow: hidden;
    }

    .hero-content h1 {
        font-size: clamp(32px, 9vw, 42px);
        line-height: 1.15;
        margin-bottom: 8px;
    }

    .hero-subtitle {
        font-size: clamp(17px, 4.5vw, 21px);
        line-height: 1.5;
    }

    .hero-description {
        width: 100%;
        max-width: 620px;
        margin-inline: auto;
        font-size: 15px;
        line-height: 1.8;
    }

    .hero-actions {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }

    .hero-actions .btn-large,
    .hero-actions .btn-primary-gradient.btn-large {
        width: 100%;
        min-height: 48px;
        justify-content: center;
        padding: 12px 18px;
        font-size: 15px;
    }

    .hero-carousel {
        width: 130px;
        height: 130px;
        max-width: 130px;
    }

    .hero-carousel-item {
        width: 100px;
        height: 100px;
    }

    .hero-carousel-item .char-emoji {
        font-size: 72px;
    }

    .hero-char-name {
        max-width: 90%;
        font-size: 15px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .hero-stats {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
    }

    .hero-stats .stat {
        min-width: 0;
        width: 100%;
        padding: 6px 5px;
    }

    .hero-stats .stat .number {
        font-size: 19px;
    }

    .hero-stats .stat .label {
        font-size: 10px;
        white-space: normal;
    }

    .section-head {
        margin: 32px 0 18px;
    }

    .section-title {
        font-size: clamp(23px, 6vw, 28px);
        line-height: 1.3;
    }

    .section-sub {
        width: 100%;
        font-size: 14px;
        line-height: 1.75;
    }

    .features-grid,
    .plans-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .feature-card,
    .plan-card {
        min-width: 0;
        padding: 18px 12px;
    }

    .feature-card h3,
    .plan-card h3 {
        line-height: 1.45;
    }

    .feature-card p,
    .plan-card li {
        overflow-wrap: anywhere;
    }

    .video-wrapper {
        width: 100%;
        max-width: 100%;
        border-radius: 20px;
    }

    .characters-scroll-wrapper {
        width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        mask-image: none;
        -webkit-mask-image: none;
        padding-inline: 2px;
    }

    .characters-track {
        gap: 12px;
        padding-inline: 8px;
        animation-duration: 35s;
    }

    .character-card-enhanced {
        flex: 0 0 150px;
        min-width: 150px;
    }

    .ai-section {
        padding: 26px 14px;
        margin-top: 26px;
        border-radius: 24px;
    }

    .ai-section h2 {
        font-size: 22px;
        line-height: 1.4;
    }

    .ai-section p {
        font-size: 14px;
        line-height: 1.8;
    }

    .ai-preview {
        width: 100%;
        padding: 16px;
        overflow-wrap: anywhere;
    }

    .auth-section {
        margin-top: 28px;
        padding-top: 24px;
    }

    .auth-wrap {
        width: 100%;
        padding-inline: 0;
    }

    .auth-card {
        width: 100%;
        max-width: 600px;
        padding: 22px 16px;
        border-radius: 24px;
    }

    .characters-grid.pickable-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
    }

    .character-card.pickable {
        min-width: 0;
        padding: 9px 5px;
    }

    .character-card.pickable .char-media {
        width: 100%;
        height: auto;
        aspect-ratio: 1;
        max-width: 72px;
        margin-inline: auto;
    }

    .character-card.pickable .name {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .field input,
    .field select {
        min-height: 46px;
        font-size: 16px; /* يمنع zoom تلقائي في iPhone */
    }

    .btn-block {
        min-height: 48px;
    }
}

/* الجوال الصغير */
@media (max-width: 480px) {
    .landing-page {
        padding-inline: 10px;
    }

    .hero-section {
        padding-top: 12px;
        gap: 16px;
    }

    .hero-visual {
        min-height: 215px;
        padding: 14px 8px;
        border-radius: 20px;
    }

    .hero-badge {
        font-size: 10px;
        padding: 4px 11px;
    }

    .hero-content h1 {
        font-size: 29px;
    }

    .hero-subtitle {
        font-size: 16px;
    }

    .hero-description {
        font-size: 13.5px;
        line-height: 1.75;
    }

    .hero-carousel {
        width: 105px;
        height: 105px;
    }

    .hero-carousel-item {
        width: 82px;
        height: 82px;
    }

    .hero-carousel-item .char-emoji {
        font-size: 58px;
    }

    .hero-char-name {
        font-size: 13px;
        padding: 2px 10px;
    }

    .hero-stats {
        gap: 5px;
    }

    .hero-stats .stat {
        padding: 4px 2px;
        border-radius: 14px;
    }

    .hero-stats .stat .number {
        font-size: 15px;
    }

    .hero-stats .stat .label {
        font-size: 8.5px;
    }

    .features-grid,
    .plans-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .feature-card,
    .plan-card {
        padding: 17px 13px;
    }

    .feature-icon {
        font-size: 32px;
    }

    .section-title {
        font-size: 21px;
    }

    .section-sub {
        font-size: 13px;
    }

    .character-card-enhanced {
        flex-basis: 125px;
        min-width: 125px;
        padding: 9px 6px;
        border-radius: 18px;
    }

    .character-card-enhanced .char-media {
        width: 58px;
        height: 58px;
    }

    .char-info .name {
        font-size: 12px;
    }

    .char-info .title {
        font-size: 9px;
    }

    .char-trait {
        max-width: 100%;
        padding: 2px 7px;
        font-size: 9px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* على اللمس لا نعتمد على hover لإظهار المحتوى */
    .char-hover-reveal {
        max-height: none;
        opacity: 1;
        margin-top: 7px;
    }

    .char-hover-reveal blockquote {
        font-size: 10px;
        padding: 5px 7px;
        line-height: 1.5;
    }

    .ai-section {
        padding: 22px 11px;
        border-radius: 20px;
    }

    .ai-section .ai-icon {
        font-size: 42px;
    }

    .ai-section h2 {
        font-size: 19px;
    }

    .ai-section p {
        font-size: 13px;
    }

    .ai-preview {
        padding: 13px;
        border-radius: 16px;
    }

    .ai-preview .story-title {
        font-size: 16px;
    }

    .ai-preview .story-snippet {
        font-size: 12px;
    }

    .auth-card {
        padding: 16px 10px;
        border-radius: 20px;
    }

    .auth-logo {
        font-size: 21px;
    }

    .auth-sub {
        font-size: 12px;
    }

    .auth-tabs {
        gap: 3px;
    }

    .auth-tab {
        min-height: 42px;
        font-size: 12px;
    }

    .characters-grid.pickable-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 6px;
    }

    .character-card.pickable {
        padding: 7px 3px;
        border-width: 1px;
    }

    .character-card.pickable .char-media {
        max-width: 58px;
    }

    .character-card.pickable .name {
        font-size: 10px;
    }

    .character-card.pickable .mini-char-badge {
        top: 2px;
        left: 2px;
        font-size: 8px;
        padding: 1px 5px;
    }

    .two-char-note {
        font-size: 12px;
        padding: 5px 8px;
    }

    .auth-toggle {
        font-size: 11px;
        line-height: 1.7;
    }

    .landing-footer {
        padding: 24px 0 12px;
        font-size: 11px;
    }
}

/* شاشات شديدة الصغر */
@media (max-width: 360px) {
    .landing-page {
        padding-inline: 8px;
    }

    .hero-content h1 {
        font-size: 26px;
    }

    .hero-subtitle {
        font-size: 15px;
    }

    .hero-stats .stat .label {
        font-size: 8px;
    }

    .character-card-enhanced {
        flex-basis: 115px;
        min-width: 115px;
    }

    .auth-card {
        padding-inline: 8px;
    }
}

/* تقليل الحركة للمستخدمين الذين يفضلون ذلك */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}

</style>

<!-- ============================================================
     صفحة الهبوط (Landing Page)
     ============================================================ -->
<div class="landing-page">

    <!-- القسم الرئيسي (Hero) -->
    <section class="hero-section">
        <div class="hero-content">
            <div class="hero-badge">🚀 منصة تربوية ذكية</div>
            <h1>
                <span class="highlight">Kidora</span>
            </h1>
            <p class="hero-subtitle">حيث يتحول التعلم إلى مغامرة بطولية</p>
            <p class="hero-description">
                مهام يومية، قصص ملهمة، ألعاب تفاعلية، وشخصيات مرافقة.
                منصة متكاملة تنمي مهارات طفلك وتصنع منه بطلاً حقيقياً.
            </p>
            <div class="hero-actions">
                <a href="#auth" class="btn btn-primary-gradient btn-large">🚀 ابدأ مغامرتك الآن</a>
                <a href="#features" class="btn btn-outline btn-large">تعرف أكثر</a>
            </div>
        </div>
        <div class="hero-visual" id="heroVisual">
            <div class="glow-ring"></div>
            <div class="hero-carousel" id="heroCarousel">
                <?php 
                $first = true;
                foreach ($carouselChars as $index => $char): 
                    $activeClass = $first ? 'active' : '';
                    $first = false;
                    $img = !empty($char['image']) ? '<img src="' . htmlspecialchars(BASE_PATH . '/' . $char['image']) . '" alt="' . htmlspecialchars($char['name']) . '">' : '<span class="char-emoji">' . htmlspecialchars($char['icon']) . '</span>';
                ?>
                <div class="hero-carousel-item <?php echo $activeClass; ?>" data-index="<?php echo $index; ?>" data-color="<?php echo htmlspecialchars($char['color']); ?>">
                    <?php echo $img; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="hero-char-name" id="heroCharName">
                <span id="heroCharNameText"><?php echo htmlspecialchars($carouselChars[0]['name']); ?></span>
            </div>
            <div class="hero-stats">
                <div class="stat">
                    <span class="number">100+</span>
                    <span class="label">قصة تفاعلية</span>
                </div>
                <div class="stat">
                    <span class="number"><?php echo count($characters); ?></span>
                    <span class="label">شخصيات</span>
                </div>
                <div class="stat">
                    <span class="number">✨ AI</span>
                    <span class="label">قصص مخصصة</span>
                </div>
            </div>
        </div>
    </section>

    <!-- فيديو تعريفي -->
    <section class="video-section" id="video">
        <div class="section-head">
            <div class="eyebrow">شاهد</div>
            <h2 class="section-title">فيديو تعريفي للمنصة</h2>
        </div>
        <div class="video-wrapper">
            <iframe src="<?php echo h(youtube_embed_url('XIQBQk6F-ok')); ?>"
                    title="Kidora"
                    loading="lazy"
                    allowfullscreen>
            </iframe>
        </div>
    </section>

    <!-- المميزات -->
    <section class="features-section" id="features">
        <div class="section-head">
            <div class="eyebrow">لماذا Kidora</div>
            <h2 class="section-title">مغامرة تعلم متكاملة</h2>
            <p class="section-sub">كل عنصر في المنصة صمم ليكون ممتعاً ومفيداً في آن واحد</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">📋</div>
                <h3>مهام يومية متسلسلة</h3>
                <p>4 مهام جديدة كل يوم، تفتح واحدة تلو الأخرى، لتعزيز الانضباط والمثابرة.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📖</div>
                <h3>قصص تاريخية وإسلامية</h3>
                <p>قصص ملهمة من تراثنا العربي والإسلامي، تُقرأ بصوت وتُعزز القيم الأخلاقية.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🎮</div>
                <h3>ألعاب تفاعلية</h3>
                <p>ألعاب ذاكرة متنوعة تنمي التفكير والتركيز، مع إمكانية تخطيها بكل مرونة.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🏆</div>
                <h3>مكافآت وتطور</h3>
                <p>اجمع النقاط، ارتقِ بالمستوى، وافتح شخصيات جديدة مع كل إنجاز.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🧠</div>
                <h3>ذكاء اصطناعي</h3>
                <p>نولّد قصصاً مخصصة لطفلك بناءً على اهتماماته ومستواه، لتكون تجربة فريدة.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📲</div>
                <h3>تقارير للوالدين</h3>
                <p>تتبع تقدم طفلك عبر تقارير واتساب دورية، وشارك رحلته مع العائلة.</p>
            </div>
        </div>
    </section>

    <!-- قسم الذكاء الاصطناعي -->
    <section class="ai-section" id="ai">
        <div class="ai-icon">🤖</div>
        <h2>قصص مخصصة بذكاء اصطناعي</h2>
        <p>نستخدم تقنيات الذكاء الاصطناعي لتوليد قصة فريدة لكل طفل، تتناسب مع عمره واهتماماته، لتصبح كل مغامرة خاصة به.</p>
        <div class="ai-preview">
            <div class="story-title">📖 مغامرة في مدينة النور</div>
            <div class="story-snippet">
                "في مدينة النور البعيدة، كان هناك طفل شجاع يدعى يوسف. ذات يوم، وجد خريطة قديمة تقوده إلى كنز الحكمة...
                تعلم يوسف أن الشجاعة ليست في قوة العضلات، بل في قوة القلب والعقل."
            </div>
            <div class="story-tag">✨ قصة مخصصة ليوسف (7 سنوات)</div>
        </div>
        <p style="font-size:13px; color:var(--text-muted); margin-top:12px;">⚠️ هذه القصة مثال، سيتم توليد قصة فريدة لكل طفل بعد التسجيل.</p>
    </section>

    <!-- الشخصيات -->
    <section class="characters-section" id="characters">
        <div class="section-head">
            <div class="eyebrow">رفقاؤك في الرحلة</div>
            <h2 class="section-title">اختر شخصيتك المفضلة</h2>
            <p class="section-sub">شخصيات كرتونية مرافقة، تتفاعل معك وتشجعك في كل خطوة</p>
        </div>
        <div class="characters-scroll-wrapper">
            <div class="characters-track" id="charactersTrack">
                <?php foreach ($characters as $c): 
                    $color = $c['color'] ?? '#a78bfa';
                    $icon = character_icons($c)[0] ?? '✨';
                    $trait = $c['trait'] ?? 'مميز';
                    $quote = $c['quote'] ?? 'مرحباً!';
                ?>
                    <div class="character-card-enhanced" 
                         style="--char-color: <?php echo h($color); ?>; 
                                --char-glow: <?php echo h($color); ?>40;
                                background: radial-gradient(circle at 30% 20%, <?php echo h($color); ?>30, transparent 70%), 
                                            radial-gradient(circle at 80% 80%, <?php echo h($color); ?>20, transparent 60%),
                                            #1a1020;">
                        <div class="char-glow-ring"></div>
                        <div class="char-media">
                            <?php if (!empty($c['image_path'])): ?>
                                <img src="<?php echo h($c['image_path']); ?>" alt="<?php echo h($c['name']); ?>">
                            <?php else: ?>
                                <span class="char-emoji"><?php echo $icon; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="char-info">
                            <div class="name"><?php echo h($c['name']); ?></div>
                            <div class="title"><?php echo h($c['title']); ?></div>
                            <div class="char-trait">🎯 <?php echo h($trait); ?></div>
                        </div>
                        <div class="char-hover-reveal">
                            <blockquote>“<?php echo h($quote); ?>”</blockquote>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($characters as $c): 
                    $color = $c['color'] ?? '#a78bfa';
                    $icon = character_icons($c)[0] ?? '✨';
                    $trait = $c['trait'] ?? 'مميز';
                    $quote = $c['quote'] ?? 'مرحباً!';
                ?>
                    <div class="character-card-enhanced" 
                         style="--char-color: <?php echo h($color); ?>; 
                                --char-glow: <?php echo h($color); ?>40;
                                background: radial-gradient(circle at 30% 20%, <?php echo h($color); ?>30, transparent 70%), 
                                            radial-gradient(circle at 80% 80%, <?php echo h($color); ?>20, transparent 60%),
                                            #1a1020;">
                        <div class="char-glow-ring"></div>
                        <div class="char-media">
                            <?php if (!empty($c['image_path'])): ?>
                                <img src="<?php echo h($c['image_path']); ?>" alt="<?php echo h($c['name']); ?>">
                            <?php else: ?>
                                <span class="char-emoji"><?php echo $icon; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="char-info">
                            <div class="name"><?php echo h($c['name']); ?></div>
                            <div class="title"><?php echo h($c['title']); ?></div>
                            <div class="char-trait">🎯 <?php echo h($trait); ?></div>
                        </div>
                        <div class="char-hover-reveal">
                            <blockquote>“<?php echo h($quote); ?>”</blockquote>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- خطط الاشتراك -->
    <section class="plans-section">
        <div class="section-head">
            <div class="eyebrow">خطط الاشتراك</div>
            <h2 class="section-title">اختر ما يناسبك</h2>
            <p class="section-sub">الخطة المجانية تمنحك تجربة رائعة، والمدفوعة تفتح لك المزيد من الشخصيات والمحتوى.</p>
        </div>
        <div class="plans-grid">
            <?php foreach ($plans as $p):
                $features = json_decode_safe($p['features_json'], []);
            ?>
                <div class="plan-card">
                    <h3><?php echo h($p['name']); ?></h3>
                    <div class="price"><?php echo (int)$p['price_ils'] === 0 ? 'مجانية' : (int)$p['price_ils'].' ₪'; ?></div>
                    <ul>
                        <?php foreach (array_slice($features, 0, 3) as $f): ?>
                            <li><?php echo h($f); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align:center; margin-top:24px;">
            <a href="#auth" class="btn btn-primary-gradient" style="font-size:16px; padding:12px 32px;">🚀 ابدأ مغامرتك الآن</a>
        </div>
    </section>

    <!-- نموذج تسجيل الدخول / إنشاء حساب -->
    <section class="auth-section" id="auth">
        <div class="section-head">
            <div class="eyebrow">انضم إلينا</div>
            <h2 class="section-title">سجّل الدخول أو أنشئ حساباً</h2>
            <p class="section-sub">ابدأ رحلة طفلك في عالم Kidora المليء بالمغامرات والتعلم.</p>
        </div>
        <div class="auth-wrap">
            <div class="auth-card">
                <div class="auth-logo">🌟 Kidora</div>
                <p class="auth-sub">منصة ذكية تحوّل طفلك إلى بطل حقيقي عبر مهام وقصص ومغامرات آمنة</p>
                <div class="auth-tabs">
                    <button type="button" class="auth-tab active" data-tab="login">تسجيل الدخول</button>
                    <button type="button" class="auth-tab" data-tab="register">إنشاء حساب</button>
                </div>
                <div id="login-tab" class="auth-form">
                    <?php if ($loginError): ?><div class="auth-error">❌ <?php echo h($loginError); ?></div><?php endif; ?>
                    <form method="POST">
                        <div class="field"><label>البريد الإلكتروني لولي الأمر</label><input type="email" name="email" required></div>
                        <div class="field"><label>كلمة المرور</label><input type="password" name="password" required></div>
                        <button type="submit" name="login" class="btn btn-primary-gradient btn-block">🚀 تسجيل الدخول</button>
                    </form>
                    <div class="auth-toggle">مسؤول المنصة؟ <a href="admin/login.php">دخول لوحة الإدارة</a></div>
                </div>
                <div id="register-tab" class="auth-form hidden">
                    <?php if ($registerError): ?><div class="auth-error">❌ <?php echo h($registerError); ?></div><?php endif; ?>
                    <p style="text-align:center;font-weight:800;color:var(--text-primary);font-size:15px;">1) اختر شخصيتين ترافقان طفلك من باقة الشخصيات المجانية</p>
                    <p style="text-align:center;color:var(--text-secondary);font-size:12px;">الشخصيات المقفلة 🔒 تُفتح تلقائياً بعد تفعيل اشتراك مدفوع</p>
                    <div class="two-char-note" id="selCountLabel">0 / 2 مختارة</div>
                    <div class="characters-grid pickable-grid" id="regCharGrid">
                        <?php foreach ($characters as $c): $locked = (bool)$c['is_premium']; ?>
                            <div class="character-card pickable <?php echo $locked ? 'locked' : ''; ?>"
                                 data-id="<?php echo (int)$c['id']; ?>"
                                 data-locked="<?php echo $locked ? '1':'0'; ?>"
                                 style="--card-color:<?php echo h($c['color']); ?>;"
                                 onclick="toggleCharPick(this)">
                                <?php if ($locked): ?><div class="mini-char-badge">🔒</div><?php endif; ?>
                                <div class="char-media">
                                    <?php if (!empty($c['image_path'])): ?>
                                        <img src="<?php echo h($c['image_path']); ?>" alt="<?php echo h($c['name']); ?>">
                                    <?php else: ?>
                                        <span style="font-size:32px;"><?php echo character_icons($c)[0] ?? '✨'; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="name"><?php echo h($c['name']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form method="POST" id="registerForm" style="margin-top:18px;">
                        <input type="hidden" name="character_1" id="character_1">
                        <input type="hidden" name="character_2" id="character_2">
                        <p style="font-weight:800;color:var(--text-primary);margin-top:16px;font-size:15px;">2) بيانات الحساب</p>
                        <div class="field"><label>اسم الطفل</label><input type="text" name="child_name" required value="<?php echo h($_POST['child_name'] ?? ''); ?>"></div>
                        <div class="field"><label>عمر الطفل</label>
                            <select name="child_age" required>
                                <option value="">اختر العمر</option>
                                <?php for ($a = 4; $a <= 12; $a++): ?>
                                    <option value="<?php echo $a; ?>" <?php echo (($_POST['child_age'] ?? '') == $a) ? 'selected' : ''; ?>><?php echo $a; ?> سنوات</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="field"><label>اسم ولي الأمر</label><input type="text" name="parent_name" required value="<?php echo h($_POST['parent_name'] ?? ''); ?>"></div>
                        <div class="field"><label>رقم واتساب ولي الأمر</label><input type="tel" name="parent_phone" required placeholder="مثال: 0599123456" value="<?php echo h($_POST['parent_phone'] ?? ''); ?>"></div>
                        <div class="field"><label>البريد الإلكتروني</label><input type="email" name="email" required value="<?php echo h($_POST['email'] ?? ''); ?>"></div>
                        <div class="field"><label>كلمة المرور (6 أحرف على الأقل)</label><input type="password" name="password" required minlength="6"></div>
                        <div class="field"><label>تأكيد كلمة المرور</label><input type="password" name="confirm_password" required></div>
                        <button type="submit" name="register" class="btn btn-primary-gradient btn-block">🌟 ابدأ المغامرة</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <footer class="landing-footer">
        <p>© 2026 Kidora. جميع الحقوق محفوظة.</p>
    </footer>

</div>

<!-- ============================================================
     JavaScript
     ============================================================ -->
<script>
    // 1. تبديل التبويبات
    document.querySelectorAll('.auth-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('login-tab').classList.toggle('hidden', this.dataset.tab !== 'login');
            document.getElementById('register-tab').classList.toggle('hidden', this.dataset.tab !== 'register');
        });
    });
    <?php if ($registerError): ?>
        document.querySelector('.auth-tab[data-tab="register"]').click();
    <?php endif; ?>

    // 2. اختيار شخصيتين
    const CHAR_DATA = <?php echo json_encode($charDataForJS, JSON_UNESCAPED_UNICODE); ?>;
    let picked = [];
    function toggleCharPick(el) {
        if (el.dataset.locked === '1') {
            alert('هذه الشخصية تُفتح بعد تفعيل اشتراك مدفوع 🔒 — اختر من الشخصيات المجانية الآن.');
            return;
        }
        const id = parseInt(el.dataset.id, 10);
        const idx = picked.indexOf(id);
        if (idx > -1) { picked.splice(idx, 1); el.classList.remove('selected'); }
        else {
            if (picked.length >= 2) return;
            picked.push(id); el.classList.add('selected');
        }
        document.getElementById('selCountLabel').textContent = picked.length + ' / 2 مختارة';
        document.getElementById('character_1').value = picked[0] || '';
        document.getElementById('character_2').value = picked[1] || '';
        const cd = CHAR_DATA.find(c => c.id === id);
        if (cd && typeof ThemeEngine !== 'undefined') {
            ThemeEngine.previewCharacter(cd);
        }
    }
    document.getElementById('registerForm').addEventListener('submit', function (e) {
        if (picked.length !== 2) {
            e.preventDefault();
            alert('الرجاء اختيار شخصيتين بالضبط قبل المتابعة.');
        }
    });

    // 3. سكرول الشخصيات التلقائي
    document.addEventListener('DOMContentLoaded', function() {
        const track = document.querySelector('.characters-track');
        if (track) {
            const cards = document.querySelectorAll('.character-card-enhanced');
            cards.forEach(card => {
                card.addEventListener('mouseenter', () => { track.style.animationPlayState = 'paused'; });
                card.addEventListener('mouseleave', () => { track.style.animationPlayState = 'running'; });
            });
        }

        // 4. كاروسيل 3D
        const carousel = document.getElementById('heroCarousel');
        const items = carousel ? carousel.querySelectorAll('.hero-carousel-item') : [];
        const nameDisplay = document.getElementById('heroCharNameText');
        const visual = document.getElementById('heroVisual');

        if (items.length > 0) {
            let currentIndex = 0;
            let intervalId = null;
            let isPaused = false;

            function goToIndex(index) {
                items.forEach(item => item.classList.remove('active'));
                const target = items[index];
                if (target) {
                    target.classList.add('active');
                    const name = target.dataset.name || target.querySelector('img')?.alt || 'بطل';
                    if (nameDisplay) nameDisplay.textContent = name;
                    const color = target.dataset.color || '#a78bfa';
                    if (visual) {
                        visual.style.borderColor = color + '40';
                    }
                }
                currentIndex = index;
            }

            function nextItem() {
                if (isPaused) return;
                let next = currentIndex + 1;
                if (next >= items.length) next = 0;
                goToIndex(next);
            }

            function startAutoPlay() {
                if (intervalId) clearInterval(intervalId);
                intervalId = setInterval(nextItem, 4000);
            }

            function pauseAutoPlay() {
                isPaused = true;
                if (intervalId) { clearInterval(intervalId); intervalId = null; }
            }

            function resumeAutoPlay() {
                isPaused = false;
                if (!intervalId) startAutoPlay();
            }

            if (carousel && visual) {
                carousel.addEventListener('mousemove', function(e) {
                    if (isPaused) return;
                    const rect = this.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;
                    const rotateX = ((y - centerY) / centerY) * -8;
                    const rotateY = ((x - centerX) / centerX) * 8;
                    this.style.transform = `perspective(800px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.02)`;
                });
                carousel.addEventListener('mouseleave', function() {
                    this.style.transform = 'perspective(800px) rotateX(0deg) rotateY(0deg) scale(1)';
                });
            }

            carousel.addEventListener('mouseenter', pauseAutoPlay);
            carousel.addEventListener('mouseleave', resumeAutoPlay);

            goToIndex(0);
            startAutoPlay();

            window.addEventListener('beforeunload', function() {
                if (intervalId) clearInterval(intervalId);
            });
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
