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
    youtube_id VARCHAR(64) DEFAULT NULL,
    game_type VARCHAR(20) DEFAULT 'catch',
    game_title VARCHAR(100) DEFAULT 'لعبة ترفيهية',
    figure_id INT DEFAULT NULL,
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

-- ---------------- محتوى الألعاب (كان مكتوباً داخل games-engine.js) ----------------
-- الموضوع يجمع الأيقونات + قائمة تصنيفات المهام/الألعاب التي تُخدَّم منه،
-- فيصبح ربط «تصنيف عربي ← موضوع محتوى» قابلاً للتعديل من لوحة التحكم.
CREATE TABLE IF NOT EXISTS game_topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_key VARCHAR(40) UNIQUE NOT NULL,
    label VARCHAR(80) NOT NULL,
    icons_json TEXT DEFAULT NULL,
    categories_json TEXT DEFAULT NULL,
    active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0
);

-- reviewed = 0 يعني «محتوى مقترح لم يُراجَع بعد» — يظهر للطفل لكنه مُعلَّم
-- في لوحة التحكم حتى تعتمده الإدارة. active = 0 يخفيه فعلياً.
CREATE TABLE IF NOT EXISTS game_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_key VARCHAR(40) NOT NULL,
    question VARCHAR(255) NOT NULL,
    answer TINYINT(1) NOT NULL,
    age_min INT DEFAULT 4,
    age_max INT DEFAULT 12,
    active TINYINT(1) DEFAULT 1,
    reviewed TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS game_scenarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_key VARCHAR(40) NOT NULL,
    prompt VARCHAR(255) NOT NULL,
    choices_json TEXT NOT NULL,
    age_min INT DEFAULT 4,
    age_max INT DEFAULT 12,
    active TINYINT(1) DEFAULT 1,
    reviewed TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS history_figures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    title VARCHAR(150) DEFAULT '',
    description TEXT,
    youtube_id VARCHAR(64) DEFAULT NULL,
    story_line VARCHAR(255) DEFAULT '',
    category VARCHAR(80) DEFAULT '',
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
    youtube_id VARCHAR(64) DEFAULT NULL,
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
-- البيانات الأولية ليست هنا.
-- كل بيانات البذر (الشخصيات، المهام، الألعاب، الشخصيات التاريخية،
-- أسئلة التحليل، محتوى الحماية، الخطط) تعيش في database/seed.php
-- كمصدر واحد، وتُستدعى تلقائياً عند أول اتصال بقاعدة فارغة.
-- ============================================================
