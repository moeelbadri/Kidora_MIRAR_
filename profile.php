<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();

// ---------------- تعديل البيانات ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $name = trim($_POST['child_name'] ?? $child['name']);
    $age = (int)($_POST['child_age'] ?? $child['age']);
    $parentName = trim($_POST['parent_name'] ?? '');
    $parentPhone = trim($_POST['parent_phone'] ?? '');
    $pdo->prepare("UPDATE children SET name=?, age=?, parent_name=?, parent_phone=? WHERE id=?")
        ->execute([$name, $age, $parentName, $parentPhone, $child['id']]);
    $_SESSION['flash_profile'] = 'تم الحفظ بنجاح';
    header('Location: profile.php'); exit;
}

// ---------------- تبديل الرفيق (شخصية واحدة نشطة؛ المدفوعة تتطلب اشتراكاً) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_companion'])) {
    $premiumUnlocked = is_premium_active($pdo, $child['id']);
    $pick = (int)($_POST['set_companion'] ?? 0);
    $row = $pick ? get_character($pdo, $pick) : null;
    if ($row && (empty($row['is_premium']) || $premiumUnlocked)) {
        // الرفيق الجديد يصبح النشط؛ السابق يبقى في الخانة الثانية للتبديل السريع (🔄)
        $prev = (int)($child['active_character'] ?: $child['character_1']);
        $other = $prev && $prev !== $pick ? $prev : (int)($child['character_2'] ?: $child['character_1']);
        if ($other === $pick) {
            $alt = $pdo->prepare("SELECT id FROM characters WHERE id <> ? AND is_premium = 0 ORDER BY sort_order ASC LIMIT 1");
            $alt->execute([$pick]);
            $other = (int)$alt->fetchColumn();
        }
        $pdo->prepare("UPDATE children SET character_1=?, character_2=?, active_character=? WHERE id=?")->execute([$pick, $other ?: $pick, $pick, $child['id']]);
        $_SESSION['flash_profile'] = "صار {$row['name']} رفيقك الآن!";
    }
    header('Location: profile.php'); exit;
}

// ---------------- تغيير صورة الطفل ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_photo'])) {
    if (!empty($_FILES['child_photo']['name'])) {
        $saved = save_image_upload('child_photo', __DIR__ . '/uploads/photos');
        if ($saved) {
            $old = (string)($child['photo_path'] ?? '');
            if ($old !== '' && str_starts_with($old, 'uploads/photos/') && is_file(__DIR__ . '/' . $old)) @unlink(__DIR__ . '/' . $old);
            $pdo->prepare("UPDATE children SET photo_path=? WHERE id=?")->execute(['uploads/photos/' . basename($saved), $child['id']]);
            $_SESSION['flash_profile'] = 'صورتك الجديدة صارت في ملفك!';
        } else {
            $_SESSION['flash_profile'] = 'الصورة غير صالحة. استخدم JPG أو PNG أو WebP بحجم أقصى 4 ميجابايت.';
        }
    }
    header('Location: profile.php'); exit;
}

// ---------------- إرسال تحليل السلوك لواتساب ----------------
$flashWaLink = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_analysis'])) {
    $axisStmt = $pdo->prepare("SELECT axis, AVG(value) avg_v, COUNT(*) c FROM quiz_history WHERE child_id = ? GROUP BY axis");
    $axisStmt->execute([$child['id']]);
    $rows = $axisStmt->fetchAll();
    if ($rows) {
        $lines = array_map(fn($r) => "• {$r['axis']}: " . number_format($r['avg_v'],1) . " / 3", $rows);
        $msg = "تحليل سلوك {$child['name']} 📊 (حديث حتى الآن):\n" . implode("\n", $lines) . "\n\nإجمالي النقاط: {$child['points']} ⭐ | أيام المغامرة: {$child['ring_days']}/30 🎬\n— منصة Kidora";
        log_wa($pdo, $child['id'], 'analysis_report', $msg);
        $_SESSION['flash_wa_link'] = whatsapp_link($pdo, $msg, $child['parent_phone']);
    }
    header('Location: profile.php'); exit;
}

