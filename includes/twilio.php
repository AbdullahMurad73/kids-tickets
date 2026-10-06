<?php
declare(strict_types=1);

/**
 * تكامل Twilio بدون SDK (cURL فقط) ليعمل على أي استضافة مشتركة.
 * - Verify: لإرسال رمز OTP والتحقق منه.
 * - Messages: لإرسال واتساب، مع SMS احتياطي.
 */

function tw_cfg(string $key): string
{
    global $CONFIG;
    return trim((string)($CONFIG['twilio'][$key] ?? ''));
}

function twilio_ready(): bool
{
    return tw_cfg('account_sid') !== '' && tw_cfg('auth_token') !== '';
}

function otp_enabled(): bool
{
    return twilio_ready() && tw_cfg('verify_service_sid') !== '';
}

function tw_request(string $url, array $fields): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_USERPWD        => tw_cfg('account_sid') . ':' . tw_cfg('auth_token'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $body = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
    return [
        'ok'    => $code >= 200 && $code < 300,
        'code'  => $code,
        'body'  => $body,
        'error' => $err ?: (string)($body['message'] ?? ''),
    ];
}

function twilio_verify_start(string $phone): array
{
    $channel = tw_cfg('otp_channel') ?: 'sms'; // sms أو whatsapp
    $url = 'https://verify.twilio.com/v2/Services/' . rawurlencode(tw_cfg('verify_service_sid')) . '/Verifications';
    $res = tw_request($url, ['To' => $phone, 'Channel' => $channel, 'Locale' => 'ar']);
    if (!$res['ok'] && $channel === 'whatsapp') {
        $res = tw_request($url, ['To' => $phone, 'Channel' => 'sms', 'Locale' => 'ar']);
    }
    return $res;
}

function twilio_verify_check(string $phone, string $code): bool
{
    $url = 'https://verify.twilio.com/v2/Services/' . rawurlencode(tw_cfg('verify_service_sid')) . '/VerificationCheck';
    $res = tw_request($url, ['To' => $phone, 'Code' => $code]);
    return $res['ok'] && ($res['body']['status'] ?? '') === 'approved';
}

/**
 * يرسل رسالة واتساب (وعند الفشل SMS) ويسجّلها في message_logs.
 * $contentSid: قالب واتساب معتمد من Meta (مطلوب للرسائل التي تبدأها المنصة خارج نافذة 24 ساعة).
 * بدون قالب تُرسل كنص عادي (يعمل في Twilio Sandbox وداخل نافذة 24 ساعة).
 */
function send_notification(?int $winnerId, string $phone, string $body, string $contentSid = '', array $vars = []): bool
{
    if (!twilio_ready()) {
        log_message($winnerId, 'whatsapp', $phone, $body, null, 'skipped', 'Twilio غير مُعدّ');
        return false;
    }
    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode(tw_cfg('account_sid')) . '/Messages.json';

    if (tw_cfg('whatsapp_from') !== '') {
        $fields = ['From' => 'whatsapp:' . tw_cfg('whatsapp_from'), 'To' => 'whatsapp:' . $phone];
        if ($contentSid !== '') {
            $fields['ContentSid'] = $contentSid;
            $fields['ContentVariables'] = json_encode($vars, JSON_UNESCAPED_UNICODE);
        } else {
            $fields['Body'] = $body;
        }
        $res = tw_request($url, $fields);
        log_message($winnerId, 'whatsapp', $phone, $body, $res['body']['sid'] ?? null, $res['ok'] ? ($res['body']['status'] ?? 'queued') : 'failed', $res['ok'] ? null : $res['error']);
        if ($res['ok']) return true;
    }

    if (tw_cfg('sms_from') !== '') {
        $res = tw_request($url, ['From' => tw_cfg('sms_from'), 'To' => $phone, 'Body' => $body]);
        log_message($winnerId, 'sms', $phone, $body, $res['body']['sid'] ?? null, $res['ok'] ? ($res['body']['status'] ?? 'queued') : 'failed', $res['ok'] ? null : $res['error']);
        return $res['ok'];
    }
    return false;
}

function log_message(?int $winnerId, string $channel, string $to, string $body, ?string $sid, string $status, ?string $error, string $direction = 'out'): void
{
    q('INSERT INTO message_logs (winner_id, channel, direction, recipient, body, twilio_sid, status, error) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
        $winnerId, $channel, $direction, $to, mb_substr($body, 0, 2000), $sid, $status, $error ? mb_substr($error, 0, 500) : null,
    ]);
}

/**
 * يتحقق أن الطلب قادم فعلاً من Twilio (X-Twilio-Signature).
 * التوقيع = Base64(HMAC-SHA1(AuthToken, الرابط + كل الحقول مرتبة أبجدياً "اسمقيمة")).
 * نجرّب الرابط المُعدّ في config ورابط الطلب الفعلي (مفيد خلف ngrok أو بروكسي).
 */
function twilio_signature_valid(array $params, string $signature, array $candidateUrls): bool
{
    $token = tw_cfg('auth_token');
    if ($token === '' || $signature === '') return false;
    ksort($params, SORT_STRING);
    $payload = '';
    foreach ($params as $k => $v) $payload .= $k . (is_array($v) ? implode('', $v) : $v);
    foreach (array_unique($candidateUrls) as $url) {
        $expected = base64_encode(hash_hmac('sha1', $url . $payload, $token, true));
        if (hash_equals($expected, $signature)) return true;
    }
    return false;
}

/** رد TwiML: رسالة نصية مع صورة اختيارية (الصورة يجب أن تكون على رابط https عام) */
function twiml_reply(?string $text, ?string $mediaUrl = null): never
{
    header('Content-Type: text/xml; charset=utf-8');
    $x = '<?xml version="1.0" encoding="UTF-8"?><Response>';
    if ($text !== null && $text !== '') {
        $x .= '<Message><Body>' . htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</Body>';
        if ($mediaUrl && str_starts_with($mediaUrl, 'https://')) {
            $x .= '<Media>' . htmlspecialchars($mediaUrl, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</Media>';
        }
        $x .= '</Message>';
    }
    echo $x . '</Response>';
    exit;
}

function bot_enabled(): bool
{
    return setting('wa_bot_enabled') === '1' && twilio_ready() && tw_cfg('whatsapp_from') !== '';
}

/** رابط wa.me لفتح محادثة البوت مع نص جاهز */
function bot_chat_link(string $text = ''): string
{
    $num = preg_replace('/\D/', '', tw_cfg('whatsapp_from')) ?? '';
    return 'https://wa.me/' . $num . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}
