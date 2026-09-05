<?php
// index.php - Landing Page + Login/Register (نسخة بنفسجية غامقة) - متجاوبة بالكامل
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
// معالجة التسجيل
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
       القسم الرئيسي (Hero) – محسّن للجوال
       ============================================================ */
    .hero-section {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        padding: 20px 0 30px;
        gap: 20px;
        min-height: auto;
    }
    .hero-content {
        flex: 1 1 400px;
        order: 1;
        text-align: center;
    }
    .hero-badge {
        display: inline-block;
        background: var(--primary-glow);
        color: var(--primary);
        padding: 6px 18px;
        border-radius: 40px;
        font-weight: 700;
        font-size: 13px;
        border: 1px solid rgba(167,139,250,0.15);
        letter-spacing: 0.5px;
        margin-bottom: 16px;
        backdrop-filter: blur(4px);
    }
    .hero-content h1 {
        font-size: 56px;
        font-weight: 900;
        line-height: 1.1;
        margin-bottom: 10px;
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
        font-size: 26px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 14px;
        text-shadow: 0 2px 14px rgba(10,6,26,.55);
    }
    .hero-description {
        font-size: 18px;
        color: #d9d0ff;
        max-width: 520px;
        margin: 0 auto 30px;
        line-height: 1.9;
        text-shadow: 0 1px 10px rgba(10,6,26,.5);
    }
    .hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        justify-content: center;
    }
    .hero-actions .btn-large {
        padding: 16px 40px;
        font-size: 20px;
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
        padding: 30px 20px 25px;
        border: 1px solid var(--border-light);
        position: relative;
        box-shadow: var(--shadow-soft);
        order: 2;
        min-height: 350px;
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
        max-width: 200px;
        height: 200px;
        margin: 0 auto 10px;
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
        transform: translate(-50%, -50%) scale(0.6);
        opacity: 0;
        transition: all 0.7s cubic-bezier(0.34, 1.56, 0.64, 1);
        width: 160px;
        height: 160px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 100px;
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
        font-size: 120px;
        line-height: 1;
        filter: drop-shadow(0 10px 30px rgba(0,0,0,0.3));
    }
    .hero-char-name {
        position: relative;
        z-index: 2;
        font-weight: 800;
        font-size: 20px;
        color: var(--text-primary);
        margin-top: 0;
        background: rgba(0,0,0,0.3);
        padding: 4px 20px;
        border-radius: 40px;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.05);
        display: inline-block;
        transition: all 0.5s;
        min-height: 40px;
        line-height: 1.4;
    }
    .hero-char-name span {
        color: var(--primary);
    }

    /* ===== الإحصائيات ===== */
    .hero-stats {
        display: flex;
        justify-content: center;
        gap: 30px;
        margin-top: 15px;
        position: relative;
        z-index: 1;
        flex-wrap: wrap;
    }
    .hero-stats .stat {
        text-align: center;
        background: rgba(255,255,255,0.03);
        padding: 6px 16px;
        border-radius: 40px;
        border: 1px solid rgba(255,255,255,0.03);
        backdrop-filter: blur(4px);
        min-width: 70px;
    }
    .hero-stats .stat .number {
        font-size: 28px;
        font-weight: 900;
        color: var(--primary);
        display: block;
        line-height: 1.2;
    }
    .hero-stats .stat .label {
        font-size: 13px;
        color: var(--text-muted);
        font-weight: 600;
        display: block;
        line-height: 1.4;
    }

    /* ============================================================
       باقي الأقسام (محسنة للجوال)
       ============================================================ */
    .section-head {
        text-align: center;
        margin: 40px 0 25px;
    }
    .eyebrow {
        color: var(--primary);
        font-weight: 700;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 4px;
    }
    .section-title {
        font-size: 34px;
        font-weight: 900;
        color: var(--text-primary);
        margin: 6px 0 8px;
        line-height: 1.2;
    }
    .section-sub {
        color: var(--text-secondary);
        font-size: 17px;
        max-width: 600px;
        margin: 0 auto;
        line-height: 1.7;
        padding: 0 10px;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 24px;
        margin-top: 10px;
    }
    .feature-card {
        background: var(--bg-card);
        backdrop-filter: blur(8px);
        border-radius: var(--radius-lg);
        padding: 28px 20px 24px;
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
        font-size: 48px;
        margin-bottom: 16px;
    }
    .feature-card h3 {
        font-size: 20px;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 8px;
    }
    .feature-card p {
        color: var(--text-secondary);
        font-size: 15px;
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
        padding: 20px 0 30px;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
        scroll-snap-type: x mandatory;
        mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
        -webkit-mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
    }
    .characters-track {
        display: flex;
        gap: 28px;
        padding: 10px 20px;
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
        flex: 0 0 220px;
        scroll-snap-align: start;
        border-radius: 28px;
        padding: 20px 14px 18px;
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
        border-radius: 28px;
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
        margin: 0 auto 14px;
        width: 100px;
        height: 100px;
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
        font-size: 48px;
    }
    .char-info .name {
        font-weight: 900;
        font-size: 20px;
        color: #fff;
        margin-bottom: 2px;
        text-shadow: 0 2px 8px rgba(0,0,0,0.6);
    }
    .char-info .title {
        font-size: 13px;
        color: #c4b5d4;
        margin-bottom: 6px;
        line-height: 1.3;
    }
    .char-trait {
        display: inline-block;
        background: rgba(255,255,255,0.05);
        padding: 2px 14px;
        border-radius: 20px;
        font-size: 12px;
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
        max-height: 120px;
        opacity: 1;
        margin-top: 14px;
    }
    .char-hover-reveal blockquote {
        font-size: 14px;
        font-style: italic;
        color: #d9d0ff;
        border-right: 3px solid var(--char-color);
        padding-right: 12px;
        margin: 0;
        line-height: 1.6;
        background: rgba(0,0,0,0.2);
        border-radius: 12px;
        padding: 10px 14px;
    }

    /* ============================================================
       خطط الاشتراك
       ============================================================ */
    .plans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 24px;
        margin-top: 10px;
    }
    .plan-card {
        background: var(--bg-card);
        backdrop-filter: blur(8px);
        border-radius: var(--radius-lg);
        padding: 26px 20px;
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
        font-size: 20px;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 4px;
    }
    .plan-card .price {
        font-size: 28px;
        font-weight: 900;
        color: var(--primary);
        margin-bottom: 16px;
    }
    .plan-card ul {
        list-style: none;
        padding: 0;
        margin: 0 0 16px;
        text-align: right;
    }
    .plan-card ul li {
        color: var(--text-secondary);
        font-size: 14px;
        padding: 6px 0;
        border-bottom: 1px solid rgba(255,255,255,0.03);
    }
    .plan-card ul li:last-child {
        border-bottom: none;
    }

    .ai-section {
        background: linear-gradient(145deg, rgba(167,139,250,0.03), rgba(124,58,237,0.03));
        border-radius: var(--radius-xl);
        padding: 40px 30px;
        border: 1px solid var(--border-light);
        text-align: center;
        margin: 40px 0 20px;
        box-shadow: var(--shadow-soft);
    }
    .ai-section .ai-icon {
        font-size: 60px;
        animation: pulse 2.5s ease-in-out infinite;
    }
    @keyframes pulse {
        0%,100% { transform: scale(1); opacity: 0.8; }
        50% { transform: scale(1.08); opacity: 1; }
    }
    .ai-section h2 {
        font-size: 28px;
        font-weight: 900;
        color: var(--text-primary);
        margin: 12px 0 8px;
    }
    .ai-section p {
        color: var(--text-secondary);
        font-size: 17px;
        max-width: 550px;
        margin: 0 auto 16px;
        line-height: 1.8;
    }
    .ai-preview {
        background: rgba(255,255,255,0.02);
        border-radius: var(--radius-lg);
        padding: 24px;
        max-width: 550px;
        margin: 0 auto;
        border: 1px dashed rgba(167,139,250,0.15);
        text-align: right;
    }
    .ai-preview .story-title {
        font-weight: 800;
        color: var(--primary);
        font-size: 20px;
        margin-bottom: 6px;
    }
    .ai-preview .story-snippet {
        color: var(--text-secondary);
        font-size: 15px;
        line-height: 1.8;
    }
    .ai-preview .story-tag {
        display: inline-block;
        background: var(--primary-glow);
        color: var(--primary);
        font-size: 12px;
        padding: 4px 16px;
        border-radius: 30px;
        margin-top: 12px;
        font-weight: 600;
    }

    /* ============================================================
       نموذج تسجيل الدخول / إنشاء حساب
       ============================================================ */
    .auth-section {
        margin-top: 60px;
        border-top: 1px solid var(--border-light);
        padding-top: 40px;
    }
    .auth-wrap {
        display: flex;
        justify-content: center;
        align-items: flex-start;
        padding: 10px 0 30px;
    }
    .auth-card {
        max-width: 640px;
        width: 100%;
        background: var(--bg-card);
        backdrop-filter: blur(16px);
        border-radius: var(--radius-xl);
        padding: 32px 30px;
        border: 1px solid var(--border-light);
        box-shadow: var(--shadow-heavy);
        box-sizing: border-box;
    }
    .auth-logo {
        font-size: 32px;
        font-weight: 900;
        color: var(--primary);
        text-align: center;
        margin-bottom: 4px;
    }
    .auth-sub {
        text-align: center;
        color: var(--text-secondary);
        font-size: 16px;
        margin-bottom: 24px;
        line-height: 1.6;
    }
    .auth-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 24px;
        background: rgba(255,255,255,0.03);
        border-radius: 60px;
        padding: 4px;
        border: 1px solid rgba(255,255,255,0.03);
    }
    .auth-tab {
        flex: 1;
        padding: 10px;
        border: none;
        background: transparent;
        color: var(--text-secondary);
        font-weight: 700;
        border-radius: 40px;
        cursor: pointer;
        transition: 0.3s;
        font-size: 16px;
    }
    .auth-tab.active {
        background: linear-gradient(135deg, var(--primary), var(--purple-dark));
        color: #fff;
        box-shadow: 0 4px 15px rgba(167,139,250,0.2);
    }
    .auth-form {
        margin-top: 10px;
    }
    .auth-form.hidden {
        display: none;
    }
    .field {
        margin-bottom: 16px;
    }
    .field label {
        display: block;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 5px;
        font-size: 14px;
    }
    .field input, .field select {
        width: 100%;
        padding: 12px 14px;
        background: rgba(255,255,255,0.04);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-md);
        color: var(--text-primary);
        font-size: 16px;
        transition: 0.3s;
        font-family: inherit;
        box-sizing: border-box;
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
        padding: 12px 16px;
        color: #f87171;
        margin-bottom: 16px;
        font-weight: 600;
    }
    .btn-block {
        width: 100%;
        justify-content: center;
        padding: 14px;
        font-size: 18px;
        margin-top: 4px;
        border-radius: 60px;
    }
    .auth-toggle {
        text-align: center;
        margin-top: 20px;
        color: var(--text-secondary);
        font-size: 14px;
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
        padding: 8px 12px;
        margin-bottom: 16px;
        border: 1px solid rgba(167,139,250,0.05);
    }
    .characters-grid.pickable-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }
    .character-card.pickable {
        cursor: pointer;
        border: 2px solid transparent;
        transition: 0.3s;
        position: relative;
        background: var(--bg-card);
        border-radius: var(--radius-md);
        padding: 12px 10px;
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
        top: 6px;
        left: 6px;
        background: rgba(15,23,42,0.8);
        color: var(--primary);
        font-size: 10px;
        padding: 2px 10px;
        border-radius: 30px;
        z-index: 2;
    }
    .character-card.pickable .char-media {
        border-radius: var(--radius-md);
        font-size: 36px;
        border: none;
        background: rgba(255,255,255,0.02);
        margin-bottom: 6px;
    }
    .character-card.pickable .name {
        font-size: 13px;
    }

    .btn-primary-gradient {
        background: linear-gradient(135deg, var(--primary), var(--purple-dark));
        border: none;
        color: #fff;
        font-weight: 800;
        padding: 12px 28px;
        border-radius: 60px;
        cursor: pointer;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        font-size: 16px;
        box-shadow: 0 8px 30px rgba(167,139,250,0.2);
    }
    .btn-primary-gradient:hover {
        transform: scale(1.04);
        box-shadow: 0 12px 40px rgba(167,139,250,0.3);
    }
    .btn-primary-gradient.btn-large {
        padding: 16px 40px;
        font-size: 20px;
    }
    .btn-outline {
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--border-light);
        color: var(--text-primary);
        padding: 12px 28px;
        border-radius: 60px;
        cursor: pointer;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        font-size: 16px;
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
        padding: 40px 0 20px;
        color: var(--text-muted);
        font-size: 14px;
        border-top: 1px solid var(--border-light);
        margin-top: 40px;
    }

    /* ============================================================
       استجابة محسّنة للجوال
       ============================================================ */
    @media (max-width: 768px) {
        .hero-content h1 { font-size: 40px; }
        .hero-subtitle { font-size: 22px; }
        .hero-description { font-size: 16px; }
        .hero-actions .btn-large { padding: 14px 28px; font-size: 18px; }
        .section-title { font-size: 28px; }
        .auth-card { padding: 20px 16px; }
        .characters-grid.pickable-grid { grid-template-columns: repeat(3, 1fr); }
        .features-grid { grid-template-columns: 1fr 1fr; }
        .character-card-enhanced {
            flex: 0 0 170px;
            padding: 14px 10px;
        }
        .char-media {
            width: 80px;
            height: 80px;
        }
        .char-info .name {
            font-size: 17px;
        }
        .hero-visual {
            padding: 20px 15px;
            min-height: 280px;
        }
        .hero-carousel {
            max-width: 150px;
            height: 150px;
        }
        .hero-carousel-item {
            width: 120px;
            height: 120px;
            font-size: 80px;
        }
        .hero-carousel-item .char-emoji {
            font-size: 90px;
        }
        .hero-stats {
            gap: 15px;
        }
        .hero-stats .stat .number {
            font-size: 22px;
        }
        .hero-stats .stat .label {
            font-size: 12px;
        }
        .hero-char-name {
            font-size: 17px;
        }
        .hero-section {
            gap: 20px;
            padding: 15px 0 20px;
        }
    }

    @media (max-width: 480px) {
        .hero-content h1 { font-size: 32px; }
        .hero-subtitle { font-size: 18px; }
        .hero-description { font-size: 15px; }
        .hero-actions .btn-large { 
            padding: 12px 20px; 
            font-size: 16px; 
            width: 100%; 
            justify-content: center; 
        }
        .section-title { font-size: 24px; }
        .features-grid { grid-template-columns: 1fr; }
        .plans-grid { grid-template-columns: 1fr; }
        .characters-grid { grid-template-columns: repeat(3, 1fr); }
        .auth-card { padding: 16px 12px; }
        .auth-logo { font-size: 24px; }
        .field input, .field select { font-size: 15px; padding: 10px 12px; }
        .characters-grid.pickable-grid { grid-template-columns: repeat(3, 1fr); }
        .character-card-enhanced {
            flex: 0 0 140px;
            padding: 10px 8px;
        }
        .char-media {
            width: 70px;
            height: 70px;
        }
        .char-info .name {
            font-size: 15px;
        }
        .char-info .title {
            font-size: 11px;
        }
        .char-hover-reveal blockquote {
            font-size: 12px;
            padding: 6px 10px;
        }
        .hero-visual {
            padding: 16px 10px;
            min-height: 220px;
            border-radius: 20px;
        }
        .hero-carousel {
            max-width: 110px;
            height: 110px;
        }
        .hero-carousel-item {
            width: 90px;
            height: 90px;
            font-size: 60px;
        }
        .hero-carousel-item .char-emoji {
            font-size: 70px;
        }
        .hero-stats {
            gap: 10px;
        }
        .hero-stats .stat {
            padding: 4px 10px;
            min-width: 50px;
        }
        .hero-stats .stat .number {
            font-size: 18px;
        }
        .hero-stats .stat .label {
            font-size: 10px;
        }
        .hero-char-name {
            font-size: 14px;
            padding: 2px 14px;
            min-height: 30px;
        }
        .hero-content {
            order: 2;
            padding: 0 10px;
        }
        .hero-visual {
            order: 1;
            width: 100%;
        }
        .hero-section {
            padding: 10px 0 15px;
            gap: 15px;
        }
        .hero-actions {
            flex-direction: column;
            width: 100%;
        }
        .hero-actions .btn-large {
            width: 100%;
            justify-content: center;
        }
        .hero-description {
            padding: 0 10px;
        }
        .section-sub {
            font-size: 15px;
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
            <!-- كاروسيل الشخصيات 3D -->
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
            <!-- اسم الشخصية الحالية -->
            <div class="hero-char-name" id="heroCharName">
                <span id="heroCharNameText"><?php echo htmlspecialchars($carouselChars[0]['name']); ?></span>
            </div>
            <!-- الإحصائيات -->
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
        <p style="font-size:14px; color:var(--text-muted); margin-top:14px;">⚠️ هذه القصة مثال، سيتم توليد قصة فريدة لكل طفل بعد التسجيل.</p>
    </section>

    <!-- الشخصيات – سكرول أفقي حيوي -->
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
        <div style="text-align:center; margin-top:28px;">
            <a href="#auth" class="btn btn-primary-gradient" style="font-size:18px; padding:14px 40px;">🚀 ابدأ مغامرتك الآن</a>
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
                    <p style="text-align:center;font-weight:800;color:var(--text-primary);">1) اختر شخصيتين ترافقان طفلك من باقة الشخصيات المجانية</p>
                    <p style="text-align:center;color:var(--text-secondary);font-size:13px;">الشخصيات المقفلة 🔒 تُفتح تلقائياً بعد تفعيل اشتراك مدفوع من ملفه الشخصي</p>
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
                                        <span style="font-size:36px;"><?php echo character_icons($c)[0] ?? '✨'; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="name"><?php echo h($c['name']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form method="POST" id="registerForm" style="margin-top:22px;">
                        <input type="hidden" name="character_1" id="character_1">
                        <input type="hidden" name="character_2" id="character_2">
                        <p style="font-weight:800;color:var(--text-primary);margin-top:20px;">2) بيانات الحساب</p>
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
     JavaScript – اختيار الشخصيات + الكاروسيل 3D
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
            alert('هذه الشخصية تُفتح بعد تفعيل اشتراك مدفوع 🔒 — اختر من الشخصيات المجانية الآن، وبإمكانك ترقية طفلك لاحقاً من ملفه الشخصي.');
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

    // 3. تحسين السكرول التلقائي للشخصيات
    document.addEventListener('DOMContentLoaded', function() {
        const track = document.querySelector('.characters-track');
        if (track) {
            const cards = document.querySelectorAll('.character-card-enhanced');
            cards.forEach(card => {
                card.addEventListener('mouseenter', () => { track.style.animationPlayState = 'paused'; });
                card.addEventListener('mouseleave', () => { track.style.animationPlayState = 'running'; });
            });
        }

        // ============================================================
        // 4. كاروسيل 3D للشخصيات في القسم الرئيسي
        // ============================================================
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
                        visual.style.setProperty('--glow-color', color);
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
                if (intervalId) {
                    clearInterval(intervalId);
                    intervalId = null;
                }
            }

            function resumeAutoPlay() {
                isPaused = false;
                if (!intervalId) {
                    startAutoPlay();
                }
            }

            // تأثير 3D بتتبع الماوس
            if (carousel && visual) {
                carousel.addEventListener('mousemove', function(e) {
                    if (isPaused) return;
                    const rect = this.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;
                    const rotateX = ((y - centerY) / centerY) * -10;
                    const rotateY = ((x - centerX) / centerX) * 10;
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

            window.heroCarousel = {
                goTo: goToIndex,
                next: nextItem,
                pause: pauseAutoPlay,
                resume: resumeAutoPlay
            };
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
