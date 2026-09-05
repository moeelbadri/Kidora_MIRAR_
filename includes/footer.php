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

            // تحميل الشخصيتين مرة واحدة حتى يتم التبديل فورياً بدون reload
            $companionChars = [];
            $charIds = array_values(array_unique(array_filter([$char1, $char2])));
            if ($charIds) {
                $placeholders = implode(',', array_fill(0, count($charIds), '?'));
                $st = $pdo->prepare("SELECT * FROM characters WHERE id IN ($placeholders)");
                $st->execute($charIds);
                foreach ($st->fetchAll() as $row) {
                    $companionChars[(int)$row['id']] = $row;
                }
            }

            $activeChar = $companionChars[$activeId] ?? null;

            if (!$activeChar && $char1 > 0) {
                $activeId = $char1;
                $activeChar = $companionChars[$char1] ?? null;
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
        <div id="companionAvatar"
             class="move-<?php echo h($activeChar['move_type'] ?? 'wiggle'); ?>"
             data-char1="<?php echo $char1; ?>"
             data-char2="<?php echo $char2; ?>"
             data-active="<?php echo $activeId; ?>"
             data-can-swap="<?php echo $canSwap ? '1' : '0'; ?>"
             aria-label="الرفيق، اضغط للتحدث وإيقاف الحركة مؤقتاً، واضغط مرة أخرى للتبديل">
            <div class="companion-character-stage">
                <?php if (!empty($activeChar['image_path'])): ?>
                    <img id="companionImage"
                         src="<?php echo BASE_PATH . '/' . h($activeChar['image_path']); ?>"
                         alt="<?php echo h($activeChar['name']); ?>">
                <?php else: ?>
                    <span id="companionEmoji" class="char-emoji"><?php echo character_icons($activeChar)[0] ?? '✨'; ?></span>
                <?php endif; ?>
            </div>
            <span class="companion-status-dot" aria-hidden="true"></span>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* ============================================================
   KIDORA COMPANION — ثابت، كبير، لا يختفي، وتبديل فوري
   ============================================================ */

#companionWidget {
    position: fixed;
    right: clamp(16px, 3vw, 38px);
    bottom: clamp(16px, 3vw, 32px);
    z-index: 999999;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    pointer-events: none;
}

#companionWidget * {
    pointer-events: auto;
}

.companion-col {
    display: flex;
    align-items: flex-end;
    justify-content: flex-end;
}

/* مساحة أكبر فعلياً للشخصية — بدون إطار أو خلفية */
#companionAvatar {
    position: relative;
    width: 205px;
    height: 205px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: transparent !important;
    border: 0 !important;
    outline: 0 !important;
    box-shadow: none !important;
    filter: drop-shadow(0 14px 28px rgba(0,0,0,.30));
    user-select: none;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
    isolation: isolate;
}

/* المسرح ثابت الحجم حتى لا يحصل jump/اختفاء أثناء التبديل */
.companion-character-stage {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    transform-origin: 50% 85%;
    will-change: transform;
}

/* الصورة نفسها هي الشخصية — لا نركب فماً أو يدين صناعيين فوقها */
#companionAvatar img,
#companionAvatar .char-emoji {
    width: 100%;
    height: 100%;
    max-width: none;
    object-fit: contain;
    object-position: center bottom;
    display: block;
    border: 0;
    background: transparent;
}

#companionAvatar .char-emoji {
    font-size: 165px;
    line-height: 1;
}

/* نقطة صغيرة فقط لمعرفة أن الرفيق تفاعلي */
.companion-status-dot {
    position: absolute;
    right: 13px;
    bottom: 16px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #34d399;
    box-shadow: 0 0 0 4px rgba(52,211,153,.14);
    opacity: .9;
    transition: transform .2s ease, background .2s ease;
}

#companionAvatar:hover {
    filter: drop-shadow(0 16px 34px rgba(0,0,0,.38));
}

#companionAvatar:hover .companion-character-stage {
    transform: scale(1.035);
}

/* ------------------------------------------------------------
   الحركة: الشخصية لا تختفي أبداً، فقط تتحرك داخل مكانها.
   عند paused تتوقف فوراً في نفس الإطار.
   ------------------------------------------------------------ */
