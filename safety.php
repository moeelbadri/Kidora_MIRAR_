<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();

// جلب كل محتوى الحماية المناسب لعمر الطفل
$stmt = $pdo->prepare("SELECT * FROM safety_content WHERE age_min <= ? AND age_max >= ? ORDER BY id");
$stmt->execute([$child['age'], $child['age']]);
$allItems = $stmt->fetchAll();

// تصنيف المحتوى حسب النوع
$videoItems = array_filter($allItems, fn($i) => $i['type'] === 'video');
$gameItems  = array_filter($allItems, fn($i) => $i['type'] === 'game');

// أخذ أول 4 فيديوهات للمراحل الأساسية (مع إعادة ترقيم المفاتيح)
$stagedVideos = array_values($videoItems); // إعادة ترقيم من 0
$extraVideos = array_slice($stagedVideos, 4); // الباقي بعد الأربعة
$mainVideos = array_slice($stagedVideos, 0, 4); // الأربعة الأولى

// تعريف المراحل الأربع الأساسية
$stages = [
    [
        'id' => 1,
        'title' => 'جسدي ملكي',
        'icon' => '🛡️',
        'description' => 'تعلّم أعضاء جسدك الخاصة التي لا يجوز لأحد لمسها.',
        'video' => $mainVideos[0] ?? null,
    ],
    [
        'id' => 2,
        'title' => 'المسافة الآمنة',
        'icon' => '📏',
        'description' => 'تدرب على الحفاظ على مسافة آمنة مع الآخرين.',
        'video' => $mainVideos[1] ?? null,
    ],
    [
        'id' => 3,
        'title' => 'كلمات السر',
        'icon' => '🔐',
        'description' => 'تعلّم كيفية اختيار كلمة سر قوية وآمنة.',
        'video' => $mainVideos[2] ?? null,
    ],
    [
        'id' => 4,
        'title' => 'الإبلاغ عن التحرش',
        'icon' => '📢',
        'description' => 'تدرب على التصرف الصحيح عند التعرض للتحرش.',
        'video' => $mainVideos[3] ?? null,
    ],
];

