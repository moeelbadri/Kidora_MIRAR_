<?php
// الواجهة العامة + تسجيل الدخول وإنشاء الحساب
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

/* ============================================================
   تسجيل الخروج — امسح التوكن + الجلسة
   ============================================================ */
if (isset($_GET['logout'])) {
    if (!empty($_SESSION['child_id'])) {
        kidora_clear_remember($pdo, (int)$_SESSION['child_id']);
    } else {
        kidora_clear_remember($pdo);
    }
    session_destroy();
    header('Location: ' . BASE_PATH . '/index.php');
    exit;
}

/* ============================================================
   الدخول التلقائي — للطفل اللي سجّل قبل (كوكي "تذكرني")
   ============================================================ */
/* ============================================================
   إذا الكوكي موجود:
   - ?continue=1  → دخول تلقائي فعلي
   - بدونها        → نعرض "متابعة كـ ريمان"
   ============================================================ */
$rememberChild = null;

if (empty($_SESSION['child_id'])) {

    if (isset($_GET['continue'])) {
        // الطفل ضغط "متابعة كـ ريمان"
        $autoChild = kidora_try_auto_login($pdo);
        if ($autoChild) {
            header('Location: ' . (needs_assessment($autoChild) ? 'welcome.php' : 'dashboard.php'));
            exit;
        }
    } else {
        // نتفحص بدون دخول — عشان نعرض الشاشة
        $rememberChild = kidora_peek_remember($pdo);
    }
}

/* إذا كان طلب POST (نموذج)، نعرض الفورم دايماً */
$showContinue = ($rememberChild && $_SERVER['REQUEST_METHOD'] !== 'POST');

/* ============================================================
   إذا عندو جلسة أصلية — روح للداشبورد
   ============================================================ */
if (!empty($_SESSION['child_id'])) {
    $chk = $pdo->prepare("SELECT * FROM children WHERE id = ?");
    $chk->execute([$_SESSION['child_id']]);
    $chkChild = $chk->fetch();
    header('Location: ' . ($chkChild && needs_assessment($chkChild) ? 'welcome.php' : 'dashboard.php'));
    exit;
}

$characters = all_characters($pdo);
$plans = $pdo->query("SELECT * FROM subscription_plans ORDER BY sort_order ASC, id ASC")->fetchAll();
$publicStats = public_counts($pdo);
$loginError = null;
$registerError = null;

$prefillName = trim((string)($_GET['name'] ?? ''));
$prefillChar = (int)($_GET['char'] ?? 0);
if (!$prefillChar && !empty($_GET['char']) && preg_match('/^[a-z0-9_-]+$/i', (string)$_GET['char'])) {
    $prefillStmt = $pdo->prepare("SELECT id FROM characters WHERE slug = ?");
    $prefillStmt->execute([(string)$_GET['char']]);
    $prefillChar = (int)($prefillStmt->fetchColumn() ?: 0);
}
$prefillCharRow = $prefillChar ? get_character($pdo, $prefillChar) : null;
if (!$prefillCharRow || !empty($prefillCharRow['is_premium'])) $prefillChar = 0;

/* ============================================================
   تسجيل الدخول
   ============================================================ */
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
            session_regenerate_id(true);
            $_SESSION['child_id'] = $user['id'];
            $_SESSION['child_name'] = $user['name'];

            /* ✅ سجّل "تذكرني" للدخول التلقائي مستقبلاً */
            kidora_remember_login($pdo, (int)$user['id']);

            $fullUser = $pdo->prepare("SELECT * FROM children WHERE id = ?");
            $fullUser->execute([$user['id']]);
            $fullUser = $fullUser->fetch();
            header('Location: ' . (needs_assessment($fullUser) ? 'welcome.php' : 'dashboard.php'));
            exit;
        }
        $loginError = 'البريد الإلكتروني أو كلمة المرور غير صحيحة.';
    }
}

/* ============================================================
   إنشاء الحساب — اختيار الشخصيتين مقيد بالمجانيتين على الخادم
   ============================================================ */
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
    } elseif ($name === '' || $age < 4 || $age > 12 || $parentName === '' || $parentPhone === '' || $email === '' || $password === '' || $confirm === '') {
        $registerError = 'الرجاء ملء جميع الحقول المطلوبة.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registerError = 'الرجاء إدخال بريد إلكتروني صحيح.';
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
                $photoPath = null;
                if (!empty($_FILES['child_photo']['name'])) {
                    $savedPhoto = save_image_upload('child_photo', __DIR__ . '/uploads/photos');
                    if (!$savedPhoto) {
                        $registerError = 'الصورة غير صالحة. استخدم JPG أو PNG أو WebP بحجم أقصى 4 ميجابايت.';
                    } else {
                        $photoPath = 'uploads/photos/' . basename($savedPhoto);
                    }
                }
                if (!$registerError) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $pdo->prepare("INSERT INTO children (name, email, password, age, parent_name, parent_phone, photo_path, character_1, character_2, active_character)
                                           VALUES (?,?,?,?,?,?,?,?,?,?)");
                    $ins->execute([$name, $email, $hashed, $age, $parentName, $parentPhone, $photoPath, $char1, $char2, $char1]);
                    $childId = (int)$pdo->lastInsertId();

                    $freePlan = $pdo->query("SELECT id FROM subscription_plans ORDER BY sort_order ASC, id ASC LIMIT 1")->fetch();
                    if ($freePlan) {
                        $subIns = $pdo->prepare("INSERT INTO subscriptions (child_id, plan_id, status, activated_at, activated_by) VALUES (?,?,'active',CURRENT_TIMESTAMP,'system')");
                        $subIns->execute([$childId, $freePlan['id']]);
                    }

                    session_regenerate_id(true);
                    $_SESSION['child_id'] = $childId;
                    $_SESSION['child_name'] = $name;

                    /* ✅ سجّل "تذكرني" للدخول التلقائي مستقبلاً */
                    kidora_remember_login($pdo, $childId);

                    header('Location: subscriptions.php?welcome=1');
                    exit;
                }
            }
        }
    }
}

