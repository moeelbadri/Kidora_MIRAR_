<?php
/**
 * ملف التذييل العام للمنصة
 * يشمل: الرفيق الدائم (الشخصية الناطقة المتحركة) بحجم أكبر، بدون إطار، مع حركة الفم واليدين.
 * تم إصلاح مشكلة ظهوره كمربع صغير يختفي.
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
</div><!-- /.app-wrapper (إن وُجد) -->

<div class="toast-wrap" id="toastWrap"></div>

<?php if ($showCompanion && $activeChar): ?>
<div id="companionWidget">
    <div id="companionBubble"></div>
    <div class="companion-col">
        <?php if ($canSwap): ?>
        <button id="companionSwapBtn" title="بدّل الرفيق">🔄</button>
        <?php endif; ?>
        <div id="companionAvatar" class="move-<?php echo h($activeChar['move_type'] ?? 'wiggle'); ?>">
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
   الرفيق الدائم – إصلاح كامل (مربع/مستطيل، كبير، لا يختفي)
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

#companionSwapBtn {
  background: rgba(255,255,255,0.1);
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 40px;
  color: #fff;
  padding: 8px 14px;
  font-size: 16px;
  cursor: pointer;
  transition: 0.3s;
  backdrop-filter: blur(6px);
  font-weight: 700;
  box-shadow: 0 4px 15px rgba(0,0,0,0.3);
}

#companionSwapBtn:hover {
  background: rgba(255,255,255,0.2);
  transform: scale(1.05);
}

/* ===== الحاوية الرئيسية للشخصية ===== */
#companionAvatar {
  position: relative;
  width: 120px;          /* حجم كبير */
  height: 120px;
  border-radius: 0;      /* لا إطار دائري – مربع/مستطيل */
  background: transparent !important;
  box-shadow: none !important;
  cursor: pointer;
  transition: transform 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: visible;     /* الأيدي تخرج خارج الحدود */
  filter: drop-shadow(0 8px 25px rgba(0,0,0,0.5));
  /* نضمن عدم وجود أي خلفية أو حدود */
  border: none;
  outline: none;
}

#companionAvatar:hover {
  transform: scale(1.08);
}

/* الصورة أو الإيموجي */
#companionAvatar img,
#companionAvatar .char-emoji {
  width: 100%;
  height: 100%;
  object-fit: contain;   /* تحافظ على النسبة مع ملء الحاوية */
  display: block;
  font-size: 90px;
  line-height: 1;
  border-radius: 0;      /* لا تقليم دائري */
}

/* ===== الفم المتحرك ===== */
.companion-mouth {
  position: absolute;
  bottom: 20%;
  left: 50%;
  transform: translateX(-50%);
  width: 28px;
  height: 12px;
  background: #2d1b3a;
  border-radius: 0 0 25px 25px;
  transition: height 0.15s, border-radius 0.15s;
  opacity: 0.8;
  z-index: 2;
  pointer-events: none;
}

#companionAvatar.talking .companion-mouth {
  height: 22px;
  border-radius: 50% / 60% 60% 20% 20%;
  animation: mouthTalk 0.3s infinite alternate;
}

@keyframes mouthTalk {
  0% { height: 12px; border-radius: 0 0 25px 25px; }
  100% { height: 26px; border-radius: 50% / 60% 60% 20% 20%; }
}

/* ===== اليدين ===== */
.companion-hand {
  position: absolute;
  width: 30px;
  height: 30px;
  background: rgba(255,255,255,0.08);
  border-radius: 50%;
  border: 2px solid rgba(255,255,255,0.1);
  backdrop-filter: blur(2px);
  transition: transform 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  color: #fff;
  z-index: 3;
  pointer-events: none;
  box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.companion-hand-left {
  bottom: 2%;
  left: -5%;
  transform-origin: center;
}

.companion-hand-right {
  bottom: 2%;
  right: -5%;
  transform-origin: center;
}

.companion-hand-left::after {
  content: "✋";
  font-size: 16px;
  opacity: 0.7;
}
.companion-hand-right::after {
  content: "✋";
  font-size: 16px;
  opacity: 0.7;
}

#companionAvatar.talking .companion-hand-left {
  animation: handWaveLeft 0.4s infinite alternate;
}
#companionAvatar.talking .companion-hand-right {
  animation: handWaveRight 0.4s infinite alternate;
}

@keyframes handWaveLeft {
  0% { transform: rotate(0deg) translateY(0); }
  100% { transform: rotate(-18deg) translateY(-10px); }
}
@keyframes handWaveRight {
  0% { transform: rotate(0deg) translateY(0); }
  100% { transform: rotate(18deg) translateY(-10px); }
}

