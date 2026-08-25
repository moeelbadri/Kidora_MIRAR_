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