$charDataForJS = array_map(static function (array $c): array {
    return [
        'id' => (int)$c['id'],
        'slug' => $c['slug'],
        'color' => preg_match('/^#[0-9a-f]{3,8}$/i', (string)$c['color']) ? $c['color'] : '#6C63FF',
        'move' => $c['move_type'],
        'icons' => character_icons($c),
        'image' => !empty($c['image_path']) ? BASE_PATH . '/' . ltrim($c['image_path'], '/') : null,
        'name' => $c['name'],
        'title' => $c['title'],
        'quote' => $c['quote'],
        'trait' => $c['trait'],
        'is_premium' => (bool)$c['is_premium'],
    ];
}, $characters);

$remoteHeroVideo = is_file(__DIR__ . '/assets/videos/hero.mp4') && is_readable(__DIR__ . '/assets/videos/hero.mp4');
$introVideo = [
    'mp4' => $remoteHeroVideo || is_file(__DIR__ . '/assets/video/intro.mp4'),
    'mp4Path' => $remoteHeroVideo ? 'assets/videos/hero.mp4' : 'assets/video/intro.mp4',
    'webm' => is_file(__DIR__ . '/assets/video/intro.webm'),
    'webmPath' => 'assets/video/intro.webm',
    'poster' => is_file(__DIR__ . '/assets/video/intro-poster.webp'),
    'posterPath' => 'assets/video/intro-poster.webp',
];
$remoteVideoId = 'XIQBQk6F-ok';
$shouldOpenRegister = isset($_GET['register']) || (bool)$registerError;
$__pageTitle = 'Kidora — حيث تبدأ المغامرة';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/public-nav.php';
?>

