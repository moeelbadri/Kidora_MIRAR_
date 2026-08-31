<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();

// القصص اليومية التي لم تُستخدم بعد في أي مغامرة كبرى
$usedIds = [];
$g = $pdo->prepare("SELECT story_ids_json FROM grand_stories WHERE child_id = ?");
$g->execute([$child['id']]);
foreach ($g->fetchAll() as $row) { $usedIds = array_merge($usedIds, json_decode_safe($row['story_ids_json'], [])); }

$allStories = $pdo->prepare("SELECT * FROM daily_stories WHERE child_id = ? ORDER BY created_at ASC");
$allStories->execute([$child['id']]);
$allStories = $allStories->fetchAll();
$pending = array_values(array_filter($allStories, fn($s) => !in_array($s['id'], $usedIds)));

const GRAND_STORY_DAYS = 30;

// ---------------- دمج المغامرة الكبرى ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['build_grand']) && count($pending) >= GRAND_STORY_DAYS) {
    $chosen = array_slice($pending, 0, GRAND_STORY_DAYS);

    $myChars = array_filter([get_character($pdo, $child['character_1']), get_character($pdo, $child['character_2'])]);
    $companions = implode(' و', array_map(fn($c) => $c['name'], $myChars)) ?: 'رفاقه';

    // المغامرة الكبرى تُبنى من إنجاز الشهر الحقيقي، لا من دمج القصص فقط
    $fromDay = date('Y-m-d', strtotime($chosen[0]['created_at']));
    $toDay   = date('Y-m-d', strtotime(end($chosen)['created_at']));
    $summary = child_achievement_summary($pdo, (int)$child['id'], $fromDay, $toDay);
    $scenes  = grand_story_scenes($child, $chosen, $summary, $companions);

    $countExisting = $pdo->prepare("SELECT COUNT(*) c FROM grand_stories WHERE child_id = ?"); $countExisting->execute([$child['id']]);
    $num = (int)$countExisting->fetch()['c'] + 1;

    $ins = $pdo->prepare("INSERT INTO grand_stories (child_id, title, scenes_json, story_ids_json) VALUES (?,?,?,?)");
    $ins->execute([$child['id'], "مغامرة {$child['name']} الكبرى #{$num}", json_encode($scenes, JSON_UNESCAPED_UNICODE), json_encode(array_column($chosen,'id'))]);

    $_SESSION['flash_toast'] = '🎉 مغامرتك الكبرى جاهزة! شوف رحلة الشهر كامل';
    header('Location: grand-story.php'); exit;
}

$myGrand = $pdo->prepare("SELECT * FROM grand_stories WHERE child_id = ? ORDER BY created_at DESC");
$myGrand->execute([$child['id']]);
$myGrand = $myGrand->fetchAll();

// صورة الطفل من إحدى القصص التي دخلت المغامرة الأحدث
$latestPhoto = null;
if ($myGrand) {
    $ids = json_decode_safe($myGrand[0]['story_ids_json'], []);
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $q = $pdo->prepare("SELECT photo_path FROM daily_stories WHERE id IN ($ph) AND photo_path IS NOT NULL LIMIT 1");
        $q->execute($ids);
        $r = $q->fetch();
        $latestPhoto = $r ? $r['photo_path'] : null;
    }
}

$flashToast = $_SESSION['flash_toast'] ?? null;
unset($_SESSION['flash_toast']);

$readyToBuild = count($pending) >= GRAND_STORY_DAYS;
$__pageTitle = 'مغامرتي الكبرى — Kidora';
$__pageLine = $readyToBuild ? "وصلنا لثلاثين قصة! يلا ندمجهم بمغامرة فخمة 🏆" : "كل يوم قصة بتقرّبنا من مغامرتنا الكبرى 🎬";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<div class="page-body">
<main class="container" style="padding-top:26px;">
  <div class="section-head">
    <div class="eyebrow">كل <?php echo GRAND_STORY_DAYS; ?> يوم</div>
    <h2 class="section-title">مغامرتي الكبرى</h2>
    <p class="section-sub">بعد إتمام <?php echo GRAND_STORY_DAYS; ?> قصة يومية، تُبنى مغامرة واحدة فخمة من إنجازك الحقيقي خلال الشهر: مهامك، مجالاتك الأقوى، أبطال التاريخ الذين قابلتهم، وتقدّم تحليلك.</p>
  </div>

  <div class="card" style="max-width:520px;margin:0 auto 10px;padding:18px;">
    <div class="chart-bar-bg" style="height:22px;"><div class="chart-bar-fg" style="width:<?php echo min(100, count($pending)/GRAND_STORY_DAYS*100); ?>%;"></div></div>
  </div>
  <p style="text-align:center;color:var(--ink-soft);"><?php echo count($pending); ?> / <?php echo GRAND_STORY_DAYS; ?> قصة يومية جاهزة للدمج</p>

  <?php if ($readyToBuild): ?>
    <form method="POST" style="text-align:center;margin:18px 0;">
      <button type="submit" name="build_grand" class="btn btn-gold">🎬 ابنِ المغامرة الكبرى الآن</button>
    </form>
  <?php endif; ?>

  <?php if ($myGrand): $latest = $myGrand[0]; ?>
    <div id="grandStoryBox" style="margin-top:20px;"></div>
    <script>
      StoryPlayer.render({
        title: <?php echo json_encode($latest['title'], JSON_UNESCAPED_UNICODE); ?>,
        scenes: <?php echo $latest['scenes_json']; ?>,
        photo: <?php echo json_encode($latestPhoto ? BASE_PATH.'/'.$latestPhoto : null); ?>
      }, 'grandStoryBox', { badge: '🏆 مغامرة شهر كامل — مبنية من إنجازك الحقيقي' });
    </script>

    <?php if (count($myGrand) > 1): ?>
      <h3 style="margin-top:36px;color:var(--ink);">مغامراتي الكبرى السابقة</h3>
      <div class="reco-strip">
        <?php foreach (array_slice($myGrand,1) as $g): ?>
          <div class="reco-card">
            <div class="reco-cover">🎥</div>
            <div class="reco-body"><b><?php echo h($g['title']); ?></b>
              <p style="font-size:12px;color:var(--ink-soft);"><?php echo date('Y-m-d', strtotime($g['created_at'])); ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</main>
</div>
<footer class="site-footer">Kidora © 2026</footer>
<script>
  window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;
  <?php if ($flashToast): ?>
    document.addEventListener('DOMContentLoaded', function(){
      const wrap = document.getElementById('toastWrap');
      const el = document.createElement('div'); el.className='toast'; el.textContent = <?php echo json_encode($flashToast, JSON_UNESCAPED_UNICODE); ?>;
      wrap.appendChild(el); setTimeout(()=>el.remove(), 5000);
    });
  <?php endif; ?>
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
