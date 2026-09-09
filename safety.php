<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();

// جلب كل محتوى الحماية المناسب لعمر الطفل
$stmt = $pdo->prepare("SELECT * FROM safety_content WHERE age_min <= ? AND age_max >= ? ORDER BY id");
$stmt->execute([$child['age'], $child['age']]);
$allItems = $stmt->fetchAll();

$videoItems = array_filter($allItems, fn($i) => $i['type'] === 'video');

// المراحل الأساسية (الأيام الأربعة الأولى)
$stages = [
    [
        'id' => 1,
        'title' => 'جسدي ملكي',
        'icon' => '🛡️',
        'description' => 'تعلّم أعضاء جسدك الخاصة التي لا يجوز لأحد لمسها.',
        'video' => array_values($videoItems)[0] ?? null,
        'activity' => 'body_game'
    ],
    [
        'id' => 2,
        'title' => 'المسافة الآمنة',
        'icon' => '📏',
        'description' => 'تدرب على الحفاظ على مسافة آمنة مع الآخرين.',
        'video' => array_values($videoItems)[1] ?? null,
        'activity' => 'distance_game'
    ],
    [
        'id' => 3,
        'title' => 'كلمات السر',
        'icon' => '🔐',
        'description' => 'تعلّم كيفية اختيار كلمة سر قوية وآمنة.',
        'video' => array_values($videoItems)[2] ?? null,
        'activity' => 'password_game'
    ],
    [
        'id' => 4,
        'title' => 'الإبلاغ عن التحرش',
        'icon' => '📢',
        'description' => 'تدرب على التصرف الصحيح عند التعرض للتحرش.',
        'video' => array_values($videoItems)[3] ?? null,
        'activity' => 'reporting_game'
    ],
];

// باقي المحتوى الإضافي
$usedIds = array_column(array_filter($stages, fn($s) => $s['video']), 'id');
$extraItems = array_values(array_filter($allItems, fn($item) => !in_array($item['id'], $usedIds)));