<style>
  :root{--k-gold:#ffc93c;--k-gold-deep:#f5a623;--k-blue:#5b8def;--k-pink:#ff6fa5;--k-cyan:#2ec4b6;--k-ink:#241645;--k-card:rgba(255,255,255,.07);--k-line:rgba(255,255,255,.14)}
  .public-page{position:relative;z-index:2;overflow:hidden;color:#f1f5f9}
  .public-container{width:min(1180px,calc(100% - 32px));margin:0 auto}
  .public-hero{min-height:clamp(620px,calc(100vh - 72px),820px);display:grid;grid-template-columns:1.1fr .9fr;align-items:center;gap:44px;padding:76px 0 48px}
  .public-eyebrow{display:inline-flex;align-items:center;gap:8px;padding:7px 14px;border:1px solid rgba(255,201,60,.34);border-radius:999px;color:#ffe99a;background:rgba(255,201,60,.1);font-size:13px;font-weight:900}
  .public-hero h1{margin:18px 0 12px;font-family:var(--font-display);font-size:clamp(48px,8vw,92px);line-height:.98;letter-spacing:-1px}
  .public-gradient-text{background-image:linear-gradient(135deg,#fff,#ffe99a 48%,#ffc93c);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
  .public-hero-lead{max-width:640px;margin:0;color:#d9d0ff;font-size:clamp(18px,2.2vw,24px);font-weight:700;line-height:1.9}
  .public-hero-copy{max-width:640px;color:#b9abd4;font-size:16px;line-height:1.9;margin:14px 0 26px}
  .public-actions{display:flex;flex-wrap:wrap;align-items:center;gap:12px}
  .k-btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:52px;padding:12px 25px;border:1px solid transparent;border-radius:999px;font-family:inherit;font-size:16px;font-weight:900;transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease}
  .k-btn:hover{transform:translateY(-3px)}
  .k-btn-gold{color:var(--k-ink);background:linear-gradient(135deg,#ffe99a,var(--k-gold));box-shadow:0 12px 28px rgba(255,201,60,.28)}
  .k-btn-gold:hover{box-shadow:0 16px 34px rgba(255,201,60,.42)}
  .k-btn-ghost{color:#fff;background:rgba(255,255,255,.07);border-color:var(--k-line)}
  .k-btn-ghost:hover{border-color:rgba(255,201,60,.6);background:rgba(255,255,255,.12)}
  .public-stat-row{display:flex;flex-wrap:wrap;gap:12px;margin-top:30px}
  .public-stat{min-width:125px;padding:13px 15px;border:1px solid var(--k-line);border-radius:18px;background:rgba(255,255,255,.055);backdrop-filter:blur(10px)}
  .public-stat strong{display:block;color:#ffe99a;font-family:var(--font-display);font-size:26px;line-height:1.1}
  .public-stat span{display:block;margin-top:4px;color:#b9abd4;font-size:12px;font-weight:700}
  .public-hero-art{position:relative;min-height:420px;display:grid;place-items:center}
  .public-video-showcase{padding-top:20px}
  .public-video-frame{position:relative;min-height:min(68svh,680px);overflow:hidden;border:1px solid rgba(255,255,255,.16);border-radius:30px;background:#05030b;box-shadow:0 28px 70px rgba(0,0,0,.38)}
  .public-video-frame:after{content:"";position:absolute;inset:0;pointer-events:none;background:linear-gradient(180deg,rgba(10,6,26,.02),rgba(10,6,26,.5))}
  .public-video-frame iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
  
  /* Full-screen video */
  .public-video-full{width:100vw;min-height:100vh;margin:0;padding:0;position:relative;overflow:hidden;background:#0a061a}
  .public-video-full .public-video-frame{position:absolute;inset:0;width:100%;height:100%;border:none;border-radius:0;box-shadow:none}
  .public-video-full .public-video-frame iframe{width:100%;height:100%;border:0}
  .public-video-full .public-video-frame:after{display:none}

  .public-orbit{position:absolute;width:min(100%,430px);aspect-ratio:1;border:1px solid rgba(255,201,60,.22);border-radius:50%;box-shadow:0 0 80px rgba(91,141,239,.15) inset,0 0 80px rgba(255,105,170,.1);animation:publicOrbit 18s linear infinite}
  .public-orbit:before,.public-orbit:after{content:"";position:absolute;width:20px;height:20px;border-radius:50%;background:#ffc93c;box-shadow:0 0 22px #ffc93c}
  .public-orbit:before{top:10%;right:12%}.public-orbit:after{bottom:18%;left:9%;background:#5b8def;box-shadow:0 0 22px #5b8def}
  @keyframes publicOrbit{to{transform:rotate(360deg)}}
  .public-hero-character{position:relative;z-index:1;width:min(72vw,290px);aspect-ratio:1;border-radius:42% 58% 54% 46%;display:grid;place-items:center;border:8px solid rgba(255,255,255,.18);background:radial-gradient(circle at 35% 25%,rgba(255,255,255,.42),transparent 25%),linear-gradient(145deg,var(--hero-color,#6c63ff),rgba(10,6,26,.72));box-shadow:0 30px 70px rgba(0,0,0,.4),0 0 80px color-mix(in srgb,var(--hero-color,#6c63ff) 45%,transparent);font-size:clamp(105px,17vw,180px);animation:publicCharacterFloat 4.2s ease-in-out infinite}
  .public-hero-character img{width:100%;height:100%;object-fit:cover;border-radius:inherit}
  @keyframes publicCharacterFloat{0%,100%{transform:translateY(0) rotate(-4deg)}50%{transform:translateY(-18px) rotate(4deg)}}
  .public-floating-badge{position:absolute;z-index:2;bottom:10%;right:4%;max-width:190px;padding:12px 15px;border:1px solid rgba(255,255,255,.2);border-radius:18px;background:rgba(10,6,26,.78);box-shadow:0 15px 30px rgba(0,0,0,.24);color:#fff;font-size:13px;font-weight:800;line-height:1.7}
  .public-section{padding:86px 0}
  .public-section-head{text-align:center;max-width:700px;margin:0 auto 34px}
  .public-section-head h2{margin:9px 0;font-family:var(--font-display);font-size:clamp(30px,5vw,50px);line-height:1.15}
  .public-section-head p{margin:0;color:#b9abd4;font-size:16px;line-height:1.9}
  .public-section-kicker{color:#ffe99a;font-size:13px;font-weight:900;letter-spacing:.5px}
  .public-carousel-shell{position:relative}
  .public-carousel-window{overflow:hidden;margin:0 38px;padding:15px 5px 24px}
  .public-carousel-track{display:flex;direction:rtl;gap:18px;will-change:transform;perspective:1200px}
  .public-character-card{position:relative;flex:0 0 clamp(190px,24vw,250px);padding:13px;border:1px solid var(--k-line);border-radius:25px;background:linear-gradient(155deg,rgba(255,255,255,.12),rgba(255,255,255,.035));color:#fff;text-align:right;cursor:pointer;overflow:hidden;transform-style:preserve-3d;transition:transform .28s ease,border-color .2s ease,box-shadow .2s ease}
  .public-character-card:hover,.public-character-card:focus-visible{transform:translateY(-7px) rotateY(-4deg);border-color:var(--char-color,#ffc93c);box-shadow:0 16px 40px rgba(0,0,0,.22),0 0 30px color-mix(in srgb,var(--char-color,#ffc93c) 28%,transparent);outline:none}
  .public-character-poster{height:205px;border-radius:18px;display:grid;place-items:center;overflow:hidden;background:radial-gradient(circle at 30% 20%,rgba(255,255,255,.35),transparent 27%),linear-gradient(145deg,var(--char-color,#6c63ff),rgba(10,6,26,.8));font-size:86px}
  .public-character-poster img{width:100%;height:100%;object-fit:cover}
  .public-character-name{display:block;margin:12px 3px 2px;font-family:var(--font-display);font-size:21px}
  .public-character-title{display:block;margin:0 3px;color:#d9d0ff;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .public-character-badge{position:absolute;top:22px;right:22px;padding:5px 9px;border-radius:999px;color:var(--k-ink);background:#ffe99a;font-size:11px;font-weight:900}
  .public-character-badge.is-premium{color:#fff;background:rgba(10,6,26,.78);border:1px solid rgba(255,255,255,.25)}
  .public-carousel-arrow{position:absolute;top:45%;z-index:2;width:42px;height:42px;border:1px solid rgba(255,255,255,.2);border-radius:50%;color:#fff;background:rgba(10,6,26,.76);font-size:24px;box-shadow:0 8px 20px rgba(0,0,0,.22)}
  .public-carousel-arrow:hover{border-color:#ffc93c;color:#ffc93c}.public-carousel-arrow.prev{right:0}.public-carousel-arrow.next{left:0}
  .public-features{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
  .public-feature{min-height:210px;padding:25px 22px;border:1px solid var(--k-line);border-radius:24px;background:rgba(255,255,255,.055);backdrop-filter:blur(9px);opacity:0;transform:translateY(22px)}
  .public-feature-icon{display:grid;place-items:center;width:56px;height:56px;border-radius:18px;color:var(--k-ink);background:linear-gradient(135deg,#ffe99a,#ffc93c);font-size:29px;box-shadow:0 8px 20px rgba(255,201,60,.18)}
  .public-feature h3{margin:17px 0 7px;font-family:var(--font-display);font-size:23px}.public-feature p{margin:0;color:#b9abd4;font-size:14px;line-height:1.8}
  .public-plans{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;align-items:stretch}
  .public-plan{position:relative;padding:28px 23px;border:1px solid var(--k-line);border-radius:25px;background:rgba(255,255,255,.06)}
  .public-plan.featured{border-color:#ffc93c;box-shadow:0 0 35px rgba(255,201,60,.15);transform:translateY(-8px)}
  .public-plan-ribbon{position:absolute;top:14px;left:14px;padding:5px 9px;border-radius:999px;color:var(--k-ink);background:#ffc93c;font-size:11px;font-weight:900}
  .public-plan h3{margin:0;font-family:var(--font-display);font-size:26px}.public-plan-price{margin:9px 0 18px;color:#ffe99a;font-family:var(--font-display);font-size:34px;font-weight:900}.public-plan-price small{color:#b9abd4;font-family:var(--font-body);font-size:13px}
  .public-plan ul{min-height:132px;margin:0 0 20px;padding:0;list-style:none}.public-plan li{padding:7px 0;border-bottom:1px solid rgba(255,255,255,.08);color:#d9d0ff;font-size:14px}.public-plan li:before{content:"✓";margin-left:7px;color:#ffc93c;font-weight:900}
  .public-plan .k-btn{width:100%}
  .public-auth-section{padding:86px 0 105px}
  .public-auth-card{max-width:760px;margin:0 auto;padding:30px;border:1px solid rgba(255,255,255,.16);border-radius:30px;background:rgba(255,255,255,.08);backdrop-filter:blur(18px);box-shadow:0 26px 70px rgba(0,0,0,.25)}
  .public-auth-tabs{display:flex;gap:8px;padding:5px;border:1px solid rgba(255,255,255,.1);border-radius:999px;background:rgba(0,0,0,.18);margin-bottom:25px}.public-auth-tab{flex:1;padding:12px;border-radius:999px;color:#b9abd4;font:inherit;font-weight:900}.public-auth-tab.active{color:var(--k-ink);background:linear-gradient(135deg,#ffe99a,#ffc93c)}
  .public-auth-form[hidden]{display:none}.public-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 15px}.public-field{margin-bottom:15px}.public-field.full{grid-column:1/-1}.public-field label{display:block;margin-bottom:6px;color:#f1f5f9;font-size:13px;font-weight:800}.public-field input,.public-field select{width:100%;min-height:47px;border:1px solid rgba(255,255,255,.15);border-radius:13px;padding:10px 13px;color:#fff;background:rgba(0,0,0,.24);font:inherit}.public-field input:focus,.public-field select:focus{outline:2px solid #ffc93c;outline-offset:1px}.public-field select option{color:#241645;background:#fff}.public-auth-card .k-btn{width:100%}
  .public-pick-note{margin:0 0 13px;color:#d9d0ff;font-size:13px;line-height:1.7}.public-pick-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;margin-bottom:20px}.public-pick{position:relative;padding:7px;border:2px solid transparent;border-radius:15px;color:#fff;background:rgba(255,255,255,.06);text-align:center}.public-pick:not(.locked):hover,.public-pick.selected{border-color:#ffc93c;background:rgba(255,201,60,.14)}.public-pick.locked{opacity:.45;filter:grayscale(.7);cursor:not-allowed}.public-pick-media{display:grid;place-items:center;aspect-ratio:1;border-radius:10px;background:linear-gradient(145deg,var(--char-color),rgba(10,6,26,.8));font-size:32px;overflow:hidden}.public-pick-media img{width:100%;height:100%;object-fit:cover}.public-pick strong{display:block;margin-top:5px;font-size:12px}.public-pick small{display:block;margin-top:2px;color:#ffe99a;font-size:9px}
  .public-photo-row{display:flex;align-items:center;gap:14px}.public-photo-preview{display:grid;place-items:center;width:64px;height:64px;flex:0 0 64px;border:2px solid rgba(255,201,60,.5);border-radius:50%;overflow:hidden;color:#ffe99a;background:rgba(0,0,0,.22);font-size:24px}.public-photo-preview img{width:100%;height:100%;object-fit:cover}
  .public-error{margin:0 0 18px;padding:11px 14px;border:1px solid rgba(248,113,113,.4);border-radius:13px;color:#fecaca;background:rgba(127,29,29,.28);font-weight:700}
  .public-footer{padding:26px 0 32px;border-top:1px solid rgba(255,255,255,.1);color:#b9abd4;text-align:center;font-size:13px}
  .public-modal{position:fixed;inset:0;z-index:400;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(3,2,13,.78);backdrop-filter:blur(12px)}.public-modal.open{display:flex}.public-modal-card{position:relative;width:min(520px,100%);max-height:calc(100vh - 36px);overflow:auto;padding:28px;border:1px solid rgba(255,255,255,.2);border-radius:28px;background:linear-gradient(160deg,#241645,#100920);box-shadow:0 30px 80px rgba(0,0,0,.5);text-align:center}.public-modal-close{position:absolute;top:12px;left:12px;width:37px;height:37px;border:1px solid rgba(255,255,255,.18);border-radius:50%;color:#fff;background:rgba(255,255,255,.08);font-size:20px}.public-modal-visual{width:125px;height:125px;margin:2px auto 15px;border-radius:35%;display:grid;place-items:center;background:linear-gradient(145deg,var(--modal-color,#6c63ff),rgba(10,6,26,.8));font-size:65px;overflow:hidden}.public-modal-visual img{width:100%;height:100%;object-fit:cover}.public-modal-card h2{margin:0;font-family:var(--font-display);font-size:32px}.public-modal-card p{color:#d9d0ff;line-height:1.8}.public-modal-trait{color:#ffe99a;font-weight:800}.public-modal-icons{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin:16px 0;font-size:23px}.public-modal-actions{display:flex;justify-content:center;gap:10px;flex-wrap:wrap}.public-modal-actions .k-btn{min-height:44px;font-size:14px}
  .public-intro{position:fixed;inset:0;z-index:500;display:grid;place-items:center;background:#0a061a;overflow:hidden}.public-intro.is-hidden{pointer-events:none}.public-intro-media{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.72}.public-intro-scrim{position:absolute;inset:0;background:linear-gradient(180deg,rgba(10,6,26,.2),rgba(10,6,26,.92))}.public-intro-content{position:relative;z-index:1;width:min(700px,calc(100% - 32px));text-align:center}.public-intro-logo{font-family:var(--font-display);font-size:clamp(56px,13vw,120px);font-weight:900;background-image:linear-gradient(135deg,#fff,#ffc93c);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}.public-intro-line{margin:8px 0 24px;color:#f1f5f9;font-size:clamp(18px,3vw,28px);font-weight:800}.public-intro-fallback{display:flex;justify-content:center;gap:10px;min-height:85px;margin-bottom:20px}.public-intro-fallback span{display:grid;place-items:center;width:64px;height:64px;border-radius:22px;background:linear-gradient(145deg,var(--char-color),rgba(255,255,255,.08));font-size:36px;box-shadow:0 0 26px color-mix(in srgb,var(--char-color) 35%,transparent)}.public-intro-actions{display:flex;justify-content:center;flex-wrap:wrap;gap:9px}.public-intro-skip{border:1px solid rgba(255,255,255,.25);color:#fff;background:rgba(255,255,255,.08)}
  @media(max-width:900px){.public-hero{grid-template-columns:1fr;text-align:center;padding-top:52px}.public-hero-copy,.public-hero-lead{margin-inline:auto}.public-actions,.public-stat-row{justify-content:center}.public-hero-art{min-height:315px}.public-hero-character{width:220px}.public-video-frame{min-height:52svh;border-radius:23px}.public-features{grid-template-columns:1fr 1fr}.public-plans{grid-template-columns:1fr}.public-plan.featured{transform:none}}
  @media(max-width:600px){.public-section{padding:64px 0}.public-container{width:min(100% - 22px,560px)}.public-features{grid-template-columns:1fr}.public-form-grid{grid-template-columns:1fr}.public-field.full{grid-column:auto}.public-pick-grid{grid-template-columns:repeat(3,1fr)}.public-auth-card{padding:20px 14px}.public-carousel-window{margin:0 28px}.public-character-card{flex-basis:calc(82vw - 22px)}.public-character-poster{height:185px}.public-stat{min-width:calc(50% - 6px)}.public-stat strong{font-size:22px}}
  @media(prefers-reduced-motion:reduce){.public-orbit,.public-hero-character{animation:none}.public-feature{opacity:1;transform:none}}
</style>

<div class="public-page">
  <?php if ($introVideo['mp4'] || $introVideo['webm'] || $introVideo['poster']): ?>
    <div class="public-intro" id="introOverlay" aria-label="المقدمة التعريفية">
      <?php if ($introVideo['mp4'] || $introVideo['webm']): ?>
        <video class="public-intro-media" id="introVideo" autoplay muted playsinline preload="auto"<?php echo $introVideo['poster'] ? ' poster="' . h(BASE_PATH . '/' . $introVideo['posterPath']) . '"' : ''; ?>>
          <?php if ($introVideo['webm']): ?><source src="<?php echo h(BASE_PATH . '/' . $introVideo['webmPath']); ?>" type="video/webm"><?php endif; ?>
          <?php if ($introVideo['mp4']): ?><source src="<?php echo h(BASE_PATH . '/' . $introVideo['mp4Path']); ?>" type="video/mp4"><?php endif; ?>
        </video>
      <?php endif; ?>
      <div class="public-intro-scrim"></div>
      <div class="public-intro-content">
        <div class="public-intro-fallback" id="introFallback" aria-hidden="true">
          <?php foreach (array_slice($charDataForJS, 0, 6) as $introChar): ?>
            <span style="--char-color:<?php echo h($introChar['color']); ?>"><?php echo h($introChar['icons'][0] ?? '✨'); ?></span>
          <?php endforeach; ?>
        </div>
        <div class="public-intro-logo">Kidora</div>
        <p class="public-intro-line">كل مغامرة كبيرة تبدأ بخطوة صغيرة</p>
        <div class="public-intro-actions">
          <button type="button" class="k-btn k-btn-gold" id="introStart">🚀 ابدأ المغامرة</button>
          <button type="button" class="k-btn public-intro-skip" id="introSkip">تخطي</button>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="public-intro" id="introOverlay" aria-label="المقدمة التعريفية">
      <div class="public-intro-scrim"></div>
      <div class="public-intro-content">
        <div class="public-intro-fallback" id="introFallback" aria-hidden="true">
          <?php foreach (array_slice($charDataForJS, 0, 6) as $introChar): ?>
            <span style="--char-color:<?php echo h($introChar['color']); ?>"><?php echo h($introChar['icons'][0] ?? '✨'); ?></span>
          <?php endforeach; ?>
        </div>
        <div class="public-intro-logo">Kidora</div>
        <p class="public-intro-line">كل مغامرة كبيرة تبدأ بخطوة صغيرة</p>
        <div class="public-intro-actions">
          <button type="button" class="k-btn k-btn-gold" id="introStart">🚀 ابدأ المغامرة</button>
          <button type="button" class="k-btn public-intro-skip" id="introSkip">تخطي</button>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <main>
    <!-- فيديو تعريفي بملء الشاشة -->
    <section class="public-video-full" id="videoShowcase">
      <div class="public-video-frame">
        <iframe
          src="<?php echo h(youtube_embed_url($remoteVideoId) . '&autoplay=1&loop=1&playlist=' . rawurlencode($remoteVideoId) . '&controls=1'); ?>"
          title="فيديو تعريفي عن Kidora"
          loading="lazy"
          allow="autoplay; encrypted-media; picture-in-picture"
          allowfullscreen></iframe>
      </div>
    </section>

    <!-- البطل الرئيسي -->
    <section class="public-container public-hero" id="hero">
      <div>
        <span class="public-eyebrow">✨ منصة آمنة تصنع مغامرات حقيقية</span>
        <h1><span class="public-gradient-text">Kidora</span><br>حيث يتحول التعلم إلى مغامرة بطولية</h1>
        <p class="public-hero-lead">مهام يومية، رفقاء محبوبون، ألعاب ذكية وقصص تجعل كل إنجاز لحظة تستحق الاحتفال.</p>
        <p class="public-hero-copy">رحلة عربية مصممة للأطفال من 4 إلى 12 عاماً، تساعدهم على النمو خطوة بخطوة وتمنح الوالدين صورة أوضح عن التقدّم.</p>
        <div class="public-actions">
          <a class="k-btn k-btn-gold" href="<?php echo h(BASE_PATH . '/demo.php'); ?>">🎮 جرب الآن</a>
          <a class="k-btn k-btn-ghost" href="#auth">🚀 ابدأ المغامرة</a>
        </div>
        <div class="public-stat-row" aria-label="إحصاءات المنصة">
          <div class="public-stat"><strong><?php echo (int)$publicStats['characters']; ?></strong><span>شخصيات مرافقة</span></div>
          <div class="public-stat"><strong><?php echo (int)$publicStats['tasks']; ?></strong><span>مهمة يومية</span></div>
          <div class="public-stat"><strong><?php echo (int)$publicStats['games']; ?></strong><span>لعبة تفاعلية</span></div>
          <div class="public-stat"><strong><?php echo (int)$publicStats['figures']; ?></strong><span>شخصية من تراثنا</span></div>
        </div>
      </div>
      <div class="public-hero-art" aria-hidden="true">
        <div class="public-orbit"></div>
        <?php $heroChar = $charDataForJS[0] ?? ['color' => '#6C63FF', 'icons' => ['✨'], 'image' => null]; ?>
        <div class="public-hero-character" id="heroCharacter" style="--hero-color:<?php echo h($heroChar['color']); ?>">
          <?php if (!empty($heroChar['image'])): ?><img src="<?php echo h($heroChar['image']); ?>" alt=""><?php else: ?><?php echo h($heroChar['icons'][0] ?? '✨'); ?><?php endif; ?>
        </div>
        <div class="public-floating-badge">رفيقك يرافقك في كل خطوة<br><span style="color:#ffe99a;">صوت وتشجيع وثيم خاص بك</span></div>
      </div>
    </section>

    <!-- الشخصيات -->
    <section class="public-section public-container" id="characters">
      <div class="public-section-head">
        <span class="public-section-kicker">رفقاء الرحلة</span>
        <h2>اختر الشخصية التي تشبه خيالك</h2>
        <p>جرّب أي شخصية في الديمو، ثم اختر رفيقين مجانيين ليبدآ الرحلة معك.</p>
      </div>
      <div class="public-carousel-shell">
        <button type="button" class="public-carousel-arrow prev" id="charPrev" aria-label="الشخصيات السابقة">›</button>
        <div class="public-carousel-window" id="characterCarousel" role="region" aria-roledescription="carousel" aria-label="شخصيات Kidora">
          <div class="public-carousel-track" id="characterTrack">
            <?php foreach ($charDataForJS as $c): ?>
              <button type="button" class="public-character-card" data-char-id="<?php echo (int)$c['id']; ?>" style="--char-color:<?php echo h($c['color']); ?>" aria-haspopup="dialog">
                <?php if ($c['is_premium']): ?><span class="public-character-badge is-premium">مدفوعة 🔒</span><?php else: ?><span class="public-character-badge">مجانية</span><?php endif; ?>
                <span class="public-character-poster">
                  <?php if (!empty($c['image'])): ?><img src="<?php echo h($c['image']); ?>" alt="<?php echo h($c['name']); ?>" loading="lazy"><?php else: ?><?php echo h($c['icons'][0] ?? '✨'); ?><?php endif; ?>
                </span>
                <span class="public-character-name"><?php echo h($c['name']); ?></span>
                <span class="public-character-title"><?php echo h($c['title']); ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
        <button type="button" class="public-carousel-arrow next" id="charNext" aria-label="الشخصيات التالية">‹</button>
      </div>
    </section>

    <!-- الميزات -->
    <section class="public-section public-container" id="features">
      <div class="public-section-head">
        <span class="public-section-kicker">لماذا Kidora؟</span>
        <h2>عالم كامل ينمو مع الطفل</h2>
        <p>كل تجربة تجمع بين المرح والفائدة والأمان، من أول مهمة حتى القصة الكبرى.</p>
      </div>
      <div class="public-features">
        <article class="public-feature" data-reveal><div class="public-feature-icon" data-motion>📋</div><h3>مهام يومية مترابطة</h3><p>أربع مهام مناسبة للعمر، مع شخصية من تراثنا ولعبة صغيرة مرتبطة بكل إنجاز.</p></article>
        <article class="public-feature" data-reveal><div class="public-feature-icon" data-motion>🎮</div><h3>ألعاب تفاعلية</h3><p>مطابقة وذاكرة وأسئلة ومغامرات، مع تجربة هادئة للصغار ووقت مناسب للكبار.</p></article>
        <article class="public-feature" data-reveal><div class="public-feature-icon" data-motion>📖</div><h3>قصة من إنجازاتك</h3><p>القصة اليومية تبنى من مهام الطفل الحقيقية، لتصبح الرحلة ذكرى يشعر أنها تخصه.</p></article>
        <article class="public-feature" data-reveal><div class="public-feature-icon" data-motion>📊</div><h3>فهم أفضل للتقدم</h3><p>تحليل سلوكي دوري يوضح المحاور الأقوى وما يحتاج إلى مزيد من التدريب بلطف.</p></article>
        <article class="public-feature" data-reveal><div class="public-feature-icon" data-motion>🛡️</div><h3>حماية رقمية وشخصية</h3><p>محتوى مبسط يساعد الطفل على فهم الحدود الآمنة وطلب المساعدة بثقة.</p></article>
        <article class="public-feature" data-reveal><div class="public-feature-icon" data-motion>📲</div><h3>الوالدان في الصورة</h3><p>تقارير مختصرة عبر واتساب تجعل متابعة رحلة الطفل أسهل وأقرب.</p></article>
      </div>
    </section>

    <!-- الخطط -->
    <section class="public-section public-container" id="plans">
      <div class="public-section-head">
        <span class="public-section-kicker">خطط بسيطة</span>
        <h2>ابدأ مجاناً، وكبّر المغامرة وقتما تشاء</h2>
        <p>الخطة المجانية تمنح الطفل حلقة يومية كاملة، والخطط المدفوعة تفتح عوالم إضافية.</p>
      </div>
      <div class="public-plans">
        <?php foreach ($plans as $planIndex => $p): $features = json_decode_safe($p['features_json'], []); ?>
          <article class="public-plan <?php echo $planIndex === 1 ? 'featured' : ''; ?>">
            <?php if ($planIndex === 1): ?><span class="public-plan-ribbon">الأكثر اختياراً</span><?php endif; ?>
            <h3><?php echo h($p['name']); ?></h3>
            <div class="public-plan-price"><?php echo (int)$p['price_ils'] === 0 ? 'مجانية' : (int)$p['price_ils'] . ' ₪'; ?><?php if ((int)$p['price_ils'] > 0): ?><small> / <?php echo h($p['billing_cycle']); ?></small><?php endif; ?></div>
            <ul>
              <?php foreach ($features as $feature): ?><li><?php echo h((string)$feature); ?></li><?php endforeach; ?>
            </ul>
            <a class="k-btn <?php echo $planIndex === 1 ? 'k-btn-gold' : 'k-btn-ghost'; ?>" href="#auth" data-open-register>🚀 ابدأ المغامرة</a>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- تسجيل الدخول / التسجيل -->
    <section class="public-auth-section public-container" id="auth">
      <div class="public-section-head">
        <span class="public-section-kicker">خطوتك الأولى</span>
        <h2>جاهز لمغامرة جديدة؟</h2>
        <p>أنشئ حساباً للطفل في دقائق، أو عد إلى رحلتك من هنا.</p>
      </div>
      <div class="public-auth-card">
        <div class="public-auth-tabs" role="tablist" aria-label="تسجيل الدخول أو إنشاء الحساب">
          <button type="button" class="public-auth-tab <?php echo $shouldOpenRegister ? '' : 'active'; ?>" data-auth-tab="login" role="tab" aria-selected="<?php echo $shouldOpenRegister ? 'false' : 'true'; ?>">تسجيل الدخول</button>
          <button type="button" class="public-auth-tab <?php echo $shouldOpenRegister ? 'active' : ''; ?>" data-auth-tab="register" role="tab" aria-selected="<?php echo $shouldOpenRegister ? 'true' : 'false'; ?>">إنشاء حساب</button>
        </div>

        <div class="public-auth-form" id="loginPanel" <?php echo $shouldOpenRegister ? 'hidden' : ''; ?>>
          <?php if ($loginError): ?><div class="public-error">❌ <?php echo h($loginError); ?></div><?php endif; ?>
          <form method="POST">
            <div class="public-form-grid">
              <div class="public-field full"><label for="loginEmail">البريد الإلكتروني لولي الأمر</label><input id="loginEmail" type="email" name="email" autocomplete="email" required></div>
              <div class="public-field full"><label for="loginPassword">كلمة المرور</label><input id="loginPassword" type="password" name="password" autocomplete="current-password" required></div>
            </div>
            <button type="submit" name="login" class="k-btn k-btn-gold">🚀 تسجيل الدخول</button>
          </form>
          <p style="text-align:center;color:#b9abd4;font-size:13px;margin:18px 0 0;">مسؤول المنصة؟ <a href="<?php echo h(BASE_PATH . '/admin/login.php'); ?>" style="color:#ffe99a;font-weight:900;">دخول لوحة الإدارة</a></p>
        </div>

        <div class="public-auth-form" id="registerPanel" <?php echo $shouldOpenRegister ? '' : 'hidden'; ?>>
          <?php if ($registerError): ?><div class="public-error">❌ <?php echo h($registerError); ?></div><?php endif; ?>
          <p class="public-pick-note">اختر شخصيتين مجانيتين ليرافقا الطفل. الشخصيات المدفوعة متاحة للتجربة في الديمو وتُفتح بعد الترقية.</p>
          <div class="public-pick-grid" id="registerCharacterGrid">
            <?php foreach ($charDataForJS as $c): ?>
              <button type="button" class="public-pick <?php echo $c['is_premium'] ? 'locked' : ''; ?>" data-pick-id="<?php echo (int)$c['id']; ?>" data-locked="<?php echo $c['is_premium'] ? '1' : '0'; ?>" style="--char-color:<?php echo h($c['color']); ?>">
                <?php if ($c['is_premium']): ?><small>🔒 مدفوعة</small><?php endif; ?>
                <span class="public-pick-media"><?php if (!empty($c['image'])): ?><img src="<?php echo h($c['image']); ?>" alt="<?php echo h($c['name']); ?>"><?php else: ?><?php echo h($c['icons'][0] ?? '✨'); ?><?php endif; ?></span>
                <strong><?php echo h($c['name']); ?></strong>
              </button>
            <?php endforeach; ?>
          </div>
          <div class="public-auth-form" id="registerErrorHint" hidden></div>
          <form method="POST" enctype="multipart/form-data" id="registerForm">
            <input type="hidden" name="character_1" id="character_1" value="<?php echo (int)($prefillChar ?: ($_POST['character_1'] ?? 0)); ?>">
            <input type="hidden" name="character_2" id="character_2" value="<?php echo (int)($_POST['character_2'] ?? 0); ?>">
            <div class="public-form-grid">
              <div class="public-field"><label for="childName">اسم الطفل</label><input id="childName" type="text" name="child_name" maxlength="100" value="<?php echo h($_POST['child_name'] ?? $prefillName); ?>" autocomplete="name" required></div>
              <div class="public-field"><label for="childAge">عمر الطفل</label><select id="childAge" name="child_age" required><option value="">اختر العمر</option><?php for ($a = 4; $a <= 12; $a++): ?><option value="<?php echo $a; ?>" <?php echo (($_POST['child_age'] ?? '') == $a) ? 'selected' : ''; ?>><?php echo $a; ?> سنوات</option><?php endfor; ?></select></div>
              <div class="public-field"><label for="parentName">اسم ولي الأمر</label><input id="parentName" type="text" name="parent_name" maxlength="100" value="<?php echo h($_POST['parent_name'] ?? ''); ?>" required></div>
              <div class="public-field"><label for="parentPhone">رقم واتساب ولي الأمر</label><input id="parentPhone" type="tel" name="parent_phone" maxlength="30" placeholder="مثال: 0599123456" value="<?php echo h($_POST['parent_phone'] ?? ''); ?>" required></div>
              <div class="public-field"><label for="registerEmail">البريد الإلكتروني</label><input id="registerEmail" type="email" name="email" maxlength="150" value="<?php echo h($_POST['email'] ?? ''); ?>" autocomplete="email" required></div>
              <div class="public-field"><label for="registerPassword">كلمة المرور</label><input id="registerPassword" type="password" name="password" minlength="6" autocomplete="new-password" required></div>
              <div class="public-field"><label for="confirmPassword">تأكيد كلمة المرور</label><input id="confirmPassword" type="password" name="confirm_password" minlength="6" autocomplete="new-password" required></div>
              <div class="public-field full public-photo-row">
                <span class="public-photo-preview" id="photoPreview">👤</span>
                <label for="childPhoto" style="flex:1;">صورة اختيارية للطفل<span style="display:block;color:#b9abd4;font-size:11px;font-weight:600;margin-top:3px;">JPG أو PNG أو WebP — حتى 4 ميجابايت</span><input id="childPhoto" type="file" name="child_photo" accept="image/jpeg,image/png,image/webp" style="margin-top:8px;padding:7px;"></label>
              </div>
            </div>
            <button type="submit" name="register" class="k-btn k-btn-gold">🌟 أنشئ حساب المغامرة</button>
          </form>
        </div>
      </div>
    </section>
  </main>

  <!-- مودال الشخصية -->
  <div class="public-modal" id="characterModal" role="dialog" aria-modal="true" aria-labelledby="modalCharacterName" aria-hidden="true">
    <div class="public-modal-card">
      <button type="button" class="public-modal-close" id="modalClose" aria-label="إغلاق">×</button>
      <div class="public-modal-visual" id="modalVisual"></div>
      <h2 id="modalCharacterName"></h2>
      <p id="modalCharacterTitle"></p>
      <div class="public-modal-trait" id="modalCharacterTrait"></div>
      <p id="modalCharacterQuote"></p>
      <div class="public-modal-icons" id="modalCharacterIcons" aria-label="رموز الشخصية"></div>
      <div class="public-modal-actions">
        <a class="k-btn k-btn-gold" id="modalDemoLink" href="<?php echo h(BASE_PATH . '/demo.php'); ?>">🎮 جرّب الشخصية</a>
        <button type="button" class="k-btn k-btn-ghost" id="modalRegisterLink" data-open-register>سجّل واختر</button>
      </div>
    </div>
  </div>
</div>

<script>
window.KIDORA_LANDING = <?php echo json_encode([
    'base' => BASE_PATH,
    'characters' => $charDataForJS,
    'prefillChar' => $prefillChar,
    'openRegister' => $shouldOpenRegister,
    'hasVideo' => $introVideo['mp4'] || $introVideo['webm'],
    'reducedMotion' => false,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="<?php echo h(BASE_PATH . '/assets/vendor/gsap/gsap.min.js'); ?>"></script>
<script src="<?php echo h(BASE_PATH . '/assets/vendor/gsap/ScrollTrigger.min.js'); ?>"></script>
<script src="<?php echo h(BASE_PATH . '/assets/js/landing.js'); ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