// ---------------- حذف رسمة من معرضي (الطفل يحذف رسوماته هو فقط) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_drawing'])) {
    $d = $pdo->prepare("SELECT * FROM drawings WHERE id = ? AND child_id = ?");
    $d->execute([(int)$_POST['delete_drawing'], $child['id']]);
    if ($row = $d->fetch()) {
        $file = __DIR__ . '/' . $row['image_path'];
        if (str_starts_with($row['image_path'], 'uploads/drawings/') && is_file($file)) @unlink($file);
        $pdo->prepare("DELETE FROM drawings WHERE id = ?")->execute([$row['id']]);
    }
    header('Location: profile.php#drawings'); exit;
}

$flashWaLink = $_SESSION['flash_wa_link'] ?? null;
unset($_SESSION['flash_wa_link']);
$flashProfile = $_SESSION['flash_profile'] ?? null;
unset($_SESSION['flash_profile']);

$myDrawings = $pdo->prepare("SELECT * FROM drawings WHERE child_id = ? ORDER BY id DESC LIMIT 12");
$myDrawings->execute([$child['id']]);
$myDrawings = $myDrawings->fetchAll();
$waShareBase = whatsapp_link($pdo, '', $child['parent_phone'] ?? '');

$premiumUnlocked = is_premium_active($pdo, $child['id']);
$allChars = selectable_characters($pdo, $premiumUnlocked);
$activeChar = active_character($pdo, $child);
$activeTheme = character_theme($activeChar);
$photoUrl = !empty($child['photo_path']) ? BASE_PATH . '/' . ltrim($child['photo_path'], '/') : null;
$myStories = $pdo->prepare("SELECT * FROM daily_stories WHERE child_id = ? ORDER BY created_at DESC");
$myStories->execute([$child['id']]);
$myStories = $myStories->fetchAll();

$axisStmt = $pdo->prepare("SELECT axis, AVG(value) avg_v FROM quiz_history WHERE child_id = ? GROUP BY axis");
$axisStmt->execute([$child['id']]);
$axisRows = $axisStmt->fetchAll();
$badges = json_decode_safe($child['badges_json'], []);

/* ============================================================
   مخطط السلوك الطفولي — أيقونات وألوان ومستويات
   ============================================================ */
if (!function_exists('pf_axis_style')) {
    function pf_axis_style(string $axis): array {
        $map = [
            'التعاطف'   => ['icon'=>'❤️','bg'=>'#FFE4EC','color'=>'#FF6FA5'],
            'الثقة'     => ['icon'=>'💪','bg'=>'#E3FBF8','color'=>'#2EC4B6'],
            'الأمان'    => ['icon'=>'🛡️','bg'=>'#E6F0FF','color'=>'#5B8DEF'],
            'الشجاعة'   => ['icon'=>'🦁','bg'=>'#FFF1D6','color'=>'#F5A623'],
            'الصدق'     => ['icon'=>'⭐','bg'=>'#FFF7D6','color'=>'#FFB13C'],
            'التعاون'   => ['icon'=>'🤝','bg'=>'#EEF7E5','color'=>'#7BC043'],
            'الصبر'     => ['icon'=>'🐢','bg'=>'#E3FBF8','color'=>'#3EB8A4'],
            'الإبداع'   => ['icon'=>'🎨','bg'=>'#F3E9FF','color'=>'#9B7BFF'],
            'المسؤولية' => ['icon'=>'🌟','bg'=>'#FFF7D6','color'=>'#FFB13C'],
            'الحدود'    => ['icon'=>'🚧','bg'=>'#FFEAD6','color'=>'#FF7A45'],
            'المشاعر'   => ['icon'=>'🌈','bg'=>'#F3E9FF','color'=>'#9B7BFF'],
            'الاحترام'  => ['icon'=>'🙏','bg'=>'#E6F0FF','color'=>'#5B8DEF'],
            'النظام'    => ['icon'=>'📋','bg'=>'#F3E9FF','color'=>'#7B6EFF'],
        ];
        foreach ($map as $k=>$v) { if (mb_strpos($axis, $k) !== false) return $v; }
        return ['icon'=>'✨','bg'=>'#EEEBFF','color'=>'#6C63FF'];
    }
}
if (!function_exists('pf_axis_level')) {
    function pf_axis_level(float $avg): string {
        if ($avg >= 2.5) return 'بطل خارق! 🦸';
        if ($avg >= 2.0) return 'رائع جداً! 🌟';
        if ($avg >= 1.5) return 'في تقدّم جميل! 🚀';
        if ($avg >= 1.0) return 'بداية الرحلة! 🌱';
        return 'خطوة أولى! 💫';
    }
}

