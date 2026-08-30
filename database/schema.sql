-- ============================================================
-- Kidora — هيكل قاعدة البيانات الكامل (MySQL / MariaDB)
-- شغّل هذا الملف مرة واحدة عند إعداد المشروع على السيرفر:
--   mysql -u root -p kidaura < database/schema.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS characters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    title VARCHAR(150) DEFAULT '',
    quote VARCHAR(200) DEFAULT '',
    trait VARCHAR(150) DEFAULT '',
    color VARCHAR(20) DEFAULT '#6C63FF',
    move_type VARCHAR(20) DEFAULT 'wiggle',
    image_path VARCHAR(255) DEFAULT NULL,
    audio_path VARCHAR(255) DEFAULT NULL,
    icons_json TEXT DEFAULT NULL,
    is_premium TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS children (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    age INT DEFAULT 8,
    parent_name VARCHAR(100) DEFAULT '',
    parent_phone VARCHAR(30) DEFAULT '',
    character_1 INT DEFAULT NULL,
    character_2 INT DEFAULT NULL,
    active_character INT DEFAULT NULL,
    points INT DEFAULT 0,
    ring_days INT DEFAULT 0,
    badges_json TEXT DEFAULT NULL,
    last_assessment_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (character_1) REFERENCES characters(id) ON DELETE SET NULL,
    FOREIGN KEY (character_2) REFERENCES characters(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    category VARCHAR(80) DEFAULT 'عام',
    age_min INT DEFAULT 4,
    age_max INT DEFAULT 12,
    story_line VARCHAR(255) DEFAULT '',
    youtube_id VARCHAR(30) DEFAULT NULL,
    game_type VARCHAR(20) DEFAULT 'catch',
    game_title VARCHAR(100) DEFAULT 'لعبة ترفيهية',
    points INT DEFAULT 5,
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    type VARCHAR(30) DEFAULT 'catch',
    category VARCHAR(50) DEFAULT 'تربوي',
    age_min INT DEFAULT 4,
    age_max INT DEFAULT 12,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS history_figures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    title VARCHAR(150) DEFAULT '',
    description TEXT,
    youtube_id VARCHAR(30) DEFAULT NULL,
    story_line VARCHAR(255) DEFAULT '',
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    axis VARCHAR(80) NOT NULL,
    question TEXT NOT NULL,
    option_1 VARCHAR(255) NOT NULL, option_1_value INT NOT NULL, option_1_msg VARCHAR(255),
    option_2 VARCHAR(255) NOT NULL, option_2_value INT NOT NULL, option_2_msg VARCHAR(255),
    option_3 VARCHAR(255) NOT NULL, option_3_value INT NOT NULL, option_3_msg VARCHAR(255),
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS quiz_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id INT NOT NULL,
    axis VARCHAR(80) NOT NULL,
    value INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS daily_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id INT NOT NULL,
    day_key VARCHAR(20) NOT NULL,
    completed_task_ids TEXT DEFAULT NULL,
    task_pool_ids TEXT DEFAULT NULL,
    games_played INT DEFAULT 0,
    quiz_question_ids TEXT DEFAULT NULL,
    quiz_answered TINYINT(1) DEFAULT 0,
    story_generated TINYINT(1) DEFAULT 0,
    UNIQUE KEY uniq_child_day (child_id, day_key),
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS daily_stories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id INT NOT NULL,
    day_index INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    scenes_json TEXT NOT NULL,
    photo_path VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS grand_stories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    scenes_json TEXT NOT NULL,
    story_ids_json TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS safety_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(20) DEFAULT 'video',
    title VARCHAR(150) NOT NULL,
    description TEXT,
    youtube_id VARCHAR(30) DEFAULT NULL,
    age_min INT DEFAULT 4,
    age_max INT DEFAULT 12,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price_ils INT DEFAULT 0,
    billing_cycle VARCHAR(50) DEFAULT 'شهرياً',
    features_json TEXT DEFAULT NULL,
    sort_order INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id INT NOT NULL UNIQUE,
    plan_id INT NOT NULL,
    status ENUM('pending','active','rejected') DEFAULT 'pending',
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    activated_at DATETIME DEFAULT NULL,
    activated_by VARCHAR(50) DEFAULT NULL,
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
);

CREATE TABLE IF NOT EXISTS institutions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS wa_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id INT NOT NULL,
    type VARCHAR(40) DEFAULT 'update',
    message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(60) PRIMARY KEY,
    setting_value TEXT
);

