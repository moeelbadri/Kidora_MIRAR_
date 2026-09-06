<?php
// index.php - الصفحة الرئيسية مع فيديو خلفية ثابت
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

// جلب البيانات
$characters = all_characters($pdo);
$plans = $pdo->query("SELECT * FROM subscription_plans ORDER BY sort_order ASC")->fetchAll();

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

// تحضير بيانات الشخصيات لـ JS
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
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Kidora — منصة التعلم بالمغامرة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ============================================================
           الأنماط العامة
           ============================================================ */
        :root {
            --bg-primary: #0a061a;
            --bg-secondary: #140a2a;
            --bg-card: rgba(255,255,255,0.05);
            --bg-card-hover: rgba(255,255,255,0.09);
            --text-primary: #f1f5f9;
            --text-secondary: #c4b5d4;
            --text-muted: #b9abd4;
            --primary: #a78bfa;
            --primary-dark: #7c3aed;
            --primary-glow: rgba(167,139,250,0.20);
            --gold: #fbbf24;
            --gold-dark: #f59e0b;
            --gold-glow: rgba(251,191,36,0.25);
            --border-light: rgba(255,255,255,0.06);
            --shadow-heavy: 0 30px 80px rgba(0,0,0,0.8);
            --shadow-soft: 0 10px 40px rgba(0,0,0,0.5);
            --radius-xl: 32px;
            --radius-lg: 24px;
            --radius-md: 16px;
            --transition: 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Segoe UI', 'Tajawal', system-ui, sans-serif;
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ============================================================
           فيديو الخلفية – ثابت (fixed)
           ============================================================ */
        .video-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
            background: #0a061a;
        }

        .video-background iframe {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100%;
            height: 100%;
            transform: translate(-50%, -50%);
            object-fit: cover;
            border: none;
            pointer-events: none;
        }

        .video-background .overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg,
                rgba(10,6,26,0.3) 0%,
                rgba(10,6,26,0.5) 50%,
                rgba(10,6,26,0.8) 85%,
                rgba(10,6,26,0.95) 100%
            );
            z-index: 1;
        }

        /* زر التحكم بالصوت */
        .sound-toggle {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 10;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.1);
            color: #fff;
            padding: 10px 14px;
            border-radius: 40px;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sound-toggle:hover {
            background: rgba(255,255,255,0.2);
        }
        .sound-toggle i {
            font-size: 20px;
        }

        /* ============================================================
           المحتوى الرئيسي (يظهر فوق الفيديو)
           ============================================================ */
        .content-wrapper {
            position: relative;
            z-index: 2;
            padding: 20px 0 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* قسم الهيرو التعريفي */
        .hero-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px 20px;
        }

        .hero-section .badge {
            display: inline-block;
            background: var(--gold-glow);
            color: var(--gold);
            padding: 6px 22px;
            border-radius: 60px;
            font-weight: 800;
            font-size: 14px;
            letter-spacing: 1px;
            border: 1px solid rgba(251,191,36,0.15);
            margin-bottom: 16px;
        }

        .hero-section h1 {
            font-size: clamp(48px, 8vw, 80px);
            font-weight: 900;
            line-height: 1.05;
            text-shadow: 0 4px 30px rgba(0,0,0,0.6);
        }

        .hero-section h1 .highlight {
            background: linear-gradient(135deg, #fff 20%, var(--gold) 80%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-section .subtitle {
            font-size: clamp(20px, 3vw, 30px);
            font-weight: 600;
            color: #e8e0ff;
            margin: 6px 0;
            text-shadow: 0 2px 20px rgba(0,0,0,0.5);
        }

        .hero-section .desc {
            font-size: clamp(15px, 1.4vw, 20px);
            color: #d9d0ff;
            max-width: 600px;
            margin: 12px auto 28px;
            line-height: 1.8;
            text-shadow: 0 2px 15px rgba(0,0,0,0.5);
        }

        .hero-section .actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 14px;
        }

        .btn {
            padding: 14px 36px;
            border-radius: 60px;
            font-weight: 800;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 16px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.3);
        }

        .btn-gold {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: #1a1a2e;
        }
        .btn-gold:hover { transform: scale(1.06) translateY(-3px); box-shadow: 0 8px 40px var(--gold-glow); }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
        }
        .btn-primary:hover { transform: scale(1.06) translateY(-3px); box-shadow: 0 8px 40px var(--primary-glow); }

        .btn-outline {
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.15);
            color: #fff;
        }
        .btn-outline:hover { background: rgba(255,255,255,0.12); transform: scale(1.04); }

        /* ============================================================
           باقي الأقسام (كروسيل، مميزات، AI، خطط، تسجيل)
           ============================================================ */
        .section-head {
            text-align: center;
            padding: 60px 20px 30px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .section-head .eyebrow {
            color: var(--gold);
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 3px;
            text-transform: uppercase;
        }
        .section-head h2 {
            font-size: clamp(30px, 5vw, 44px);
            font-weight: 900;
            color: #fff;
            margin: 6px 0 10px;
        }
        .section-head .sub {
            color: var(--text-secondary);
            font-size: 18px;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.7;
        }

        /* كروسيل الشخصيات */
        .characters-carousel-section {
            padding: 20px 0 40px;
        }

        .carousel-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 0 20px 16px 20px;
            max-width: 1400px;
            margin: 0 auto;
            flex-wrap: wrap;
            gap: 8px;
        }

        .carousel-header h2 {
            font-size: clamp(26px, 4vw, 38px);
            font-weight: 900;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .carousel-header h2 span { color: var(--gold); }

        .carousel-header .view-all {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .carousel-header .view-all:hover { color: var(--gold); }

        .carousel-wrapper {
            position: relative;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 10px;
        }

        .carousel-track {
            display: flex;
            gap: 16px;
            overflow-x: auto;
            padding: 12px 16px 30px 16px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scroll-snap-type: x mandatory;
            scrollbar-width: none;
        }
        .carousel-track::-webkit-scrollbar { display: none; }

        .char-card-netflix {
            flex: 0 0 clamp(180px, 18vw, 260px);
            scroll-snap-align: start;
            border-radius: var(--radius-lg);
            overflow: hidden;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            box-shadow: 0 8px 30px rgba(0,0,0,0.4);
            transform: scale(0.98);
            opacity: 0.85;
        }

        .char-card-netflix:hover {
            transform: scale(1.04) translateY(-12px);
            opacity: 1;
            border-color: var(--gold);
            box-shadow: 0 20px 60px rgba(0,0,0,0.6), 0 0 40px var(--gold-glow);
            z-index: 10;
        }

        .char-card-netflix .card-image {
            width: 100%;
            aspect-ratio: 3/4;
            overflow: hidden;
            background: rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .char-card-netflix .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s;
        }
        .char-card-netflix:hover .card-image img { transform: scale(1.08); }

        .char-card-netflix .card-image .char-emoji {
            font-size: clamp(60px, 10vw, 90px);
        }

        .char-card-netflix .card-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--gold);
            color: #1a1a2e;
            padding: 2px 14px;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .char-card-netflix .card-lock {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11px;
            color: var(--gold);
            border: 1px solid rgba(251,191,36,0.15);
        }

        .char-card-netflix .card-body {
            padding: 14px 14px 18px;
            text-align: center;
            background: rgba(10,6,26,0.6);
            backdrop-filter: blur(4px);
        }

        .char-card-netflix .card-body .name {
            font-weight: 800;
            font-size: clamp(15px, 1.6vw, 20px);
            color: #fff;
            margin-bottom: 2px;
        }

        .char-card-netflix .card-body .trait {
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .char-card-netflix .card-body .trait .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--gold);
            display: inline-block;
        }

        .char-card-netflix .card-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(0deg, rgba(10,6,26,0.9) 0%, transparent 60%);
            opacity: 0;
            transition: 0.4s;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding: 20px;
        }
        .char-card-netflix:hover .card-overlay { opacity: 1; }

        .char-card-netflix .card-overlay .play-btn {
            background: var(--gold);
            color: #1a1a2e;
            border: none;
            padding: 8px 24px;
            border-radius: 40px;
            font-weight: 800;
            font-size: 14px;
            cursor: pointer;
            transition: 0.3s;
            transform: translateY(10px);
            opacity: 0;
        }
        .char-card-netflix:hover .card-overlay .play-btn {
            transform: translateY(0);
            opacity: 1;
        }
        .char-card-netflix .card-overlay .play-btn:hover { transform: scale(1.05); }

        .carousel-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 20;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.06);
            color: #fff;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.3s;
            font-size: 18px;
        }
        .carousel-nav:hover { background: var(--gold); color: #1a1a2e; }
        .carousel-nav.prev { left: 0; }
        .carousel-nav.next { right: 0; }

        /* المميزات */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            max-width: 1200px;
            margin: 0 auto 20px;
            padding: 0 20px;
        }
        .feature-card {
            background: var(--bg-card);
            backdrop-filter: blur(8px);
            border-radius: var(--radius-lg);
            padding: 28px 18px 24px;
            text-align: center;
            border: 1px solid var(--border-light);
            transition: var(--transition);
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .feature-card:hover {
            transform: translateY(-10px);
            border-color: var(--gold);
            box-shadow: 0 16px 50px rgba(0,0,0,0.4), 0 0 30px var(--gold-glow);
        }
        .feature-card .icon { font-size: 44px; margin-bottom: 10px; }
        .feature-card h3 { font-size: 20px; font-weight: 800; }
        .feature-card p { color: var(--text-secondary); font-size: 14px; margin-top: 4px; }

        /* AI */
        .ai-section {
            max-width: 1000px;
            margin: 20px auto;
            padding: 40px 24px;
            background: linear-gradient(145deg, rgba(167,139,250,0.04), rgba(124,58,237,0.04));
            border-radius: var(--radius-xl);
            border: 1px solid var(--border-light);
            text-align: center;
        }
        .ai-section .ai-icon { font-size: 52px; animation: pulse 2.5s ease-in-out infinite; }
        @keyframes pulse { 0%,100%{transform:scale(1);opacity:0.8} 50%{transform:scale(1.08);opacity:1} }
        .ai-section h2 { font-size: 28px; font-weight: 900; margin: 8px 0; }
        .ai-section p { color: var(--text-secondary); font-size: 16px; max-width: 550px; margin: 0 auto 16px; line-height: 1.8; }

        .ai-preview {
            background: rgba(255,255,255,0.02);
            border-radius: var(--radius-lg);
            padding: 24px;
            max-width: 600px;
            margin: 0 auto;
            border: 1px dashed rgba(167,139,250,0.12);
            text-align: right;
        }
        .ai-preview .story-title { font-weight: 800; color: var(--gold); font-size: 20px; }
        .ai-preview .story-snippet { color: var(--text-secondary); font-size: 15px; line-height: 1.8; margin: 6px 0; }
        .ai-preview .story-tag {
            display: inline-block;
            background: var(--primary-glow);
            color: var(--primary);
            font-size: 12px;
            padding: 4px 16px;
            border-radius: 30px;
            font-weight: 600;
        }

        /* خطط الاشتراك */
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 24px;
            max-width: 1200px;
            margin: 0 auto 20px;
            padding: 0 20px;
        }
        .plan-card {
            background: var(--bg-card);
            backdrop-filter: blur(8px);
            border-radius: var(--radius-lg);
            padding: 28px 18px;
            text-align: center;
            border: 1px solid var(--border-light);
            transition: var(--transition);
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .plan-card:hover {
            transform: translateY(-8px);
            border-color: var(--gold);
            box-shadow: 0 16px 40px rgba(0,0,0,0.3);
        }
        .plan-card h3 { font-size: 22px; font-weight: 800; }
        .plan-card .price { font-size: 30px; font-weight: 900; color: var(--gold); margin: 10px 0; }
        .plan-card ul { list-style: none; padding: 0; text-align: right; }
        .plan-card ul li { padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--text-secondary); font-size: 14px; }

        /* نموذج التسجيل */
        .auth-section {
            max-width: 640px;
            margin: 40px auto 20px;
            padding: 0 20px;
        }
        .auth-card {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            border-radius: var(--radius-xl);
            padding: 32px 28px;
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-soft);
        }
        .auth-logo { font-size: 28px; font-weight: 900; color: var(--gold); text-align: center; }
        .auth-sub { text-align: center; color: var(--text-secondary); font-size: 15px; margin: 4px 0 20px; }

        .auth-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 20px;
            background: rgba(255,255,255,0.03);
            border-radius: 60px;
            padding: 4px;
            border: 1px solid var(--border-light);
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
            font-size: 14px;
        }
        .auth-tab.active {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: #1a1a2e;
            box-shadow: 0 4px 20px var(--gold-glow);
        }
        .auth-form { display: none; }
        .auth-form.active { display: block; }

        .field { margin-bottom: 16px; }
        .field label { display: block; font-weight: 600; color: var(--text-primary); font-size: 13px; margin-bottom: 4px; }
        .field input, .field select {
            width: 100%;
            padding: 10px 14px;
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
            border-color: var(--gold);
            box-shadow: 0 0 0 3px var(--gold-glow);
        }
        .field select option { background: #1e293b; }

        .auth-error {
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.15);
            border-radius: var(--radius-md);
            padding: 10px 14px;
            color: #f87171;
            margin-bottom: 14px;
            font-weight: 600;
        }

        .btn-block { width: 100%; justify-content: center; padding: 14px; font-size: 16px; }
        .auth-toggle { text-align: center; margin-top: 16px; color: var(--text-secondary); font-size: 13px; }
        .auth-toggle a { color: var(--gold); text-decoration: none; font-weight: 700; }

        .pickable-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 10px;
            margin: 12px 0;
        }
        .pickable {
            border: 2px solid transparent;
            padding: 8px 4px;
            border-radius: var(--radius-md);
            cursor: pointer;
            text-align: center;
            background: rgba(255,255,255,0.03);
            transition: 0.3s;
        }
        .pickable:hover { border-color: var(--primary); transform: scale(1.04); }
        .pickable.selected { border-color: var(--gold); background: var(--gold-glow); }
        .pickable.locked { opacity: 0.4; pointer-events: none; filter: grayscale(0.6); }
        .pickable .char-media {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 4px;
            background: rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .pickable .char-media img { width: 100%; height: 100%; object-fit: cover; }
        .pickable .name { font-size: 12px; font-weight: 600; }

        .two-char-note {
            text-align: center;
            font-weight: 700;
            color: var(--gold);
            background: var(--gold-glow);
            border-radius: 40px;
            padding: 6px 10px;
            margin-bottom: 12px;
            border: 1px solid rgba(251,191,36,0.06);
            font-size: 14px;
        }

        .landing-footer {
            text-align: center;
            padding: 32px 0 16px;
            color: var(--text-muted);
            font-size: 13px;
            border-top: 1px solid var(--border-light);
            margin-top: 40px;
        }

        /* ============================================================
           استجابة
           ============================================================ */
        @media (max-width: 768px) {
            .hero-section { min-height: auto; padding: 80px 20px 40px; }
            .hero-section h1 { font-size: 36px; }
            .hero-section .subtitle { font-size: 18px; }
            .hero-section .desc { font-size: 14px; }
            .char-card-netflix { flex: 0 0 clamp(130px, 30vw, 160px); }
            .carousel-nav { width: 32px; height: 32px; font-size: 13px; }
            .features-grid { grid-template-columns: 1fr 1fr; gap: 14px; }
            .plans-grid { grid-template-columns: 1fr; }
            .auth-card { padding: 20px 16px; }
            .pickable-grid { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 480px) {
            .hero-section h1 { font-size: 28px; }
            .hero-section .subtitle { font-size: 16px; }
            .char-card-netflix { flex: 0 0 120px; }
            .char-card-netflix .card-body .name { font-size: 13px; }
            .features-grid { grid-template-columns: 1fr; }
            .pickable-grid { grid-template-columns: repeat(3, 1fr); }
        }
    </style>
</head>
<body>

    <!-- ==========================================================
    فيديو الخلفية الثابت (Fixed)
    ========================================================== -->
    <div class="video-background" id="videoBackground">
        <iframe 
            src="https://www.youtube.com/embed/XIQBQk6F-ok?autoplay=1&mute=1&loop=1&playlist=XIQBQk6F-ok&controls=0&showinfo=0&rel=0&modestbranding=1" 
            frameborder="0" 
            allow="autoplay; encrypted-media" 
            allowfullscreen>
        </iframe>
        <div class="overlay"></div>
    </div>

    <!-- زر التحكم بالصوت -->
    <button class="sound-toggle" id="soundToggle" onclick="toggleSound()">
        <i class="fas fa-volume-mute"></i>
        <span>تفعيل الصوت</span>
    </button>

    <!-- ==========================================================
    المحتوى الرئيسي
    ========================================================== -->
    <div class="content-wrapper">

        <!-- قسم الهيرو التعريفي -->
        <section class="hero-section" id="hero">
            <div class="hero-content">
                <div class="badge">🚀 منصة تربوية ذكية</div>
                <h1><span class="highlight">Kidora</span></h1>
                <p class="subtitle">حيث يتحول التعلم إلى مغامرة بطولية</p>
                <p class="desc">
                    مهام يومية، قصص ملهمة، ألعاب تفاعلية، وشخصيات مرافقة.
                    منصة متكاملة تنمي مهارات طفلك وتصنع منه بطلاً حقيقياً.
                </p>
                <div class="actions">
                    <a href="#carousel" class="btn btn-gold">🎮 استكشف الشخصيات</a>
                    <a href="#auth" class="btn btn-outline">🚀 سجل وابدأ</a>
                </div>
            </div>
        </section>

        <!-- كروسيل الشخصيات -->
        <section class="characters-carousel-section" id="carousel">
            <div class="carousel-header">
                <h2>🌟 شخصياتك المفضلة <span>✦</span></h2>
                <a href="#auth" class="view-all">اختر شخصيتك <i class="fas fa-arrow-left"></i></a>
            </div>

            <div class="carousel-wrapper">
                <button class="carousel-nav prev" onclick="scrollCarousel(-1)"><i class="fas fa-chevron-left"></i></button>
                <button class="carousel-nav next" onclick="scrollCarousel(1)"><i class="fas fa-chevron-right"></i></button>

                <div class="carousel-track" id="charTrack">
                    <?php foreach ($characters as $c):
                        $color = $c['color'] ?? '#a78bfa';
                        $icon = character_icons($c)[0] ?? '🌟';
                        $locked = (bool)$c['is_premium'];
                    ?>
                        <div class="char-card-netflix" style="--char-color: <?= h($color) ?>;">
                            <div class="card-image">
                                <?php if (!empty($c['image_path'])): ?>
                                    <img src="<?= h($c['image_path']) ?>" alt="<?= h($c['name']) ?>">
                                <?php else: ?>
                                    <span class="char-emoji"><?= $icon ?></span>
                                <?php endif; ?>

                                <?php if (!$locked): ?>
                                    <span class="card-badge">مجانية</span>
                                <?php else: ?>
                                    <span class="card-lock">🔒 مدفوعة</span>
                                <?php endif; ?>
                            </div>

                            <div class="card-body">
                                <div class="name"><?= h($c['name']) ?></div>
                                <div class="trait">
                                    <span class="dot"></span>
                                    <?= h($c['trait'] ?? 'مميز') ?>
                                </div>
                            </div>

                            <div class="card-overlay">
                                <?php if ($locked): ?>
                                    <button class="play-btn" onclick="alert('🔓 اشترك الآن لفتح هذه الشخصية!')">🔓 اشترك</button>
                                <?php else: ?>
                                    <button class="play-btn" onclick="alert('✅ اختر هذه الشخصية عند التسجيل!')">✅ اخترها</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- المميزات -->
        <div class="section-head" id="features">
            <div class="eyebrow">✨ لماذا Kidora</div>
            <h2>مغامرة تعلم متكاملة</h2>
            <p class="sub">كل عنصر في المنصة صمم ليكون ممتعاً ومفيداً في آن واحد</p>
        </div>
        <div class="features-grid">
            <div class="feature-card"><div class="icon">📋</div><h3>مهام يومية</h3><p>4 مهام جديدة كل يوم</p></div>
            <div class="feature-card"><div class="icon">📖</div><h3>قصص تفاعلية</h3><p>قصص صوتية ومرئية</p></div>
            <div class="feature-card"><div class="icon">🎮</div><h3>ألعاب تعليمية</h3><p>تنمي الذاكرة والتركيز</p></div>
            <div class="feature-card"><div class="icon">🏆</div><h3>مكافآت وتطور</h3><p>افتح شخصيات جديدة</p></div>
            <div class="feature-card"><div class="icon">🧠</div><h3>ذكاء اصطناعي</h3><p>قصص مخصصة لكل طفل</p></div>
            <div class="feature-card"><div class="icon">📲</div><h3>تقارير للوالدين</h3><p>تابع تقدم طفلك</p></div>
        </div>

        <!-- الذكاء الاصطناعي -->
        <div class="ai-section">
            <div class="ai-icon">🤖</div>
            <h2>قصص مخصصة بذكاء اصطناعي</h2>
            <p>نستخدم تقنيات الذكاء الاصطناعي لتوليد قصة فريدة لكل طفل، تتناسب مع عمره واهتماماته.</p>
            <div class="ai-preview">
                <div class="story-title">📖 مغامرة في مدينة النور</div>
                <div class="story-snippet">
                    "في مدينة النور البعيدة، كان هناك طفل شجاع يدعى يوسف. ذات يوم، وجد خريطة قديمة تقوده إلى كنز الحكمة..."
                </div>
                <div class="story-tag">✨ قصة مخصصة ليوسف (7 سنوات)</div>
            </div>
        </div>

        <!-- خطط الاشتراك -->
        <div class="section-head">
            <div class="eyebrow">📦 خطط الاشتراك</div>
            <h2>اختر ما يناسبك</h2>
            <p class="sub">الخطة المجانية تمنحك تجربة رائعة، والمدفوعة تفتح لك المزيد من الشخصيات والمحتوى.</p>
        </div>
        <div class="plans-grid">
            <?php foreach ($plans as $p):
                $features = json_decode_safe($p['features_json'], []);
            ?>
                <div class="plan-card">
                    <h3><?= h($p['name']) ?></h3>
                    <div class="price"><?= (int)$p['price_ils'] === 0 ? 'مجانية' : (int)$p['price_ils'].' ₪' ?></div>
                    <ul>
                        <?php foreach (array_slice($features, 0, 4) as $f): ?>
                            <li>✅ <?= h($f) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- تسجيل الدخول / إنشاء حساب -->
        <section class="auth-section" id="auth">
            <div class="auth-card">
                <div class="auth-logo">🌟 Kidora</div>
                <p class="auth-sub">منصة ذكية تحوّل طفلك إلى بطل حقيقي</p>

                <div class="auth-tabs">
                    <button type="button" class="auth-tab active" data-tab="login">تسجيل الدخول</button>
                    <button type="button" class="auth-tab" data-tab="register">إنشاء حساب</button>
                </div>

                <div id="login-tab" class="auth-form active">
                    <?php if ($loginError): ?><div class="auth-error">❌ <?= h($loginError) ?></div><?php endif; ?>
                    <form method="POST">
                        <div class="field"><label>البريد الإلكتروني لولي الأمر</label><input type="email" name="email" required></div>
                        <div class="field"><label>كلمة المرور</label><input type="password" name="password" required></div>
                        <button type="submit" name="login" class="btn btn-gold btn-block">🚀 تسجيل الدخول</button>
                    </form>
                    <div class="auth-toggle">مسؤول المنصة؟ <a href="admin/login.php">دخول لوحة الإدارة</a></div>
                </div>

                <div id="register-tab" class="auth-form hidden">
                    <?php if ($registerError): ?><div class="auth-error">❌ <?= h($registerError) ?></div><?php endif; ?>
                    <p style="text-align:center;font-weight:700;color:var(--gold);font-size:15px;">
                        1) اختر شخصيتين مجانيتين
                    </p>
                    <div class="two-char-note" id="selCountLabel">0 / 2 مختارة</div>
                    <div class="pickable-grid" id="regCharGrid">
                        <?php foreach ($characters as $c): $locked = (bool)$c['is_premium']; ?>
                            <div class="pickable <?= $locked ? 'locked' : '' ?>"
                                 data-id="<?= (int)$c['id'] ?>"
                                 data-locked="<?= $locked ? '1':'0' ?>"
                                 onclick="toggleCharPick(this)">
                                <?php if ($locked): ?><div style="font-size:10px;color:var(--gold);">🔒</div><?php endif; ?>
                                <div class="char-media">
                                    <?php if (!empty($c['image_path'])): ?>
                                        <img src="<?= h($c['image_path']) ?>" alt="<?= h($c['name']) ?>">
                                    <?php else: ?>
                                        <span style="font-size:28px;"><?= character_icons($c)[0] ?? '✨' ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="name"><?= h($c['name']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <form method="POST" id="registerForm" style="margin-top:16px;">
                        <input type="hidden" name="character_1" id="character_1">
                        <input type="hidden" name="character_2" id="character_2">
                        <p style="font-weight:700;color:var(--text-primary);font-size:15px;">2) بيانات الحساب</p>

                        <div class="field"><label>اسم الطفل</label><input type="text" name="child_name" required value="<?= h($_POST['child_name'] ?? '') ?>"></div>
                        <div class="field"><label>عمر الطفل</label>
                            <select name="child_age" required>
                                <option value="">اختر العمر</option>
                                <?php for ($a = 4; $a <= 12; $a++): ?>
                                    <option value="<?= $a ?>" <?= (($_POST['child_age'] ?? '') == $a) ? 'selected' : '' ?>><?= $a ?> سنوات</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="field"><label>اسم ولي الأمر</label><input type="text" name="parent_name" required value="<?= h($_POST['parent_name'] ?? '') ?>"></div>
                        <div class="field"><label>رقم واتساب ولي الأمر</label><input type="tel" name="parent_phone" required placeholder="مثال: 0599123456" value="<?= h($_POST['parent_phone'] ?? '') ?>"></div>
                        <div class="field"><label>البريد الإلكتروني</label><input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>"></div>
                        <div class="field"><label>كلمة المرور (6 أحرف)</label><input type="password" name="password" required minlength="6"></div>
                        <div class="field"><label>تأكيد كلمة المرور</label><input type="password" name="confirm_password" required></div>
                        <button type="submit" name="register" class="btn btn-gold btn-block">🌟 ابدأ المغامرة</button>
                    </form>
                </div>
            </div>
        </section>

        <footer class="landing-footer">
            <p>© 2026 Kidora. جميع الحقوق محفوظة.</p>
        </footer>
    </div>

    <!-- ==========================================================
    JavaScript
    ========================================================== -->
    <script>
        // ===== التحكم بالصوت =====
        let soundEnabled = false;

        function toggleSound() {
            const iframe = document.querySelector('.video-background iframe');
            const btn = document.getElementById('soundToggle');
            const icon = btn.querySelector('i');
            const text = btn.querySelector('span');

            if (!soundEnabled) {
                // تفعيل الصوت: تغيير المصدر لإزالة mute وإضافة autoplay
                if (iframe) {
                    let src = iframe.src;
                    if (src.includes('mute=1')) {
                        src = src.replace('mute=1', 'mute=0');
                    } else {
                        src += '&mute=0';
                    }
                    // تأكد من وجود autoplay
                    if (!src.includes('autoplay=1')) {
                        src += '&autoplay=1';
                    }
                    iframe.src = src;
                }
                icon.className = 'fas fa-volume-up';
                text.textContent = 'كتم الصوت';
                soundEnabled = true;
            } else {
                // كتم الصوت
                if (iframe) {
                    let src = iframe.src;
                    if (src.includes('mute=0')) {
                        src = src.replace('mute=0', 'mute=1');
                    } else if (src.includes('mute=1')) {
                        // already muted
                    } else {
                        src += '&mute=1';
                    }
                    iframe.src = src;
                }
                icon.className = 'fas fa-volume-mute';
                text.textContent = 'تفعيل الصوت';
                soundEnabled = false;
            }
        }

        // ===== كروسيل الشخصيات =====
        function scrollCarousel(direction) {
            const track = document.getElementById('charTrack');
            const cardWidth = track.querySelector('.char-card-netflix')?.offsetWidth || 200;
            const gap = 16;
            const scrollAmount = (cardWidth + gap) * direction * 2;
            track.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        }

        // ===== تبديل التبويبات =====
        document.querySelectorAll('.auth-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                const tabName = this.dataset.tab;
                document.getElementById('login-tab').classList.toggle('active', tabName === 'login');
                document.getElementById('register-tab').classList.toggle('active', tabName === 'register');
            });
        });

        <?php if ($registerError): ?>
            document.querySelector('.auth-tab[data-tab="register"]').click();
        <?php endif; ?>

        // ===== اختيار شخصيتين للتسجيل =====
        const CHAR_DATA = <?= json_encode($charDataForJS, JSON_UNESCAPED_UNICODE) ?>;
        let picked = [];

        function toggleCharPick(el) {
            if (el.dataset.locked === '1') {
                alert('🔒 هذه الشخصية مدفوعة، اشترك لفتحها.');
                return;
            }
            const id = parseInt(el.dataset.id, 10);
            const idx = picked.indexOf(id);
            if (idx > -1) {
                picked.splice(idx, 1);
                el.classList.remove('selected');
            } else {
                if (picked.length >= 2) return;
                picked.push(id);
                el.classList.add('selected');
            }
            document.getElementById('selCountLabel').textContent = picked.length + ' / 2 مختارة';
            document.getElementById('character_1').value = picked[0] || '';
            document.getElementById('character_2').value = picked[1] || '';
        }

        document.getElementById('registerForm').addEventListener('submit', function(e) {
            if (picked.length !== 2) {
                e.preventDefault();
                alert('الرجاء اختيار شخصيتين مجانيتين.');
            }
        });

        // ===== تحميل الصفحة – التأكد من تشغيل الفيديو =====
        document.addEventListener('DOMContentLoaded', function() {
            const iframe = document.querySelector('.video-background iframe');
            if (iframe) {
                // إعادة تحميل المصدر مع muted لضمان التشغيل التلقائي
                if (!iframe.src.includes('autoplay=1')) {
                    iframe.src += '&autoplay=1&mute=1';
                }
            }
        });
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
