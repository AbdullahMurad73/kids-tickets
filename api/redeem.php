<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'message' => 'Method not allowed'], 405);
csrf_check();

$res = redeem_code((string)($_POST['code'] ?? ''), client_ip(), 'web');

if ($res['result'] === 'win') {
    $_SESSION['claims'][$res['claim_token']] = time();
    unset($res['winner_id']);
    $res['otp'] = otp_enabled();
}
json_out($res, $res['result'] === 'error' ? 500 : 200);