/* ===== فقاعة الكلام ===== */
#companionBubble {
  background: rgba(20, 10, 42, 0.9);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(167, 139, 250, 0.2);
  border-radius: 20px 20px 4px 20px;
  padding: 14px 20px;
  max-width: 260px;
  color: #f1f5f9;
  font-size: 16px;
  line-height: 1.7;
  display: none;
  margin-bottom: 6px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.6);
  pointer-events: none;
}

#companionBubble.show {
  display: block;
  animation: bubbleAppear 0.3s ease;
}

@keyframes bubbleAppear {
  0% { opacity: 0; transform: translateY(15px) scale(0.9); }
  100% { opacity: 1; transform: translateY(0) scale(1); }
}

/* ===== حركات الشخصية الأساسية ===== */
.move-wiggle {
  animation: wiggle 2s infinite ease-in-out;
}
.move-bounce {
  animation: bounce 2s infinite ease;
}
.move-dash {
  animation: dash 3s infinite linear;
}
.move-float {
  animation: float 3s infinite ease-in-out;
}
.move-hop {
  animation: hop 1.8s infinite ease;
}
.move-stomp {
  animation: stomp 2s infinite ease;
}

@keyframes wiggle {
  0%,100% { transform: rotate(-4deg) translateY(0); }
  50% { transform: rotate(4deg) translateY(-8px); }
}
@keyframes bounce {
  0%,100% { transform: translateY(0); }
  50% { transform: translateY(-20px); }
}
@keyframes dash {
  0% { transform: translateX(0); }
  50% { transform: translateX(20px); }
  100% { transform: translateX(0); }
}
@keyframes float {
  0%,100% { transform: translateY(0) rotate(0deg); }
  50% { transform: translateY(-25px) rotate(5deg); }
}
@keyframes hop {
  0%,100% { transform: translateY(0) scale(1,1); }
  30% { transform: translateY(-25px) scale(1.1,0.9); }
  60% { transform: translateY(0) scale(0.95,1.05); }
}
@keyframes stomp {
  0%,100% { transform: translateY(0); }
  50% { transform: translateY(-12px) scale(1.05,0.95); }
}

/* استجابة للشاشات الصغيرة */
@media (max-width: 480px) {
  #companionAvatar {
    width: 90px;
    height: 90px;
  }
  #companionAvatar .char-emoji {
    font-size: 70px;
  }
  .companion-hand {
    width: 24px;
    height: 24px;
    font-size: 16px;
  }
  .companion-hand-left::after,
  .companion-hand-right::after {
    font-size: 13px;
  }
  .companion-mouth {
    width: 22px;
    height: 10px;
    bottom: 18%;
  }
  #companionBubble {
    font-size: 14px;
    max-width: 180px;
    padding: 10px 14px;
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
    const swapBtn = document.getElementById('companionSwapBtn');

    if (!avatar) return;

    // ----- 1. تفعيل حركة التحدث -----
    function startTalking() {
        avatar.classList.add('talking');
    }

    function stopTalking() {
        avatar.classList.remove('talking');
    }

    // ----- 2. ربط الحركة بـ SoundEngine -----
    if (window.SoundEngine && typeof SoundEngine.speak === 'function') {
        const originalSpeak = SoundEngine.speak;
        SoundEngine.speak = function(text, character, callback) {
            startTalking();
            const duration = Math.max(800, (text ? text.length : 0) * 70);
            const timer = setTimeout(function() {
                stopTalking();
            }, duration);

            const wrappedCallback = function() {
                clearTimeout(timer);
                stopTalking();
                if (typeof callback === 'function') callback();
            };

            return originalSpeak.call(this, text, character, wrappedCallback);
        };
    } else {
        // اختبار عند النقر على الرفيق
        avatar.addEventListener('click', function() {
            avatar.classList.toggle('talking');
            setTimeout(() => avatar.classList.remove('talking'), 2500);
        });
    }

    // ----- 3. فقاعة الكلام (دالة عالمية) -----
    window.showCompanionBubble = function(message, duration) {
        if (!bubble) return;
        bubble.textContent = message || 'مرحباً!';
        bubble.classList.add('show');
        clearTimeout(bubble._hideTimer);
        bubble._hideTimer = setTimeout(function() {
            bubble.classList.remove('show');
        }, duration || 4000);
    };

    // ----- 4. تبديل الشخصية -----
    if (swapBtn) {
        swapBtn.addEventListener('click', function() {
            if (window.swapCompanion) {
                window.swapCompanion();
            } else {
                alert('وظيفة تبديل الرفيق غير متاحة حالياً.');
            }
        });
    }

    // ----- 5. رسالة ترحيب -----
    setTimeout(function() {
        const charName = <?php echo json_encode($activeChar['name'] ?? 'الرفيق'); ?>;
        window.showCompanionBubble('👋 أنا ' + charName + '، أنا معك في كل خطوة!');
    }, 2000);
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