-- ============================================================
-- بيانات أولية
-- ============================================================
INSERT INTO settings (setting_key, setting_value) VALUES
    ('whatsapp_number', ''),
    ('platform_name', 'Kidora')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

INSERT INTO characters (slug, name, title, quote, trait, color, move_type, icons_json, is_premium, sort_order) VALUES
('mimo',  'ميمو',  'القط الذكي الشقي',      'يلا نحل اللغز سوا!',   'الفضول والذكاء',   '#6C63FF', 'wiggle', '["🐱","🧩","✨","🔍","⭐","📘","🌙"]', 0, 1),
('zizo',  'زيزو',  'إسفنجة البحر المرِحة',   'يلا نضحك ونمرح!',      'المرح والصداقة',    '#FFC93C', 'bounce', '["🧽","🫧","🌊","🍩","⭐","😄","🐚"]', 0, 2),
('finn',  'فين',   'الثعلب الشجاع',         'ولا خطوة للوراء!',     'الشجاعة والمغامرة', '#FF7A50', 'dash',   '["🦊","🏔️","🔥","⚡","🌟","🏃","🧭"]', 1, 3),
('nova',  'نوفا',  'روبوتة النجوم الفضولية', 'دعنا نحسبها بدقة!',    'العلم والاكتشاف',   '#2EC4B6', 'float',  '["🤖","🔢","🚀","⭐","🌌","💡","🛰️"]', 1, 4),
('lulu',  'لولو',  'الأرنبة الحنونة',        'كيف تشعر اليوم؟',      'التعاطف واللطف',    '#FF6FA5', 'hop',    '["🐰","💗","🌸","🎀","⭐","🦋","🌈"]', 1, 5),
('rex',   'ريكس',  'الديناصور الصغير القوي', 'أنا هنا أحميك!',       'القوة والحماية',    '#4CAF6D', 'stomp',  '["🦖","🛡️","🌳","💪","⭐","🌿","🏰"]', 1, 6)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO subscription_plans (name, price_ils, billing_cycle, features_json, sort_order) VALUES
('البداية', 0, '-', '["شخصيتان مفضّلتان","3 مهام يومياً","قصة أسبوعية"]', 1),
('المستكشف', 49, 'شهرياً', '["مهام يومية غير محدودة","قصة شخصية يومية بفيديو","تحليل تقدّم بالمخطط","قصص ثقافية وعربية"]', 2),
('العائلة', 99, 'شهرياً', '["كل مزايا المستكشف","حتى 4 أطفال","تقارير واتساب لولي الأمر","قصة المغامرة الكبرى شهرياً"]', 3);

