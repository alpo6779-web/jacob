<?php
/*
 * IN The Name OF God
 * 
 * Developer : @Camaeal
 * Channel   : @GrokCreator
 * Botsaz    : @GrokCreatorBot
 */
 
//------------//
$BOT_TOKEN = '8342748520:AAHaLxjLBY4tZGD1nYDcu_PJDbc34zFB4Xs';//توکن ربات
$OWNER_ID  = 5959954413 ;//مالک عددی
//------------//

// دیتابیس Neon.tech
$DB_HOST = 'ep-xxx-pool.us-east-1.aws.neon.tech';
$DB_NAME = 'neondb';
$DB_USER = 'neondb_owner';
$DB_PASS = 'npg_Mp0FVwT1GkNI';
$DB_PORT = '5432';

// اتصال به دیتابیس
try {
    $pdo = new PDO("pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    exit("Database error");
}

$API_URL   = "https://api.telegram.org/bot{$BOT_TOKEN}/";
$FILE_API  = "https://api.telegram.org/file/bot{$BOT_TOKEN}/";

// برای render.com از محیطی استفاده می‌کنیم که writable باشه
$DATA_DIR  = sys_get_temp_dir() . '/telegram_bot';

@is_dir($DATA_DIR) || @mkdir($DATA_DIR, 0775, true);

$LOG_FILE  = $DATA_DIR . '/app.log';

$SECRET_SALT = '.';


$EMOJI = [

    'wave'     => "👋",

    'rocket'   => "🚀",

    'user'     => "👤",

    'support'  => "🛟",

    'shield'   => "🛡️",

    'gear'     => "⚙️",

    'check'    => "✅",

    'off'      => "⛔",

    'on'       => "🟢",

    'file'     => "📦",

    'link'     => "🔗",

    'id'       => "🆔",

    'clock'    => "⏳",

    'trash'    => "🗑️",

    'inbox'    => "📥",

    'outbox'   => "📤",

    'warn'     => "⚠️",

    'pin'      => "📌",

    'folder'   => "🗂️",

    'ok'       => "✨",

    'stop'     => "🛑",

    'loop'     => "🔁",

    'stats'    => "📊",

    'broadcast'=> "📣",

    'backup'   => "🧩",

    'search'   => "🔍",

];

initDatabase($pdo);

function initDatabase($pdo) {
    $tables = [
        "CREATE TABLE IF NOT EXISTS settings (
            key VARCHAR(100) PRIMARY KEY,
            value TEXT
        )",
        
        "CREATE TABLE IF NOT EXISTS admins (
            user_id BIGINT PRIMARY KEY,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS users (
            user_id BIGINT PRIMARY KEY,
            state TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            counters JSONB DEFAULT '{}'
        )",
        
        "CREATE TABLE IF NOT EXISTS files (
            code VARCHAR(50) PRIMARY KEY,
            type VARCHAR(20),
            file_id TEXT,
            owner_id BIGINT,
            caption TEXT,
            size BIGINT,
            name TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS force_join (
            channel TEXT PRIMARY KEY
        )"
    ];
    
    foreach ($tables as $table) {
        $pdo->exec($table);
    }
    
    // تنظیمات پیش‌فرض
    $defaultSettings = [
        'bot_on' => 'true',
        'auto_delete_ttl' => '20',
        'auto_delete_for_admins' => 'false',
        'welcome_text' => 'به ربات آپلودر ما خوش آمدید'
    ];
    
    $stmt = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT (key) DO NOTHING");
    foreach ($defaultSettings as $key => $value) {
        $stmt->execute([$key, $value]);
    }
    
    // اضافه کردن مالک به ادمین‌ها
    $stmt = $pdo->prepare("INSERT INTO admins (user_id) VALUES (?) ON CONFLICT (user_id) DO NOTHING");
    $stmt->execute([$GLOBALS['OWNER_ID']]);
}