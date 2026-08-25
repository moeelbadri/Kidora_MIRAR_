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
    header('Location: profile.php?saved=1'); exit;
}

// ---------------- تبديل الشخصيات (يتطلب اشتراكاً مدفوعاً لاختيار شخصية حصرية) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_characters'])) {
    $premiumUnlocked = is_premium_active($pdo, $child['id']);
    $c1 = (int)$_POST['character_1']; $c2 = (int)$_POST['character_2'];
    if ($c1 && $c2 && $c1 !== $c2) {
        $chk = $pdo->prepare("SELECT COUNT(*) c FROM characters WHERE id IN (?,?) AND is_premium = 1");
        $chk->execute([$c1, $c2]);
        if ((int)$chk->fetch()['c'] === 0 || $premiumUnlocked) {
            $active = in_array($child['active_character'], [$c1, $c2]) ? $child['active_character'] : $c1;
            $pdo->prepare("UPDATE children SET character_1=?, character_2=?, active_character=? WHERE id=?")->execute([$c1, $c2, $active, $child['id']]);
        }
    }
    header('Location: profile.php?saved=1'); exit;
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
$flashWaLink = $_SESSION['flash_wa_link'] ?? null;
unset($_SESSION['flash_wa_link']);

$premiumUnlocked = is_premium_active($pdo, $child['id']);
$allChars = selectable_characters($pdo, $premiumUnlocked);
$myChars = array_filter([get_character($pdo, $child['character_1']), get_character($pdo, $child['character_2'])]);
$myStories = $pdo->prepare("SELECT * FROM daily_stories WHERE child_id = ? ORDER BY created_at DESC");
$myStories->execute([$child['id']]);
$myStories = $myStories->fetchAll();

$axisStmt = $pdo->prepare("SELECT axis, AVG(value) avg_v FROM quiz_history WHERE child_id = ? GROUP BY axis");
$axisStmt->execute([$child['id']]);
$axisRows = $axisStmt->fetchAll();
$badges = json_decode_safe($child['badges_json'], []);

