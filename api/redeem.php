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
// كل تذكرة صحيحة تعطي المتسابق المسجّل نقاطاً (رابحة أو لا)
if (in_array($res['result'], ['win', 'lose'], true) && ($pl = current_player())) {
    $pts = max(0, (int)setting('points_ticket', '50'));
    if ($pts > 0) {
        $res['levels'] = array_map(fn($l) => ['name' => $l['name'], 'prize' => $l['prize'], 'rank' => $l['rank']], award_points((int)$pl['id'], $pts, 'ticket', normalize_code((string)($_POST['code'] ?? ''))));
        $res['points_added'] = $pts;
    }
}
json_out($res, $res['result'] === 'error' ? 500 : 200);