INSERT INTO tasks (title, description, category, age_min, age_max, story_line, points) VALUES
('رتّب غرفتك', 'صف ألعابك وكتبك في أماكنها بعد اللعب', 'مهارات حياتية', 4, 6, 'رتّب البطل غرفته فلمع نجمه في السماء ✨', 5),
('اقرأ قصة قصيرة', 'اقرأ 5 صفحات من كتابك المفضّل بصوت عالٍ', 'تعلّم', 4, 9, 'سافر البطل بخياله بين سطور القصة 📖', 5),
('مهمة الرياضة', 'قم بـ 10 قفزات و10 تمارين تمدد', 'صحة', 4, 12, 'قفز البطل عالياً حتى لامس الغيوم 🤸', 5),
('ارسم صديقك المفضّل', 'ارسم شخصية كيدورا التي تحبها', 'إبداع', 4, 9, 'رسم البطل لوحة أضاءت الغرفة بالألوان 🎨', 5),
('ساعد في المنزل', 'ساعد أحد أفراد أسرتك في مهمة بسيطة', 'قيم', 5, 12, 'ساعد البطل عائلته فكسب وسام اللطف 💛', 5),
('تحدي الكلمات', 'تعلّم 3 كلمات جديدة واستخدمها في جملة', 'تعلّم', 7, 12, 'اكتشف البطل كلمات سحرية جديدة 🔤', 5),
('دقيقة هدوء', 'أغلق عينيك وتنفّس بعمق لمدة دقيقة', 'صحة نفسية', 4, 12, 'وجد البطل جزيرة الهدوء داخل قلبه 🧘', 5),
('مهمة الأمان', 'راجع مع أهلك 3 قواعد أمان على الإنترنت', 'حماية', 7, 12, 'تعلّم البطل كيف يحمي نفسه كالأبطال 🛡️', 5),
('قل شكراً', 'اشكر شخصاً بصدق اليوم', 'قيم', 4, 9, 'انتشر الشكر كنور دافئ حول البطل 🌟', 5),
('تحدي البناء', 'ابنِ برجاً أو شكلاً من المكعبات', 'إبداع', 4, 7, 'شيّد البطل قلعة أحلامه بيديه 🏰', 5),
('تحدي الحساب السريع', 'حلّ 5 مسائل جمع أو طرح بسيطة', 'تعلّم', 6, 10, 'حسب البطل بسرعة البرق كنوفا! 🔢', 5),
('اعتنِ بنبتة', 'اسقِ نبتة في المنزل وتحدّث معها', 'مسؤولية', 5, 10, 'نمت النبتة سعيدة بعناية البطل 🌱', 5),
('اكتب رسالة حب', 'اكتب أو ارسم رسالة لأحد أفراد أسرتك', 'قيم', 6, 12, 'أضاء حب البطل قلب من أحبّه 💌', 5),
('تحدي التوازن', 'قف على قدم واحدة لمدة 20 ثانية 3 مرات', 'صحة', 5, 10, 'وقف البطل ثابتاً كصخرة قوية 🦵', 5),
('رتّب لعبة جماعية', 'نظّم لعبة بسيطة مع إخوتك أو أصدقائك', 'اجتماعي', 5, 12, 'جمع البطل أصدقاءه بلعبة مليئة بالضحك 🎲', 5),
('تحدي الألوان', 'سمِّ 5 أشياء بلون واحد حولك', 'تعلّم', 4, 7, 'اكتشف البطل عالماً كاملاً من الألوان 🌈', 5),
('اعتذر بصدق', 'إذا أخطأت اليوم، اعتذر بصدق لمن أزعجته', 'قيم', 5, 12, 'شعر البطل بخفة القلب بعد اعتذاره 🤍', 5),
('تحدي الحفظ', 'احفظ بيتاً من الشعر أو آية قصيرة', 'ثقافي', 6, 12, 'حفظ البطل كنزاً من الكلمات الجميلة 📜', 5),
('نظّف مكاناً مشتركاً', 'رتّب طاولة الطعام أو غرفة الجلوس', 'مهارات حياتية', 6, 12, 'لمع المكان بفضل جهد البطل الصغير ✨', 5),
('تحدي الابتكار', 'اخترع لعبة جديدة بقواعد من عندك', 'إبداع', 7, 12, 'ابتكر البطل عالماً جديداً بقواعده الخاصة 🎮', 5),
('استمع لأحد بانتباه', 'اسأل أحد والديك عن يومه واستمع جيداً', 'اجتماعي', 6, 12, 'شعر أهل البطل بالتقدير والحب 👂', 5),
('تحدي الحيوانات', 'قلّد حركة وصوت 3 حيوانات مختلفة', 'صحة', 4, 7, 'تحوّل البطل لحيوانات مختلفة بمرح 🐾', 5),
('وثّق يومك', 'ارسم أو اكتب عن أفضل لحظة اليوم', 'تعلّم', 6, 12, 'حفظ البطل ذكرى جميلة إلى الأبد 📔', 5),
('ساعد صديقاً', 'اعرض المساعدة على صديق أو زميل بالمدرسة', 'قيم', 6, 12, 'صار البطل بطلاً حقيقياً بعين صديقه 🦸', 5);

