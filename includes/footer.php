<?php
/**
 * ملف التذييل العام للمنصة
 * يشمل: الرفيق الدائم (الشخصية الناطقة المتحركة) بحجم أكبر، بدون إطار،
 * مع حركة الفم واليدين، وإمكانية تبديل الشخصية بالنقر على الرفيق نفسه.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// متغيرات الرفيق
$showCompanion = false;
$activeChar = null;
$childData = null;
$canSwap = false;
$char1 = 0;
$char2 = 0;

if (!empty($_SESSION['child_id'])) {
    if (!isset($pdo) && file_exists(__DIR__ . '/../config/db.php')) {
        require_once __DIR__ . '/../config/db.php';
    }
    if (!function_exists('character_icons') && file_exists(__DIR__ . '/functions.php')) {
        require_once __DIR__ . '/functions.php';
    }
    if (!defined('BASE_PATH') && file_exists(__DIR__ . '/../config/config.php')) {
        require_once __DIR__ . '/../config/config.php';
    }

    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT * FROM children WHERE id = ?");
        $stmt->execute([$_SESSION['child_id']]);
        $childData = $stmt->fetch();

        if ($childData) {
            $showCompanion = true;
            $char1 = (int)$childData['character_1'];
            $char2 = (int)$childData['character_2'];
            $activeId = (int)$childData['active_character'];

            $st = $pdo->prepare("SELECT * FROM characters WHERE id = ?");
            $st->execute([$activeId]);
            $activeChar = $st->fetch();

            if (!$activeChar && $char1 > 0) {
                $st = $pdo->prepare("SELECT * FROM characters WHERE id = ?");
                $st->execute([$char1]);
                $activeChar = $st->fetch();
            }

            $canSwap = ($char1 > 0 && $char2 > 0 && $char1 !== $char2);
        }
    }
}

if (!defined('BASE_PATH')) {
    $__docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $__projectRoot = realpath(__DIR__ . '/..');
    $__base = '';
    if ($__projectRoot && $__docRoot && strpos($__projectRoot, $__docRoot) === 0) {
        $__base = substr($__projectRoot, strlen($__docRoot));
        $__base = str_replace('\\', '/', $__base);
        if ($__base === '/' ) $__base = '';
    }
    define('BASE_PATH', $__base);
}
?>
</div><!-- /.app-wrapper -->

<div class="toast-wrap" id="toastWrap"></div>

<?php if ($showCompanion && $activeChar): ?>
<div id="companionWidget">
    <div id="companionBubble"></div>
    <div class="companion-col">
        <!-- إزالة زر التبديل المنفصل وجعل النقر على الرفيق هو الذي يبدل -->
        <div id="companionAvatar" class="move-<?php echo h($activeChar['move_type'] ?? 'wiggle'); ?>" data-char1="<?php echo $char1; ?>" data-char2="<?php echo $char2; ?>" data-active="<?php echo $activeId; ?>">
            <?php if (!empty($activeChar['image_path'])): ?>
                <img src="<?php echo BASE_PATH . '/' . h($activeChar['image_path']); ?>" alt="<?php echo h($activeChar['name']); ?>">
            <?php else: ?>
                <span class="char-emoji"><?php echo character_icons($activeChar)[0] ?? '✨'; ?></span>
            <?php endif; ?>
            <div class="companion-mouth"></div>
            <div class="companion-hand companion-hand-left"></div>
            <div class="companion-hand companion-hand-right"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* ============================================================
   الرفيق الدائم – إصلاح كامل (كبير، لا يختفي، نقر سهل)
   ============================================================ */
#companionWidget {
  position: fixed;
  bottom: 30px;
  right: 30px;
  z-index: 999999;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 10px;
  pointer-events: none;
}

#companionWidget * {
  pointer-events: auto;
}

.companion-col {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* ===== الحاوية الرئيسية للشخصية – حجم أكبر ===== */
#companionAvatar {
  position: relative;
  width: 140px;          /* حجم كبير جداً */
  height: 140px;
  border-radius: 0;      /* لا إطار دائري – مربع/مستطيل */
  background: transparent !important;
  box-shadow: none !important;
  cursor: pointer;
  transition: transform 0.3s, filter 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: visible;
  filter: drop-shadow(0 10px 30px rgba(0,0,0,0.6));
  border: none;
  outline: none;
  /* زيادة مساحة النقر عبر padding */
  padding: 10px;
  margin: -10px; /* تعويض padding ليبقى الحجم المرئي كما هو */
}

#companionAvatar:hover {
  transform: scale(1.08);
  filter: drop-shadow(0 12px 40px rgba(167,139,250,0.3));
}

/* الصورة أو الإيموجي */
#companionAvatar img,
#companionAvatar .char-emoji {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
  font-size: 110px;
  line-height: 1;
  border-radius: 0;
}

