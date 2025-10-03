<?php
/*
 * IN The Name OF God
 * 
 * Developer : @Camaeal
 * Channel   : @GrokCreator
 * Botsaz    : @GrokCreatorBot
 */
 
 //------------//
$BOT_TOKEN = getenv('BOT_TOKEN') ?: '8342748520:AAHaLxjLBY4tZGD1nYDcu_PJDbc34zFB4Xs';//توکن ربات
$OWNER_ID  = (int) (getenv('OWNER_ID') ?: 5959954413);//مالک عددی
//------------//

$DATABASE_URL = getenv('DATABASE_URL') ?: 'postgresql://neondo_owner:npg_Mp0FVwT1GkNI@ep-frosty-darkness-adjcq426-pooler.c-2.us-east-1.aws.neon.tech/neondo?sslmode=require';

$API_URL   = "https://api.telegram.org/bot{$BOT_TOKEN}/";
$FILE_API  = "https://api.telegram.org/file/bot{$BOT_TOKEN}/";

$DATA_DIR  = __DIR__ . '/data';

$DB_FILE   = $DATA_DIR . '/db.json';

$LOG_FILE  = $DATA_DIR . '/app.log';

@is_dir($DATA_DIR) || @mkdir($DATA_DIR, 0775, true);

$SECRET_SALT = '.';

$DEFAULTS = [

    'settings' => [

        'bot_on'          => true,

        'force_join'      => [],

        'auto_delete_ttl' => 20,

        'auto_delete_for_admins' => false,

        'rate_limit'      => ['window' => 10, 'max' => 20],

        'welcome_text'    => 'به ربات آپلودر ما خوش آمدید',

    ],

    'admins'  => [$OWNER_ID],

    'users'   => [],

    'files'   => [],

    'albums'  => [],

];

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

if (!file_exists($DB_FILE)) {

    file_put_contents($DB_FILE, json_encode($DEFAULTS, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

}