$__pageTitle = 'قسم الحماية — Kidora';
$__pageLine = "حماية نفسك أهم مهارة يا بطل 🛡️";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<style>
  /* ===== تصميم خاص بصفحة الحماية ===== */
  .safety-page {
    background: linear-gradient(135deg, #0a061a 0%, #1a1040 100%);
    color: #f1f5f9;
    min-height: 100vh;
    padding: 2rem 1rem 4rem;
  }

  .safety-container {
    max-width: 1000px;
    margin: 0 auto;
  }

  /* شخصية المرشدة */
  .safety-guide {
    display: flex;
    align-items: center;
    gap: 1.2rem;
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(10px);
    border-radius: 30px;
    padding: 1.2rem 1.8rem;
    margin-bottom: 2.5rem;
    border: 1px solid rgba(255,255,255,0.12);
  }
  .safety-guide-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(145deg, #f5a623, #ffc93c);
    display: grid;
    place-items: center;
    font-size: 48px;
    flex-shrink: 0;
    box-shadow: 0 10px 30px rgba(255,201,60,0.3);
  }
  .safety-guide-bubble {
    font-size: 1.1rem;
    line-height: 1.8;
    font-weight: 500;
  }
  .safety-guide-name {
    display: block;
    color: #ffc93c;
    font-weight: 900;
    font-size: 1rem;
    margin-bottom: 0.25rem;
  }

  /* شريط التقدم */
  .progress-bar {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    margin: 2rem 0 2.5rem;
    padding: 0 0.5rem;
  }
  .progress-step {
    flex: 1;
    text-align: center;
    font-size: 0.8rem;
    color: #b9abd4;
    font-weight: 700;
    transition: all 0.3s;
    position: relative;
  }
  .progress-step .step-circle {
    display: block;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
    border: 2px solid rgba(255,255,255,0.2);
    margin: 0 auto 8px;
    line-height: 40px;
    font-size: 1.2rem;
    transition: all 0.4s;
  }
  .progress-step.active .step-circle {
    background: #ffc93c;
    border-color: #ffc93c;
    color: #241645;
    box-shadow: 0 0 20px rgba(255,201,60,0.4);
  }
  .progress-step.completed .step-circle {
    background: #2ec4b6;
    border-color: #2ec4b6;
    color: #fff;
  }
  .progress-step .step-label {
    display: block;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  /* بطاقات المراحل */
  .stage-card {
    background: rgba(255,255,255,0.06);
    border-radius: 30px;
    padding: 2rem;
    margin-bottom: 2rem;
    border: 1px solid rgba(255,255,255,0.08);
    backdrop-filter: blur(5px);
    transition: opacity 0.5s, transform 0.5s;
  }
  .stage-card.hidden {
    display: none;
  }
  .stage-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
  }
  .stage-icon {
    font-size: 2.8rem;
    background: rgba(255,255,255,0.1);
    width: 70px;
    height: 70px;
    border-radius: 20px;
    display: grid;
    place-items: center;
  }
  .stage-title {
    font-family: var(--font-display);
    font-size: 1.8rem;
    margin: 0;
    color: #fff;
  }
  .stage-desc {
    color: #d9d0ff;
    font-size: 1rem;
    margin-bottom: 1.5rem;
  }

  /* محتوى الفيديو/القصة */
  .video-story {
    background: rgba(0,0,0,0.3);
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border-left: 4px solid #ffc93c;
  }
  .video-story h4 {
    color: #ffc93c;
    margin-top: 0;
  }
  .video-story p {
    color: #e0d8f0;
    line-height: 1.8;
  }
  .video-story .btn-play {
    background: #ffc93c;
    border: none;
    color: #241645;
    font-weight: 900;
    padding: 0.5rem 1.5rem;
    border-radius: 50px;
    cursor: pointer;
    transition: 0.2s;
  }
  .video-story .btn-play:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(255,201,60,0.4);
  }

  /* الأنشطة التفاعلية */
  .activity-area {
    min-height: 250px;
    padding: 1rem 0;
  }

  /* نشاط الأعضاء الخاصة */
  .body-zone {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 1.5rem;
    margin: 1.5rem 0;
  }
  .body-part {
    display: flex;
    flex-direction: column;
    align-items: center;
    background: rgba(255,255,255,0.05);
    border-radius: 20px;
    padding: 1rem;
    width: 100px;
    cursor: pointer;
    border: 3px solid transparent;
    transition: 0.3s;
    user-select: none;
  }
  .body-part:hover {
    transform: translateY(-5px);
    background: rgba(255,255,255,0.1);
  }
  .body-part.selected-safe {
    border-color: #2ec4b6;
    background: rgba(46,196,182,0.15);
  }
  .body-part.selected-unsafe {
    border-color: #ff6b6b;
    background: rgba(255,107,107,0.15);
  }
  .body-part .part-icon {
    font-size: 3rem;
  }
  .body-part .part-label {
    font-size: 0.8rem;
    margin-top: 0.5rem;
    color: #d9d0ff;
    font-weight: 700;
  }
  .body-part.unsafe-part {
    border-color: #ff6b6b;
  }
  .body-part.safe-part {
    border-color: #2ec4b6;
  }
  .body-part.correct {
    border-color: #ffc93c;
    box-shadow: 0 0 25px rgba(255,201,60,0.3);
  }

  /* نشاط المسافة الآمنة */
  .distance-game {
    position: relative;
    height: 220px;
    background: radial-gradient(circle at 20% 30%, #1a1040, #0a061a);
    border-radius: 30px;
    overflow: hidden;
    touch-action: none;
  }
  .distance-person {
    position: absolute;
    bottom: 20px;
    left: 20%;
    font-size: 4rem;
    transition: left 0.1s;
    cursor: grab;
    user-select: none;
  }
  .distance-person.dragging {
    cursor: grabbing;
  }
  .distance-other {
    position: absolute;
    bottom: 20px;
    right: 15%;
    font-size: 3rem;
    opacity: 0.7;
  }
  .distance-safe-zone {
    position: absolute;
    bottom: 0;
    left: 30%;
    width: 40%;
    height: 100%;
    border: 3px dashed rgba(46,196,182,0.4);
    border-radius: 30px 30px 0 0;
    pointer-events: none;
  }
  .distance-feedback {
    text-align: center;
    margin-top: 1rem;
    font-weight: 700;
    font-size: 1.1rem;
    min-height: 2.5rem;
  }

  /* نشاط كلمة السر */
  .password-game input {
    background: rgba(255,255,255,0.1);
    border: 2px solid rgba(255,255,255,0.15);
    border-radius: 15px;
    padding: 0.8rem 1.2rem;
    color: #fff;
    font-size: 1.2rem;
    width: 100%;
    max-width: 350px;
    margin: 0.5rem 0;
  }
  .password-game input:focus {
    outline: none;
    border-color: #ffc93c;
  }
  .password-rules {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin: 1rem 0;
    justify-content: center;
  }
  .password-rules .rule {
    background: rgba(255,255,255,0.05);
    padding: 0.4rem 1rem;
    border-radius: 50px;
    font-size: 0.9rem;
    color: #b9abd4;
    border: 1px solid transparent;
  }
  .rule.valid {
    border-color: #2ec4b6;
    color: #8ff0d4;
  }

  /* نشاط الإبلاغ */
  .scenario-card {
    background: rgba(255,255,255,0.05);
    border-radius: 20px;
    padding: 1.5rem;
    margin: 1rem 0;
    border: 1px solid rgba(255,255,255,0.08);
  }
  .scenario-card .choices {
    display: flex;
    flex-wrap: wrap;
    gap: 0.8rem;
    margin-top: 1rem;
    justify-content: center;
  }
  .scenario-card .choice-btn {
    background: rgba(255,255,255,0.08);
    border: 2px solid transparent;
    border-radius: 50px;
    padding: 0.6rem 1.5rem;
    color: #fff;
    font-weight: 700;
    cursor: pointer;
    transition: 0.3s;
  }
  .choice-btn:hover {
    background: rgba(255,255,255,0.15);
  }
  .choice-btn.correct {
    border-color: #2ec4b6;
    background: rgba(46,196,182,0.2);
  }
  .choice-btn.wrong {
    border-color: #ff6b6b;
    background: rgba(255,107,107,0.2);
  }

  /* أزرار التحكم */
  .btn-next {
    background: linear-gradient(135deg, #ffc93c, #f5a623);
    border: none;
    color: #241645;
    font-weight: 900;
    padding: 0.8rem 2.5rem;
    border-radius: 50px;
    font-size: 1rem;
    cursor: pointer;
    transition: 0.3s;
    margin-top: 1.5rem;
    box-shadow: 0 8px 25px rgba(255,201,60,0.25);
  }
  .btn-next:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 35px rgba(255,201,60,0.4);
  }
  .btn-next:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
  }

  /* نافذة الفيديو المنبثقة (للمشاهدة) */
  .modal-video {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.85);
    backdrop-filter: blur(10px);
    z-index: 999;
    justify-content: center;
    align-items: center;
  }
  .modal-video.open {
    display: flex;
  }
  .modal-video-content {
    background: #1a1040;
    border-radius: 30px;
    max-width: 700px;
    width: 90%;
    padding: 2rem;
    position: relative;
    border: 1px solid rgba(255,255,255,0.1);
  }
  .modal-video-content .close-modal {
    position: absolute;
    top: 10px;
    right: 20px;
    font-size: 2rem;
    color: #fff;
    cursor: pointer;
    background: none;
    border: none;
  }
  .modal-video-content .video-player {
    width: 100%;
    aspect-ratio: 16/9;
    background: #000;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #b9abd4;
    font-size: 1.2rem;
  }

  /* رسائل التشجيع */
  .encourage {
    display: inline-block;
    background: rgba(255,201,60,0.15);
    color: #ffc93c;
    padding: 0.3rem 1.2rem;
    border-radius: 50px;
    font-weight: 900;
    margin: 0.5rem 0;
  }

  /* تذييل */
  .safety-footer {
    text-align: center;
    color: #b9abd4;
    padding: 2rem 0 1rem;
    font-size: 0.9rem;
    border-top: 1px solid rgba(255,255,255,0.05);
    margin-top: 3rem;
  }

  /* قسم المحتوى الإضافي (جميع المحتويات الأخرى) */
  .extra-content {
    margin-top: 3rem;
    border-top: 2px dashed rgba(255,201,60,0.3);
    padding-top: 2rem;
  }
  .extra-content h2 {
    font-size: 2rem;
    color: #ffc93c;
    text-align: center;
    margin-bottom: 2rem;
  }
  .extra-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1.5rem;
  }
  .extra-item {
    background: rgba(255,255,255,0.06);
    border-radius: 20px;
    padding: 1.2rem;
    border: 1px solid rgba(255,255,255,0.08);
    transition: 0.3s;
  }
  .extra-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.4);
  }
  .extra-item .item-icon {
    font-size: 2.5rem;
    display: block;
    margin-bottom: 0.5rem;
  }
  .extra-item h4 {
    color: #fff;
    margin: 0.2rem 0;
  }
  .extra-item p {
    color: #d9d0ff;
    font-size: 0.9rem;
    line-height: 1.6;
  }
  .extra-item .btn-play-sm {
    background: rgba(255,201,60,0.2);
    border: 1px solid #ffc93c;
    color: #ffc93c;
    padding: 0.3rem 1.2rem;
    border-radius: 50px;
    cursor: pointer;
    font-weight: 700;
    transition: 0.2s;
    margin-top: 0.5rem;
  }
  .extra-item .btn-play-sm:hover {
    background: #ffc93c;
    color: #241645;
  }

  /* وسائط */
  @media (max-width: 600px) {
    .safety-guide { flex-direction: column; text-align: center; }
    .progress-step .step-label { display: none; }
    .body-part { width: 70px; }
    .body-part .part-icon { font-size: 2rem; }
    .stage-card { padding: 1.2rem; }
  }