INSERT INTO games (title, type, category, age_min, age_max) VALUES
('التقط الحروف', 'catch', 'تربوي', 4, 8),
('لعبة ذاكرة الحروف', 'match', 'تربوي', 4, 9),
('سباق أسئلة العلوم', 'quiz', 'علمي', 7, 12),
('ذاكرة العلوم', 'match', 'علمي', 6, 11),
('اختبر تعاونك', 'quiz', 'اجتماعي', 6, 12),
('ذاكرة الأصدقاء', 'match', 'اجتماعي', 4, 9),
('قرارات صح وخطأ', 'quiz', 'سلوكي', 6, 12),
('التقط الهدوء', 'catch', 'سلوكي', 4, 9),
('سباق أبطال بلادي', 'quiz', 'ثقافي', 8, 12),
('ذاكرة التراث', 'match', 'ثقافي', 7, 12),
('سباق القفز', 'catch', 'صحي', 4, 10),
('اختبر صحتك', 'quiz', 'صحي', 7, 12);

INSERT INTO history_figures (name, title, description, youtube_id, story_line) VALUES
('ابن بطوطة', 'أعظم رحّالة عرفه التاريخ', 'رحّالة مغربي زار أكثر من 40 دولة حول العالم على مدى 28 عاماً ودوّن رحلاته في كتاب "تحفة النظار".', 'wpN-gqVSDDA', 'سافر البطل بخياله حول العالم مع ابن بطوطة، وتعلّم أن الفضول يفتح أبواباً لا تُحصى 🧭'),
('الخوارزمي', 'أبو علم الجبر', 'عالم رياضيات من بغداد ابتكر "الجبر" الذي تقوم عليه الرياضيات والحاسوب حتى اليوم.', NULL, 'تعلّم البطل أن حب الأرقام والتفكير المنظّم يمكن أن يغيّر العالم كله 🔢'),
('ابن سينا', 'الطبيب الحكيم', 'طبيب وعالم بدأ التميّز في العلوم وهو صغير جداً، وألّف "القانون في الطب" الذي دُرّس لقرون.', NULL, 'تعلّم البطل أن السعي للمساعدة والعلم منذ الصغر يترك أثراً يبقى للأجيال ⚕️'),
('خديجة بنت خويلد', 'سيدة أعمال ناجحة وصادقة', 'تاجرة عربية اشتهرت بالأمانة والذكاء التجاري، وأدارت قوافل تجارية ناجحة بثقة كبيرة.', NULL, 'تعلّم البطل أن الصدق والأمانة أساس كل نجاح حقيقي 🤝'),
('شهرزاد', 'راوية ألف ليلة وليلة', 'فتاة ذكية اشتهرت بحبها للقراءة وقدرتها على سرد حكايات رائعة ألهمت العالم كله.', NULL, 'تعلّم البطل أن الكلمة الطيبة والحكاية الجميلة يمكن أن تكون أقوى سلاح 📖'),
('عمر بن الخطاب', 'القائد العادل', 'اشتهر بعدله الشديد وتواضعه رغم قيادته لدولة كبيرة، وكان يتفقّد أحوال الناس بنفسه.', NULL, 'تعلّم البطل أن العدل والتواضع صفتان تصنعان قائداً حقيقياً ⚖️');