/* ===== الفم المتحرك ===== */
.companion-mouth {
  position: absolute;
  bottom: 20%;
  left: 50%;
  transform: translateX(-50%);
  width: 32px;
  height: 14px;
  background: #2d1b3a;
  border-radius: 0 0 30px 30px;
  transition: height 0.15s, border-radius 0.15s;
  opacity: 0.8;
  z-index: 2;
  pointer-events: none;
}

#companionAvatar.talking .companion-mouth {
  height: 26px;
  border-radius: 50% / 60% 60% 20% 20%;
  animation: mouthTalk 0.3s infinite alternate;
}

@keyframes mouthTalk {
  0% { height: 14px; border-radius: 0 0 30px 30px; }
  100% { height: 30px; border-radius: 50% / 60% 60% 20% 20%; }
}

/* ===== اليدين ===== */
.companion-hand {
  position: absolute;
  width: 36px;
  height: 36px;
  background: rgba(255,255,255,0.1);
  border-radius: 50%;
  border: 2px solid rgba(255,255,255,0.12);
  backdrop-filter: blur(4px);
  transition: transform 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  color: #fff;
  z-index: 3;
  pointer-events: none;
  box-shadow: 0 4px 15px rgba(0,0,0,0.3);
}

.companion-hand-left {
  bottom: 2%;
  left: -6%;
  transform-origin: center;
}

.companion-hand-right {
  bottom: 2%;
  right: -6%;
  transform-origin: center;
}

.companion-hand-left::after {
  content: "✋";
  font-size: 18px;
  opacity: 0.8;
}
.companion-hand-right::after {
  content: "✋";
  font-size: 18px;
  opacity: 0.8;
}

#companionAvatar.talking .companion-hand-left {
  animation: handWaveLeft 0.5s infinite alternate;
}
#companionAvatar.talking .companion-hand-right {
  animation: handWaveRight 0.5s infinite alternate;
}

@keyframes handWaveLeft {
  0% { transform: rotate(0deg) translateY(0); }
  100% { transform: rotate(-20deg) translateY(-12px); }
}
@keyframes handWaveRight {
  0% { transform: rotate(0deg) translateY(0); }
  100% { transform: rotate(20deg) translateY(-12px); }
}

/* ===== فقاعة الكلام ===== */
#companionBubble {
  background: rgba(20, 10, 42, 0.92);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(167, 139, 250, 0.25);
  border-radius: 20px 20px 4px 20px;
  padding: 14px 22px;
  max-width: 280px;
  color: #f1f5f9;
  font-size: 16px;
  line-height: 1.8;
  display: none;
  margin-bottom: 8px;
  box-shadow: 0 12px 50px rgba(0,0,0,0.7);
  pointer-events: none;
}

#companionBubble.show {
  display: block;
  animation: bubbleAppear 0.35s ease;
}

@keyframes bubbleAppear {
  0% { opacity: 0; transform: translateY(20px) scale(0.9); }
  100% { opacity: 1; transform: translateY(0) scale(1); }
}

/* ===== حركات الشخصية الأساسية (تبقى كما هي مع زيادة السعة) ===== */
.move-wiggle {
  animation: wiggle 2s infinite ease-in-out;
}
.move-bounce {
  animation: bounce 2.2s infinite ease;
}
.move-dash {
  animation: dash 3.5s infinite linear;
}
.move-float {
  animation: float 3.5s infinite ease-in-out;
}
.move-hop {
  animation: hop 2s infinite ease;
}
.move-stomp {
  animation: stomp 2.2s infinite ease;
}

@keyframes wiggle {
  0%,100% { transform: rotate(-5deg) translateY(0); }
  50% { transform: rotate(5deg) translateY(-10px); }
}
@keyframes bounce {
  0%,100% { transform: translateY(0); }
  50% { transform: translateY(-25px); }
}
@keyframes dash {
  0% { transform: translateX(0); }
  50% { transform: translateX(25px); }
  100% { transform: translateX(0); }
}
@keyframes float {
  0%,100% { transform: translateY(0) rotate(0deg); }
  50% { transform: translateY(-30px) rotate(6deg); }
}
@keyframes hop {
  0%,100% { transform: translateY(0) scale(1,1); }
  30% { transform: translateY(-30px) scale(1.12,0.88); }
  60% { transform: translateY(0) scale(0.95,1.05); }
}
@keyframes stomp {
  0%,100% { transform: translateY(0); }
  50% { transform: translateY(-15px) scale(1.06,0.94); }
}

