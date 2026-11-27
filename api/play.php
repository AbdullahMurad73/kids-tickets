<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'message' => 'Method not allowed'], 405);
csrf_check();

$action = (string)($_POST['action'] ?? '');

function player_payload(array $p): array
{
    $s = level_state((int)$p['points']);
    return [
        'name'    => first_name($p['full_name']),
        'points'  => (int)$p['points'],
        'streak'  => (int)$p['streak'],
        'level'   => $s['current']['name'] ?? 'مبتدئ',
        'next'    => $s['next']['name'] ?? null,
        'to_next' => $s['to_next'],
        'pct'     => $s['pct'],
        'left'    => max(0, daily_limit() - answered_today((int)$p['id'])),
    ];
}

function otp_throttled(string $phone): bool
{
    $_SESSION['otp_hits'] = array_filter($_SESSION['otp_hits'] ?? [], fn($t) => $t > time() - 3600);
    if (count($_SESSION['otp_hits']) >= 4) return true;
    $_SESSION['otp_hits'][] = time();
    return false;
}

/* ---------- التسجيل ---------- */
if ($action === 'register') {
    $name  = clean_name((string)($_POST['full_name'] ?? ''));
    $phone = normalize_sa_phone((string)($_POST['phone'] ?? ''));
    $age   = (int)($_POST['age'] ?? 0);
    $avatar = (string)($_POST['avatar'] ?? 'a1');
    if (!valid_full_name($name)) json_out(['ok' => false, 'message' => 'اكتب الاسم الرباعي (أربعة أسماء) بالحروف فقط.']);
    if (!$phone) json_out(['ok' => false, 'message' => 'رقم جوال ولي الأمر غير صحيح. مثال: 05XXXXXXXX']);
    if ($age < 5 || $age > 17) json_out(['ok' => false, 'message' => 'اختر العمر من 5 إلى 17 سنة.']);
    if (empty($_POST['consent'])) json_out(['ok' => false, 'message' => 'يلزم موافقة ولي الأمر على الشروط.']);
    if (!in_array($avatar, PLAYER_AVATARS, true)) $avatar = 'a1';
    if (q('SELECT 1 FROM players WHERE phone = ?', [$phone])->fetchColumn()) {
        json_out(['ok' => false, 'message' => 'هذا الرقم مسجّل من قبل. استخدم «عندي حساب» للدخول.', 'exists' => true]);
    }
    $_SESSION['pending_player'] = compact('name', 'phone', 'age', 'avatar');
    if (otp_enabled()) {
        if (otp_throttled($phone)) json_out(['ok' => false, 'message' => 'طلبت الرمز مرات كثيرة. حاول بعد ساعة.']);
        $r = twilio_verify_start($phone);
        if (!$r['ok']) json_out(['ok' => false, 'message' => 'تعذّر إرسال رمز التحقق الآن، حاول بعد قليل.']);
        json_out(['ok' => true, 'step' => 'verify', 'phone' => mask_phone($phone)]);
    }
    $action = 'create';
}

if ($action === 'verify_register') {
    $pp = $_SESSION['pending_player'] ?? null;
    if (!$pp) json_out(['ok' => false, 'message' => 'ابدأ التسجيل من جديد.']);
    $otp = preg_replace('/\D/', '', normalize_arabic_digits((string)($_POST['otp'] ?? ''))) ?? '';
    if (!twilio_verify_check($pp['phone'], $otp)) json_out(['ok' => false, 'message' => 'الرمز غير صحيح أو انتهت صلاحيته.']);
    $action = 'create';
}

