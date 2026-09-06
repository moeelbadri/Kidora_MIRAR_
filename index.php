<!-- ==========================================================
     SECTION 1: فيديو كامل الشاشة (بدون نصوص)
     ========================================================== -->
<section class="video-fullscreen" id="videoSection">
    <div class="video-wrapper">
        <!-- فيديو يوتيوب كخلفية -->
        <iframe 
            src="https://www.youtube.com/embed/XIQBQk6F-ok?autoplay=1&mute=1&loop=1&playlist=XIQBQk6F-ok&controls=0&showinfo=0&rel=0&modestbranding=1" 
            frameborder="0" 
            allow="autoplay; encrypted-media" 
            allowfullscreen>
        </iframe>

        <!-- خلفية احتياطية (فيديو محلي) في حال تعذر تحميل يوتيوب -->
        <video autoplay muted playsinline loop id="fallbackVideo" style="display:none;position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;z-index:0;">
            <source src="assets/videos/hero.mp4" type="video/mp4">
        </video>
    </div>
    <div class="overlay"></div>

    <!-- زر تخطي الفيديو -->
    <button class="skip-btn" onclick="skipVideo()">⏭ تخطي</button>

    <!-- مؤشر للتمرير للأسفل -->
    <div class="scroll-indicator">
        <span>تمرير للأسفل</span>
        <i class="fas fa-chevron-down"></i>
    </div>
</section>