#companionAvatar.move-wiggle .companion-character-stage {
    animation: companionWiggle 2.2s infinite ease-in-out;
}
#companionAvatar.move-bounce .companion-character-stage {
    animation: companionBounce 2.3s infinite ease-in-out;
}
#companionAvatar.move-dash .companion-character-stage {
    animation: companionDash 3.2s infinite ease-in-out;
}
#companionAvatar.move-float .companion-character-stage {
    animation: companionFloat 3.5s infinite ease-in-out;
}
#companionAvatar.move-hop .companion-character-stage {
    animation: companionHop 2.1s infinite ease-in-out;
}
#companionAvatar.move-stomp .companion-character-stage {
    animation: companionStomp 2.2s infinite ease-in-out;
}

#companionAvatar.is-paused .companion-character-stage {
    animation-play-state: paused !important;
}

#companionAvatar.is-paused .companion-status-dot {
    background: #facc15;
    transform: scale(1.25);
}

@keyframes companionWiggle {
    0%, 100% { transform: translate3d(0,0,0) rotate(-4deg); }
    50% { transform: translate3d(0,-8px,0) rotate(4deg); }
}

@keyframes companionBounce {
    0%, 100% { transform: translate3d(0,0,0); }
    50% { transform: translate3d(0,-18px,0); }
}

@keyframes companionDash {
    0%, 100% { transform: translate3d(0,0,0); }
    50% { transform: translate3d(16px,-3px,0); }
}

@keyframes companionFloat {
    0%, 100% { transform: translate3d(0,0,0) rotate(0); }
    50% { transform: translate3d(0,-20px,0) rotate(3deg); }
}

@keyframes companionHop {
    0%, 100% { transform: translate3d(0,0,0) scale(1); }
    30% { transform: translate3d(0,-22px,0) scale(1.04,.98); }
    60% { transform: translate3d(0,0,0) scale(.98,1.02); }
}

@keyframes companionStomp {
    0%, 100% { transform: translate3d(0,0,0); }
    50% { transform: translate3d(0,-12px,0) scale(1.025,.99); }
}

/* ------------------------------------------------------------
   أثناء الكلام:
   لا نضيف فم/يدين مزيفين. نستخدم حركة جسم خفيفة + نبضة
   على الشخصية نفسها، وهذا أنظف مع اختلاف أشكال الشخصيات.
   ------------------------------------------------------------ */
#companionAvatar.talking .companion-character-stage {
    animation: companionTalking .28s infinite alternate ease-in-out !important;
}

#companionAvatar.is-paused.talking .companion-character-stage {
    animation-play-state: running !important;
}

@keyframes companionTalking {
    from { transform: translate3d(0,0,0) scale(1); }
    to   { transform: translate3d(0,-2px,0) scale(1.018); }
}

/* ------------------------------------------------------------
   فقاعة الكلام
   ------------------------------------------------------------ */
#companionBubble {
    background: rgba(15,23,42,.95);
    backdrop-filter: blur(14px);
    border: 1px solid rgba(167,139,250,.30);
    border-radius: 20px 20px 5px 20px;
    padding: 12px 18px;
    max-width: min(320px, 72vw);
    color: #f8fafc;
    font-size: 15px;
    line-height: 1.75;
    display: none;
    margin: 0 8px 8px 0;
    box-shadow: 0 14px 45px rgba(0,0,0,.35);
    pointer-events: none;
    text-align: right;
}

#companionBubble.show {
    display: block;
    animation: bubbleAppear .22s ease-out;
}

@keyframes bubbleAppear {
    from { opacity: 0; transform: translate3d(0,8px,0) scale(.97); }
    to   { opacity: 1; transform: translate3d(0,0,0) scale(1); }
}

/* زر التبديل ليس ضرورياً؛ النقر الثاني على الشخصية نفسها يبدلها */

@media (max-width: 768px) {
    #companionAvatar {
        width: 165px;
        height: 165px;
    }

    #companionAvatar .char-emoji {
        font-size: 135px;
    }

    #companionBubble {
        max-width: 68vw;
        font-size: 13px;
        padding: 10px 14px;
    }

    .companion-status-dot {
        right: 8px;
        bottom: 10px;
        width: 8px;
        height: 8px;
    }
}