$__pageTitle = 'ملفي الشخصي — Kidora';
$__pageLine = $flashProfile ?: "هذا ملفك يا {$child['name']}… انظر كل ما حققناه معاً! ⭐";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<style>
  .profile-drawings{ display:grid; grid-template-columns:repeat(auto-fill,minmax(170px,1fr)); gap:14px; margin-bottom:30px; }
  .profile-drawing{ overflow:hidden; }
  .profile-drawing img{ width:100%; aspect-ratio:4/3; object-fit:cover; display:block; background:#fff; }
  .profile-drawing-body{ padding:10px 12px 12px; }
  .profile-drawing-body b{ display:block; color:var(--ink); font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .profile-drawing-body small{ color:var(--ink-soft); font-weight:700; font-size:11px; }
  .profile-drawing-actions{ display:flex; gap:6px; margin-top:8px; }
  .profile-drawing-actions .btn{ padding:6px 10px; min-width:0; }
  .pf-hero{ position:relative; padding:28px; margin-bottom:24px; display:flex; gap:22px; align-items:center; flex-wrap:wrap; overflow:hidden; }
  .pf-photo-wrap{ position:relative; width:150px; height:150px; flex:0 0 150px; }
  .pf-photo{ display:grid; place-items:center; width:150px; height:150px; border-radius:50%; overflow:hidden; cursor:pointer; position:relative;
    background:linear-gradient(135deg,var(--violet),var(--pink)); border:5px solid #fff; box-shadow:0 16px 36px rgba(0,0,0,.25), 0 0 0 6px color-mix(in srgb, var(--theme-accent) 40%, transparent); }
  .pf-photo img{ width:100%; height:100%; object-fit:cover; }
  .pf-photo-empty{ display:grid; place-items:center; color:#fff; font-size:38px; text-align:center; line-height:1; }
  .pf-photo-empty small{ font-size:12px; font-weight:800; margin-top:4px; }
  .pf-photo-edit{ position:absolute; bottom:6px; inset-inline-start:6px; width:36px; height:36px; border-radius:50%; background:#fff; display:grid; place-items:center; font-size:16px; box-shadow:0 6px 14px rgba(0,0,0,.25); }
  .pf-verified{ position:absolute; top:4px; inset-inline-start:0; background:#2D6CDF; color:#fff; border-radius:50%; width:30px; height:30px; display:grid; place-items:center; font-size:14px; border:2px solid #fff; }
  .pf-hero-body{ flex:1; min-width:220px; }
  .pf-name{ margin:0; color:var(--ink); font-size:26px; }
  .pf-companion-badge{ width:96px; height:96px; border-radius:50%; display:grid; place-items:center; font-size:48px; overflow:hidden; background:linear-gradient(150deg, var(--card-color), #fff2); border:4px solid #fff; box-shadow:0 12px 26px rgba(0,0,0,.2); animation:companionFloat 3.6s ease-in-out infinite; }
  .pf-companion-badge img{ width:100%; height:100%; object-fit:cover; }
  .pf-companions{ grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:14px; margin-bottom:10px; }
  .pf-companion button{ width:100%; padding:12px 10px 14px; border-radius:22px; background:rgba(255,255,255,.96); border:3px solid transparent; text-align:center; cursor:pointer; transition:transform .2s, border-color .2s, box-shadow .2s; min-height:64px; }
  .pf-companion button:hover:not([disabled]){ transform:translateY(-4px); border-color:var(--card-color); box-shadow:0 14px 30px rgba(0,0,0,.2); }
  .pf-companion.is-active button{ border-color:var(--card-color); box-shadow:0 0 0 4px color-mix(in srgb, var(--card-color) 35%, transparent), 0 14px 30px rgba(0,0,0,.2); }
  .pf-companion.is-locked button{ opacity:.55; filter:grayscale(.6); cursor:not-allowed; }
  .pf-companion-media{ position:relative; display:grid; place-items:center; width:100%; aspect-ratio:1; border-radius:18px; overflow:hidden; font-size:54px; background:linear-gradient(150deg, var(--card-color), #fff2); }
  .pf-companion-media img{ width:100%; height:100%; object-fit:cover; }
  .pf-companion-check{ position:absolute; bottom:8px; inset-inline:8px; background:var(--card-color); color:#fff; border-radius:999px; font-size:13px; font-weight:900; padding:4px 8px; text-shadow:0 1px 2px rgba(0,0,0,.4); }
  .pf-companion-lock{ position:absolute; top:8px; inset-inline-end:8px; font-size:22px; }
  .pf-companion b{ display:block; margin-top:8px; color:var(--ink); font-size:16px; }
  .pf-companion small{ display:block; color:var(--ink-soft); font-size:12px; font-weight:700; margin-top:2px; }

  /* ============================================================
     مخطط السلوك الطفولي
     ============================================================ */
  .pf-chart{
    position:relative;
    padding:26px 22px 24px;
    border-radius:28px;
    background:linear-gradient(160deg,#FFF8E7 0%,#F0E9FF 100%);
    border:3px dashed #C9B8FF;
    overflow:hidden;
    max-width:640px;
    margin-bottom:14px;
    box-shadow:0 20px 50px rgba(108,99,255,.15);
  }
  .pf-chart::before{
    content:"";position:absolute;top:-50px;right:-50px;
    width:160px;height:160px;border-radius:50%;
    background:radial-gradient(circle,rgba(255,201,60,.45),transparent 70%);
    pointer-events:none;
  }
  .pf-chart::after{
    content:"";position:absolute;bottom:-40px;left:-40px;
    width:140px;height:140px;border-radius:50%;
    background:radial-gradient(circle,rgba(108,99,255,.3),transparent 70%);
    pointer-events:none;
  }
  .pf-chart-head{
    display:flex;align-items:center;gap:14px;
    margin-bottom:20px;position:relative;z-index:1;
  }
  .pf-chart-mascot{
    width:60px;height:60px;border-radius:50%;
    display:grid;place-items:center;font-size:34px;
    background:linear-gradient(135deg,#FFE07A,#FFB13C);
    box-shadow:0 10px 24px rgba(255,177,60,.5);
    animation:pfFloat 3s ease-in-out infinite;
    flex-shrink:0;
  }
  @keyframes pfFloat{
    0%,100%{transform:translateY(0) rotate(-4deg)}
    50%{transform:translateY(-6px) rotate(4deg)}
  }
  .pf-chart-head h3{margin:0;color:#3B2E6B;font-size:1.2rem;font-weight:900;}
  .pf-chart-head p{margin:3px 0 0;color:#7B6EA8;font-size:.85rem;font-weight:700;}

  .pf-axis{
    position:relative;z-index:1;
    background:#fff;
    border-radius:22px;
    padding:14px 16px;
    margin-bottom:12px;
    box-shadow:0 8px 22px rgba(108,99,255,.12);
    border:2px solid #EFE9FF;
    display:grid;
    grid-template-columns:auto 1fr;
    gap:14px;
    align-items:center;
    transition:transform .25s,box-shadow .25s;
  }
  .pf-axis:hover{
    transform:translateY(-3px);
    box-shadow:0 14px 30px rgba(108,99,255,.22);
  }
  .pf-axis-icon{
    width:52px;height:52px;border-radius:16px;
    display:grid;place-items:center;font-size:28px;
    background:var(--axis-bg,#EEEBFF);
    flex-shrink:0;
    box-shadow:inset 0 -3px 0 rgba(0,0,0,.06);
  }
  .pf-axis-info{min-width:0;}
  .pf-axis-name{
    display:flex;justify-content:space-between;align-items:center;
    gap:10px;margin-bottom:8px;
  }
  .pf-axis-name b{
    color:#3B2E6B;font-size:1rem;font-weight:900;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  }
  .pf-axis-stars{
    font-size:.9rem;letter-spacing:1px;white-space:nowrap;
    flex-shrink:0;
  }
  .pf-axis-bar{
    height:18px;border-radius:999px;
    background:#EFE9FF;overflow:hidden;position:relative;
    box-shadow:inset 0 2px 4px rgba(0,0,0,.07);
  }
  .pf-axis-fill{
    height:100%;border-radius:999px;
    background:linear-gradient(90deg,
      var(--axis-color,#6C63FF),
      color-mix(in srgb,var(--axis-color,#6C63FF) 60%,#fff));
    box-shadow:0 0 12px color-mix(in srgb,var(--axis-color,#6C63FF) 60%,transparent);
    transition:width 1.3s cubic-bezier(.34,1.56,.64,1);
    position:relative;
  }
  .pf-axis-fill::after{
    content:"";position:absolute;inset:0;
    background-image:repeating-linear-gradient(
      45deg,
      rgba(255,255,255,.28) 0 8px,
      transparent 8px 16px
    );
    border-radius:999px;
    animation:pfStripe 1.6s linear infinite;
  }
  @keyframes pfStripe{to{background-position:32px 0;}}
  .pf-axis-level{
    margin-top:8px;
    color:var(--axis-color,#6C63FF);
    font-size:.82rem;font-weight:900;
    display:flex;justify-content:space-between;align-items:center;gap:8px;
  }
  .pf-axis-num{
    color:#8E82B8;font-size:.75rem;font-weight:800;
    background:#F6F3FF;padding:2px 8px;border-radius:999px;
  }

  .pf-chart-summary{
    position:relative;z-index:1;
    margin-top:18px;
    padding:16px 18px;
    border-radius:22px;
    background:linear-gradient(135deg,#FFE07A,#FFB13C);
    color:#3B2E6B;
    text-align:center;
    box-shadow:0 12px 28px rgba(255,177,60,.45);
  }
  .pf-chart-summary .big{
    display:block;font-size:1.15rem;font-weight:900;margin-bottom:4px;
  }
  .pf-chart-summary .sub{
    display:block;font-size:.85rem;font-weight:800;opacity:.85;
  }
  .pf-chart-summary b{color:#7B3FAF;}

  .pf-chart-empty{
    position:relative;z-index:1;
    text-align:center;
    padding:30px 16px;
    color:#7B6EA8;
    font-weight:800;
    font-size:.95rem;
  }
  .pf-chart-empty .em{
    font-size:3.2rem;display:block;margin-bottom:10px;
    animation:pfFloat 2.4s ease-in-out infinite;
  }

  @media(max-width:500px){
    .pf-chart{padding:20px 14px 18px;border-radius:22px;}
    .pf-axis{padding:12px 12px;gap:10px;border-radius:18px;}
    .pf-axis-icon{width:44px;height:44px;font-size:24px;border-radius:14px;}
    .pf-axis-name b{font-size:.9rem;}
    .pf-axis-stars{font-size:.78rem;}
    .pf-chart-summary .big{font-size:1rem;}
    .pf-chart-summary .sub{font-size:.78rem;}
  }
</style>
<div class="page-body">
<main class="container" style="padding-top:26px;">
  <div class="section-head">
    <div class="eyebrow">بروفايل البطل</div>
    <h2 class="section-title">ملفي الشخصي</h2>
  </div>

  <div class="card pf-hero">
    <form method="POST" enctype="multipart/form-data" id="photoForm" class="pf-photo-wrap">
      <label class="pf-photo" title="غيّر صورتك">
        <?php if ($photoUrl): ?>
          <img src="<?php echo h($photoUrl); ?>" alt="<?php echo h($child['name']); ?>">
        <?php else: ?>
          <span class="pf-photo-empty">📷<small>أضف صورتك</small></span>
        <?php endif; ?>
        <input type="file" name="child_photo" accept="image/jpeg,image/png,image/webp" hidden onchange="document.getElementById('photoForm').submit()">
        <span class="pf-photo-edit">✏️</span>
      </label>
      <input type="hidden" name="save_photo" value="1">
      <?php if ($premiumUnlocked): ?><span class="pf-verified" title="حساب مشترك موثّق">✔️</span><?php endif; ?>
    </form>
    <div class="pf-hero-body">
      <h3 class="pf-name"><?php echo h($child['name']); ?> <?php echo $premiumUnlocked ? '<span style="color:#2D6CDF;font-size:14px;">✔️ مشترك موثّق</span>' : ''; ?></h3>
      <p style="color:var(--ink-soft);margin:4px 0;">العمر <?php echo (int)$child['age']; ?> سنوات · رفيقي <?php echo h($activeChar['name'] ?? ''); ?> من <?php echo h($activeTheme['world']); ?></p>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
        <span class="pill">⭐ <?php echo (int)$child['points']; ?> نقطة</span>
        <span class="pill" style="color:var(--mint);background:#E3FBF8;">🎬 <?php echo (int)$child["ring_days"]; ?>/30 قصة</span>
        <span class="pill" style="color:var(--violet);background:#EEEBFF;">🏅 <?php echo count($badges); ?> وسام</span>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
        <button class="btn btn-ghost btn-sm" type="button" onclick="document.getElementById('editBox').classList.toggle('hidden')">✏️ تعديل البيانات</button>
        <button class="btn btn-ghost btn-sm" type="button" onclick="document.querySelector('#photoForm input[type=file]').click()">📷 تغيير الصورة</button>
      </div>
    </div>
    <div class="pf-companion-badge" style="--card-color:<?php echo h($activeChar['color'] ?? '#6C63FF'); ?>;">
      <?php if (!empty($activeChar['image_path'])): ?><img src="<?php echo BASE_PATH . '/' . h($activeChar['image_path']); ?>" alt=""><?php else: ?><?php echo h(character_icons($activeChar)[0] ?? '✨'); ?><?php endif; ?>
    </div>
  </div>

  <div id="editBox" class="card hidden" style="padding:22px;margin-bottom:24px;">
    <h3 style="color:var(--ink);margin-top:0;">تعديل بيانات الحساب</h3>
    <form method="POST">
      <div class="field"><label>اسم الطفل</label><input type="text" name="child_name" value="<?php echo h($child['name']); ?>"></div>
      <div class="field"><label>عمر الطفل</label>
        <select name="child_age">
          <?php for ($a=6;$a<=12;$a++): ?><option value="<?php echo $a; ?>" <?php echo $a==$child['age']?'selected':''; ?>><?php echo $a; ?> سنوات</option><?php endfor; ?>
        </select>
      </div>
      <div class="field"><label>اسم ولي الأمر</label><input type="text" name="parent_name" value="<?php echo h($child['parent_name']); ?>"></div>
      <div class="field"><label>رقم واتساب ولي الأمر</label><input type="tel" name="parent_phone" value="<?php echo h($child['parent_phone']); ?>"></div>
      <div class="field"><label>صوت الرفيق</label>
        <select id="voicePicker"><option value="">الأفضل تلقائياً</option></select>
        <small style="color:var(--ink-soft);display:block;margin-top:4px;">الأصوات المتاحة تعتمد على جهازك. اختر ثم اضغط «جرّب».</small>
        <button type="button" class="btn btn-ghost btn-sm" style="margin-top:8px;" onclick="testVoice()">🔊 جرّب الصوت</button>
      </div>
      <button type="submit" name="save_profile" class="btn btn-primary">حفظ التعديلات</button>
    </form>
  </div>

  <h3 style="color:#fff;text-shadow:0 2px 8px rgba(0,0,0,.35);">رفيقي — اختر من يرافقك</h3>
  <p style="color:#D9D0FF;font-size:14px;margin-top:-6px;">اضغط على شخصية لتصير رفيقك فوراً. <?php if (!$premiumUnlocked): ?>الشخصيات المقفلة تُفتح مع <a href="subscriptions.php" style="color:var(--gold);font-weight:800;">الاشتراك المدفوع</a>.<?php endif; ?></p>
  <div class="characters-grid pf-companions">
    <?php foreach ($allChars as $c): $isActive = (int)$c['id'] === (int)($child['active_character'] ?: $child['character_1']); $locked = !empty($c['is_premium']) && !$premiumUnlocked; $th = character_theme($c); ?>
      <form method="POST" class="pf-companion <?php echo $isActive ? 'is-active' : ''; ?> <?php echo $locked ? 'is-locked' : ''; ?>" style="--card-color:<?php echo h($c['color']); ?>;">
        <button type="submit" name="set_companion" value="<?php echo (int)$c['id']; ?>" <?php echo $locked ? 'disabled' : ''; ?> aria-label="اختر <?php echo h($c['name']); ?>">
          <span class="pf-companion-media">
            <?php if (!empty($c['image_path'])): ?><img src="<?php echo BASE_PATH . '/' . h($c['image_path']); ?>" alt=""><?php else: ?><?php echo h(character_icons($c)[0] ?? '✨'); ?><?php endif; ?>
            <?php if ($isActive): ?><span class="pf-companion-check">✓ رفيقي</span><?php elseif ($locked): ?><span class="pf-companion-lock">🔒</span><?php endif; ?>
          </span>
          <b><?php echo h($c['name']); ?></b>
          <small><?php echo h($th['world']); ?> <?php echo h($th['sidekick']['icon']); ?></small>
        </button>
      </form>
    <?php endforeach; ?>
  </div>

  <h3 style="margin-top:34px;color:#fff;text-shadow:0 2px 8px rgba(0,0,0,.35);">🧭 رحلة نموي البطولية</h3>

  <?php if (!$axisRows): ?>
    <div class="pf-chart">
      <div class="pf-chart-head">
        <div class="pf-chart-mascot">🧭</div>
        <div>
          <h3>هنا ستكبر قواي!</h3>
          <p>أجب عن أسئلة التحليل لتبدأ الرحلة 🌱</p>
        </div>
      </div>
      <div class="pf-chart-empty">
        <span class="em">🌱</span>
        لا يوجد مخطط بعد — أكمل تحليل السلوك ليظهر نموك!
      </div>
    </div>
  <?php else:
    $sum = 0; $cnt = 0;
    foreach ($axisRows as $r) { $sum += (float)$r['avg_v']; $cnt++; }
    $overall = $cnt ? ($sum / $cnt) : 0;
    $overallPct = (int)round(($overall / 3) * 100);
    $overallLevel = pf_axis_level($overall);
  ?>
    <div class="pf-chart">
      <div class="pf-chart-head">
        <div class="pf-chart-mascot">🧭</div>
        <div>
          <h3>رحلة نموي البطولية</h3>
          <p>كل قوة تكبر معك خطوة بخطوة 🌟</p>
        </div>
      </div>

      <?php foreach ($axisRows as $row):
        $avg  = (float)$row['avg_v'];
        $pct  = (int)round(($avg / 3) * 100);
        $st   = pf_axis_style((string)$row['axis']);
        $full = max(0, min(3, (int)round($avg)));
        $stars = str_repeat('⭐', $full) . str_repeat('☆', 3 - $full);
        $level = pf_axis_level($avg);
      ?>
        <div class="pf-axis"
             style="--axis-bg:<?php echo h($st['bg']); ?>;--axis-color:<?php echo h($st['color']); ?>;">
          <div class="pf-axis-icon"><?php echo $st['icon']; ?></div>
          <div class="pf-axis-info">
            <div class="pf-axis-name">
              <b><?php echo h($row['axis']); ?></b>
              <span class="pf-axis-stars" aria-label="مستوى <?php echo $full; ?> من 3"><?php echo $stars; ?></span>
            </div>
            <div class="pf-axis-bar">
              <div class="pf-axis-fill" data-pct="<?php echo $pct; ?>" style="width:0%;"></div>
            </div>
            <div class="pf-axis-level">
              <?php echo $level; ?>
              <span class="pf-axis-num"><?php echo number_format($avg,1); ?> / 3</span>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="pf-chart-summary">
        <span class="big"><?php echo $overallLevel; ?></span>
        <span class="sub">أنت في <b><?php echo $overallPct; ?>%</b> من رحلتك — واصل التألق! 🚀</span>
      </div>
    </div>
  <?php endif; ?>

  <form method="POST"><button type="submit" name="send_analysis" class="btn btn-mint btn-sm" style="margin:14px 0 26px;">📲 إرسال نتيجة التحليل لولي الأمر عبر واتساب</button></form>

  <h3 style="color:#fff;text-shadow:0 2px 8px rgba(0,0,0,.35);">سجل قصصي اليومية (فيديو لكل يوم)</h3>
  <div class="reco-strip">
    <?php if (!$myStories): ?><p style="color:var(--ink-soft);">لم تُنجز أي قصة بعد، أكمل مهامك اليوم لتبدأ!</p><?php endif; ?>
    <?php foreach ($myStories as $i => $s): ?>
      <div class="reco-card">
        <div class="reco-cover">🎬</div>
        <div class="reco-body">
          <b>اليوم <?php echo (int)$s['day_index']; ?></b>
          <p style="font-size:12px;color:var(--ink-soft);"><?php echo h($s['title']); ?></p>
          <button class="btn btn-sm btn-primary" onclick="viewProfileStory(<?php echo $i; ?>)">مشاهدة</button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div id="profileStoryBox" style="margin-top:18px;"></div>

  <h3 id="drawings" style="margin-top:34px;color:#fff;text-shadow:0 2px 8px rgba(0,0,0,.35);">🖼️ رسوماتي <a href="<?php echo BASE_PATH; ?>/draw.php" class="btn btn-sm btn-gold" style="margin-right:10px;">🎨 ارسم لوحة جديدة</a></h3>
  <?php if (!$myDrawings): ?>
    <p style="color:#d9d0ff;">لا توجد لوحات بعد — <a href="<?php echo BASE_PATH; ?>/draw.php" style="color:var(--gold);font-weight:900;">افتح لوحتك</a> وارسم ما تشعر به اليوم.</p>
  <?php else: ?>
    <div class="profile-drawings">
      <?php foreach ($myDrawings as $d): $url = BASE_PATH . '/' . $d['image_path']; ?>
        <div class="profile-drawing card">
          <a href="<?php echo h($url); ?>" target="_blank" rel="noopener"><img src="<?php echo h($url); ?>" alt="<?php echo h($d['title']); ?>" loading="lazy"></a>
          <div class="profile-drawing-body">
            <b><?php echo h($d['title']); ?></b>
            <small><?php echo h(date('Y/m/d', strtotime($d['created_at']))); ?></small>
            <div class="profile-drawing-actions">
              <a class="btn btn-sm btn-ghost" href="<?php echo h($url); ?>" download>⬇️</a>
              <a class="btn btn-sm btn-mint" target="_blank" rel="noopener" href="<?php echo h($waShareBase . rawurlencode("🎨 لوحة «{$d['title']}» من ريشة {$child['name']} على Kidora:\n") . rawurlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $url)); ?>">📲</a>
              <form method="POST" onsubmit="return confirm('حذف هذه اللوحة نهائياً؟');" style="display:inline;"><button type="submit" name="delete_drawing" value="<?php echo (int)$d['id']; ?>" class="btn btn-sm btn-ghost" title="حذف">🗑️</button></form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
</div>
<footer class="site-footer">Kidora © 2026</footer>
<script>
  window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;
  const PROFILE_STORIES = <?php echo json_encode(array_map(fn($s)=>[
      'title'=>$s['title'], 'scenes'=>json_decode($s['scenes_json']), 'photo'=>$s['photo_path']? BASE_PATH.'/'.$s['photo_path'] : null
  ], $myStories), JSON_UNESCAPED_UNICODE); ?>;
  function viewProfileStory(i){ StoryPlayer.render(PROFILE_STORIES[i], 'profileStoryBox', {}); document.getElementById('profileStoryBox').scrollIntoView({behavior:'smooth'}); }

  // اختيار صوت الرفيق (الأصوات المتاحة على هذا الجهاز)
  function fillVoices(){
    const sel = document.getElementById('voicePicker'); if (!sel || !window.SoundEngine) return;
    const cur = SoundEngine.getPreferredVoice();
    sel.querySelectorAll('option:not([value=""])').forEach(o => o.remove());
    SoundEngine.listVoices().forEach(v => { const o = document.createElement('option'); o.value = v.name; o.textContent = v.name + ' (' + v.lang + ')'; if (v.name === cur) o.selected = true; sel.appendChild(o); });
    sel.onchange = () => SoundEngine.setPreferredVoice(sel.value);
  }
  function testVoice(){ if (window.Companion) Companion.say('مرحباً يا <?php echo h($child['name']); ?>! هكذا يبدو صوتي. هل يعجبك؟', { mood: 'wave' }); }
  document.addEventListener('DOMContentLoaded', fillVoices);
  if ('speechSynthesis' in window) window.speechSynthesis.addEventListener('voiceschanged', fillVoices);

  /* ============================================================
     تحريك شرائط مخطط السلوك عند الدخول
     ============================================================ */
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.pf-axis-fill').forEach(function(el, i){
      const pct = parseInt(el.dataset.pct || '0', 10);
      setTimeout(function(){
        el.style.width = pct + '%';
      }, 150 * i + 200);
    });
  });

  <?php if ($flashWaLink): ?> window.open(<?php echo json_encode($flashWaLink); ?>, '_blank'); <?php endif; ?>
  <?php if ($flashProfile): ?>
    document.addEventListener('DOMContentLoaded', function(){
      const wrap = document.getElementById('toastWrap');
      const el = document.createElement('div'); el.className='toast'; el.textContent=<?php echo json_encode($flashProfile, JSON_UNESCAPED_UNICODE); ?>;
      wrap.appendChild(el); setTimeout(()=>el.remove(),3200);
    });
  <?php endif; ?>
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
