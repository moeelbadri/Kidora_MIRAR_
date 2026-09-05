<?php
// index.php - Landing Page (نسخة مبسطة Responsive)
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (!empty($_SESSION['child_id'])) {
    $chk = $pdo->prepare("SELECT * FROM children WHERE id = ?");
    $chk->execute([$_SESSION['child_id']]);
    $chkChild = $chk->fetch();
    header('Location: ' . ($chkChild && needs_assessment($chkChild) ? 'welcome.php' : 'dashboard.php'));
    exit;
}

$characters = all_characters($pdo);
$plans = $pdo->query("SELECT * FROM subscription_plans ORDER BY sort_order ASC")->fetchAll();

$loginError = null;
$registerError = null;

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
                $ins = $pdo->prepare("INSERT INTO children (name, email, password, age, parent_name, parent_phone, character_1, character_2, active_character) VALUES (?,?,?,?,?,?,?,?,?)");
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

$__pageTitle = 'Kidora — منصة التعلم بالمغامرة';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<style>
    /* ============================================================
       RESET + BASE
       ============================================================ */
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
        background: #0a061a;
        color: #f1f5f9;
        font-family: 'Segoe UI', 'Tajawal', sans-serif;
        line-height: 1.6;
    }
    .landing-page {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 16px 32px;
    }

    /* ============================================================
       HERO – بسيط و Responsive
       ============================================================ */
    .hero-section {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 24px;
        padding: 20px 0 30px;
    }

    .hero-content {
        text-align: center;
        width: 100%;
    }

    .hero-badge {
        display: inline-block;
        background: rgba(167,139,250,0.15);
        color: #a78bfa;
        padding: 4px 14px;
        border-radius: 40px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid rgba(167,139,250,0.15);
        margin-bottom: 12px;
    }

    .hero-content h1 {
        font-size: 2.5rem;
        font-weight: 900;
        line-height: 1.1;
        margin-bottom: 8px;
    }
    .hero-content h1 .highlight {
        background: linear-gradient(135deg, #fff, #c4b5fd);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .hero-subtitle {
        font-size: 1.3rem;
        font-weight: 600;
        color: #f1f5f9;
        margin-bottom: 8px;
    }
    .hero-description {
        font-size: 1rem;
        color: #d9d0ff;
        max-width: 600px;
        margin: 0 auto 20px;
        line-height: 1.8;
    }

    .hero-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 12px;
    }
    .hero-actions .btn-large {
        padding: 12px 28px;
        font-size: 1rem;
        border-radius: 60px;
        font-weight: 800;
        text-decoration: none;
        display: inline-block;
    }

    /* ===== Hero Visual ===== */
    .hero-visual {
        width: 100%;
        max-width: 400px;
        background: rgba(255,255,255,0.04);
        backdrop-filter: blur(12px);
        border-radius: 32px;
        padding: 24px 16px 20px;
        text-align: center;
        border: 1px solid rgba(255,255,255,0.04);
        box-shadow: 0 10px 40px rgba(0,0,0,0.5);
    }

    .hero-carousel {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 160px;
        margin-bottom: 8px;
    }

    .hero-carousel-item {
        display: none;
        width: 140px;
        height: 140px;
    }
    .hero-carousel-item.active {
        display: block;
        animation: fadeIn 0.5s ease;
    }
    .hero-carousel-item img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        filter: drop-shadow(0 10px 30px rgba(0,0,0,0.3));
    }
    .hero-carousel-item .char-emoji {
        font-size: 100px;
        line-height: 1;
        display: block;
    }

    @keyframes fadeIn {
        0% { opacity: 0; transform: scale(0.9); }
        100% { opacity: 1; transform: scale(1); }
    }

    .hero-char-name {
        font-weight: 800;
        font-size: 1.2rem;
        background: rgba(0,0,0,0.3);
        padding: 4px 16px;
        border-radius: 40px;
        display: inline-block;
        margin-bottom: 12px;
        border: 1px solid rgba(255,255,255,0.05);
    }
    .hero-char-name span {
        color: #a78bfa;
    }

    .hero-stats {
        display: flex;
        justify-content: center;
        gap: 20px;
        flex-wrap: wrap;
    }
    .hero-stats .stat {
        background: rgba(255,255,255,0.03);
        padding: 4px 14px;
        border-radius: 40px;
        border: 1px solid rgba(255,255,255,0.03);
        min-width: 60px;
    }
    .hero-stats .stat .number {
        font-size: 1.4rem;
        font-weight: 900;
        color: #a78bfa;
        display: block;
        line-height: 1.2;
    }
    .hero-stats .stat .label {
        font-size: 0.7rem;
        color: #b9abd4;
        font-weight: 600;
        display: block;
    }

    /* ============================================================
       باقي الأقسام (مختصرة)
       ============================================================ */
    .section-head {
        text-align: center;
        margin: 40px 0 24px;
    }
    .section-title {
        font-size: 2rem;
        font-weight: 900;
        color: #f1f5f9;
        margin: 6px 0 8px;
    }
    .section-sub {
        color: #c4b5d4;
        font-size: 1rem;
        max-width: 600px;
        margin: 0 auto;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 10px;
    }
    .feature-card {
        background: rgba(255,255,255,0.04);
        backdrop-filter: blur(8px);
        border-radius: 24px;
        padding: 24px 16px;
        text-align: center;
        border: 1px solid rgba(255,255,255,0.04);
        transition: 0.3s;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    }
    .feature-card:hover {
        transform: translateY(-6px);
        border-color: rgba(167,139,250,0.2);
    }
    .feature-icon { font-size: 40px; margin-bottom: 12px; }
    .feature-card h3 { font-size: 1.1rem; font-weight: 800; color: #f1f5f9; margin-bottom: 6px; }
    .feature-card p { color: #c4b5d4; font-size: 0.9rem; line-height: 1.6; }

    .video-wrapper {
        max-width: 800px;
        margin: 0 auto;
        border-radius: 32px;
        overflow: hidden;
        aspect-ratio: 16/9;
        background: #000;
        box-shadow: 0 30px 80px rgba(0,0,0,0.8);
        border: 1px solid rgba(255,255,255,0.04);
    }
    .video-wrapper iframe { width: 100%; height: 100%; border: 0; }

    /* الشخصيات – سكرول */
    .characters-scroll-wrapper {
        overflow-x: auto;
        padding: 16px 0 24px;
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
    .characters-track:hover { animation-play-state: paused; }
    @keyframes autoScroll {
        0% { transform: translateX(0); }
        100% { transform: translateX(-50%); }
    }
    .character-card-enhanced {
        flex: 0 0 160px;
        border-radius: 24px;
        padding: 16px 12px;
        text-align: center;
        background: rgba(255,255,255,0.04);
        border: 1px solid rgba(255,255,255,0.06);
        box-shadow: 0 8px 30px rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
        transition: 0.3s;
        transform: scale(0.95);
        opacity: 0.8;
    }
    .character-card-enhanced:hover {
        transform: scale(1.05) translateY(-8px);
        opacity: 1;
        border-color: var(--char-color, #a78bfa);
        box-shadow: 0 0 30px var(--char-glow, rgba(167,139,250,0.2));
    }
    .char-media {
        width: 80px;
        height: 80px;
        margin: 0 auto 10px;
        border-radius: 50%;
        overflow: hidden;
        background: rgba(0,0,0,0.3);
        border: 3px solid rgba(255,255,255,0.08);
    }
    .char-media img { width: 100%; height: 100%; object-fit: cover; }
    .char-emoji { font-size: 40px; display: block; text-align: center; }
    .char-info .name { font-weight: 900; font-size: 1rem; color: #fff; }
    .char-info .title { font-size: 0.75rem; color: #c4b5d4; }

    /* خطط الاشتراك */
    .plans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }
    .plan-card {
        background: rgba(255,255,255,0.04);
        backdrop-filter: blur(8px);
        border-radius: 24px;
        padding: 22px 16px;
        text-align: center;
        border: 1px solid rgba(255,255,255,0.04);
        transition: 0.3s;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    }
    .plan-card:hover { transform: translateY(-6px); border-color: rgba(167,139,250,0.15); }
    .plan-card h3 { font-size: 1.1rem; font-weight: 800; color: #f1f5f9; }
    .plan-card .price { font-size: 1.5rem; font-weight: 900; color: #a78bfa; margin: 8px 0 12px; }
    .plan-card ul { list-style: none; padding: 0; margin: 0 0 12px; }
    .plan-card ul li { color: #c4b5d4; font-size: 0.85rem; padding: 4px 0; border-bottom: 1px solid rgba(255,255,255,0.03); }

    /* النموذج */
    .auth-section { margin-top: 40px; border-top: 1px solid rgba(255,255,255,0.04); padding-top: 32px; }
    .auth-card {
        max-width: 600px;
        margin: 0 auto;
        background: rgba(255,255,255,0.04);
        backdrop-filter: blur(16px);
        border-radius: 32px;
        padding: 28px 24px;
        border: 1px solid rgba(255,255,255,0.04);
        box-shadow: 0 10px 40px rgba(0,0,0,0.5);
    }
    .auth-logo { font-size: 1.8rem; font-weight: 900; color: #a78bfa; text-align: center; margin-bottom: 4px; }
    .auth-sub { text-align: center; color: #c4b5d4; font-size: 0.95rem; margin-bottom: 20px; }
    .auth-tabs {
        display: flex;
        gap: 6px;
        background: rgba(255,255,255,0.03);
        border-radius: 60px;
        padding: 4px;
        margin-bottom: 20px;
    }
    .auth-tab {
        flex: 1;
        padding: 8px;
        border: none;
        background: transparent;
        color: #c4b5d4;
        font-weight: 700;
        border-radius: 40px;
        cursor: pointer;
        transition: 0.3s;
        font-size: 0.95rem;
    }
    .auth-tab.active {
        background: linear-gradient(135deg, #a78bfa, #7c3aed);
        color: #fff;
    }
    .auth-form.hidden { display: none; }
    .field { margin-bottom: 14px; }
    .field label { display: block; font-weight: 600; color: #f1f5f9; font-size: 0.85rem; margin-bottom: 4px; }
    .field input, .field select {
        width: 100%;
        padding: 10px 12px;
        background: rgba(255,255,255,0.04);
        border: 1px solid rgba(255,255,255,0.04);
        border-radius: 16px;
        color: #f1f5f9;
        font-size: 1rem;
        transition: 0.3s;
    }
    .field input:focus, .field select:focus {
        outline: none;
        border-color: #a78bfa;
        box-shadow: 0 0 0 3px rgba(167,139,250,0.08);
    }
    .auth-error {
        background: rgba(239,68,68,0.08);
        border: 1px solid rgba(239,68,68,0.15);
        border-radius: 16px;
        padding: 10px 14px;
        color: #f87171;
        margin-bottom: 14px;
        font-weight: 600;
    }
    .btn-block { width: 100%; justify-content: center; padding: 12px; font-size: 1rem; border-radius: 60px; }
    .auth-toggle { text-align: center; margin-top: 16px; color: #c4b5d4; font-size: 0.9rem; }
    .auth-toggle a { color: #a78bfa; text-decoration: none; font-weight: 700; }

    .btn-primary-gradient {
        background: linear-gradient(135deg, #a78bfa, #7c3aed);
        border: none;
        color: #fff;
        font-weight: 800;
        padding: 10px 24px;
        border-radius: 60px;
        cursor: pointer;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        font-size: 1rem;
        box-shadow: 0 8px 30px rgba(167,139,250,0.2);
    }
    .btn-primary-gradient:hover { transform: scale(1.04); box-shadow: 0 12px 40px rgba(167,139,250,0.3); }
    .btn-outline {
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.04);
        color: #f1f5f9;
        padding: 10px 24px;
        border-radius: 60px;
        cursor: pointer;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        font-size: 1rem;
        font-weight: 700;
    }
    .btn-outline:hover { background: rgba(255,255,255,0.06); border-color: #a78bfa; color: #a78bfa; }

    .landing-footer {
        text-align: center;
        padding: 32px 0 16px;
        color: #b9abd4;
        font-size: 0.85rem;
        border-top: 1px solid rgba(255,255,255,0.04);
        margin-top: 32px;
    }

    /* ============================================================
       RESPONSIVE – الحل النهائي
       ============================================================ */
    @media (min-width: 768px) {
        .hero-section {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
        }
        .hero-content {
            text-align: right;
            flex: 1;
        }
        .hero-description {
            margin-left: 0;
            margin-right: 0;
        }
        .hero-actions {
            justify-content: flex-start;
        }
        .hero-visual {
            flex: 0 0 380px;
        }
        .hero-content h1 { font-size: 3.5rem; }
        .hero-subtitle { font-size: 1.6rem; }
        .hero-carousel { height: 200px; }
        .hero-carousel-item { width: 180px; height: 180px; }
        .hero-carousel-item .char-emoji { font-size: 130px; }
    }

    @media (max-width: 480px) {
        .hero-content h1 { font-size: 2rem; }
        .hero-subtitle { font-size: 1.1rem; }
        .hero-description { font-size: 0.9rem; }
        .hero-carousel { height: 120px; }
        .hero-carousel-item { width: 100px; height: 100px; }
        .hero-carousel-item .char-emoji { font-size: 70px; }
        .hero-char-name { font-size: 1rem; }
        .hero-stats .stat .number { font-size: 1.1rem; }
        .hero-stats .stat .label { font-size: 0.6rem; }
        .section-title { font-size: 1.6rem; }
        .features-grid { grid-template-columns: 1fr; }
        .plans-grid { grid-template-columns: 1fr; }
        .auth-card { padding: 16px; }
        .hero-actions .btn-large { padding: 10px 18px; font-size: 0.9rem; width: 100%; justify-content: center; }
        .hero-actions { flex-direction: column; width: 100%; }
        .character-card-enhanced { flex: 0 0 130px; padding: 12px 8px; }
        .char-media { width: 60px; height: 60px; }
    }
</style>

<!-- ============================================================
     الصفحة
     ============================================================ -->
<div class="landing-page">

    <!-- HERO -->
    <section class="hero-section">
        <div class="hero-content">
            <div class="hero-badge">🚀 منصة تربوية ذكية</div>
            <h1><span class="highlight">Kidora</span></h1>
            <p class="hero-subtitle">حيث يتحول التعلم إلى مغامرة بطولية</p>
            <p class="hero-description">
                مهام يومية، قصص ملهمة، ألعاب تفاعلية، وشخصيات مرافقة.
                منصة متكاملة تنمي مهارات طفلك وتصنع منه بطلاً حقيقياً.
            </p>
            <div class="hero-actions">
                <a href="#auth" class="btn-primary-gradient btn-large">🚀 ابدأ مغامرتك الآن</a>
                <a href="#features" class="btn-outline btn-large">تعرف أكثر</a>
            </div>
        </div>
        <div class="hero-visual" id="heroVisual">
            <div class="hero-carousel" id="heroCarousel">
                <?php 
                $first = true;
                foreach ($characters as $c): 
                    $activeClass = $first ? 'active' : '';
                    $first = false;
                    $img = !empty($c['image_path']) 
                        ? '<img src="' . htmlspecialchars(BASE_PATH . '/' . $c['image_path']) . '" alt="' . htmlspecialchars($c['name']) . '">' 
                        : '<span class="char-emoji">' . htmlspecialchars(character_icons($c)[0] ?? '✨') . '</span>';
                ?>
                <div class="hero-carousel-item <?php echo $activeClass; ?>" data-name="<?php echo htmlspecialchars($c['name']); ?>">
                    <?php echo $img; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="hero-char-name"><span id="heroCharNameText"><?php echo htmlspecialchars($characters[0]['name'] ?? 'بطل'); ?></span></div>
            <div class="hero-stats">
                <div class="stat"><span class="number">100+</span><span class="label">قصة تفاعلية</span></div>
                <div class="stat"><span class="number"><?php echo count($characters); ?></span><span class="label">شخصيات</span></div>
                <div class="stat"><span class="number">✨ AI</span><span class="label">قصص مخصصة</span></div>
            </div>
        </div>
    </section>

    <!-- فيديو -->
    <section id="video">
        <div class="section-head"><div class="section-title">فيديو تعريفي للمنصة</div></div>
        <div class="video-wrapper">
            <iframe src="<?php echo h(youtube_embed_url('XIQBQk6F-ok')); ?>" allowfullscreen loading="lazy"></iframe>
        </div>
    </section>

    <!-- مميزات -->
    <section id="features">
        <div class="section-head">
            <div class="section-title">مغامرة تعلم متكاملة</div>
            <p class="section-sub">كل عنصر في المنصة صمم ليكون ممتعاً ومفيداً</p>
        </div>
        <div class="features-grid">
            <div class="feature-card"><div class="feature-icon">📋</div><h3>مهام يومية متسلسلة</h3><p>4 مهام جديدة كل يوم لتعزيز الانضباط</p></div>
            <div class="feature-card"><div class="feature-icon">📖</div><h3>قصص تاريخية وإسلامية</h3><p>قصص ملهمة من تراثنا العربي</p></div>
            <div class="feature-card"><div class="feature-icon">🎮</div><h3>ألعاب تفاعلية</h3><p>ألعاب ذاكرة تنمي التفكير والتركيز</p></div>
            <div class="feature-card"><div class="feature-icon">🏆</div><h3>مكافآت وتطور</h3><p>اجمع النقاط وافتح شخصيات جديدة</p></div>
            <div class="feature-card"><div class="feature-icon">🧠</div><h3>ذكاء اصطناعي</h3><p>قصص مخصصة لطفلك حسب اهتماماته</p></div>
            <div class="feature-card"><div class="feature-icon">📲</div><h3>تقارير للوالدين</h3><p>تتبع تقدم طفلك عبر واتساب</p></div>
        </div>
    </section>

    <!-- الشخصيات -->
    <section id="characters">
        <div class="section-head">
            <div class="section-title">اختر شخصيتك المفضلة</div>
            <p class="section-sub">شخصيات كرتونية مرافقة تشجعك في كل خطوة</p>
        </div>
        <div class="characters-scroll-wrapper">
            <div class="characters-track">
                <?php foreach ($characters as $c): 
                    $color = $c['color'] ?? '#a78bfa';
                    $icon = character_icons($c)[0] ?? '✨';
                ?>
                    <div class="character-card-enhanced" style="--char-color:<?php echo $color; ?>;--char-glow:<?php echo $color; ?>40;">
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
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($characters as $c): 
                    $color = $c['color'] ?? '#a78bfa';
                    $icon = character_icons($c)[0] ?? '✨';
                ?>
                    <div class="character-card-enhanced" style="--char-color:<?php echo $color; ?>;--char-glow:<?php echo $color; ?>40;">
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
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- خطط الاشتراك -->
    <section>
        <div class="section-head">
            <div class="section-title">اختر ما يناسبك</div>
            <p class="section-sub">الخطة المجانية تمنحك تجربة رائعة، والمدفوعة تفتح لك المزيد</p>
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
            <a href="#auth" class="btn-primary-gradient" style="font-size:1rem; padding:12px 32px;">🚀 ابدأ مغامرتك الآن</a>
        </div>
    </section>

    <!-- نموذج تسجيل الدخول / إنشاء حساب -->
    <section class="auth-section" id="auth">
        <div class="section-head">
            <div class="section-title">سجّل الدخول أو أنشئ حساباً</div>
            <p class="section-sub">ابدأ رحلة طفلك في عالم Kidora</p>
        </div>
        <div class="auth-card">
            <div class="auth-logo">🌟 Kidora</div>
            <p class="auth-sub">منصة ذكية تحوّل طفلك إلى بطل حقيقي</p>
            <div class="auth-tabs">
                <button type="button" class="auth-tab active" data-tab="login">تسجيل الدخول</button>
                <button type="button" class="auth-tab" data-tab="register">إنشاء حساب</button>
            </div>
            <div id="login-tab" class="auth-form">
                <?php if ($loginError): ?><div class="auth-error">❌ <?php echo h($loginError); ?></div><?php endif; ?>
                <form method="POST">
                    <div class="field"><label>البريد الإلكتروني</label><input type="email" name="email" required></div>
                    <div class="field"><label>كلمة المرور</label><input type="password" name="password" required></div>
                    <button type="submit" name="login" class="btn-primary-gradient btn-block">🚀 تسجيل الدخول</button>
                </form>
                <div class="auth-toggle">مسؤول المنصة؟ <a href="admin/login.php">دخول لوحة الإدارة</a></div>
            </div>
            <div id="register-tab" class="auth-form hidden">
                <?php if ($registerError): ?><div class="auth-error">❌ <?php echo h($registerError); ?></div><?php endif; ?>
                <p style="text-align:center;font-weight:800;color:var(--text-primary);font-size:15px;">اختر شخصيتين مجانيتين</p>
                <div class="characters-grid pickable-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:10px;margin-bottom:14px;">
                    <?php foreach ($characters as $c): $locked = (bool)$c['is_premium']; ?>
                        <div class="character-card pickable <?php echo $locked ? 'locked' : ''; ?>" data-id="<?php echo (int)$c['id']; ?>" data-locked="<?php echo $locked?'1':'0'; ?>" onclick="toggleCharPick(this)" style="cursor:pointer;border:2px solid transparent;border-radius:16px;padding:10px 8px;text-align:center;background:rgba(255,255,255,0.04);<?php echo $locked?'opacity:0.4;pointer-events:none;filter:grayscale(0.6);':''; ?>">
                            <?php if ($locked): ?><div style="position:absolute;top:4px;left:4px;background:rgba(0,0,0,0.8);color:#a78bfa;font-size:9px;padding:2px 8px;border-radius:30px;">🔒</div><?php endif; ?>
                            <div style="font-size:32px;"><?php echo character_icons($c)[0] ?? '✨'; ?></div>
                            <div style="font-size:12px;color:#f1f5f9;"><?php echo h($c['name']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="two-char-note" id="selCountLabel" style="text-align:center;font-weight:700;color:#a78bfa;background:rgba(167,139,250,0.06);border-radius:40px;padding:6px 10px;margin-bottom:14px;border:1px solid rgba(167,139,250,0.05);">0 / 2 مختارة</div>
                <form method="POST" id="registerForm">
                    <input type="hidden" name="character_1" id="character_1">
                    <input type="hidden" name="character_2" id="character_2">
                    <div class="field"><label>اسم الطفل</label><input type="text" name="child_name" required></div>
                    <div class="field"><label>عمر الطفل</label>
                        <select name="child_age" required>
                            <option value="">اختر العمر</option>
                            <?php for ($a=4;$a<=12;$a++): ?><option value="<?php echo $a; ?>"><?php echo $a; ?> سنوات</option><?php endfor; ?>
                        </select>
                    </div>
                    <div class="field"><label>اسم ولي الأمر</label><input type="text" name="parent_name" required></div>
                    <div class="field"><label>رقم واتساب</label><input type="tel" name="parent_phone" required placeholder="0599123456"></div>
                    <div class="field"><label>البريد الإلكتروني</label><input type="email" name="email" required></div>
                    <div class="field"><label>كلمة المرور (6 أحرف)</label><input type="password" name="password" required minlength="6"></div>
                    <div class="field"><label>تأكيد كلمة المرور</label><input type="password" name="confirm_password" required></div>
                    <button type="submit" name="register" class="btn-primary-gradient btn-block">🌟 ابدأ المغامرة</button>
                </form>
            </div>
        </div>
    </section>

    <footer class="landing-footer">
        <p>© 2026 Kidora. جميع الحقوق محفوظة.</p>
    </footer>
</div>

<script>
    // تبديل التبويبات
    document.querySelectorAll('.auth-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('login-tab').classList.toggle('hidden', this.dataset.tab !== 'login');
            document.getElementById('register-tab').classList.toggle('hidden', this.dataset.tab !== 'register');
        });
    });

    // اختيار شخصيتين
    let picked = [];
    function toggleCharPick(el) {
        if (el.dataset.locked === '1') {
            alert('هذه الشخصية مدفوعة 🔒 اختر من المجانية');
            return;
        }
        const id = parseInt(el.dataset.id, 10);
        const idx = picked.indexOf(id);
        if (idx > -1) { picked.splice(idx, 1); el.style.borderColor = 'transparent'; }
        else {
            if (picked.length >= 2) return;
            picked.push(id); el.style.borderColor = '#a78bfa';
        }
        document.getElementById('selCountLabel').textContent = picked.length + ' / 2 مختارة';
        document.getElementById('character_1').value = picked[0] || '';
        document.getElementById('character_2').value = picked[1] || '';
    }
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        if (picked.length !== 2) { e.preventDefault(); alert('اختر شخصيتين'); }
    });

    // كاروسيل بسيط
    document.addEventListener('DOMContentLoaded', function() {
        const items = document.querySelectorAll('.hero-carousel-item');
        const nameDisplay = document.getElementById('heroCharNameText');
        let current = 0;
        if (items.length > 1) {
            setInterval(function() {
                items.forEach(i => i.classList.remove('active'));
                current = (current + 1) % items.length;
                items[current].classList.add('active');
                const name = items[current].dataset.name || 'بطل';
                if (nameDisplay) nameDisplay.textContent = name;
            }, 4000);
        }

        // إيقاف السكرول عند hover
        const track = document.querySelector('.characters-track');
        if (track) {
            document.querySelectorAll('.character-card-enhanced').forEach(card => {
                card.addEventListener('mouseenter', () => track.style.animationPlayState = 'paused');
                card.addEventListener('mouseleave', () => track.style.animationPlayState = 'running');
            });
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