if ($action === 'create') {
    $pp = $_SESSION['pending_player'] ?? null;
    if (!$pp) json_out(['ok' => false, 'message' => 'ابدأ التسجيل من جديد.']);
    try {
        q('INSERT INTO players (full_name, phone, age, avatar, consent_at) VALUES (?, ?, ?, ?, NOW())', [$pp['name'], $pp['phone'], $pp['age'], $pp['avatar']]);
    } catch (PDOException $e) {
        json_out(['ok' => false, 'message' => 'هذا الرقم مسجّل من قبل.']);
    }
    $id = (int)db()->lastInsertId();
    unset($_SESSION['pending_player']);
    player_login($id);
    award_points($id, 20, 'welcome');
    json_out(['ok' => true, 'step' => 'done', 'player' => player_payload(current_player())]);
}

/* ---------- الدخول ---------- */
if ($action === 'login') {
    $phone = normalize_sa_phone((string)($_POST['phone'] ?? ''));
    if (!$phone) json_out(['ok' => false, 'message' => 'رقم الجوال غير صحيح.']);
    $p = q('SELECT * FROM players WHERE phone = ? AND is_blocked = 0', [$phone])->fetch();
    if (otp_enabled()) {
        if (!$p) json_out(['ok' => false, 'message' => 'لا يوجد حساب بهذا الرقم. سجّل أولاً.']);
        if (otp_throttled($phone)) json_out(['ok' => false, 'message' => 'طلبت الرمز مرات كثيرة. حاول بعد ساعة.']);
        $_SESSION['pending_login'] = (int)$p['id'];
        $r = twilio_verify_start($phone);
        if (!$r['ok']) json_out(['ok' => false, 'message' => 'تعذّر إرسال رمز التحقق الآن.']);
        json_out(['ok' => true, 'step' => 'verify', 'phone' => mask_phone($phone)]);
    }
    // بدون OTP: يلزم الاسم الرباعي مطابقاً
    $name = clean_name((string)($_POST['full_name'] ?? ''));
    if (!$p || $name === '' || !hash_equals(mb_strtolower($p['full_name']), mb_strtolower($name))) {
        json_out(['ok' => false, 'message' => 'الرقم أو الاسم الرباعي غير مطابق.', 'need_name' => true]);
    }
    player_login((int)$p['id']);
    json_out(['ok' => true, 'step' => 'done', 'player' => player_payload(current_player())]);
}

if ($action === 'verify_login') {
    $id = (int)($_SESSION['pending_login'] ?? 0);
    $p = $id ? q('SELECT * FROM players WHERE id = ?', [$id])->fetch() : null;
    if (!$p) json_out(['ok' => false, 'message' => 'ابدأ الدخول من جديد.']);
    $otp = preg_replace('/\D/', '', normalize_arabic_digits((string)($_POST['otp'] ?? ''))) ?? '';
    if (!twilio_verify_check($p['phone'], $otp)) json_out(['ok' => false, 'message' => 'الرمز غير صحيح أو انتهت صلاحيته.']);
    unset($_SESSION['pending_login']);
    player_login($id);
    json_out(['ok' => true, 'step' => 'done', 'player' => player_payload(current_player())]);
}

if ($action === 'logout') {
    unset($_SESSION['player_id'], $_SESSION['quiz']);
    json_out(['ok' => true]);
}

/* ---------- التحدي ---------- */
$p = current_player();
if (!$p) json_out(['ok' => false, 'message' => 'سجّل دخولك أولاً.', 'auth' => true], 401);

if ($action === 'me') json_out(['ok' => true, 'player' => player_payload($p)]);

