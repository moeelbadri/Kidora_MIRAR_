-- نسخة SQLite من هيكل قاعدة البيانات (للتجربة السريعة محلياً فقط)
-- تُنفَّذ تلقائياً من config/db.php عند أول تشغيل، لا حاجة لتشغيلها يدوياً.

CREATE TABLE IF NOT EXISTS characters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    title TEXT DEFAULT '',
    quote TEXT DEFAULT '',
    trait TEXT DEFAULT '',
    color TEXT DEFAULT '#6C63FF',
    move_type TEXT DEFAULT 'wiggle',
    image_path TEXT DEFAULT NULL,
    audio_path TEXT DEFAULT NULL,
    icons_json TEXT DEFAULT NULL,
    is_premium INTEGER DEFAULT 0,
    sort_order INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS children (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    age INTEGER DEFAULT 8,
    parent_name TEXT DEFAULT '',
    parent_phone TEXT DEFAULT '',
    character_1 INTEGER DEFAULT NULL,
    character_2 INTEGER DEFAULT NULL,
    active_character INTEGER DEFAULT NULL,
    points INTEGER DEFAULT 0,
    ring_days INTEGER DEFAULT 0,
    badges_json TEXT DEFAULT NULL,
    last_assessment_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    category TEXT DEFAULT 'عام',
    age_min INTEGER DEFAULT 4,
    age_max INTEGER DEFAULT 12,
    story_line TEXT DEFAULT '',
    youtube_id TEXT DEFAULT NULL,
    game_type TEXT DEFAULT 'catch',
    game_title TEXT DEFAULT 'لعبة ترفيهية',
    points INTEGER DEFAULT 5,
    active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS games (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    type TEXT DEFAULT 'catch',
    category TEXT DEFAULT 'تربوي',
    age_min INTEGER DEFAULT 4,
    age_max INTEGER DEFAULT 12,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS history_figures (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    title TEXT DEFAULT '',
    description TEXT,
    youtube_id TEXT DEFAULT NULL,
    story_line TEXT DEFAULT '',
    active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS quiz_questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    axis TEXT NOT NULL,
    question TEXT NOT NULL,
    option_1 TEXT NOT NULL, option_1_value INTEGER NOT NULL, option_1_msg TEXT,
    option_2 TEXT NOT NULL, option_2_value INTEGER NOT NULL, option_2_msg TEXT,
    option_3 TEXT NOT NULL, option_3_value INTEGER NOT NULL, option_3_msg TEXT,
    active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS quiz_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    child_id INTEGER NOT NULL,
    axis TEXT NOT NULL,
    value INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS daily_progress (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    child_id INTEGER NOT NULL,
    day_key TEXT NOT NULL,
    completed_task_ids TEXT DEFAULT NULL,
    task_pool_ids TEXT DEFAULT NULL,
    games_played INTEGER DEFAULT 0,
    quiz_question_ids TEXT DEFAULT NULL,
    quiz_answered INTEGER DEFAULT 0,
    story_generated INTEGER DEFAULT 0,
    UNIQUE (child_id, day_key)
);

CREATE TABLE IF NOT EXISTS daily_stories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    child_id INTEGER NOT NULL,
    day_index INTEGER NOT NULL,
    title TEXT NOT NULL,
    scenes_json TEXT NOT NULL,
    photo_path TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS grand_stories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    child_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    scenes_json TEXT NOT NULL,
    story_ids_json TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS safety_content (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT DEFAULT 'video',
    title TEXT NOT NULL,
    description TEXT,
    youtube_id TEXT DEFAULT NULL,
    age_min INTEGER DEFAULT 4,
    age_max INTEGER DEFAULT 12,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS subscription_plans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    price_ils INTEGER DEFAULT 0,
    billing_cycle TEXT DEFAULT 'شهرياً',
    features_json TEXT DEFAULT NULL,
    sort_order INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS subscriptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    child_id INTEGER NOT NULL UNIQUE,
    plan_id INTEGER NOT NULL,
    status TEXT DEFAULT 'pending',
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    activated_at DATETIME DEFAULT NULL,
    activated_by TEXT DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS institutions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS wa_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    child_id INTEGER NOT NULL,
    type TEXT DEFAULT 'update',
    message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT
);




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