</style>

<div class="safety-page">
  <div class="safety-container">

    <!-- شخصية المرشدة -->
    <div class="safety-guide" id="safetyGuide">
      <div class="safety-guide-avatar">🦉</div>
      <div class="safety-guide-bubble">
        <span class="safety-guide-name">رفيقتك الحكيمة</span>
        <span id="guideMessage">مرحباً بطل! 🌟 جهز نفسك لرحلة ممتعة تتعلم فيها كيف تحمي نفسك. كل مرحلة فيها فيديو ونشاط تشويقي. هيا بنا!</span>
      </div>
    </div>

    <!-- شريط التقدم -->
    <div class="progress-bar" id="progressBar">
      <?php foreach ($stages as $index => $stage): ?>
        <div class="progress-step <?php echo $index === 0 ? 'active' : ''; ?>" data-step="<?php echo $index; ?>">
          <span class="step-circle"><?php echo $stage['icon']; ?></span>
          <span class="step-label"><?php echo $stage['title']; ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- حاوية المراحل -->
    <div id="stagesContainer">
      <?php foreach ($stages as $index => $stage): ?>
        <div class="stage-card <?php echo $index === 0 ? '' : 'hidden'; ?>" data-stage="<?php echo $index; ?>">
          <div class="stage-header">
            <div class="stage-icon"><?php echo $stage['icon']; ?></div>
            <h2 class="stage-title"><?php echo $stage['title']; ?></h2>
          </div>
          <p class="stage-desc"><?php echo $stage['description']; ?></p>

          <!-- عرض الفيديو/القصة من قاعدة البيانات إن وجد -->
          <?php if ($stage['video']): ?>
            <div class="video-story">
              <h4>🎬 <?php echo h($stage['video']['title']); ?></h4>
              <p><?php echo h($stage['video']['description']); ?></p>
              <button class="btn-play" data-youtube="<?php echo h($stage['video']['youtube_id']); ?>">▶ شاهد القصة</button>
            </div>
          <?php else: ?>
            <div class="video-story" style="border-left-color: #6C63FF;">
              <h4>📖 قصة توعوية</h4>
              <p>في هذه المرحلة سنتعلم معاً كيفية حماية أنفسنا. استمع جيداً ثم شارك في النشاط.</p>
              <button class="btn-play" id="playStoryBtn<?php echo $index; ?>">▶ استمع للقصة</button>
            </div>
          <?php endif; ?>

          <!-- منطقة النشاط التفاعلي -->
          <div class="activity-area" id="activityArea<?php echo $index; ?>">
            <!-- سيتم ملؤها بواسطة الجافا سكريبت حسب نوع المرحلة -->
          </div>

          <!-- زر التالي (يظهر بعد إكمال النشاط) -->
          <button class="btn-next" id="nextBtn<?php echo $index; ?>" disabled>
            <?php echo $index === count($stages) - 1 ? '🏆 أنهِ الرحلة' : '➡️ المرحلة التالية'; ?>
          </button>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- شاشة النهاية (تظهر بعد آخر مرحلة) -->
    <div class="stage-card hidden" id="finalScreen">
      <div style="text-align:center; padding:2rem 1rem;">
        <div style="font-size:5rem;">🏆</div>
        <h2 style="font-size:2.5rem; margin:0.5rem 0;">أحسنت يا بطل!</h2>
        <p style="font-size:1.2rem; color:#d9d0ff;">لقد أنهيت جميع مراحل الحماية الأساسية. أنت الآن أكثر وعياً وأقوى. تذكر دائماً أن تحمي نفسك وتطلب المساعدة عند الحاجة.</p>
        <button class="btn-next" id="showExtraBtn" style="margin-top:1.5rem;">📚 استكشف المزيد من دروس الحماية</button>
      </div>
    </div>

    <!-- ======== قسم المحتوى الإضافي (كل الفيديوهات والألعاب المتبقية) ======== -->
    <div id="extraSection" class="extra-content hidden">
      <h2>📚 دروس إضافية في الحماية</h2>
      <div class="extra-grid">
        <?php
        // عرض جميع العناصر المتبقية (فيديوهات وألعاب) بعد استثناء الأربعة الأولى من الفيديوهات
        // نأخذ كل العناصر التي لم تستخدم في المراحل الأساسية.
        // نستخدم مصفوفة mainVideos التي تحتوي على أول 4 فيديوهات، ونستثنيها من العرض الإضافي.
        $usedIds = array_column($mainVideos, 'id'); // معرفات الفيديوهات المستخدمة
        $extraItems = array_filter($allItems, function($item) use ($usedIds) {
            return !in_array($item['id'], $usedIds);
        });
        foreach ($extraItems as $item):
        ?>
          <div class="extra-item" data-id="<?php echo $item['id']; ?>">
            <span class="item-icon"><?php echo $item['type'] === 'video' ? '🎬' : '🎮'; ?></span>
            <h4><?php echo h($item['title']); ?></h4>
            <p><?php echo h($item['description']); ?></p>
            <?php if ($item['type'] === 'video' && !empty($item['youtube_id'])): ?>
              <button class="btn-play-sm" data-youtube="<?php echo h($item['youtube_id']); ?>">▶ شاهد الفيديو</button>
            <?php elseif ($item['type'] === 'game'): ?>
              <button class="btn-play-sm game-btn" data-gameid="<?php echo $item['id']; ?>">🎮 العب اللعبة</button>
            <?php else: ?>
              <span style="color:#b9abd4; font-size:0.8rem;">(محتوى قيد التجهيز)</span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if (empty($extraItems)): ?>
          <p style="color:#b9abd4; text-align:center; grid-column:1/-1;">لا يوجد محتوى إضافي متاح لعمرك حالياً.</p>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<!-- نافذة عرض الفيديو المنبثقة -->
