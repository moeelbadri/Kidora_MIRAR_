<?php
/**
 * ملف التذييل العام للمنصة
 * يشمل: الرفيق الدائم (الشخصية الناطقة المتحركة)، الفقاعة، وسكربتات التشغيل العامة.
 * تم تعديل الرفيق ليظهر أكبر، بدون إطار دائري، مع حركة الفم واليدين.
 */

// التأكد من بدء الجلسة في حال لم تبدأ
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

// إذا كان هناك طفل مسجل دخوله، نجلب بياناته وشخصيته النشطة
if (!empty($_SESSION['child_id'])) {
    // محاولة تضمين الملفات الأساسية إذا لم تكن مضمنة مسبقاً (آمن للتكرار)
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

            // جلب الشخصية النشطة
            $st = $pdo->prepare("SELECT * FROM characters WHERE id = ?");
            $st->execute([$activeId]);
            $activeChar = $st->fetch();

            // إذا لم توجد الشخصية النشطة، نأخذ الأولى
            if (!$activeChar && $char1 > 0) {
                $st = $pdo->prepare("SELECT * FROM characters WHERE id = ?");
                $st->execute([$char1]);
                $activeChar = $st->fetch();
            }

            $canSwap = ($char1 > 0 && $char2 > 0 && $char1 !== $char2);
        }
    }
}

// تحديد المسار الأساسي للروابط (إذا لم يُعرّف مسبقاً)
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

<!-- ============================================================
     فقاعة التنبيهات العامة
     ============================================================ -->
<div class="toast-wrap" id="toastWrap"></div>

<!-- ============================================================
     الرفيق الدائم — نسخة أكبر، بدون إطار، مع حركة الفم واليدين
     ============================================================ -->
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
            <!-- الفم المتحرك -->
            <div class="companion-mouth"></div>
            <!-- اليد اليسرى -->
            <div class="companion-hand companion-hand-left"></div>
            <!-- اليد اليمنى -->
            <div class="companion-hand companion-hand-right"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================
     الأنماط الخاصة بالرفيق والتذييل
     ============================================================ -->
<style>
/* ============================================================
   الرفيق الدائم – تصميم جديد
   ============================================================ */
#companionWidget {
  position: fixed;
  bottom: 20px;
  right: 20px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
  pointer-events: none;
}

#companionWidget * {
  pointer-events: auto;
}

.companion-col {
  display: flex;
  align-items: center;
  gap: 8px;
}

#companionSwapBtn {
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 40px;
  color: #fff;
  padding: 6px 12px;
  font-size: 14px;
  cursor: pointer;
  transition: 0.3s;
  backdrop-filter: blur(4px);
  font-weight: 700;
}

#companionSwapBtn:hover {
  background: rgba(255,255,255,0.15);
  transform: scale(1.05);
}

/* === الصورة الرئيسية (أكبر، بدون إطار) === */
#companionAvatar {
  position: relative;
  width: 100px;          /* تكبير مقارنة بـ 60px السابقة */
  height: 100px;
  border-radius: 0;      /* إزالة الإطار الدائري */
  background: transparent;
  box-shadow: none;
  cursor: pointer;
  transition: transform 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: visible;     /* نسمح للأيدي بالخروج خارج الحدود */
  filter: drop-shadow(0 4px 15px rgba(0,0,0,0.4));
}

#companionAvatar:hover {
  transform: scale(1.06);
}

#companionAvatar img,
#companionAvatar .char-emoji {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
  font-size: 80px;
  line-height: 1;
}

/* === الفم المتحرك === */
.companion-mouth {
  position: absolute;
  bottom: 18%;
  left: 50%;
  transform: translateX(-50%);
  width: 24px;
  height: 10px;
  background: #2d1b3a;
  border-radius: 0 0 20px 20px;
  transition: height 0.15s, border-radius 0.15s;
  opacity: 0.7;
  z-index: 2;
}

/* فم مفتوح أثناء التحدث */
#companionAvatar.talking .companion-mouth {
  height: 18px;
  border-radius: 50% / 60% 60% 20% 20%;
  animation: mouthTalk 0.3s infinite alternate;
}