$__pageTitle = 'قسم الحماية — Kidora';
$__pageLine = "حماية نفسك أهم مهارة يا بطل 🛡️";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<style>
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

  .daily-task {
    background: rgba(255,255,255,0.06);
    border-radius: 30px;
    padding: 2rem;
    border: 1px solid rgba(255,255,255,0.08);
    backdrop-filter: blur(5px);
    margin-bottom: 2rem;
  }
  .daily-task .stage-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
  }
  .daily-task .stage-icon {
    font-size: 2.8rem;
    background: rgba(255,255,255,0.1);
    width: 70px;
    height: 70px;
    border-radius: 20px;
    display: grid;
    place-items: center;
  }
  .daily-task .stage-title {
    font-family: var(--font-display);
    font-size: 1.8rem;
    margin: 0;
    color: #fff;
  }
  .daily-task .stage-desc {
    color: #d9d0ff;
    font-size: 1rem;
    margin-bottom: 1.5rem;
  }

  .video-story {
    background: rgba(0,0,0,0.3);
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border-left: 4px solid #ffc93c;
  }
  .video-story h4 { color: #ffc93c; margin-top: 0; }
  .video-story p { color: #e0d8f0; line-height: 1.8; }
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

  .activity-area {
    min-height: 280px;
    padding: 1rem 0;
  }

  /* ===== تصميم جسم الإنسان (جسدي ملكي) ===== */
  .human-body-wrapper {
    background: rgba(0,0,0,0.2);
    border-radius: 40px;
    padding: 1.5rem;
    text-align: center;
  }
  .human-body {
    position: relative;
    width: 180px;
    height: 280px;
    margin: 0 auto 1rem;
  }
  .body-part {
    position: absolute;
    background: #f7d9aa;
    border: 3px solid rgba(255,255,255,0.2);
    border-radius: 40px;
    transition: all 0.2s ease;
    cursor: pointer;
    user-select: none;
    box-shadow: inset 0 -4px 8px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    color: #1a1040;
    font-size: 0.7rem;
  }
  .body-part .part-label {
    background: rgba(0,0,0,0.6);
    color: #fff;
    padding: 2px 8px;
    border-radius: 30px;
    font-size: 0.6rem;
    position: absolute;
    bottom: -22px;
    white-space: nowrap;
    opacity: 0;
    transition: 0.3s;
    pointer-events: none;
  }
  .body-part:hover .part-label {
    opacity: 1;
    bottom: -30px;
  }
  .body-part:hover {
    transform: scale(1.05);
    z-index: 10;
    border-color: #ffc93c;
  }

  .part-head {
    width: 60px;
    height: 60px;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    border-radius: 50%;
    background: #f7d9aa;
  }
  .part-torso {
    width: 90px;
    height: 100px;
    top: 55px;
    left: 50%;
    transform: translateX(-50%);
    border-radius: 30px 30px 40px 40px;
    background: #f0cfa0;
  }
  .part-arm {
    width: 20px;
    height: 70px;
    top: 60px;
    border-radius: 20px;
    background: #f7d9aa;
  }
  .part-arm-left { left: 5px; transform: rotate(15deg); transform-origin: top center; }
  .part-arm-right { right: 5px; transform: rotate(-15deg); transform-origin: top center; }
  .part-leg {
    width: 26px;
    height: 80px;
    bottom: 0;
    border-radius: 20px 20px 10px 10px;
    background: #f7d9aa;
  }
  .part-leg-left { left: 35px; }
  .part-leg-right { right: 35px; }

  .body-part.selected-private {
    border-color: #ff3b3b;
    background: #ff3b3b !important;
    box-shadow: 0 0 30px rgba(255,59,59,0.7);
    transform: scale(1.05);
  }
  .body-part.selected-safe {
    border-color: #2ec4b6;
    box-shadow: 0 0 25px rgba(46,196,182,0.4);
    transform: scale(0.95);
  }
  .body-part.wrong-click {
    border-color: #ff6b6b;
    animation: shake 0.4s ease;
  }
  @keyframes shake {
    0%, 100% { transform: translateX(0) rotate(0); }
    25% { transform: translateX(-8px) rotate(-3deg); }
    75% { transform: translateX(8px) rotate(3deg); }
  }
  .body-part.disabled-part {
    pointer-events: none;
    opacity: 0.7;
    filter: grayscale(0.3);
  }

  .game-feedback {
    font-size: 1.2rem;
    font-weight: 700;
    min-height: 3rem;
    margin: 0.8rem 0;
    padding: 0.8rem;
    border-radius: 40px;
    background: rgba(255,255,255,0.05);
  }

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
  .modal-video.open { display: flex; }
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

  .extra-section {
    margin-top: 2rem;
    border-top: 2px dashed rgba(255,201,60,0.2);
    padding-top: 2rem;
  }
  .extra-section h2 {
    font-size: 1.6rem;
    color: #ffc93c;
    text-align: center;
    margin-bottom: 1.5rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
  }
  .extra-section h2:hover { opacity: 0.8; }
  .extra-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1.2rem;
  }
  .extra-item {
    background: rgba(255,255,255,0.06);
    border-radius: 20px;
    padding: 1.2rem;
    border: 1px solid rgba(255,255,255,0.08);
    transition: 0.3s;
  }
  .extra-item:hover { transform: translateY(-4px); }
  .extra-item h4 { color: #fff; margin: 0.2rem 0; }
  .extra-item p { color: #d9d0ff; font-size: 0.9rem; }
  .extra-item .btn-play-sm {
    background: rgba(255,201,60,0.2);
    border: 1px solid #ffc93c;
    color: #ffc93c;
    padding: 0.3rem 1.2rem;
    border-radius: 50px;
    cursor: pointer;
    font-weight: 700;
    transition: 0.2s;
    font-size: 0.9rem;
  }
  .extra-item .btn-play-sm:hover {
    background: #ffc93c;
    color: #241645;
  }

  .hidden-extra { display: none; }

  @media (max-width: 600px) {
    .safety-guide { flex-direction: column; text-align: center; }
    .human-body { transform: scale(0.8); }
    .daily-task .stage-header { flex-direction: column; text-align: center; }
  }
</style>

<div class="safety-page">
  <div class="safety-container">

    <!-- شخصية المرشدة -->
    <div class="safety-guide">
      <div class="safety-guide-avatar">🦉</div>
      <div class="safety-guide-bubble">
        <span class="safety-guide-name">رفيقتك الحكيمة</span>
        <span id="guideMessage">مرحباً بطل! 🌟 مهمة جديدة تنتظرك اليوم.</span>
      </div>
    </div>

    <!-- ===== تم حذف شريط اليوم والنجوم بالكامل ===== -->

    <!-- حاوية المهمة اليومية (عنصر واحد فقط) -->
    <div id="dailyTaskContainer" class="daily-task">
      <!-- يتم ملؤها بالجافا سكريبت -->
    </div>

    <!-- زر استكشاف المحتوى الإضافي -->
    <div style="text-align:center; margin-top:1rem;">
      <button id="toggleExtraBtn" class="btn-next" style="background:rgba(255,255,255,0.1); color:#d9d0ff; box-shadow:none; border:1px solid rgba(255,255,255,0.1); padding:0.6rem 2rem;">
        📚 استعرض كل دروس الحماية
      </button>
    </div>

    <!-- قسم المحتوى الإضافي (مخفي افتراضيًا) -->
    <div id="extraSection" class="extra-section hidden-extra">
      <h2>📚 كل دروس الحماية</h2>
      <div class="extra-grid" id="extraGrid">
        <!-- يتم ملؤها بالجافا سكريبت -->
      </div>
    </div>

  </div>
</div>

<!-- نافذة الفيديو -->
<div class="modal-video" id="videoModal">
  <div class="modal-video-content">
    <button class="close-modal" onclick="closeVideoModal()">✕</button>
    <div class="video-player" id="videoPlayer"></div>
  </div>
</div>

<audio id="bgMusic" loop preload="auto">
  <source src="https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3" type="audio/mpeg">
</audio>

<script>
// ============================================================
// بيانات من PHP
// ============================================================
const STAGES = <?php echo json_encode($stages, JSON_UNESCAPED_UNICODE); ?>;
const EXTRA_ITEMS = <?php echo json_encode($extraItems, JSON_UNESCAPED_UNICODE); ?>;

// ============================================================
// إدارة الأيام (مهمة واحدة في اليوم) - بدون عداد أو نجوم
// ============================================================
function getTodayKey() {
    return new Date().toDateString();
}

function getProgress() {
    let progress = localStorage.getItem('safety_progress');
    if (!progress) {
        progress = { dayIndex: 0, lastDate: null };
    } else {
        progress = JSON.parse(progress);
    }
    const today = getTodayKey();
    const totalItems = STAGES.length + EXTRA_ITEMS.length;

    if (progress.lastDate !== today) {
        if (progress.dayIndex < totalItems - 1) {
            progress.dayIndex += 1;
        } else {
            progress.dayIndex = Math.min(progress.dayIndex, totalItems - 1);
        }
        progress.lastDate = today;
        localStorage.setItem('safety_progress', JSON.stringify(progress));
    }
    return progress;
}

let progress = getProgress();
let currentDayIndex = progress.dayIndex;

// ============================================================
// عرض المهمة اليومية (عنصر واحد فقط)
// ============================================================
function renderDailyTask() {
    const container = document.getElementById('dailyTaskContainer');
    const totalItems = STAGES.length + EXTRA_ITEMS.length;

    if (currentDayIndex >= totalItems) {
        container.innerHTML = `
            <div style="text-align:center; padding:2rem;">
                <div style="font-size:4rem;">🏆</div>
                <h2 style="color:#ffc93c;">أنت البطل الكبير!</h2>
                <p style="color:#d9d0ff;">لقد أنهيت جميع دروس الحماية. عد غداً لمهمة جديدة!</p>
                <button class="btn-next" onclick="location.reload();">🔄 تحديث</button>
            </div>
        `;
        document.getElementById('guideMessage').textContent = 'أنت مذهل! أنهيت كل الدروس. عد غداً لمزيد من التعلم.';
        return;
    }

    let stageData;
    let isExtra = false;
    if (currentDayIndex < STAGES.length) {
        stageData = STAGES[currentDayIndex];
    } else {
        isExtra = true;
        const extraIdx = currentDayIndex - STAGES.length;
        const item = EXTRA_ITEMS[extraIdx];
        if (!item) {
            container.innerHTML = '<p style="color:#b9abd4;">جاري تجهيز المهام...</p>';
            return;
        }
        stageData = {
            id: item.id,
            title: item.title,
            icon: item.type === 'video' ? '🎬' : '🎮',
            description: item.description,
            video: item,
            activity: 'extra_content'
        };
    }

    let html = `
        <div class="stage-header">
            <div class="stage-icon">${stageData.icon}</div>
            <h2 class="stage-title">${stageData.title}</h2>
        </div>
        <p class="stage-desc">${stageData.description}</p>
    `;

    if (stageData.video) {
        const vid = stageData.video;
        if (vid.youtube_id) {
            html += `
                <div class="video-story">
                    <h4>🎬 ${vid.title}</h4>
                    <p>${vid.description}</p>
                    <button class="btn-play" data-youtube="${vid.youtube_id}">▶ شاهد القصة</button>
                </div>
            `;
        } else {
            html += `
                <div class="video-story" style="border-left-color: #6C63FF;">
                    <h4>📖 قصة توعوية</h4>
                    <p>${vid.description}</p>
                    <button class="btn-play" onclick="speakText('${vid.description.replace(/['"]/g, '')}');">🔊 استمع للقصة</button>
                </div>
            `;
        }
    }

    html += `<div class="activity-area" id="activityArea"></div>`;
    html += `
        <button class="btn-next" id="finishTaskBtn" disabled>
            ${isExtra ? '📚 أنهيت الدرس' : '🎯 أنهيت المهمة'}
        </button>
    `;

    container.innerHTML = html;

    const activityContainer = document.getElementById('activityArea');
    if (!isExtra && currentDayIndex < STAGES.length) {
        switch (currentDayIndex) {
            case 0: renderBodyGame(activityContainer); break;
            case 1: renderDistanceGame(activityContainer); break;
            case 2: renderPasswordGame(activityContainer); break;
            case 3: renderReportingGame(activityContainer); break;
            default: activityContainer.innerHTML = '<p style="color:#b9abd4;">نشاط قادم...</p>';
        }
    } else {
        if (stageData.video && stageData.video.type === 'game') {
            activityContainer.innerHTML = `
                <div style="text-align:center; padding:1.5rem; background:rgba(255,255,255,0.05); border-radius:30px;">
                    <div style="font-size:3rem;">🎮</div>
                    <p style="color:#d9d0ff;">لعبة "${stageData.title}" قيد التطوير، لكن يمكنك مشاهدة الفيديو أعلاه!</p>
                    <button class="btn-next" style="margin-top:0.5rem;" onclick="enableDailyTask()">✅ أنهيت المشاهدة</button>
                </div>
            `;
        } else {
            activityContainer.innerHTML = `
                <div style="text-align:center; padding:1.5rem; background:rgba(255,255,255,0.05); border-radius:30px;">
                    <p style="color:#d9d0ff;">📺 شاهد الفيديو أعلاه لتتعلم معلومة جديدة.</p>
                    <button class="btn-next" style="margin-top:0.5rem;" onclick="enableDailyTask()">✅ شاهدت الفيديو</button>
                </div>
            `;
        }
    }

    document.getElementById('guideMessage').textContent = `اليوم: "${stageData.title}". أنجز النشاط لتنتقل للمهمة التالية غداً.`;
}

// ============================================================
// تمكين زر إنهاء المهمة (بدون نجوم)
// ============================================================
function enableDailyTask() {
    const btn = document.getElementById('finishTaskBtn');
    if (btn) {
        btn.disabled = false;
        btn.style.opacity = 1;
    }
}

document.addEventListener('click', function(e) {
    if (e.target.id === 'finishTaskBtn' && !e.target.disabled) {
        const today = getTodayKey();
        progress.lastDate = today;
        progress.dayIndex = currentDayIndex + 1;
        localStorage.setItem('safety_progress', JSON.stringify(progress));
        currentDayIndex = progress.dayIndex;
        renderDailyTask();
        speakText('أحسنت! أنهيت مهمة اليوم. تعال غداً لمهمة جديدة.');
        document.getElementById('guideMessage').textContent = '🎉 مبروك! أنهيت المهمة. غداً مهمة جديدة بإذن الله.';
    }
});

// ============================================================
// لعبة الجسم (جسدي ملكي)
// ============================================================
function renderBodyGame(container) {
    const parts = [
        { id: 'head', label: 'الرأس', private: false, cls: 'part-head' },
        { id: 'torso', label: 'الجذع (منطقة خاصة)', private: true, cls: 'part-torso' },
        { id: 'arm-left', label: 'الذراع', private: false, cls: 'part-arm part-arm-left' },
        { id: 'arm-right', label: 'الذراع', private: false, cls: 'part-arm part-arm-right' },
        { id: 'leg-left', label: 'الساق', private: false, cls: 'part-leg part-leg-left' },
        { id: 'leg-right', label: 'الساق', private: false, cls: 'part-leg part-leg-right' },
    ];

    let html = `
        <div class="human-body-wrapper">
            <div style="font-weight:700; color:#ffc93c; margin-bottom:0.8rem;">👇 اضغط على المنطقة الخاصة (التي لا يجوز لمسها)</div>
            <div class="human-body" id="humanBody">
    `;
    parts.forEach(p => {
        html += `
            <div class="body-part ${p.cls}" data-id="${p.id}" data-private="${p.private}">
                <span class="part-label">${p.label}</span>
            </div>
        `;
    });
    html += `
            </div>
            <div class="game-feedback" id="bodyFeedback">🔴 اضغط على الجذع (المنطقة الخاصة).</div>
            <div style="font-size:0.9rem; color:#b9abd4;">🟢 الأطراف والرأس مسموح لمسها | 🔴 الجذع ممنوع</div>
        </div>
    `;
    container.innerHTML = html;

    const body = document.getElementById('humanBody');
    const feedback = document.getElementById('bodyFeedback');
    let completed = false;

    body.querySelectorAll('.body-part').forEach(el => {
        el.addEventListener('click', function() {
            if (this.classList.contains('disabled-part') || completed) return;
            const isPrivate = this.dataset.private === 'true';
            const label = this.querySelector('.part-label').textContent;

            if (isPrivate) {
                this.classList.add('selected-private');
                this.classList.add('disabled-part');
                completed = true;
                feedback.innerHTML = `✅ صحيح! "${label}" منطقة خاصة، لا يجوز لأحد لمسها. 🌟`;
                feedback.style.color = '#2ec4b6';
                speakText(`أحسنت! الجذع منطقة خاصة.`);
                enableDailyTask();
            } else {
                this.classList.add('wrong-click');
                feedback.innerHTML = `❌ "${label}" ليس منطقة خاصة، لا بأس بلمسه. لكن تذكر احترام حدود الآخرين.`;
                feedback.style.color = '#ff6b6b';
                speakText(`تذكر، ${label} ليس منطقة خاصة.`);
                setTimeout(() => {
                    this.classList.remove('wrong-click');
                }, 500);
            }
        });
    });
}

// ============================================================
// باقي الألعاب (مسافة، كلمات سر، إبلاغ) - مختصرة
// ============================================================
function renderDistanceGame(container) {
    container.innerHTML = `
        <p style="font-weight:700; color:#ffc93c;">اسحب الشخصية للدائرة الآمنة (المنقطة).</p>
        <div style="position:relative;height:200px;background:radial-gradient(circle at 20% 30%, #1a1040, #0a061a);border-radius:30px;overflow:hidden;touch-action:none;">
            <div id="dragPerson" style="position:absolute;bottom:20px;left:15%;font-size:4rem;cursor:grab;user-select:none;">🧒</div>
            <div style="position:absolute;bottom:20px;right:15%;font-size:3rem;opacity:0.7;">👤</div>
            <div style="position:absolute;bottom:0;left:30%;width:40%;height:100%;border:3px dashed rgba(46,196,182,0.4);border-radius:30px 30px 0 0;pointer-events:none;"></div>
        </div>
        <div id="distanceFeedback" style="text-align:center;margin-top:1rem;font-weight:700;">اسحبني للمنطقة الآمنة!</div>
    `;
    const person = document.getElementById('dragPerson');
    const game = container.querySelector('.distance-game') || container;
    const feedback = document.getElementById('distanceFeedback');
    let isDragging = false, done = false;
    const handleMove = (e) => {
        if (!isDragging || done) return;
        const rect = game.getBoundingClientRect();
        let x = (e.clientX || e.touches?.[0]?.clientX || 0) - rect.left;
        x = Math.max(0, Math.min(x, rect.width - 60));
        person.style.left = x + 'px';
        const pct = (x / rect.width) * 100;
        if (pct >= 30 && pct <= 70) {
            feedback.innerHTML = '✅ ممتاز! في المنطقة الآمنة.';
            feedback.style.color = '#2ec4b6';
            if (!done) { done = true; speakText('أحسنت! حافظت على مسافة آمنة.'); enableDailyTask(); }
        } else {
            feedback.innerHTML = '⬅️ حركني داخل المنطقة المنقطة.';
            feedback.style.color = '#ffc93c';
        }
    };
    person.addEventListener('mousedown', (e) => { isDragging = true; e.preventDefault(); });
    document.addEventListener('mousemove', handleMove);
    document.addEventListener('mouseup', () => isDragging = false);
    person.addEventListener('touchstart', (e) => { isDragging = true; e.preventDefault(); });
    document.addEventListener('touchmove', handleMove, { passive: false });
    document.addEventListener('touchend', () => isDragging = false);
}

function renderPasswordGame(container) {
    container.innerHTML = `
        <p style="font-weight:700; color:#ffc93c;">اكتب كلمة سر قوية (8 أحرف، حروف كبيرة وصغيرة، رقم، رمز).</p>
        <div style="text-align:center;">
            <input type="text" id="passwordInput" placeholder="كلمة السر..." style="background:rgba(255,255,255,0.1);border:2px solid rgba(255,255,255,0.15);border-radius:15px;padding:0.8rem 1.2rem;color:#fff;font-size:1.2rem;width:100%;max-width:350px;text-align:center;">
            <div id="passFeedback" style="margin-top:0.8rem;font-weight:bold;"></div>
        </div>
    `;
    const input = document.getElementById('passwordInput');
    const fb = document.getElementById('passFeedback');
    let done = false;
    input.addEventListener('input', function() {
        if (done) return;
        const v = this.value;
        const checks = [
            v.length >= 8,
            /[A-Z]/.test(v),
            /[a-z]/.test(v),
            /\d/.test(v),
            /[!@#$%^&*()]/.test(v)
        ];
        if (checks.every(c => c) && v.length > 0) {
            fb.innerHTML = '🎉 كلمة سر قوية! أحسنت.';
            fb.style.color = '#2ec4b6';
            done = true;
            speakText('كلمة سر قوية!');
            enableDailyTask();
        } else {
            fb.innerHTML = '⚠️ أكمل كل القواعد.';
            fb.style.color = '#ffc93c';
        }
    });
}

function renderReportingGame(container) {
    const scenarios = [
        { q: 'غريب على الإنترنت طلب صورتك؟', choices: ['أرسلها', 'أخبر والدي', 'أتجاهل'], correct: 1 },
        { q: 'شخص بالغ طلب منك السر؟', choices: ['أوافق', 'أرفض وأخبر أهلي', 'أخبر صديقي'], correct: 1 },
        { q: 'زميل يلمسك بشكل مزعج؟', choices: ['أصرخ وأطلب مساعدة', 'أضربه', 'أبتعد وأسكت'], correct: 0 }
    ];
    let idx = 0;
    function renderScenario() {
        if (idx >= scenarios.length) {
            container.innerHTML = `<div style="text-align:center;padding:1.5rem;"><div style="font-size:3rem;">🎉</div><p style="font-weight:700;color:#ffc93c;">أجبت على كل السيناريوهات!</p></div>`;
            speakText('أحسنت! أنهيت السيناريوهات.');
            enableDailyTask();
            return;
        }
        const s = scenarios[idx];
        let html = `<div style="background:rgba(255,255,255,0.05);border-radius:20px;padding:1.5rem;"><p style="font-weight:700;">${s.q}</p><div style="display:flex;flex-wrap:wrap;gap:0.8rem;justify-content:center;">`;
        s.choices.forEach((c, i) => {
            html += `<button class="choice-btn" data-idx="${i}" data-correct="${s.correct}" style="background:rgba(255,255,255,0.08);border:2px solid transparent;border-radius:50px;padding:0.6rem 1.5rem;color:#fff;font-weight:700;cursor:pointer;transition:0.3s;">${c}</button>`;
        });
        html += `</div><div id="scenarioFeedback" style="margin-top:0.8rem;font-weight:bold;"></div></div>`;
        container.innerHTML = html;
        container.querySelectorAll('.choice-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (this.disabled) return;
                const correct = parseInt(this.dataset.correct);
                const chosen = parseInt(this.dataset.idx);
                const fb = document.getElementById('scenarioFeedback');
                container.querySelectorAll('.choice-btn').forEach(b => b.disabled = true);
                if (chosen === correct) {
                    this.style.borderColor = '#2ec4b6';
                    this.style.background = 'rgba(46,196,182,0.2)';
                    fb.innerHTML = '✅ صحيح! أحسنت.';
                    fb.style.color = '#2ec4b6';
                    speakText('إجابة صحيحة!');
                    setTimeout(() => { idx++; renderScenario(); }, 1200);
                } else {
                    this.style.borderColor = '#ff6b6b';
                    this.style.background = 'rgba(255,107,107,0.2)';
                    fb.innerHTML = '❌ ليس تماماً. فكر مرة أخرى.';
                    fb.style.color = '#ff6b6b';
                    speakText('ليس تماماً. حاول مرة أخرى.');
                    setTimeout(() => {
                        container.querySelectorAll('.choice-btn').forEach(b => { b.disabled = false; b.style.borderColor = 'transparent'; b.style.background = 'rgba(255,255,255,0.08)'; });
                    }, 1500);
                }
            });
        });
    }
    renderScenario();
}

// ============================================================
// عرض الفيديو
// ============================================================
function openVideoModal(embedUrl) {
    const modal = document.getElementById('videoModal');
    const player = document.getElementById('videoPlayer');
    if (embedUrl) {
        player.innerHTML = `<iframe width="100%" height="100%" src="${embedUrl}" frameborder="0" allowfullscreen></iframe>`;
    } else {
        player.innerHTML = '<p style="color:#b9abd4;">لا يوجد فيديو</p>';
    }
    modal.classList.add('open');
}
function closeVideoModal() {
    document.getElementById('videoModal').classList.remove('open');
    document.getElementById('videoPlayer').innerHTML = '';
}
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('btn-play') && e.target.dataset.youtube) {
        openVideoModal(`https://www.youtube.com/embed/${e.target.dataset.youtube}`);
    }
});