<div class="modal-video" id="videoModal">
  <div class="modal-video-content">
    <button class="close-modal" onclick="closeVideoModal()">✕</button>
    <div class="video-player" id="videoPlayer">
      <!-- سيتم وضع محتوى الفيديو هنا (iframe) -->
    </div>
  </div>
</div>

<!-- موسيقى خلفية -->
<audio id="bgMusic" loop preload="auto">
  <source src="https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3" type="audio/mpeg">
  <!-- يمكنك استبدال الرابط بملف صوتي محلي -->
</audio>

<script>
// ============================================================
// بيانات المراحل والمحتوى الإضافي
// ============================================================
const STAGES = <?php echo json_encode($stages, JSON_UNESCAPED_UNICODE); ?>;
const CHILD_AGE = <?php echo (int)$child['age']; ?>;

// إعدادات الصوت
let bgMusic = document.getElementById('bgMusic');
let musicStarted = false;

// تشغيل الموسيقى عند أول تفاعل
document.addEventListener('click', () => {
  if (!musicStarted) {
    bgMusic.volume = 0.2;
    bgMusic.play().catch(() => {});
    musicStarted = true;
  }
}, { once: true });

// ============================================================
// وظائف التحكم في المراحل
// ============================================================
let currentStage = 0;
const totalStages = STAGES.length;