@keyframes mouthTalk {
  0% { height: 10px; border-radius: 0 0 20px 20px; }
  100% { height: 22px; border-radius: 50% / 60% 60% 20% 20%; }
}

/* === اليدين === */
.companion-hand {
  position: absolute;
  width: 26px;
  height: 26px;
  background: rgba(255,255,255,0.05);
  border-radius: 50%;
  border: 2px solid rgba(255,255,255,0.08);
  backdrop-filter: blur(2px);
  transition: transform 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: #fff;
  z-index: 3;
}

/* اليد اليسرى (أسفل يسار) */
.companion-hand-left {
  bottom: 4%;
  left: -4%;
  transform-origin: center;
}

/* اليد اليمنى (أسفل يمين) */
.companion-hand-right {
  bottom: 4%;
  right: -4%;
  transform-origin: center;
}

/* أيقونات داخل اليدين (اختياري) */
.companion-hand-left::after {
  content: "✋";
  font-size: 14px;
  opacity: 0.6;
}
.companion-hand-right::after {
  content: "✋";
  font-size: 14px;
  opacity: 0.6;
}

/* حركة الأيدي أثناء التحدث */
#companionAvatar.talking .companion-hand-left {
  animation: handWaveLeft 0.4s infinite alternate;
}

#companionAvatar.talking .companion-hand-right {
  animation: handWaveRight 0.4s infinite alternate;
}

@keyframes handWaveLeft {
  0% { transform: rotate(0deg) translateY(0); }
  100% { transform: rotate(-20deg) translateY(-8px); }
}

@keyframes handWaveRight {
  0% { transform: rotate(0deg) translateY(0); }
  100% { transform: rotate(20deg) translateY(-8px); }
}

/* === فقاعة الكلام === */
#companionBubble {
  background: rgba(20, 10, 42, 0.85);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(167, 139, 250, 0.15);
  border-radius: 20px 20px 4px 20px;
  padding: 12px 18px;
  max-width: 240px;
  color: #f1f5f9;
  font-size: 15px;
  line-height: 1.7;
  display: none;
  margin-bottom: 4px;
  box-shadow: 0 8px 30px rgba(0,0,0,0.5);
  pointer-events: none;
}

#companionBubble.show {
  display: block;
  animation: bubbleAppear 0.3s ease;
}

@keyframes bubbleAppear {
  0% { opacity: 0; transform: translateY(12px) scale(0.92); }
  100% { opacity: 1; transform: translateY(0) scale(1); }
}

/* === حركات الشخصية الأساسية (موجودة مسبقاً في header ولكن نكررها للاستقلالية) === */
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
  0%,100% { transform: rotate(-3deg) translateY(0); }
  50% { transform: rotate(3deg) translateY(-6px); }
}
@keyframes bounce {
  0%,100% { transform: translateY(0); }
  50% { transform: translateY(-16px); }
}
@keyframes dash {
  0% { transform: translateX(0); }
  50% { transform: translateX(16px); }
  100% { transform: translateX(0); }
}
@keyframes float {
  0%,100% { transform: translateY(0) rotate(0deg); }
  50% { transform: translateY(-20px) rotate(4deg); }
}
@keyframes hop {
  0%,100% { transform: translateY(0) scale(1,1); }
  30% { transform: translateY(-22px) scale(1.1,0.9); }
  60% { transform: translateY(0) scale(0.95,1.05); }
}
@keyframes stomp {
  0%,100% { transform: translateY(0); }
  50% { transform: translateY(-10px) scale(1.05,0.95); }
}

/* استجابة للشاشات الصغيرة */
@media (max-width: 480px) {
  #companionAvatar {
    width: 80px;
    height: 80px;
  }
  #companionAvatar .char-emoji {
    font-size: 60px;
  }
  .companion-hand {
    width: 22px;
    height: 22px;
    font-size: 14px;
  }
  .companion-hand-left::after,
  .companion-hand-right::after {
    font-size: 12px;
  }
  .companion-mouth {
    width: 20px;
    height: 8px;
    bottom: 16%;
  }
  #companionBubble {
    font-size: 13px;
    max-width: 180px;
    padding: 8px 14px;
  }
}
</style>