/* استجابة للشاشات الصغيرة */
@media (max-width: 480px) {
  #companionAvatar {
    width: 100px;
    height: 100px;
    padding: 6px;
    margin: -6px;
  }
  #companionAvatar .char-emoji {
    font-size: 80px;
  }
  .companion-hand {
    width: 28px;
    height: 28px;
    font-size: 18px;
  }
  .companion-hand-left::after,
  .companion-hand-right::after {
    font-size: 15px;
  }
  .companion-mouth {
    width: 26px;
    height: 12px;
    bottom: 18%;
  }
  #companionBubble {
    font-size: 14px;
    max-width: 200px;
    padding: 10px 16px;
  }
  #companionWidget {
    bottom: 15px;
    right: 15px;
  }
}
</style>

<?php if (isset($activeChar) && $activeChar): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const avatar = document.getElementById('companionAvatar');
    const bubble = document.getElementById('companionBubble');

    if (!avatar) return;

    // ===== 1. متغيرات التحكم =====
    let talkTimeout = null;
    let isTalking = false;

    // ===== 2. وظائف حركة التحدث =====
    function startTalking() {
        if (isTalking) return;
        isTalking = true;
        avatar.classList.add('talking');
        // إلغاء أي مؤقت سابق
        if (talkTimeout) clearTimeout(talkTimeout);
        // بعد 3 ثوانٍ نوقف الحركة
        talkTimeout = setTimeout(function() {
            stopTalking();
        }, 3000);
    }

    function stopTalking() {
        isTalking = false;
        avatar.classList.remove('talking');
        if (talkTimeout) {
            clearTimeout(talkTimeout);
            talkTimeout = null;
        }
    }

    // ===== 3. ربط الحركة بـ SoundEngine =====
    if (window.SoundEngine && typeof SoundEngine.speak === 'function') {
        const originalSpeak = SoundEngine.speak;
        SoundEngine.speak = function(text, character, callback) {
            startTalking(); // تشغيل الحركة لمدة 3 ثوانٍ
            const wrappedCallback = function() {
                stopTalking();
                if (typeof callback === 'function') callback();
            };
            return originalSpeak.call(this, text, character, wrappedCallback);
        };
    } else {
        // اختبار عند النقر (ننشط الحركة لمدة 3 ثوانٍ)
        avatar.addEventListener('click', function() {
            startTalking();
        });
    }

    // ===== 4. تبديل الشخصية عند النقر على الرفيق =====
    avatar.addEventListener('click', function(e) {
        // منع تنفيذ الاختبار أعلاه إذا كان SoundEngine غير موجود
        // لكننا نضيف وظيفة التبديل هنا
        const char1 = parseInt(avatar.dataset.char1, 10);
        const char2 = parseInt(avatar.dataset.char2, 10);
        if (char1 && char2 && char1 !== char2) {
            // نعرض رسالة تبديل
            window.showCompanionBubble('🔄 جارٍ تبديل الرفيق...');
            // استدعاء دالة التبديل (نفترض وجودها في التطبيق)
            if (window.swapCompanion) {
                window.swapCompanion();
            } else {
                // بديل: إرسال طلب إلى الخادم لتبديل الشخصية
                fetch(window.KIDAURA_BASE + '/api/swap-companion.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({})
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        window.showCompanionBubble('⚠️ لم نتمكن من التبديل، حاول مرة أخرى.');
                    }
                })
                .catch(() => {
                    window.showCompanionBubble('⚠️ حدث خطأ، حاول لاحقاً.');
                });
            }
        } else {
            // إذا كان هناك شخصية واحدة فقط، نعرض رسالة ترحيبية
            window.showCompanionBubble('👋 أنا هنا لمساعدتك!');
        }
    });

    // ===== 5. فقاعة الكلام (دالة عالمية) =====
    window.showCompanionBubble = function(message, duration) {
        if (!bubble) return;
        bubble.textContent = message || 'مرحباً!';
        bubble.classList.add('show');
        clearTimeout(bubble._hideTimer);
        bubble._hideTimer = setTimeout(function() {
            bubble.classList.remove('show');
        }, duration || 4000);
    };

    // ===== 6. رسالة ترحيب بعد 2 ثانية =====
    setTimeout(function() {
        const charName = <?php echo json_encode($activeChar['name'] ?? 'الرفيق'); ?>;
        window.showCompanionBubble('👋 أنا ' + charName + '، أنا معك في كل خطوة!');
        // تشغيل حركة التحدث للترحيب
        startTalking();
    }, 2000);

    // ===== 7. إيقاف الحركة عند مغادرة الصفحة (تنظيف) =====
    window.addEventListener('beforeunload', function() {
        if (talkTimeout) clearTimeout(talkTimeout);
        stopTalking();
    });
});
</script>
<?php else: ?>
<script>
window.showCompanionBubble = function(msg) { console.log('Companion: ' + msg); };
</script>
<?php endif; ?>

<?php if (defined('BASE_PATH')): ?>
<script src="<?php echo BASE_PATH; ?>/assets/js/app.js"></script>
<?php else: ?>
<script src="assets/js/app.js"></script>
<?php endif; ?>

</body>
</html>