function showStage(index) {
  // إخفاء الكل
  document.querySelectorAll('.stage-card').forEach(el => el.classList.add('hidden'));
  // إظهار المرحلة المطلوبة
  const stageEl = document.querySelector(`.stage-card[data-stage="${index}"]`);
  if (stageEl) stageEl.classList.remove('hidden');
  // تحديث شريط التقدم
  document.querySelectorAll('.progress-step').forEach((el, i) => {
    el.classList.remove('active', 'completed');
    if (i === index) el.classList.add('active');
    else if (i < index) el.classList.add('completed');
  });
  // تحديث رسالة المرشدة
  const guideMsg = document.getElementById('guideMessage');
  if (STAGES[index]) {
    guideMsg.textContent = `الآن في مرحلة "${STAGES[index].title}"، استمع للقصة ثم شارك في النشاط. أنت رائع!`;
  }
  currentStage = index;
  // تهيئة النشاط
  initActivity(index);
}

// تهيئة النشاط حسب رقم المرحلة
function initActivity(index) {
  const container = document.getElementById(`activityArea${index}`);
  if (!container) return;
  // تعبئة المحتوى حسب المرحلة
  switch (index) {
    case 0: renderBodyParts(container); break;
    case 1: renderDistanceGame(container); break;
    case 2: renderPasswordGame(container); break;
    case 3: renderReportingGame(container); break;
    default: container.innerHTML = '<p style="color:#b9abd4;">نشاط قادم قريباً...</p>';
  }
}

// ============================================================
// المرحلة 1: جسدي ملكي (اختيار الأعضاء الخاصة)
// ============================================================
const bodyParts = [
  { id: 'head', label: 'الرأس', icon: '🧑', safe: true },
  { id: 'chest', label: 'الصدر', icon: '🫀', safe: false },
  { id: 'belly', label: 'البطن', icon: '🤰', safe: false },
  { id: 'private', label: 'الأعضاء التناسلية', icon: '🔞', safe: false },
  { id: 'arms', label: 'الذراعان', icon: '💪', safe: true },
  { id: 'legs', label: 'الساقان', icon: '🦵', safe: true },
];

function renderBodyParts(container) {
  let html = `
    <p style="font-weight:700; color:#ffc93c;">👇 اضغط على الأعضاء التي لا يجوز لأي شخص لمسها (أعضاء خاصة).</p>
    <div class="body-zone" id="bodyZone">
  `;
  bodyParts.forEach(part => {
    html += `
      <div class="body-part" data-part="${part.id}" data-safe="${part.safe}">
        <span class="part-icon">${part.icon}</span>
        <span class="part-label">${part.label}</span>
      </div>
    `;
  });
  html += `</div>
    <div id="bodyFeedback" style="text-align:center; margin-top:0.8rem; font-weight:bold; min-height:2.5rem;"></div>
  `;
  container.innerHTML = html;

  // إضافة الأحداث
  let selectedCount = 0;
  const totalUnsafe = bodyParts.filter(p => !p.safe).length;
  let correctSelections = 0;
  const zone = document.getElementById('bodyZone');
  zone.querySelectorAll('.body-part').forEach(el => {
    el.addEventListener('click', function() {
      if (this.classList.contains('correct')) return; // منع التكرار
      const isSafe = this.dataset.safe === 'true';
      const partName = this.querySelector('.part-label').textContent;
      if (!isSafe) {
        // عضو خاص - يجب اختياره
        this.classList.add('correct', 'selected-unsafe');
        correctSelections++;
        document.getElementById('bodyFeedback').innerHTML = `✅ صحيح! "${partName}" عضو خاص لا يجوز لمسه.`;
        speakText(`أحسنت! ${partName} من الأعضاء الخاصة.`);
      } else {
        // عضو عام - لا يجب اختياره
        this.classList.add('wrong', 'selected-safe');
        document.getElementById('bodyFeedback').innerHTML = `❌ "${partName}" ليس عضواً خاصاً، لا بأس بلمسه. لكن تذكر أن تحترم حدود الآخرين.`;
        speakText(`تذكر، ${partName} ليس عضواً خاصاً.`);
      }
      // التحقق من الفوز
      if (correctSelections === totalUnsafe) {
        document.getElementById('bodyFeedback').innerHTML = '🎉 ممتاز! لقد اخترت جميع الأعضاء الخاصة بشكل صحيح. أنت تعرف جيداً كيف تحمي جسدك.';
        speakText('ممتاز! لقد أنهيت النشاط بنجاح.');
        enableNextButton(currentStage);
      }
    });
  });
}