INSERT INTO quiz_questions (axis, question, option_1, option_1_value, option_1_msg, option_2, option_2_value, option_2_msg, option_3, option_3_value, option_3_msg) VALUES
('الثقة بالنفس', 'لو طلب منك المعلم التحدث أمام الصف، ماذا تفعل؟', 'أتحدث بثقة وبدون خوف', 3, 'رائع! ثقتك بنفسك تُلهم من حولك 🌟', 'أتحدث لكن بقلق بسيط', 2, 'جيد جداً، كل خطوة تقربك من الثقة الكاملة 💪', 'أفضّل عدم التحدث', 1, 'لا بأس! سنتدرّب معاً خطوة بخطوة 🤍'),
('المهارات الاجتماعية', 'صديقك حزين، ما أول شيء تفعله؟', 'أجلس معه وأسأله عن سبب حزنه', 3, 'قلبك الطيب يصنع فرقاً حقيقياً 💛', 'أحاول أن أضحكه', 2, 'جميل! نشر الفرح مهارة رائعة 😊', 'لا أعرف ماذا أفعل', 1, 'لا مشكلة، سنتعلّم معاً كيف نساعد الأصدقاء 🤝'),
('الذكاء العاطفي', 'عندما تغضب، ماذا تفعل عادة؟', 'أتنفس بعمق وأهدأ بنفسي', 3, 'استطعت أن تتحكم في مشاعرك، هذا بطولة حقيقية 🧘', 'أطلب المساعدة من أحد الكبار', 2, 'طلب المساعدة دائماً خطوة ذكية 🌈', 'أصرخ أو أبكي كثيراً', 1, 'مشاعرك مهمة، وسنتعلم طرقاً أهدأ للتعبير عنها 🤍'),
('الإبداع', 'في وقت فراغك تفضّل أن:', 'أخترع لعبة أو قصة جديدة', 3, 'خيالك واسع كالسماء! 🎨', 'أرسم أو ألوّن شيئاً', 2, 'إبداعك يتلوّن كل يوم أكثر 🖌️', 'أفضّل مشاهدة شيء جاهز', 1, 'سنكتشف معاً متعة الإبداع خطوة بخطوة ✨'),
('التركيز', 'عند أداء الواجب، كيف تركّز؟', 'أكمل المهمة كاملة دون توقف', 3, 'تركيزك يشبه تركيز الأبطال الحقيقيين 🎯', 'أتوقف قليلاً ثم أكمل', 2, 'ممتاز، أخذ استراحة قصيرة يقوّي تركيزك 🌿', 'أشتّت بسهولة', 1, 'سنتدرّب معاً على ألعاب تقوّي التركيز خطوة بخطوة 🧩'),
('الأمان الشخصي', 'لو طلب منك شخص غريب سراً لا تخبر أهلك به، ماذا تفعل؟', 'أرفض وأخبر أهلي فوراً', 3, 'بطل حقيقي! إخبار أهلك دائماً القرار الصحيح 🛡️', 'أشعر بالحيرة ولا أعرف ماذا أفعل', 2, 'تذكّر: أي سرّ يزعجك يجب أن تخبر به أهلك 💙', 'أحتفظ بالسر', 1, 'لا توجد أسرار مع الغرباء، أخبر أهلك دائماً 🚨');

INSERT INTO safety_content (type, title, description, age_min, age_max) VALUES
('video', 'سرّي الخاص', 'أتعلّم ما هي أجزاء جسدي الخاصة ولماذا هي مهمة', 4, 8),
('video', 'الغريب الآمن', 'ماذا أفعل لو تحدث معي شخص لا أعرفه؟', 4, 10),
('game', 'صح أم خطأ: الإنترنت الآمن', 'لعبة تفاعلية لتعلّم قواعد الأمان الرقمي', 7, 12),
('video', 'قول لا بثقة', 'من حقي أن أرفض أي شيء يزعجني وأخبر أهلي', 4, 12);



-- =============================================================
-- ألعاب جديدة وقوية لمكتبة الألعاب
-- =============================================================

INSERT INTO games (title, category, type, age_min, age_max, description, is_active) VALUES

-- ===== ألعاب تربوية =====
('مغامرة الأرقام', 'تربوي', 'quiz', 4, 8, 'تعلّم الأرقام من 1 إلى 20 مع مغامرات ممتعة', 1),
('رحلة الحروف', 'تربوي', 'quiz', 3, 7, 'تعرّف على الحروف العربية مع الشخصيات الكرتونية', 1),
('تحدي الجمع', 'تربوي', 'quiz', 5, 9, 'تمارين الجمع البسيط بطريقة تفاعلية', 1),
('الطرح السحري', 'تربوي', 'quiz', 6, 10, 'تعلم الطرح من خلال الألعاب', 1),

