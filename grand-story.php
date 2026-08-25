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

// ---------------- دمج المغامرة الكبرى ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['build_grand']) && count($pending) >= 30) {
    $chosen = array_slice($pending, 0, 10);
    $scenes = [['caption' => "تبدأ اليوم مغامرة {$child['name']} الكبرى... رحلة عشرة أيام من الشجاعة والتعلّم! 🌟", 'grad' => '#1B1035,#6C63FF']];
    $photo = null;
    foreach ($chosen as $s) {
        $sc = json_decode($s['scenes_json'], true);
        if ($sc) { $scenes[] = $sc[0]; $scenes[] = end($sc); }
        if (!$photo && $s['photo_path']) $photo = $s['photo_path'];
    }
    $scenes[] = ['caption' => "وهكذا أصبح {$child['name']} بطلاً حقيقياً! نهاية سعيدة لبداية مغامرات جديدة 🏆", 'grad' => '#FF7A50,#FFC93C'];

    $countExisting = $pdo->prepare("SELECT COUNT(*) c FROM grand_stories WHERE child_id = ?"); $countExisting->execute([$child['id']]);
    $num = (int)$countExisting->fetch()['c'] + 1;

    $ins = $pdo->prepare("INSERT INTO grand_stories (child_id, title, scenes_json, story_ids_json) VALUES (?,?,?,?)");
    $ins->execute([$child['id'], "مغامرة {$child['name']} الكبرى #{$num}", json_encode($scenes, JSON_UNESCAPED_UNICODE), json_encode(array_column($chosen,'id'))]);

    header('Location: grand-story.php'); exit;
}

$myGrand = $pdo->prepare("SELECT * FROM grand_stories WHERE child_id = ? ORDER BY created_at DESC");
$myGrand->execute([$child['id']]);
$myGrand = $myGrand->fetchAll();

$__pageTitle = 'مغامرتي الكبرى — Kidora';
$__pageLine = count($pending) >= 30 ? "وصلنا لثلاثين قصة! يلا ندمجهم بمغامرة فخمة 🏆" : "كل يوم قصة بتقرّبنا من مغامرتنا الكبرى 🎬";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<div class="page-body">
<main class="container" style="padding-top:26px;">
  <div class="section-head">
    <div class="eyebrow">كل 30 يوم</div>
    <h2 class="section-title">مغامرتي الكبرى</h2>
    <p class="section-sub">بعد إتمام 30 قصة يومية، تُدمج جميعها في مغامرة واحدة فخمة يمكن مشاهدتها ومشاركتها وتنزيلها كفيديو.</p>
  </div>

  <div class="card" style="max-width:520px;margin:0 auto 10px;padding:18px;">
    <div class="chart-bar-bg" style="height:22px;"><div class="chart-bar-fg" style="width:<?php echo min(100, count($pending)/30*100); ?>%;"></div></div>
  </div>
  <p style="text-align:center;color:var(--ink-soft);"><?php echo count($pending); ?> / 30 قصة يومية جاهزة للدمج</p>

  <?php if (count($pending) >= 30): ?>
    <form method="POST" style="text-align:center;margin:18px 0;">
      <button type="submit" name="build_grand" class="btn btn-gold">🎬 ادمج المغامرة الكبرى الآن</button>
    </form>
  <?php endif; ?>

  <?php if ($myGrand): $latest = $myGrand[0]; ?>
    <div id="grandStoryBox" style="margin-top:20px;"></div>
    <script>
      StoryPlayer.render({
        title: <?php echo json_encode($latest['title'], JSON_UNESCAPED_UNICODE); ?>,
        scenes: <?php echo $latest['scenes_json']; ?>
      }, 'grandStoryBox', {});
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
<script>window.KIDAURA_PAGE_LINE = <?php echo json_encode($__pageLine, JSON_UNESCAPED_UNICODE); ?>;</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