if ($action === 'next') {
    if (answered_today((int)$p['id']) >= daily_limit()) {
        json_out(['ok' => true, 'done' => true, 'message' => 'خلصت تحديات اليوم! ارجع بكرة وكمّل سلسلتك.', 'player' => player_payload($p)]);
    }
    // سؤال مفتوح لم يُجب عليه بعد يبقى نفسه (لا يمكن تبديله للهروب من سؤال صعب)
    $open = $_SESSION['quiz'] ?? null;
    $qq = null;
    if ($open && $open['player'] === (int)$p['id']) {
        $qq = q('SELECT id, body, opt_a, opt_b, opt_c, opt_d, category FROM questions WHERE id = ?', [$open['q']])->fetch() ?: null;
    }
    if (!$qq) {
        $qq = pick_question($p);
        if (!$qq) json_out(['ok' => true, 'done' => true, 'message' => 'جاوبت على كل الأسئلة المتاحة! أسئلة جديدة قريباً.', 'player' => player_payload($p)]);
        $_SESSION['quiz'] = ['player' => (int)$p['id'], 'q' => (int)$qq['id'], 't' => microtime(true)];
    }
    $sec = max(5, (int)setting('question_seconds', '20'));
    $elapsed = microtime(true) - $_SESSION['quiz']['t'];
    json_out([
        'ok' => true,
        'question' => [
            'id' => (int)$qq['id'], 'body' => $qq['body'], 'category' => $qq['category'],
            'options' => ['a' => $qq['opt_a'], 'b' => $qq['opt_b'], 'c' => $qq['opt_c'], 'd' => $qq['opt_d']],
            'seconds' => $sec, 'remaining' => max(0, (int)ceil($sec - $elapsed)),
            'n' => answered_today((int)$p['id']) + 1, 'of' => daily_limit(),
        ],
    ]);
}

if ($action === 'answer') {
    $open = $_SESSION['quiz'] ?? null;
    if (!$open || $open['player'] !== (int)$p['id']) json_out(['ok' => false, 'message' => 'لا يوجد سؤال مفتوح.']);
    $choice = (string)($_POST['choice'] ?? '');
    if (!in_array($choice, ['a', 'b', 'c', 'd', 'timeout'], true)) json_out(['ok' => false, 'message' => 'اختر إجابة.']);
    $qq = q('SELECT * FROM questions WHERE id = ?', [$open['q']])->fetch();
    unset($_SESSION['quiz']);
    if (!$qq) json_out(['ok' => false, 'message' => 'السؤال غير متاح.']);

    $sec = max(5, (int)setting('question_seconds', '20'));
    $ms = (int)round((microtime(true) - $open['t']) * 1000);
    $inTime = $ms <= ($sec * 1000 + 1500); // هامش بسيط لتأخر الشبكة
    $correct = $inTime && $choice === $qq['correct'];
    $points = 0;
    if ($correct) {
        $speed = max(0, (int)setting('points_speed', '5'));
        $points = max(0, (int)setting('points_correct', '10')) + (int)round($speed * max(0, 1 - $ms / ($sec * 1000)));
    }
    try {
        q('INSERT INTO answers (player_id, question_id, choice, is_correct, ms, points) VALUES (?, ?, ?, ?, ?, ?)',
          [$p['id'], $qq['id'], $choice === 'timeout' ? null : $choice, $correct ? 1 : 0, $ms, $points]);
    } catch (PDOException $e) {
        json_out(['ok' => false, 'message' => 'تمت الإجابة على هذا السؤال مسبقاً.']);
    }
    $streakBonus = touch_streak($p);
    $levels = [];
    if ($points) $levels = array_merge($levels, award_points((int)$p['id'], $points, 'answer', 'q' . $qq['id']));
    if ($streakBonus) $levels = array_merge($levels, award_points((int)$p['id'], $streakBonus, 'streak', date('Y-m-d')));
    $fresh = q('SELECT * FROM players WHERE id = ?', [$p['id']])->fetch();
    json_out([
        'ok' => true,
        'correct' => $correct,
        'timeout' => !$inTime || $choice === 'timeout',
        'answer' => $qq['correct'],
        'points' => $points,
        'streak_bonus' => $streakBonus,
        'levels' => array_map(fn($l) => ['name' => $l['name'], 'prize' => $l['prize'], 'rank' => $l['rank'], 'limit' => (int)$l['winners_limit']], $levels),
        'player' => player_payload($fresh),
    ]);
}

json_out(['ok' => false, 'message' => 'طلب غير معروف.'], 400);
