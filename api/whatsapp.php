<?php
declare(strict_types=1);
/**
 * بوت واتساب — Webhook يستقبل رسائل Twilio ويرد بـ TwiML.
 * ضع هذا الرابط في Twilio ← رقم واتساب ← "A message comes in" (HTTP POST):
 *     https://دومينك/api/whatsapp.php
 *
 * المحادثة:
 *   أي رسالة   ← ترحيب وشرح
 *   رمز تذكرة  ← النتيجة فوراً (ألف مبروك / حظ أوفر)
 *   بعد الفوز  ← البوت يطلب اسم الطفل، ورقم الواتساب نفسه يُعتمد كرقم ولي الأمر (بدون OTP)
 */
require dirname(__DIR__) . '/includes/bootstrap.php';

const SESSION_TTL = 24 * 3600;
const FLOOD_LIMIT = 30; // رسالة لكل 10 دقائق لكل رقم

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST only');
}

// 1) التحقق أن الطلب من Twilio فعلاً
$sig = (string)($_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '');
$proto = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https' : 'http';
$candidates = [
    base_url('api/whatsapp.php'),
    $proto . '://' . ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? ''),
];
if (!twilio_signature_valid($_POST, $sig, $candidates)) {
    http_response_code(403);
    error_log('[whatsapp] invalid signature from ' . client_ip());
    exit('Forbidden');
}

if (!bot_enabled()) twiml_reply(null);

// 2) بيانات الرسالة
$from = preg_replace('/^whatsapp:/', '', (string)($_POST['From'] ?? '')) ?? '';
if (!preg_match('/^\+\d{8,15}$/', $from)) twiml_reply(null);
$text = trim(normalize_arabic_digits((string)($_POST['Body'] ?? '')));
$actor = 'wa:' . $from;

log_message(null, 'whatsapp', $from, $text === '' ? '[وسائط]' : $text, (string)($_POST['MessageSid'] ?? '') ?: null, 'received', null, 'in');

$recent = (int)q("SELECT COUNT(*) FROM message_logs WHERE direction = 'in' AND recipient = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)", [$from])->fetchColumn();
if ($recent > FLOOD_LIMIT) twiml_reply(null);

// 3) حالة المحادثة
$session = q('SELECT * FROM wa_sessions WHERE phone = ?', [$from])->fetch() ?: ['state' => 'idle', 'winner_id' => null, 'updated_at' => null];
if ($session['updated_at'] && time() - strtotime($session['updated_at']) > SESSION_TTL) {
    $session['state'] = 'idle';
    $session['winner_id'] = null;
}

function set_session(string $phone, string $state, ?int $winnerId): void
{
    q('INSERT INTO wa_sessions (phone, state, winner_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE state = VALUES(state), winner_id = VALUES(winner_id), updated_at = NOW()', [$phone, $state, $winnerId]);
}

function reply(string $to, string $text, ?int $winnerId = null, ?string $media = null): never
{
    log_message($winnerId, 'whatsapp', $to, $text, null, 'replied', null, 'out');
    twiml_reply($text, $media);
}

$site = setting('site_name', 'تذكرة الحظ');
$code = normalize_code($text);
$hasArabic = (bool)preg_match('/\p{Arabic}/u', $text);
$looksLikeCode = !$hasArabic && strlen($code) >= 6 && strlen($code) <= 14;
if ($session['state'] === 'await_name') {
    // أثناء انتظار الاسم: الاسم اللاتيني (مثل Sara) لا يُعامل كرمز إلا إذا كان شكله رمزاً فعلاً
    $compact = preg_replace('/[\s\-]/', '', $text) ?? '';
    $looksLikeCode = !$hasArabic && strlen($code) === CODE_LENGTH && (preg_match('/\d/', $code) || $compact === strtoupper($compact));
}

// 4) ننتظر اسم الطفل بعد الفوز
if ($session['state'] === 'await_name' && $session['winner_id'] && !$looksLikeCode) {
    $name = trim(preg_replace('/[^\p{L}\s]/u', '', $text) ?? '');
    if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
        reply($from, 'اكتب اسم الطفل فقط من فضلك (حروف بدون أرقام) 😊', (int)$session['winner_id']);
    }
    $w = complete_winner((int)$session['winner_id'], $name, $from);
    set_session($from, 'idle', null);
    if (!$w) reply($from, 'حدث خطأ، تواصل معنا وأرسل رقم المطالبة.');
    reply($from, fill_template(setting('bot_done'), winner_vars($w) + ['site' => $site]), (int)$w['id']);
}

// 5) رمز تذكرة
if ($looksLikeCode) {
    $res = redeem_code($text, $actor, 'whatsapp');
    if ($res['result'] === 'win') {
        set_session($from, 'await_name', (int)$res['winner_id']);
        q('UPDATE winners SET guardian_phone = ? WHERE id = ?', [$from, $res['winner_id']]);
        $msg = fill_template(setting('bot_win'), ['prize' => $res['prize']['name'], 'claim' => $res['claim_no'], 'site' => $site]);
        reply($from, $msg, (int)$res['winner_id'], $res['prize']['image']);
    }
    if ($res['result'] === 'lose') {
        reply($from, fill_template(setting('bot_lose'), ['site' => $site]));
    }
    reply($from, '⚠️ ' . $res['message']);
}

// 6) أي رسالة أخرى ← ترحيب
reply($from, fill_template(setting('bot_welcome'), ['site' => $site]));
