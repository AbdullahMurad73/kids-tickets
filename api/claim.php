<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'message' => 'Method not allowed'], 405);
csrf_check();

$token = (string)($_POST['claim_token'] ?? '');
$action = (string)($_POST['action'] ?? 'start');

// لا يُقبل الطلب إلا من نفس الجلسة التي كشفت التذكرة الرابحة
if (!preg_match('/^[a-f0-9]{32}$/', $token) || empty($_SESSION['claims'][$token])) {
    json_out(['ok' => false, 'message' => 'انتهت الجلسة. تواصل معنا وأرسل رقم المطالبة.'], 403);
}

$w = q(
    'SELECT w.*, p.name AS prize_name, t.code
       FROM winners w JOIN tickets t ON t.id = w.ticket_id LEFT JOIN prizes p ON p.id = t.prize_id
      WHERE w.claim_token = ?',
    [$token]
)->fetch();
if (!$w) json_out(['ok' => false, 'message' => 'لم نجد هذه الجائزة.'], 404);
if ($w['phone_verified_at']) json_out(['ok' => true, 'done' => true, 'message' => 'تم تسجيل الجائزة مسبقاً.']);

function finish_claim(array $w): never
{
    $w = complete_winner((int)$w['id'], (string)$w['child_name'], (string)$w['guardian_phone']) ?? $w;
    $vars = winner_vars($w);
    $body = fill_template(setting('msg_win'), $vars);
    $sent = send_notification((int)$w['id'], $w['guardian_phone'], $body, setting('wa_content_sid_win'), ['1' => $vars['child'], '2' => $vars['prize'], '3' => $vars['claim']]);
    unset($_SESSION['claims'][$w['claim_token']]);
    json_out([
        'ok'      => true,
        'done'    => true,
        'message' => $sent ? 'تم! أرسلنا تفاصيل الاستلام على واتساب ولي الأمر.' : 'تم تسجيل جائزتك! سنتواصل مع ولي الأمر قريباً.',
    ]);
}

if ($action === 'start') {
    $child = trim(mb_substr((string)($_POST['child_name'] ?? ''), 0, 60));
    $phone = normalize_sa_phone((string)($_POST['phone'] ?? ''));
    if (mb_strlen($child) < 2) json_out(['ok' => false, 'message' => 'اكتب اسمك من فضلك.']);
    if (!$phone) json_out(['ok' => false, 'message' => 'رقم الجوال غير صحيح. مثال: 05XXXXXXXX']);
    if (empty($_POST['consent'])) json_out(['ok' => false, 'message' => 'يلزم موافقة ولي الأمر.']);

    q('UPDATE winners SET child_name = ?, guardian_phone = ? WHERE id = ?', [$child, $phone, $w['id']]);
    $w['child_name'] = $child;
    $w['guardian_phone'] = $phone;

    if (!otp_enabled()) finish_claim($w);

    $recent = (int)q('SELECT COUNT(*) FROM otp_requests WHERE winner_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)', [$w['id']])->fetchColumn();
    if ($recent >= 3) json_out(['ok' => false, 'message' => 'طلبت الرمز مرات كثيرة. حاول بعد ساعة.']);
    q('INSERT INTO otp_requests (winner_id, phone, ip) VALUES (?, ?, ?)', [$w['id'], $phone, client_ip()]);

    $res = twilio_verify_start($phone);
    if (!$res['ok']) {
        error_log('[otp] ' . $res['error']);
        json_out(['ok' => false, 'message' => 'تعذّر إرسال الرمز الآن، حاول بعد قليل.']);
    }
    json_out(['ok' => true, 'step' => 'verify', 'phone' => mask_phone($phone)]);
}

if ($action === 'verify') {
    if (!otp_enabled() || !$w['guardian_phone']) json_out(['ok' => false, 'message' => 'ابدأ من جديد.']);
    $otp = preg_replace('/\D/', '', normalize_arabic_digits((string)($_POST['otp'] ?? ''))) ?? '';
    if (strlen($otp) < 4) json_out(['ok' => false, 'message' => 'اكتب الرمز الذي وصلك.']);
    if (!twilio_verify_check($w['guardian_phone'], $otp)) json_out(['ok' => false, 'message' => 'الرمز غير صحيح أو انتهت صلاحيته.']);
    finish_claim($w);
}

json_out(['ok' => false, 'message' => 'طلب غير معروف.'], 400);