<!-- ============================================================
     سكربتات التشغيل العامة
     ============================================================ -->
<?php if (isset($activeChar) && $activeChar): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const avatar = document.getElementById('companionAvatar');
    const bubble = document.getElementById('companionBubble');
    const swapBtn = document.getElementById('companionSwapBtn');

    if (!avatar) return;

    // ----- 1. تفعيل حركة التحدث (الفم واليدين) -----
    function startTalking() {
        avatar.classList.add('talking');
    }

    function stopTalking() {
        avatar.classList.remove('talking');
    }

    // ----- 2. ربط الحركة بـ SoundEngine (إذا كان موجوداً) -----
    if (window.SoundEngine && typeof SoundEngine.speak === 'function') {
        // نحتفظ بالدالة الأصلية
        const originalSpeak = SoundEngine.speak;

        // نستبدلها بوظيفة موسعة
        SoundEngine.speak = function(text, character, callback) {
            // تفعيل حركة التحدث
            startTalking();

            // تقدير مدة النطق (لإيقاف الحركة)
            const duration = Math.max(800, (text ? text.length : 0) * 70);
            const timer = setTimeout(function() {
                stopTalking();
            }, duration);

            // استدعاء الدالة الأصلية مع إعادة توجيه المعطيات
            // نمرر callback خاص بنا لتنظيف المؤقت عند الانتهاء المبكر
            const wrappedCallback = function() {
                clearTimeout(timer);
                stopTalking();
                if (typeof callback === 'function') callback();
            };

            return originalSpeak.call(this, text, character, wrappedCallback);
        };
    } else {
        // إذا لم يكن SoundEngine موجوداً، نضيف اختبار بالنقر على الرفيق
        console.warn('SoundEngine غير موجود، سيتم تفعيل الحركة يدوياً عند النقر.');
        avatar.addEventListener('click', function() {
            avatar.classList.toggle('talking');
            setTimeout(() => avatar.classList.remove('talking'), 2500);
        });
    }

    // ----- 3. إظهار الفقاعة (اختبار بسيط) -----
    // يمكن استدعاؤها من أي مكان خارجي مثلاً: window.showCompanionBubble('مرحباً!')
    window.showCompanionBubble = function(message, duration) {
        if (!bubble) return;
        bubble.textContent = message || 'مرحباً! كيف يمكنني مساعدتك؟';
        bubble.classList.add('show');
        clearTimeout(bubble._hideTimer);
        bubble._hideTimer = setTimeout(function() {
            bubble.classList.remove('show');
        }, duration || 4000);
    };

    // ----- 4. تبديل الشخصية (إذا كان الزر موجوداً) -----
    if (swapBtn) {
        swapBtn.addEventListener('click', function() {
            if (window.swapCompanion) {
                window.swapCompanion();
            } else {
                alert('وظيفة تبديل الرفيق غير متاحة حالياً.');
            }
        });
    }

    // ----- 5. إظهار رسالة ترحيبية قصيرة بعد 2 ثانية -----
    setTimeout(function() {
        const charName = <?php echo json_encode($activeChar['name'] ?? 'الرفيق'); ?>;
        window.showCompanionBubble('👋 أنا ' + charName + '، أنا معك في كل خطوة!');
    }, 2000);
});
</script>
<?php else: ?>
<!-- إذا لم يكن هناك رفيق، نعرف دالة وهمية لتجنب الأخطاء -->
<script>
window.showCompanionBubble = function(msg) { console.log('Companion: ' + msg); };
</script>
<?php endif; ?>

<!-- ============================================================
     تضمين السكربت العام (app.js)
     ============================================================ -->
<?php if (defined('BASE_PATH')): ?>
<script src="<?php echo BASE_PATH; ?>/assets/js/app.js"></script>
<?php else: ?>
<script src="assets/js/app.js"></script>
<?php endif; ?>

</body>
</html>