-- ===== ألعاب علمية =====
('تحدي الذاكرة', 'علمي', 'memory', 5, 12, 'لعبة تنشيط الذاكرة والتذكر البصري', 1),
('جسم الإنسان', 'علمي', 'quiz', 6, 12, 'تعلم أجزاء جسم الإنسان ووظائفها', 1),
('الكواكب والفضاء', 'علمي', 'quiz', 7, 12, 'رحلة إلى الفضاء وتعرف على الكواكب', 1),
('الحيوانات المذهلة', 'علمي', 'quiz', 4, 10, 'تعرف على الحيوانات وبيئاتها', 1),
('الطبيعة من حولنا', 'علمي', 'quiz', 5, 11, 'استكشف النباتات والبيئة', 1),

-- ===== ألعاب اجتماعية =====
('الصداقة الحقيقية', 'اجتماعي', 'quiz', 4, 10, 'تعلم كيفية تكوين الصداقات والتعاون', 1),
('مشاعري وأنا', 'اجتماعي', 'quiz', 4, 10, 'تعرّف على مشاعرك وكيفية التعبير عنها', 1),
('التعاون مع الأصدقاء', 'اجتماعي', 'reaction', 5, 11, 'تحديات تعاونية مع الشخصيات', 1),
('آداب الحديث', 'اجتماعي', 'quiz', 4, 10, 'تعلم آداب الحديث والاستماع للآخرين', 1),

-- ===== ألعاب سلوكية =====
('الشجاعة في المواقف', 'سلوكي', 'reaction', 5, 12, 'واجه مخاوفك وتعلم كيف تكون شجاعاً', 1),
('قواعد الأمان', 'سلوكي', 'quiz', 4, 10, 'تعلم قواعد الأمان الشخصي', 1),
('التصرف الصحيح', 'سلوكي', 'quiz', 5, 11, 'تعلم التصرف الصحيح في المواقف المختلفة', 1),
('الانضباط الذاتي', 'سلوكي', 'quiz', 6, 12, 'تعلم كيفية التحكم في الانفعالات', 1),

-- ===== ألعاب ثقافية =====
('رحلاتي حول العالم', 'ثقافي', 'quiz', 6, 12, 'تعرف على ثقافات ودول العالم', 1),
('العادات والتقاليد', 'ثقافي', 'quiz', 5, 12, 'تعلم عادات وتقاليد الشعوب', 1),
('المهن المختلفة', 'ثقافي', 'quiz', 5, 11, 'تعرف على المهن وأهميتها', 1),
('أبطال التاريخ', 'ثقافي', 'quiz', 7, 12, 'تعرف على شخصيات تاريخية ملهمة', 1),

-- ===== ألعاب صحية =====
('العادات الصحية', 'صحي', 'quiz', 4, 10, 'تعلم العادات الصحية السليمة', 1),
('الغذاء الصحي', 'صحي', 'memory', 5, 12, 'تعلم الطعام الصحي والغير صحي', 1),
('الرياضة واللياقة', 'صحي', 'quiz', 5, 12, 'تعلم أهمية الرياضة والحركة', 1),
('النوم الصحي', 'صحي', 'quiz', 4, 10, 'تعلم أهمية النوم المنتظم', 1),

-- ===== ألعاب تفاعلية جديدة =====
('مغامرة القراءة', 'تربوي', 'quiz', 5, 10, 'اقرأ القصة القصيرة وأجب عن الأسئلة', 1),
('بناء الجمل', 'تربوي', 'quiz', 6, 11, 'رتب الكلمات لتكوين جملة مفيدة', 1),
('تحدي السرعة', 'تربوي', 'reaction', 4, 8, 'أجب بسرعة عن الأسئلة قبل نفاد الوقت', 1),
('الألغاز المسلية', 'علمي', 'quiz', 5, 12, 'حل الألغاز والأحاجي الشيقة', 1),
('الترتيب الصحيح', 'علمي', 'quiz', 4, 9, 'رتب الأحداث أو الأرقام بالترتيب الصحيح', 1);

INSERT INTO games (title, category, type, age_min, age_max, description, is_active) VALUES
('المغامر الصغير', 'تربوي', 'adventure', 5, 10, 'اختر مسار المغامرة واتخذ القرارات المناسبة', 1);

INSERT INTO games (title, category, type, age_min, age_max, description, is_active) VALUES
('المطابقة السريعة', 'علمي', 'memory', 4, 10, 'طابق الصور مع الكلمات في أسرع وقت', 1);