$__pageTitle = 'ملفي الشخصي — Kidora';
$__pageLine = "هاد ملفك... شايف كل اللي حققناه سوا؟ 🌈";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<div class="page-body">
<main class="container" style="padding-top:26px;">
  <div class="section-head">
    <div class="eyebrow">بروفايل البطل</div>
    <h2 class="section-title">ملفي الشخصي</h2>
  </div>

  <div class="card" style="padding:28px;margin-bottom:24px;display:flex;gap:20px;align-items:center;flex-wrap:wrap;">
    <div style="position:relative;width:84px;height:84px;">
      <div style="width:84px;height:84px;border-radius:50%;background:linear-gradient(135deg,var(--violet),var(--pink));display:flex;align-items:center;justify-content:center;font-size:36px;color:#fff;">👤</div>
      <?php if ($premiumUnlocked): ?>
        <span title="حساب مشترك موثّق" style="position:absolute;bottom:0;left:-4px;background:#2D6CDF;color:#fff;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:14px;border:2px solid #fff;">✔️</span>
      <?php endif; ?>
    </div>
    <div style="flex:1;min-width:200px;">
      <h3 style="margin:0;color:var(--ink);"><?php echo h($child['name']); ?> <?php echo $premiumUnlocked ? '<span style="color:#2D6CDF;font-size:14px;">✔️ مشترك موثّق</span>' : ''; ?></h3>
      <p style="color:var(--ink-soft);margin:4px 0;"><?php echo h($child['email']); ?> · العمر <?php echo (int)$child['age']; ?> سنوات</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
        <span class="pill">⭐ <?php echo (int)$child['points']; ?> نقطة</span>
        <span class="pill" style="color:var(--mint);background:#E3FBF8;">🎬 <?php echo (int)$child["ring_days"]; ?>/30 قصص للمغامرة الكبرى</span>
        <span class="pill" style="color:var(--violet);background:#EEEBFF;">🏅 <?php echo count($badges); ?> وسام</span>
      </div>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="document.getElementById('editBox').classList.toggle('hidden')">✏️ تعديل البيانات</button>
  </div>

  <div id="editBox" class="card hidden" style="padding:22px;margin-bottom:24px;">
    <h3 style="color:var(--ink);margin-top:0;">تعديل بيانات الحساب</h3>
    <form method="POST">
      <div class="field"><label>اسم الطفل</label><input type="text" name="child_name" value="<?php echo h($child['name']); ?>"></div>
      <div class="field"><label>عمر الطفل</label>
        <select name="child_age">
          <?php for ($a=4;$a<=12;$a++): ?><option value="<?php echo $a; ?>" <?php echo $a==$child['age']?'selected':''; ?>><?php echo $a; ?> سنوات</option><?php endfor; ?>
        </select>
      </div>
      <div class="field"><label>اسم ولي الأمر</label><input type="text" name="parent_name" value="<?php echo h($child['parent_name']); ?>"></div>
      <div class="field"><label>رقم واتساب ولي الأمر</label><input type="tel" name="parent_phone" value="<?php echo h($child['parent_phone']); ?>"></div>
      <button type="submit" name="save_profile" class="btn btn-primary">حفظ التعديلات</button>
    </form>
  </div>

  <h3 style="color:#fff;text-shadow:0 2px 8px rgba(0,0,0,.35);">شخصياتي المفضّلة</h3>
  <?php if (!$premiumUnlocked): ?>
    <p style="color:#D9D0FF;font-size:14px;">لديك حالياً وصول لشخصيتين من الباقة المجانية فقط. <a href="subscriptions.php" style="color:var(--gold);font-weight:800;">فعّل اشتراكاً مدفوعاً</a> لفتح باقي الشخصيات والتنقل بينها بحرية!</p>
  <?php endif; ?>
  <form method="POST">
    <div class="characters-grid" style="grid-template-columns:repeat(auto-fill,minmax(140px,1fr));">
      <?php foreach ($allChars as $c): $isMine = in_array($c['id'], [$child['character_1'],$child['character_2']]); ?>
        <div class="character-card <?php echo $isMine?'selected':''; ?>" data-id="<?php echo (int)$c['id']; ?>" style="--card-color:<?php echo h($c['color']); ?>;" onclick="pickProfileChar(this)">
          <div class="character-media" style="aspect-ratio:1/1;">
            <?php if (!empty($c['image_path'])): ?><img src="<?php echo h($c['image_path']); ?>"><?php else: ?>
              <div class="fallback-emoji" style="background:linear-gradient(150deg, <?php echo h($c['color']); ?>, #fff2);"><?php echo character_icons($c)[0] ?? '✨'; ?></div>
            <?php endif; ?>
          </div>
          <div class="name"><?php echo h($c['name']); ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="character_1" id="pc1" value="<?php echo (int)$child['character_1']; ?>">
    <input type="hidden" name="character_2" id="pc2" value="<?php echo (int)$child['character_2']; ?>">
    <button type="submit" name="save_characters" class="btn btn-primary btn-sm" style="margin-top:14px;">حفظ اختيار الشخصيات</button>
  </form>

  <h3 style="margin-top:34px;color:#fff;text-shadow:0 2px 8px rgba(0,0,0,.35);">مخطط تحليل السلوك الحقيقي</h3>
  <div class="card" style="padding:22px;max-width:560px;margin-bottom:10px;">
    <div class="chart-wrap">
      <?php if (!$axisRows): ?>
        <p style="color:var(--ink-soft);text-align:center;">أجب عن أسئلة التحليل ليظهر مخططك 📊</p>
      <?php else: foreach ($axisRows as $row): $pct=((float)$row['avg_v']/3)*100; ?>
        <div class="chart-row"><div><?php echo h($row['axis']); ?></div><div class="chart-bar-bg"><div class="chart-bar-fg" style="width:<?php echo $pct; ?>%;"></div></div><div style="font-weight:800;color:var(--violet);"><?php echo number_format($row['avg_v'],1); ?></div></div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <form method="POST"><button type="submit" name="send_analysis" class="btn btn-mint btn-sm" style="margin-bottom:26px;">📲 إرسال نتيجة التحليل لولي الأمر عبر واتساب</button></form>

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
</main>
</div>
<footer class="site-footer">Kidora © 2026</footer>
<script>
  window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;
  const PROFILE_STORIES = <?php echo json_encode(array_map(fn($s)=>[
      'title'=>$s['title'], 'scenes'=>json_decode($s['scenes_json']), 'photo'=>$s['photo_path']? BASE_PATH.'/'.$s['photo_path'] : null
  ], $myStories), JSON_UNESCAPED_UNICODE); ?>;
  function viewProfileStory(i){ StoryPlayer.render(PROFILE_STORIES[i], 'profileStoryBox', {}); document.getElementById('profileStoryBox').scrollIntoView({behavior:'smooth'}); }

  let picked = [<?php echo (int)$child['character_1']; ?>, <?php echo (int)$child['character_2']; ?>];
  function pickProfileChar(el){
    const id = parseInt(el.dataset.id,10);
    const idx = picked.indexOf(id);
    if (idx>-1){ picked.splice(idx,1); el.classList.remove('selected'); }
    else { if (picked.length>=2){ const first=picked.shift(); document.querySelector(`.character-card[data-id="${first}"]`)?.classList.remove('selected'); } picked.push(id); el.classList.add('selected'); }
    document.getElementById('pc1').value = picked[0]||''; document.getElementById('pc2').value = picked[1]||'';
  }
  <?php if ($flashWaLink): ?> window.open(<?php echo json_encode($flashWaLink); ?>, '_blank'); <?php endif; ?>
  <?php if (isset($_GET['saved'])): ?>
    document.addEventListener('DOMContentLoaded', function(){
      const wrap = document.getElementById('toastWrap');
      const el = document.createElement('div'); el.className='toast'; el.textContent='تم الحفظ بنجاح ✅';
      wrap.appendChild(el); setTimeout(()=>el.remove(),3000);
    });
  <?php endif; ?>
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
