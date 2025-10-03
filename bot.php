<?php
/*
 * IN The Name OF God
 * 
 * Developer : @Camaeal
 * Channel   : @GrokCreator
 * Botsaz    : @GrokCreatorBot
 */
$telegram_ip_ranges = [
    ['lower' => '149.154.160.0', 'upper' => '149.154.175.255'],
    ['lower' => '91.108.4.0',    'upper' => '91.108.7.255'],
];

if (!filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
    http_response_code(403);
    exit("کیر شدی مادر قحبه");
}

$ip_dec = (float) sprintf("%u", ip2long($_SERVER['REMOTE_ADDR']));
$ok = false;

foreach ($telegram_ip_ranges as $range) {
    $lower_dec = (float) sprintf("%u", ip2long($range['lower']));
    $upper_dec = (float) sprintf("%u", ip2long($range['upper']));
    if ($ip_dec >= $lower_dec && $ip_dec <= $upper_dec) {
        $ok = true;
        break;
    }
}

if (!$ok) {
    http_response_code(403);
    exit("کیر شدی مادر قحبه");
}
//--------------------------//
require_once __DIR__ . '/config.php';

function logit($msg){ global $LOG_FILE; @file_put_contents($LOG_FILE, '['.date('Y-m-d H:i:s')."] $msg\n", FILE_APPEND); }
function safe($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function db_load() {
    global $pdo;
    
    $data = [
        'settings' => [],
        'admins' => [],
        'users' => [],
        'files' => []
    ];
    
    // بارگذاری تنظیمات
    $stmt = $pdo->query("SELECT key, value FROM settings");
    $data['settings'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // بارگذاری ادمین‌ها
    $stmt = $pdo->query("SELECT user_id FROM admins");
    $data['admins'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // بارگذاری کاربران
    $stmt = $pdo->query("SELECT user_id, state, created_at, counters FROM users");
    $users = $stmt->fetchAll();
    foreach ($users as $user) {
        $data['users'][$user['user_id']] = [
            'state' => $user['state'],
            'created_at' => strtotime($user['created_at']),
            'counters' => json_decode($user['counters'], true) ?: []
        ];
    }
    
    // بارگذاری فایل‌ها
    $stmt = $pdo->query("SELECT * FROM files");
    $files = $stmt->fetchAll();
    foreach ($files as $file) {
        $data['files'][$file['code']] = $file;
        $data['files'][$file['code']]['created_at'] = strtotime($file['created_at']);
    }
    
    // بارگذاری کانال‌های اجباری
    $stmt = $pdo->query("SELECT channel FROM force_join");
    $data['settings']['force_join'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return $data;
}

function db_save($data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // ذخیره تنظیمات
        $stmt = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value");
        foreach ($data['settings'] as $key => $value) {
            if ($key !== 'force_join') {
                $stmt->execute([$key, is_bool($value) ? ($value ? 'true' : 'false') : (string)$value]);
            }
        }
        
        // ذخیره کانال‌های اجباری
        $pdo->exec("DELETE FROM force_join");
        if (!empty($data['settings']['force_join'])) {
            $stmt = $pdo->prepare("INSERT INTO force_join (channel) VALUES (?)");
            foreach ($data['settings']['force_join'] as $channel) {
                $stmt->execute([$channel]);
            }
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Database save error: " . $e->getMessage());
        return false;
    }
}

function ensureUser(&$db, $uid) {
    global $pdo;
    
    if (!isset($db['users'][$uid])) {
        $stmt = $pdo->prepare("INSERT INTO users (user_id, counters) VALUES (?, ?) ON CONFLICT (user_id) DO NOTHING");
        $stmt->execute([$uid, '{}']);
        
        $db['users'][$uid] = [
            'state' => null,
            'created_at' => time(),
            'counters' => ['files_received' => 0],
            'last_ts' => 0
        ];
    }
}

function tg($m,$p=[]){ global $API_URL;
    $ch=curl_init(); curl_setopt_array($ch,[CURLOPT_URL=>$API_URL.$m,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$p,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30]);
    $r=curl_exec($ch); if($r===false){ logit('curl: '.curl_error($ch)); curl_close($ch); return null; } curl_close($ch);
    return json_decode($r,true);
}
function sendHTML($cid,$txt,$kb=null,$noPrev=true){
    $p=['chat_id'=>$cid,'text'=>$txt,'parse_mode'=>'HTML','disable_web_page_preview'=>$noPrev];
    if($kb) $p['reply_markup']=json_encode($kb);
    return tg('sendMessage',$p);
}
function sendDoc($cid,$file_id,$cap='',$kb=null,$auto=false,$ttl=20){
    $p=['chat_id'=>$cid,'document'=>$file_id,'caption'=>$cap,'parse_mode'=>'HTML'];
    if($kb) $p['reply_markup']=json_encode($kb);
    $r=tg('sendDocument',$p); if($auto) autoDelete($cid,$r,$ttl); return $r;
}
function sendVid($cid,$file_id,$cap='',$kb=null,$auto=false,$ttl=20){
    $p=['chat_id'=>$cid,'video'=>$file_id,'caption'=>$cap,'parse_mode'=>'HTML'];
    if($kb) $p['reply_markup']=json_encode($kb);
    $r=tg('sendVideo',$p); if($auto) autoDelete($cid,$r,$ttl); return $r;
}
function sendAud($cid,$file_id,$cap='',$kb=null,$auto=false,$ttl=20){
    $p=['chat_id'=>$cid,'audio'=>$file_id,'caption'=>$cap,'parse_mode'=>'HTML'];
    if($kb) $p['reply_markup']=json_encode($kb);
    $r=tg('sendAudio',$p); if($auto) autoDelete($cid,$r,$ttl); return $r;
}
function sendPic($cid,$file_id,$cap='',$kb=null,$auto=false,$ttl=20){
    $p=['chat_id'=>$cid,'photo'=>$file_id,'caption'=>$cap,'parse_mode'=>'HTML'];
    if($kb) $p['reply_markup']=json_encode($kb);
    $r=tg('sendPhoto',$p); if($auto) autoDelete($cid,$r,$ttl); return $r;
}
function autoDelete($cid,$res,$ttl){
    if(!$res || empty($res['ok'])) return;
    $mid=$res['result']['message_id']??null; if(!$mid) return;
    register_shutdown_function(function() use($cid,$mid,$ttl){ sleep((int)$ttl); tg('deleteMessage',['chat_id'=>$cid,'message_id'=>$mid]); });
    if(function_exists('fastcgi_finish_request')) fastcgi_finish_request();
}

function userKB(){ global $EMOJI;
    return ['keyboard'=>[
        [$EMOJI['user'].' حساب کاربری'],[$EMOJI['support'].' پشتیبانی']
    ],'resize_keyboard'=>true,'one_time_keyboard'=>false];
}
function panelKB($db){ global $EMOJI;
    $on = !empty($db['settings']['bot_on']);
    $fj = count($db['settings']['force_join']??[]) ? $EMOJI['on'].' فعال' : $EMOJI['off'].' غیرفعال';
    $ttl = (int)($db['settings']['auto_delete_ttl']??20).'s';
    return ['inline_keyboard'=>[
        [
            ['text'=>($on?$EMOJI['stop'].' خاموش کردن':$EMOJI['on'].' روشن کردن'),'callback_data'=>'toggle_bot'],
            ['text'=>$EMOJI['pin'].' جوین اجباری: '.$fj,'callback_data'=>'force_menu'],
        ],
        [
            ['text'=>'➕ افزودن ادمین','callback_data'=>'add_admin'],
            ['text'=>'➖ حذف ادمین','callback_data'=>'remove_admin'],
        ],
        [
            ['text'=>$EMOJI['inbox'].' آپلود تکی','callback_data'=>'upload_single'],
            ['text'=>$EMOJI['folder'].' آپلود گروهی','callback_data'=>'upload_group'],
        ],
        [
            ['text'=>$EMOJI['file'].' اطلاعات فایل','callback_data'=>'file_info'],
            ['text'=>$EMOJI['loop'].' دریافت با کد','callback_data'=>'file_get'],
        ],
        [
            ['text'=>$EMOJI['broadcast'].' برادکست','callback_data'=>'broadcast'],
            ['text'=>$EMOJI['stats'].' آمار','callback_data'=>'stats'],
        ],
        [
            ['text'=>$EMOJI['backup'].' بکاپ','callback_data'=>'backup'],
            ['text'=>$EMOJI['backup'].' ریکاوری','callback_data'=>'restore'],
        ],
        [
            ['text'=>$EMOJI['clock']." TTL: {$ttl}",'callback_data'=>'ttl_menu'],
            ['text'=>'❌ بستن پنل','callback_data'=>'close_panel'],
        ],
    ]];
}
function isAdmin($db,$uid){ return in_array((int)$uid,$db['admins']??[],true); }

function rateCheck(&$db,$uid){
    $rl=$db['settings']['rate_limit']; $now=time();
    $u=&$db['users'][$uid];
    if(!isset($u['rl'])) $u['rl']=['win_start'=>$now,'count'=>0];
    if($now - $u['rl']['win_start'] > $rl['window']){ $u['rl']=['win_start'=>$now,'count'=>0]; }
    if($u['rl']['count'] >= $rl['max']) return false;
    $u['rl']['count']++; return true;
}
function genCode($len=8){ $raw=bin2hex(random_bytes(4)); return strtoupper(substr($raw,0,$len)); }
function signCode($code){ global $SECRET_SALT; return substr(hash_hmac('sha256',$code,$SECRET_SALT),0,6); }
function makeSignedCode(){ $c=genCode(8); return $c.'-'.strtoupper(signCode($c)); }

$update = json_decode(file_get_contents('php://input'), true);
if(!$update){ echo "OK"; exit; }

$db = db_load();

if(isset($update['callback_query'])){
    $cb=$update['callback_query'];
    $uid=(int)$cb['from']['id']; $cid=(int)$cb['message']['chat']['id']; $data=$cb['data']??'';
    if(!isAdmin($db,$uid)){ tg('answerCallbackQuery',['callback_query_id'=>$cb['id'],'text'=>'دسترسی ندارید.']); exit; }

    if($data==='toggle_bot'){
        $db['settings']['bot_on']=!($db['settings']['bot_on']??true); db_save($db);
        tg('answerCallbackQuery',['callback_query_id'=>$cb['id'],'text'=>$db['settings']['bot_on']?'روشن شد':'خاموش شد']);
        tg('editMessageReplyMarkup',['chat_id'=>$cid,'message_id'=>$cb['message']['message_id'],'reply_markup'=>json_encode(panelKB($db))]); exit;
    }
    if($data==='force_menu'){
        $list=$db['settings']['force_join']??[];
        $txt = $list && count($list) ? ("لیست جوین اجباری:\n• ".implode("\n• ", array_map('safe',$list))."\n\nافزودن: <code>/forceadd @Channel</code>\nحذف: <code>/forcedel @Channel</code>\nحذف همه: <code>/forceoff</code>")
                                     : "غیرفعال است.\nبرای افزودن: <code>/forceadd @Channel</code>";
        tg('answerCallbackQuery',['callback_query_id'=>$cb['id'],'text'=>'راهنما']); sendHTML($cid,"🧭 تنظیمات جوین اجباری\n\n{$txt}"); exit;
    }
    if($data==='ttl_menu'){
        $ttl=(int)($db['settings']['auto_delete_ttl']??20);
        sendHTML($cid,"⏳ TTL فعلی: <b>{$ttl}</b> ثانیه\nتغییر با دستور: <code>/ttl 5..3600</code>\nحذف برای ادمین‌ها: <code>/ttladmins on|off</code>");
        tg('answerCallbackQuery',['callback_query_id'=>$cb['id'],'text'=>'TTL']); exit;
    }
    if($data==='add_admin'){ $db['users'][$uid]['state']='await_admin_add'; db_save($db); tg('answerCallbackQuery',['callback_query_id'=>$cb['id'],'text'=>'آیدی عددی را بفرستید']); sendHTML($cid,"➕ آیدی عددی کاربر را ارسال کنید:"); exit; }
    if($data==='remove_admin'){ $db['users'][$uid]['state']='await_admin_remove'; db_save($db); tg('answerCallbackQuery',['callback_query_id'=>$cb['id'],'text'=>'آیدی را بفرستید']); sendHTML($cid,"➖ آیدی عددی ادمین برای حذف را ارسال کنید:"); exit; }
    if($data==='upload_single'){ $db['users'][$uid]['state']='upload_single'; db_save($db); tg('answerCallbackQuery',['callback_query_id'=>$cb['id'],'text'=>'یک فایل ارسال کنید']); sendHTML($cid,"📥 حالت آپلود تکی فعال شد.\nیک فایل ارسال کنید.",['keyboard'=>[['لغو ❌']], 'resize_keyboard'=>true]); exit; }
    if($data==='upload_group'){ $db['users'][$uid]['state']='upload_group'; db_save($db); tg('answerCallbackQuery',['callback_query_id'=>$cb['id'],'text'=>'چند فایل و سپس اتمام ✅']); sendHTML($cid,"🗂️ حالت آپلود گروهی فعال شد.\nچند فایل پشت‌سرهم ارسال کنید و در پایان «اتمام ✅».",['keyboard'=>[['اتمام ✅'],['لغو ❌']], 'resize_keyboard'=>true]); exit; }
    if($data==='file_info'){ $db['users'][$uid]['state']='await_file_info_code'; db_save($db); tg('answerCallbackQuery',['callback_query_id'=>$cb['id'],'text'=>'کد فایل؟']); sendHTML($cid,"ℹ️ کد فایل را ارسال کنید:"); exit; }
    if($data==='file_get'){ $db['users'][$uid]['state']='await_file_get_code'; db_save($db); tg('answerCallbackQuery',['callback_query_id'=>$cb['id'],'text'=>'کد فایل؟']); sendHTML($cid,"📦 کد فایل را ارسال کنید.\n(پس از دریافت، پیام بعد از TTL حذف می‌شود)"); exit; }
    if($data==='broadcast'){ $db['users'][$uid]['state']='await_broadcast'; db_save($db); tg('answerCallbackQuery',['callback_query_id'=>$cb['id'],'text'=>'متن/فوروارد برادکست']); sendHTML($cid,"📣 متن یا پیام را ارسال کنید تا برای همه کاربران ارسال شود.\nلغو: «لغو ❌»"); exit; }
    if($data==='stats'){
        $users = count($db['users']??[]);
        $files = count($db['files']??[]);
        $admins= count($db['admins']??[]);
        sendHTML($cid,"📊 آمار ربات\nکاربران: <b>{$users}</b>\nفایل‌ها: <b>{$files}</b>\nادمین‌ها: <b>{$admins}</b>");
        tg('answerCallbackQuery',['callback_query_id'=>$cb['id'],'text'=>'آمار']); exit;
    }
    if($data==='backup'){
        $path=__DIR__.'/data/db.json';
        tg('answerCallbackQuery',['callback_query_id'=>$cb['id'],'text'=>'ارسال بکاپ']);
        tg('sendDocument',['chat_id'=>$cid,'document'=>new CURLFile($path),'caption'=>'🧩 بکاپ دیتابیس']);
        exit;
    }
    if($data==='restore'){
        $db['users'][$uid]['state']='await_restore'; db_save($db);
        tg('answerCallbackQuery',['callback_query_id'=>$cb['id'],'text'=>'فایل JSON را بفرستید']);
        sendHTML($cid,"🧩 فایل JSON بکاپ را ارسال کنید. «لغو ❌» برای انصراف.");
        exit;
    }
    if($data==='close_panel'){
        tg('answerCallbackQuery',['callback_query_id'=>$cb['id'],'text'=>'بسته شد']);
        tg('deleteMessage',['chat_id'=>$cid,'message_id'=>$cb['message']['message_id']]); exit;
    }
    exit;
}

if(isset($update['message'])){
    $m=$update['message']; $chat=$m['chat']; $cid=(int)$chat['id']; $from=$m['from']; $uid=(int)$from['id'];
    $text = isset($m['text']) ? trim($m['text']) : null;
    $is_private = ($chat['type']??'')==='private';

    ensureUser($db,$uid);

    if(!$db['settings']['bot_on'] && !isAdmin($db,$uid)){
        if($is_private) sendHTML($cid,"⛔ ربات موقتاً غیرفعال است.");
        echo "OK"; exit;
    }

    if(!isAdmin($db,$uid) && !rateCheck($db,$uid)){ db_save($db); echo "OK"; exit; }
    db_save($db);

    $force = $db['settings']['force_join'] ?? [];
    if($is_private && $force && !isAdmin($db,$uid)){
        foreach($force as $fch){
            $cm=tg('getChatMember',['chat_id'=>$fch,'user_id'=>$uid]);
            $st=$cm['result']['status']??'left';
            if(in_array($st,['left','kicked'],true)){
                $links = array_map(function($c){ return "<a href='https://t.me/".ltrim($c,'@')."'>".$c."</a>"; }, $force);
                sendHTML($cid,"📌 برای استفاده، ابتدا عضو شوید:\n• ".implode("\n• ",$links));
                echo "OK"; exit;
            }
        }
    }

    $state=$db['users'][$uid]['state']??null;

    if($text && strpos($text,'/start')===0){
        global $EMOJI;
        $args = trim(substr($text,6));
        if($args && strpos($args,'f_')===0){
            $code = substr($args,2);
            $update['message']['text']='/get '.$code;
        }else{
            $name = trim(($from['first_name']??'').' '.($from['last_name']??''));
            $welcome = "{$EMOJI['wave']} <b>".safe($db['settings']['welcome_text'])."</b> {$EMOJI['rocket']}\nاز دکمه‌های زیر استفاده کنید.";
            sendHTML($cid,$welcome,userKB()); echo "OK"; exit;
        }
    }

    if($text==='/panel' && isAdmin($db,$uid)){
        global $EMOJI;
        $on=$db['settings']['bot_on']?$EMOJI['on'].' روشن':$EMOJI['off'].' خاموش';
        $fj=count($db['settings']['force_join']??[])?implode(', ',$db['settings']['force_join']):'—';
        $ttl=(int)$db['settings']['auto_delete_ttl'];
        $msg="{$EMOJI['shield']} <b>پنل مدیریت</b>\n{$EMOJI['gear']} وضعیت: <b>{$on}</b>\n{$EMOJI['pin']} جوین: <code>".safe($fj)."</code>\n{$EMOJI['clock']} TTL: <b>{$ttl}</b>s";
        sendHTML($cid,$msg,panelKB($db)); echo "OK"; exit;
    }

    if(isAdmin($db,$uid) && $text){
        if($text==='/forceoff'){ $db['settings']['force_join']=[]; db_save($db); sendHTML($cid,"✅ جوین اجباری غیرفعال شد."); echo "OK"; exit; }
        if(strpos($text,'/forceadd ')===0){
            $x=trim(substr($text,10));
            if(preg_match('~^@[\w_]{4,}$~u',$x)){ $fj=$db['settings']['force_join']??[]; if(!in_array($x,$fj,true)) $fj[]=$x; $db['settings']['force_join']=$fj; db_save($db); sendHTML($cid,"✅ افزوده شد: <code>".safe($x)."</code>"); }
            else sendHTML($cid,"فرمت: <code>/forceadd @ChannelOrGroup</code>");
            echo "OK"; exit;
        }
        if(strpos($text,'/forcedel ')===0){
            $x=trim(substr($text,10)); $fj=$db['settings']['force_join']??[];
            $db['settings']['force_join']=array_values(array_filter($fj,function($v)use($x){return $v!==$x;})); db_save($db);
            sendHTML($cid,"✅ اگر موجود بود حذف شد: <code>".safe($x)."</code>"); echo "OK"; exit;
        }
        if(strpos($text,'/ttl ')===0){
            $n=(int)trim(substr($text,5)); if($n>=5 && $n<=3600){ $db['settings']['auto_delete_ttl']=$n; db_save($db); sendHTML($cid,"✅ TTL روی <b>{$n}</b> ثانیه تنظیم شد."); } else sendHTML($cid,"محدوده مجاز 5..3600 ثانیه");
            echo "OK"; exit;
        }
        if(strpos($text,'/ttladmins ')===0){
            $arg=strtolower(trim(substr($text,11))); $v=($arg==='on'); $db['settings']['auto_delete_for_admins']=$v; db_save($db);
            sendHTML($cid,"✅ حذف خودکار برای ادمین‌ها: <b>".($v?'روشن':'خاموش')."</b>"); echo "OK"; exit;
        }
        if(strpos($text,'/pm ')===0){
            if(preg_match('~^/pm\s+(\d+)\s+(.+)$~us',$text,$mm)){ sendHTML((int)$mm[1],"📬 پیام از پشتیبانی:\n\n".safe($mm[2])); sendHTML($cid,"✅ ارسال شد."); } else sendHTML($cid,"مثال: <code>/pm 123 سلام</code>");
            echo "OK"; exit;
        }
        if(strpos($text,'/file ')===0){
            $code=trim(substr($text,6)); $f=$db['files'][$code]??null;
            if(!$f) sendHTML($cid,"❌ فایلی با این کد نیست."); else{
                $when=date('Y-m-d H:i',$f['created_at']); $sz=isset($f['size'])?number_format($f['size']/1024,1).' KB':'—';
                $info="ℹ️ اطلاعات فایل\nنوع: <b>{$f['type']}</b>\nکد: <code>{$f['code']}</code>\nسایز: <b>{$sz}</b>\nمالک: <code>{$f['owner_id']}</code>\nزمان: <code>{$when}</code>\nبرچسب: <code>".safe($f['name']??'—')."</code>";
                sendHTML($cid,$info);
            } echo "OK"; exit;
        }
        if(strpos($text,'/edit ')===0){
            if(preg_match('~^/edit\s+([A-Z0-9\-]{8,})\s+(.+)$~u',$text,$mm)){
                $c=$mm[1]; if(isset($db['files'][$c])){ $db['files'][$c]['caption']=$mm[2]; db_save($db); sendHTML($cid,"✅ کپشن بروزرسانی شد."); } else sendHTML($cid,"❌ کد نامعتبر.");
            } else sendHTML($cid,"مثال: <code>/edit CODE کپشن جدید</code>");
            echo "OK"; exit;
        }
        if(strpos($text,'/del ')===0){
            $c=trim(substr($text,5)); if(isset($db['files'][$c])){ unset($db['files'][$c]); db_save($db); sendHTML($cid,"🗑️ حذف شد."); } else sendHTML($cid,"❌ وجود ندارد.");
            echo "OK"; exit;
        }
        if(strpos($text,'/welcome ')===0){
            $w=trim(substr($text,9)); if($w!==''){ $db['settings']['welcome_text']=$w; db_save($db); sendHTML($cid,"✅ متن خوش‌آمد بروزرسانی شد."); }
            echo "OK"; exit;
        }
        if($text==='/search'){
            $db['users'][$uid]['state']='await_search'; db_save($db);
            sendHTML($cid,"🔍 بخشی از کد/نام/نوع را بفرستید."); echo "OK"; exit;
        }
    }

    if(isAdmin($db,$uid) && $state==='await_admin_add' && ctype_digit((string)$text)){
        $nid=(int)$text; if(!in_array($nid,$db['admins'],true)) $db['admins'][]=$nid; db_save($db);
        $db['users'][$uid]['state']=null; db_save($db); sendHTML($cid,"✅ ادمین افزوده شد: <code>{$nid}</code>"); echo "OK"; exit;
    }
    if(isAdmin($db,$uid) && $state==='await_admin_remove' && ctype_digit((string)$text)){
        $nid=(int)$text; global $OWNER_ID;
        if($nid===$OWNER_ID) sendHTML($cid,"⚠️ مالک قابل حذف نیست."); else{
            $db['admins']=array_values(array_filter($db['admins'],fn($x)=> (int)$x!==$nid)); db_save($db); sendHTML($cid,"✅ اگر ادمین بود حذف شد.");
        }
        $db['users'][$uid]['state']=null; db_save($db); echo "OK"; exit;
    }
    if(isAdmin($db,$uid) && $state==='await_broadcast'){
        if($text==='لغو ❌'){ $db['users'][$uid]['state']=null; db_save($db); sendHTML($cid,"لغو شد.",['remove_keyboard'=>true]); echo "OK"; exit; }
        $ok=0;$fail=0;
        foreach(array_keys($db['users']) as $u){
            $r=tg('copyMessage',['chat_id'=>$u,'from_chat_id'=>$cid,'message_id'=>$m['message_id']]); if(!empty($r['ok'])) $ok++; else $fail++;
            usleep(120000);
        }
        $db['users'][$uid]['state']=null; db_save($db);
        sendHTML($cid,"📣 برادکست پایان یافت.\nموفق: <b>{$ok}</b> | ناموفق: <b>{$fail}</b>",['remove_keyboard'=>true]); echo "OK"; exit;
    }
    if(isAdmin($db,$uid) && $state==='await_restore'){
        if($text==='لغو ❌'){ $db['users'][$uid]['state']=null; db_save($db); sendHTML($cid,"لغو شد.",['remove_keyboard'=>true]); echo "OK"; exit; }
        if(isset($m['document'])){
            $fid=$m['document']['file_id']; $gf=tg('getFile',['file_id'=>$fid]);
            if(!empty($gf['ok'])){ global $FILE_API,$DB_FILE;
                $url=$FILE_API.$gf['result']['file_path']; $json=@file_get_contents($url);
                $new=json_decode($json,true);
                if(is_array($new) && isset($new['settings'],$new['users'],$new['files'])){
                    @file_put_contents($DB_FILE,json_encode($new,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
                    $db=$new;
                    sendHTML($cid,"✅ ریکاوری انجام شد.");
                } else sendHTML($cid,"❌ فایل بکاپ نامعتبر است.");
            } else sendHTML($cid,"❌ دریافت فایل ناموفق.");
        } else sendHTML($cid,"لطفاً فایل JSON ارسال کنید.");
        $db['users'][$uid]['state']=null; db_save($db); echo "OK"; exit;
    }
    if(isAdmin($db,$uid) && $state==='await_search' && $text){
        $q=mb_strtolower($text,'UTF-8'); $hits=[];
        foreach($db['files'] as $c=>$f){
            $hay = mb_strtolower(($c.' '.($f['type']??'').' '.($f['name']??'').' '.($f['caption']??'')),'UTF-8');
            if(mb_strpos($hay,$q)!==false){ $hits[]=$c; if(count($hits)>=25) break; }
        }
        if(!$hits) sendHTML($cid,"نتیجه‌ای نبود."); else sendHTML($cid,"نتایج:\n<code>".safe(implode(", ",$hits))."</code>\nبرای اطلاعات: <code>/file CODE</code>");
        $db['users'][$uid]['state']=null; db_save($db); echo "OK"; exit;
    }

    if($is_private && $text==='👤 حساب کاربری'){
        global $EMOJI;
        $photos=tg('getUserProfilePhotos',['user_id'=>$uid,'limit'=>1]); $photo_id=null;
        if(!empty($photos['result']['photos'][0])){ $last=$photos['result']['photos'][0]; $photo_id=$last[count($last)-1]['file_id']??null; }
        $uname = isset($from['username'])?'@'.$from['username']:'—';
        $name  = trim(($from['first_name']??'').' '.($from['last_name']??''));
        $created = date('Y-m-d H:i',$db['users'][$uid]['created_at']??time());
        $bio = "<b>{$EMOJI['user']} پروفایل شما</b>\n{$EMOJI['id']} آیدی: <code>{$uid}</code>\n{$EMOJI['link']} یوزرنیم: <b>".safe($uname)."</b>\nنام: <b>".safe($name)."</b>\nعضویت: <code>{$created}</code>";
        if($photo_id) sendPic($cid,$photo_id,$bio); else sendHTML($cid,$bio);
        echo "OK"; exit;
    }
    if($is_private && $text==='🛟 پشتیبانی'){
        $db['users'][$uid]['state']='await_support_msg'; db_save($db);
        sendHTML($cid,"لطفاً مشکل خود را ارسال کنید. {$EMOJI['inbox']}"); echo "OK"; exit;
    }
    if($is_private && $state==='await_support_msg'){
        global $OWNER_ID;
        tg('forwardMessage',['chat_id'=>$OWNER_ID,'from_chat_id'=>$cid,'message_id'=>$m['message_id']]);
        $tag = isset($from['username'])?'@'.$from['username']:'—';
        sendHTML($OWNER_ID,"تیکت جدید:\nاز: <code>{$uid}</code> ({$tag})\nپاسخ: <code>/pm {$uid} متن...</code>");
        sendHTML($cid,"✅ پیام شما برای پشتیبانی ارسال شد."); $db['users'][$uid]['state']=null; db_save($db); echo "OK"; exit;
    }

    $anyFile = $m['document'] ?? ($m['photo'][count($m['photo'])-1]??null) ?? $m['video'] ?? $m['audio'] ?? null;
    if(isAdmin($db,$uid) && in_array($state,['upload_single','upload_group'],true) && $anyFile){
        $type = isset($m['document'])?'document':(isset($m['video'])?'video':(isset($m['audio'])?'audio':'photo'));
        $obj  = $type==='photo' ? $m['photo'][count($m['photo'])-1] : $m[$type];
        $file_id=$obj['file_id']; $file_unique_id=$obj['file_unique_id']; $size=$obj['file_size']??null;
        $name = $m['document']['file_name']??($type.'_'.$uid);
        $caption = $m['caption']??'';

        $base = strtoupper(substr($file_unique_id,0,6)).'-'.genCode(6);
        $code = $base.'-'.strtoupper(signCode($base));

        $db['files'][$code]=[
            'code'=>$code,'type'=>$type,'file_id'=>$file_id,'owner_id'=>$uid,
            'caption'=>$caption,'size'=>$size,'name'=>$name,'created_at'=>time()
        ];
        db_save($db);

        sendHTML($cid,"✅ فایل ذخیره شد.\n🆔 کد: <code>{$code}</code>\nدریافت: <code>/get {$code}</code>\nدیپ‌لینک: <code>/start f_{$code}</code>");
        if($state==='upload_single'){ $db['users'][$uid]['state']=null; db_save($db); sendHTML($cid,"حالت آپلود تکی پایان یافت.",['remove_keyboard'=>true]); }
        echo "OK"; exit;
    }
    if(in_array($state,['upload_single','upload_group'],true) && $text==='لغو ❌'){
        $db['users'][$uid]['state']=null; db_save($db); sendHTML($cid,"لغو شد.",['remove_keyboard'=>true]); echo "OK"; exit;
    }
    if($state==='upload_group' && $text==='اتمام ✅'){
        $db['users'][$uid]['state']=null; db_save($db); sendHTML($cid,"✅ آپلود گروهی پایان یافت.",['remove_keyboard'=>true]); echo "OK"; exit;
    }

    if($text && (strpos($text,'/get ')===0 || $state==='await_file_get_code')){
        $code = ($state==='await_file_get_code') ? $text : trim(substr($text,5));
        $parts=explode('-',$code); if(count($parts)>=3){
            $base=$parts[0].'-'.$parts[1]; $sig=end($parts);
            if(strtoupper(signCode($base))!==strtoupper($sig)){ sendHTML($cid,"❌ کد نامعتبر است."); echo "OK"; exit; }
        }
        $f=$db['files'][$code]??null;
        if(!$f){ sendHTML($cid,"❌ فایلی با این کد یافت نشد."); }
        else{
            $ttl=(int)($db['settings']['auto_delete_ttl']??20);
            $auto = (!isAdmin($db,$uid)) || ($db['settings']['auto_delete_for_admins']??false);
            $cap = "📦 کد: <code>{$f['code']}</code>\n⏳ حذف خودکار پس از <b>{$ttl}</b> ثانیه.";
            if($f['type']==='document') sendDoc($cid,$f['file_id'],$cap,null,$auto,$ttl);
            elseif($f['type']==='video') sendVid($cid,$f['file_id'],$cap,null,$auto,$ttl);
            elseif($f['type']==='audio') sendAud($cid,$f['file_id'],$cap,null,$auto,$ttl);
            else sendPic($cid,$f['file_id'],$cap,null,$auto,$ttl);
            $db['users'][$uid]['counters']['files_received']=($db['users'][$uid]['counters']['files_received']??0)+1; db_save($db);
        }
        $db['users'][$uid]['state']=null; db_save($db); echo "OK"; exit;
    }

    if((isAdmin($db,$uid) && $text && strpos($text,'/file ')===0) || (isAdmin($db,$uid) && $state==='await_file_info_code')){
        $code = ($state==='await_file_info_code') ? $text : trim(substr($text,6));
        $f=$db['files'][$code]??null;
        if(!$f) sendHTML($cid,"❌ یافت نشد.");
        else{
            $when=date('Y-m-d H:i',$f['created_at']); $sz=isset($f['size'])?number_format($f['size']/1024,1).' KB':'—';
            $info="ℹ️ اطلاعات فایل\nنوع: <b>{$f['type']}</b>\nکد: <code>{$f['code']}</code>\nسایز: <b>{$sz}</b>\nنام: <code>".safe($f['name']??'—')."</code>\nمالک: <code>{$f['owner_id']}</code>\nزمان: <code>{$when}</code>";
            sendHTML($cid,$info);
        }
        $db['users'][$uid]['state']=null; db_save($db); echo "OK"; exit;
    }

    if($text==='/help' || $text==='help'){
        $help="دستورات:\n• /panel (ادمین)\n• /get CODE — دریافت فایل\n• /file CODE — اطلاعات فایل (ادمین)\n• /edit CODE کپشن — ویرایش کپشن (ادمین)\n• /del CODE — حذف از دیتابیس (ادمین)\n• /forceadd @ch | /forcedel @ch | /forceoff\n• /ttl N — تنظیم TTL حذف\n• /ttladmins on|off\n• /pm user_id msg — پاسخ پشتیبانی\n• /search — جستجوی فایل";
        sendHTML($cid,$help); echo "OK"; exit;
    }

    if($is_private){ sendHTML($cid,"از منوی زیر انتخاب کنید:",userKB()); echo "OK"; exit; }
}
echo "OK";

?>