// ============================================================
// المرحلة 2: المسافة الآمنة (سحب الشخصية)
// ============================================================
function renderDistanceGame(container) {
  container.innerHTML = `
    <p style="font-weight:700; color:#ffc93c;">اسحب الشخصية الصغيرة (🧒) لتكون بعيداً بما يكفي عن الشخص الآخر (👤) في المنطقة الآمنة (المنطقة المتقطعة).</p>
    <div class="distance-game" id="distanceGame">
      <div class="distance-person" id="dragPerson" style="left:15%;">🧒</div>
      <div class="distance-other">👤</div>
      <div class="distance-safe-zone"></div>
    </div>
    <div class="distance-feedback" id="distanceFeedback">اسحبني لأكون في المنطقة الآمنة!</div>
  `;
  const person = document.getElementById('dragPerson');
  const game = document.getElementById('distanceGame');
  const feedback = document.getElementById('distanceFeedback');
  let isDragging = false;
  let isCompleted = false;

  function handleMove(e) {
    if (!isDragging || isCompleted) return;
    const rect = game.getBoundingClientRect();
    let x = (e.clientX || e.touches?.[0]?.clientX || 0) - rect.left;
    x = Math.max(0, Math.min(x, rect.width - 60));
    person.style.left = x + 'px';
    // التحقق من المسافة الآمنة (نسبة مئوية)
    const percent = (x / rect.width) * 100;
    if (percent >= 30 && percent <= 70) {
      feedback.innerHTML = '✅ ممتاز! أنت في المنطقة الآمنة.';
      feedback.style.color = '#2ec4b6';
      if (!isCompleted) {
        isCompleted = true;
        speakText('أحسنت! حافظت على مسافة آمنة.');
        enableNextButton(currentStage);
      }
    } else {
      feedback.innerHTML = '⬅️ حركني قليلاً إلى اليمين أو اليسار لتكون في المنطقة الآمنة.';
      feedback.style.color = '#ffc93c';
    }
  }

  // أحداث الماوس
  person.addEventListener('mousedown', (e) => { isDragging = true; person.classList.add('dragging'); e.preventDefault(); });
  document.addEventListener('mousemove', handleMove);
  document.addEventListener('mouseup', () => { isDragging = false; person.classList.remove('dragging'); });
  // أحداث اللمس
  person.addEventListener('touchstart', (e) => { isDragging = true; person.classList.add('dragging'); e.preventDefault(); });
  document.addEventListener('touchmove', handleMove, { passive: false });
  document.addEventListener('touchend', () => { isDragging = false; person.classList.remove('dragging'); });
}