@media (max-width: 480px) {
    #companionWidget {
        right: 8px;
        bottom: 10px;
    }

    #companionAvatar {
        width: 140px;
        height: 140px;
    }

    #companionAvatar .char-emoji {
        font-size: 112px;
    }

    #companionBubble {
        max-width: 72vw;
        font-size: 12px;
        line-height: 1.65;
        padding: 8px 11px;
        margin-right: 3px;
    }
}

@media (max-width: 360px) {
    #companionAvatar {
        width: 125px;
        height: 125px;
    }

    #companionAvatar .char-emoji {
        font-size: 100px;
    }
}

@media (prefers-reduced-motion: reduce) {
    #companionAvatar .companion-character-stage {
        animation-duration: 1ms !important;
        animation-iteration-count: 1 !important;
    }
}
</style>

<?php if (isset($activeChar) && $activeChar): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const avatar = document.getElementById('companionAvatar');
    const bubble = document.getElementById('companionBubble');
    const image = document.getElementById('companionImage');
    const emoji = document.getElementById('companionEmoji');

    if (!avatar) return;

    const BASE = <?php echo json_encode(rtrim(BASE_PATH, '/')); ?>;

    const companions = <?php
        $jsChars = [];
        foreach ([$char1, $char2] as $cid) {
            if ($cid > 0 && isset($companionChars[$cid])) {
                $c = $companionChars[$cid];
                $icon = function_exists('character_icons') ? (character_icons($c)[0] ?? '✨') : '✨';
                $jsChars[(string)$cid] = [
                    'id' => (int)$c['id'],
                    'name' => (string)($c['name'] ?? 'الرفيق'),
                    'image' => !empty($c['image_path']) ? BASE_PATH . '/' . ltrim((string)$c['image_path'], '/') : '',
                    'emoji' => $icon,
                    'move' => (string)($c['move_type'] ?? 'wiggle')
                ];
            }
        }
        echo json_encode($jsChars, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ?>;

    let activeId = parseInt(avatar.dataset.active || '0', 10);
    let isPaused = false;
    let isTalking = false;
    let talkTimeout = null;
    let bubbleTimer = null;
    let swapLock = false;

    function showBubble(message, duration = 3500) {
        if (!bubble) return;
        bubble.textContent = message || 'مرحباً!';
        bubble.classList.add('show');

        clearTimeout(bubbleTimer);
        bubbleTimer = setTimeout(function () {
            bubble.classList.remove('show');
        }, duration);
    }

    window.showCompanionBubble = showBubble;

    function pauseCompanion() {
        isPaused = true;
        avatar.classList.add('is-paused');
    }

    function resumeCompanion() {
        isPaused = false;
        avatar.classList.remove('is-paused');
    }

    function stopTalking() {
        isTalking = false;
        avatar.classList.remove('talking');

        if (talkTimeout) {
            clearTimeout(talkTimeout);
            talkTimeout = null;
        }

        resumeCompanion();
    }

    function startTalking(text) {
        if (isTalking) return;

        isTalking = true;
        pauseCompanion();
        avatar.classList.add('talking');

        // SoundEngine هو المسؤول عن الصوت الحقيقي إن كان متوفراً.
        // المؤقت هنا فقط fallback حتى لا تبقى الشخصية متوقفة.
        talkTimeout = setTimeout(stopTalking, 3200);

        if (window.SoundEngine && typeof window.SoundEngine.speak === 'function' && text) {
            try {
                window.SoundEngine.speak(text, companions[String(activeId)] || null, function () {
                    stopTalking();
                });
            } catch (e) {
                // fallback timer
            }
        }
    }

    function renderCharacter(charId, announce = true) {
        const c = companions[String(charId)];
        if (!c) return false;

        activeId = c.id;
        avatar.dataset.active = String(c.id);

        // تغيير المحتوى داخل نفس العنصر = لا يوجد اختفاء ولا reload
        if (c.image) {
            if (!image) {
                const newImg = document.createElement('img');
                newImg.id = 'companionImage';
                newImg.alt = c.name;
                newImg.loading = 'eager';
                newImg.decoding = 'async';
                document.querySelector('.companion-character-stage').replaceChildren(newImg);
            }
            const currentImage = document.getElementById('companionImage');
            if (currentImage) {
                currentImage.src = c.image;
                currentImage.alt = c.name;
                currentImage.style.display = 'block';
            }
        } else {
            const stage = document.querySelector('.companion-character-stage');
            stage.innerHTML = '';
            const span = document.createElement('span');
            span.id = 'companionEmoji';
            span.className = 'char-emoji';
            span.textContent = c.emoji || '✨';
            stage.appendChild(span);
        }

        // إعادة الحركة حسب الشخصية الجديدة بدون إعادة تحميل الصفحة
        avatar.className = '';
        avatar.id = 'companionAvatar';
        avatar.classList.add('move-' + (c.move || 'wiggle'));
        if (isPaused) avatar.classList.add('is-paused');
        if (isTalking) avatar.classList.add('talking');

        if (announce) {
            showBubble('✨ الآن معك ' + c.name + '!', 2200);
        }

        return true;
    }

    function swapCharacter() {
        if (swapLock) return;

        const id1 = parseInt(avatar.dataset.char1 || '0', 10);
        const id2 = parseInt(avatar.dataset.char2 || '0', 10);
        const nextId = activeId === id1 ? id2 : id1;

        if (!id1 || !id2 || !companions[String(nextId)]) {
            showBubble('👋 أنا هنا معك!');
            return;
        }

        swapLock = true;
        stopTalking();
        pauseCompanion();

        if (renderCharacter(nextId, true)) {
            // نخلي الشخصية الجديدة ثابتة للحظة حتى تكون جاهزة ثم تستأنف
            setTimeout(function () {
                resumeCompanion();
            }, 700);
        }

        setTimeout(function () {
            swapLock = false;
        }, 800);
    }

    // جعل الدالة متاحة لو احتاجتها أجزاء أخرى من المنصة
    window.swapCompanion = swapCharacter;

    // النقر الأول: توقف + تتكلم.
    // النقر الثاني أثناء التوقف: تبديل فوري للشخصية بدون reload.
    avatar.addEventListener('click', function () {
        if (swapLock) return;

        if (isPaused) {
            swapCharacter();
            return;
        }

        pauseCompanion();

        const c = companions[String(activeId)];
        const text = '👋 أنا ' + (c?.name || 'رفيقك') + '، أنا معك! اضغط مرة ثانية لتبديل الشخصية.';
        showBubble(text, 3600);
        startTalking(text);
    });

    // دعم لوحة المفاتيح
    avatar.setAttribute('tabindex', '0');
    avatar.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            avatar.click();
        }
    });

    // ربط SoundEngine بدون كسر أي استدعاءات موجودة في المنصة
    if (window.SoundEngine && typeof window.SoundEngine.speak === 'function') {
        const originalSpeak = window.SoundEngine.speak.bind(window.SoundEngine);

        window.SoundEngine.speak = function (text, character, callback) {
            pauseCompanion();
            avatar.classList.add('talking');
            isTalking = true;

            if (talkTimeout) clearTimeout(talkTimeout);
            talkTimeout = setTimeout(stopTalking, 7000);

            const wrappedCallback = function () {
                stopTalking();
                if (typeof callback === 'function') callback();
            };

            try {
                return originalSpeak(text, character, wrappedCallback);
            } catch (e) {
                stopTalking();
                if (typeof callback === 'function') callback();
            }
        };
    }

    // ترحيب مرة واحدة، مع بقاء الشخصية ظاهرة دائماً
    setTimeout(function () {
        const c = companions[String(activeId)];
        if (c) {
            showBubble('👋 أنا ' + c.name + '، أنا معك في كل خطوة!', 3200);
            startTalking('أنا ' + c.name + '، أنا معك في كل خطوة!');
        }
    }, 1200);

    window.addEventListener('beforeunload', function () {
        if (talkTimeout) clearTimeout(talkTimeout);
        clearTimeout(bubbleTimer);
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