// ============================================================
// عرض المحتوى الإضافي (زر الإظهار/الإخفاء)
// ============================================================
function renderExtraContent() {
    const grid = document.getElementById('extraGrid');
    if (EXTRA_ITEMS.length === 0) {
        grid.innerHTML = '<p style="color:#b9abd4; text-align:center; grid-column:1/-1;">لا يوجد محتوى إضافي حالياً.</p>';
        return;
    }
    let html = '';
    EXTRA_ITEMS.forEach(item => {
        html += `
            <div class="extra-item">
                <h4>${item.type === 'video' ? '🎬' : '🎮'} ${item.title}</h4>
                <p>${item.description}</p>
                ${item.type === 'video' && item.youtube_id ? `<button class="btn-play-sm" onclick="openVideoModal('https://www.youtube.com/embed/${item.youtube_id}')">▶ شاهد</button>` : ''}
                ${item.type === 'game' ? `<button class="btn-play-sm" onclick="alert('سيتم إطلاق اللعبة قريباً!')">🎮 العب</button>` : ''}
            </div>
        `;
    });
    grid.innerHTML = html;
}

document.getElementById('toggleExtraBtn').addEventListener('click', function() {
    const section = document.getElementById('extraSection');
    section.classList.toggle('hidden-extra');
    this.textContent = section.classList.contains('hidden-extra') ? '📚 استعرض كل دروس الحماية' : '📕 إخفاء الدروس';
});

// ============================================================
// موسيقى وقراءة
// ============================================================
let bgMusic = document.getElementById('bgMusic'), musicStarted = false;
document.addEventListener('click', () => {
    if (!musicStarted) { bgMusic.volume = 0.2; bgMusic.play().catch(()=>{}); musicStarted = true; }
}, { once: true });

function speakText(text) {
    if (!('speechSynthesis' in window)) return;
    window.speechSynthesis.cancel();
    const u = new SpeechSynthesisUtterance(text);
    u.lang = 'ar-SA'; u.rate = 1.1; u.pitch = 1.2;
    const voices = speechSynthesis.getVoices();
    const ar = voices.find(v => v.lang.startsWith('ar'));
    if (ar) u.voice = ar;
    speechSynthesis.speak(u);
}

// ============================================================
// بدء التشغيل
// ============================================================
renderDailyTask();
renderExtraContent();
setTimeout(() => speakText('مرحباً بطل! مهمة اليوم في انتظارك.'), 1000);

</script>

<div class="safety-footer">
  Kidora © 2026 — تعلم الحماية بذكاء ومتعة
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