// ============================================================
// المرحلة 3: كلمات السر
// ============================================================
function renderPasswordGame(container) {
  container.innerHTML = `
    <p style="font-weight:700; color:#ffc93c;">اكتب كلمة سر قوية (8 أحرف على الأقل، تحتوي على حروف كبيرة وصغيرة وأرقام ورموز).</p>
    <div class="password-game" style="text-align:center;">
      <input type="text" id="passwordInput" placeholder="اكتب كلمة السر هنا..." style="text-align:center;">
      <div class="password-rules">
        <span class="rule" id="ruleLength">🔢 8 أحرف على الأقل</span>
        <span class="rule" id="ruleUpper">🔠 حرف كبير</span>
        <span class="rule" id="ruleLower">🔡 حرف صغير</span>
        <span class="rule" id="ruleNumber">🔢 رقم</span>
        <span class="rule" id="ruleSymbol">🔣 رمز (!@#$%^&*)</span>
      </div>
      <div id="passwordFeedback" style="margin-top:0.8rem; font-weight:bold; min-height:2rem;"></div>
    </div>
  `;
  const input = document.getElementById('passwordInput');
  const rules = {
    length: document.getElementById('ruleLength'),
    upper: document.getElementById('ruleUpper'),
    lower: document.getElementById('ruleLower'),
    number: document.getElementById('ruleNumber'),
    symbol: document.getElementById('ruleSymbol'),
  };
  const feedback = document.getElementById('passwordFeedback');

  input.addEventListener('input', function() {
    const val = this.value;
    const checks = {
      length: val.length >= 8,
      upper: /[A-Z]/.test(val),
      lower: /[a-z]/.test(val),
      number: /\d/.test(val),
      symbol: /[!@#$%^&*()\-_+=]/.test(val),
    };
    // تحديث واجهة القواعد
    Object.keys(checks).forEach(key => {
      if (checks[key]) {
        rules[key].classList.add('valid');
        rules[key].innerHTML = rules[key].innerHTML.replace('⬜', '✅');
      } else {
        rules[key].classList.remove('valid');
        rules[key].innerHTML = rules[key].innerHTML.replace('✅', '⬜');
      }
    });
    // التحقق من اكتمال جميع القواعد
    const allValid = Object.values(checks).every(v => v === true);
    if (allValid && val.length > 0) {
      feedback.innerHTML = '🎉 كلمة سر قوية! أحسنت.';
      feedback.style.color = '#2ec4b6';
      speakText('كلمة سر قوية! أحسنت.');
      enableNextButton(currentStage);
    } else {
      feedback.innerHTML = '⚠️ أكمل جميع القواعد للحصول على كلمة سر قوية.';
      feedback.style.color = '#ffc93c';
    }
  });

  // تهيئة النصوص
  Object.keys(rules).forEach(key => {
    const labels = {
      length: '🔢 8 أحرف على الأقل',
      upper: '🔠 حرف كبير',
      lower: '🔡 حرف صغير',
      number: '🔢 رقم',
      symbol: '🔣 رمز (!@#$%^&*)'
    };
    rules[key].innerHTML = '⬜ ' + labels[key];
  });
}

// ============================================================
// المرحلة 4: الإبلاغ عن التحرش (سيناريوهات)
// ============================================================
const scenarios = [
  {
    question: 'شخص غريب على الإنترنت طلب منك إرسال صورة لك، ماذا تفعل؟',
    choices: [
      { text: 'أرسل الصورة فوراً', correct: false },
      { text: 'أخبر والديّ ولا أرسل شيئاً', correct: true },
      { text: 'أتجاهل الطلب ولا أخبر أحداً', correct: false },
    ]
  },
  {
    question: 'شخص كبير بالغ طلب منك أن تبقي لقاءكما سراً، ما تصرفك؟',
    choices: [
      { text: 'أوافق وأحتفظ بالسر', correct: false },
      { text: 'أرفض وأخبر أمي أو أبي', correct: true },
      { text: 'أخبر صديقي فقط', correct: false },
    ]
  },
  {
    question: 'أحد زملائك في المدرسة يلمسك بطريقة غير مريحة، ماذا تفعل؟',
    choices: [
      { text: 'أصرخ وأطلب المساعدة من المعلم', correct: true },
      { text: 'أضرب زميلي', correct: false },
      { text: 'أبتعد ولا أخبر أحداً', correct: false },
    ]
  }
];

let scenarioIndex = 0;
function renderReportingGame(container) {
  scenarioIndex = 0;
  renderScenario(container, scenarioIndex);
}

function renderScenario(container, idx) {
  if (idx >= scenarios.length) {
    container.innerHTML = `
      <div style="text-align:center; padding:1.5rem;">
        <div style="font-size:3rem;">🎉</div>
        <p style="font-weight:700; color:#ffc93c;">لقد أجبت على جميع السيناريوهات بشكل صحيح! أنت تعرف كيف تتصرف بحكمة.</p>
      </div>
    `;
    speakText('أحسنت! لقد أنهيت جميع السيناريوهات.');
    enableNextButton(currentStage);
    return;
  }
  const s = scenarios[idx];
  let html = `
    <div class="scenario-card">
      <p style="font-size:1.1rem; font-weight:700;">${s.question}</p>
      <div class="choices">
  `;
  s.choices.forEach((c, i) => {
    html += `<button class="choice-btn" data-correct="${c.correct}" data-index="${i}">${c.text}</button>`;
  });
  html += `</div>
    <div id="scenarioFeedback" style="margin-top:0.8rem; font-weight:bold; min-height:2rem;"></div>
  </div>`;
  container.innerHTML = html;

  // إضافة الأحداث للأزرار
  container.querySelectorAll('.choice-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      if (this.dataset.answered === 'true') return;
      const isCorrect = this.dataset.correct === 'true';
      const feedback = document.getElementById('scenarioFeedback');
      // تعطيل جميع الأزرار
      container.querySelectorAll('.choice-btn').forEach(b => b.disabled = true);
      if (isCorrect) {
        this.classList.add('correct');
        feedback.innerHTML = '✅ إجابة صحيحة! أحسنت.';
        feedback.style.color = '#2ec4b6';
        speakText('إجابة صحيحة!');
        setTimeout(() => {
          scenarioIndex++;
          renderScenario(container, scenarioIndex);
        }, 1200);
      } else {
        this.classList.add('wrong');
        feedback.innerHTML = '❌ ليس تماماً. فكر مرة أخرى، من الأفضل إخبار شخص بالغ تثق به.';
        feedback.style.color = '#ff6b6b';
        speakText('ليس تماماً. تذكر أن تطلب المساعدة من شخص بالغ.');
        // إعادة تمكين الأزرار بعد قليل
        setTimeout(() => {
          container.querySelectorAll('.choice-btn').forEach(b => b.disabled = false);
          this.classList.remove('wrong');
        }, 2000);
      }
    });
  });
}

// ============================================================
// وظائف مساعدة
// ============================================================
function speakText(text) {
  if (!('speechSynthesis' in window)) return;
  window.speechSynthesis.cancel();
  const utterance = new SpeechSynthesisUtterance(text);
  utterance.lang = 'ar-SA';
  utterance.rate = 1.1;
  utterance.pitch = 1.2;
  // البحث عن صوت عربي
  const voices = speechSynthesis.getVoices();
  const arabic = voices.find(v => v.lang.startsWith('ar'));
  if (arabic) utterance.voice = arabic;
  speechSynthesis.speak(utterance);
}

function enableNextButton(stageIndex) {
  const btn = document.getElementById(`nextBtn${stageIndex}`);
  if (btn) {
    btn.disabled = false;
    btn.style.opacity = 1;
  }
}

// الانتقال للمرحلة التالية
document.querySelectorAll('.btn-next').forEach(btn => {
  btn.addEventListener('click', function() {
    const stageIdx = parseInt(this.id.replace('nextBtn', ''));
    if (stageIdx === totalStages - 1) {
      // نهاية الرحلة الأساسية → نعرض شاشة النهاية
      document.querySelectorAll('.stage-card').forEach(el => el.classList.add('hidden'));
      document.getElementById('finalScreen').classList.remove('hidden');
      document.getElementById('guideMessage').textContent = 'أنت بطل! لقد أنهيت جميع مراحل الحماية الأساسية. استمر في حماية نفسك ومساعدة الآخرين.';
      speakText('أنت بطل! لقد أنهيت جميع مراحل الحماية الأساسية.');
      return;
    }
    showStage(stageIdx + 1);
  });
});

// ============================================================
// عرض الفيديو باستخدام youtube_id
// ============================================================
function openVideoModal(embedUrl, title = '') {
  const modal = document.getElementById('videoModal');
  const player = document.getElementById('videoPlayer');
  if (embedUrl) {
    player.innerHTML = `<iframe width="100%" height="100%" src="${embedUrl}" frameborder="0" allowfullscreen></iframe>`;
  } else {
    player.innerHTML = `<div style="padding:1rem; text-align:center; color:#b9abd4;">لا يوجد رابط فيديو متاح</div>`;
  }
  modal.classList.add('open');
}

function closeVideoModal() {
  document.getElementById('videoModal').classList.remove('open');
  document.getElementById('videoPlayer').innerHTML = '';
}

// ربط أزرار "شاهد القصة" في المراحل (استخدام youtube_id)
document.querySelectorAll('.stage-card .btn-play[data-youtube]').forEach(btn => {
  btn.addEventListener('click', function() {
    const youtubeId = this.dataset.youtube;
    if (youtubeId) {
      const embedUrl = `https://www.youtube.com/embed/${youtubeId}`;
      openVideoModal(embedUrl);
    } else {
      // إذا لم يوجد youtube_id، نعرض قصة نصية
      const stageIndex = this.closest('.stage-card')?.dataset?.stage || 0;
      const stage = STAGES[stageIndex];
      const content = `<div style="padding:1.5rem; text-align:center; background:linear-gradient(135deg,#1a1040,#0a061a); border-radius:15px;">
        <div style="font-size:4rem;">${stage.icon}</div>
        <h3 style="color:#ffc93c;">${stage.title}</h3>
        <p style="color:#d9d0ff; line-height:1.8;">${stage.description}</p>
        <button onclick="speakText('${stage.description.replace(/['"]/g, '')}');" style="background:#ffc93c;border:none;padding:0.5rem 1.5rem;border-radius:50px;font-weight:900;color:#241645;cursor:pointer;">🔊 استمع</button>
      </div>`;
      openVideoModal(null); // لا يوجد فيديو، نعرض النص
      document.getElementById('videoPlayer').innerHTML = content;
    }
  });
});

// ============================================================
// ربط أزرار المحتوى الإضافي (فيديوهات)
// ============================================================
document.querySelectorAll('.extra-item .btn-play-sm[data-youtube]').forEach(btn => {
  btn.addEventListener('click', function(e) {
    e.stopPropagation();
    const youtubeId = this.dataset.youtube;
    if (youtubeId) {
      const embedUrl = `https://www.youtube.com/embed/${youtubeId}`;
      openVideoModal(embedUrl);
    }
  });
});

// أزرار الألعاب في المحتوى الإضافي (يمكن توسيعها لاحقاً)
document.querySelectorAll('.extra-item .game-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    alert('سيتم تشغيل اللعبة قريباً! 🎮');
  });
});

// ============================================================
// إظهار المحتوى الإضافي بعد الضغط على زر الاستكشاف
// ============================================================
document.getElementById('showExtraBtn')?.addEventListener('click', function() {
  document.getElementById('finalScreen').classList.add('hidden');
  document.getElementById('extraSection').classList.remove('hidden');
  document.getElementById('guideMessage').textContent = 'استكشف المزيد من دروس الحماية! كل فيديو أو لعبة يعلمك شيئاً جديداً.';
  speakText('استكشف المزيد من دروس الحماية!');
  // التمرير إلى المحتوى الإضافي
  document.getElementById('extraSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
});

// ============================================================
// بدء التشغيل
// ============================================================
showStage(0);
setTimeout(() => speakText('مرحباً بطل! جهز نفسك لرحلة ممتعة تتعلم فيها كيف تحمي نفسك.'), 1000);

</script>

<div class="safety-footer">
  Kidora © 2026 — تعلم الحماية بذكاء ومتعة
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
