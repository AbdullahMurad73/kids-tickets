<?php
declare(strict_types=1);

/**
 * منطق التذاكر المشترك بين الموقع وبوت واتساب.
 * $actor: من يحاول (عنوان IP للموقع، أو "wa:+9665..." لواتساب) — يُستخدم لحد المحاولات.
 */
function redeem_code(string $raw, string $actor, string $source = 'web'): array
{
    $code = normalize_code($raw);

    if (failed_attempts_last_hour($actor) >= MAX_FAILED_PER_HOUR) {
        record_attempt($actor, $code, 'blocked', $source);
        return ['ok' => false, 'result' => 'blocked', 'message' => 'محاولات كثيرة! استرح قليلاً وجرّب بعد ساعة.'];
    }
    if (strlen($code) !== CODE_LENGTH) {
        record_attempt($actor, $code, 'invalid', $source);
        return ['ok' => false, 'result' => 'invalid', 'message' => 'الرمز يتكون من ' . CODE_LENGTH . ' حروف وأرقام. تأكد منه وجرّب مرة ثانية.'];
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $t = q(
            'SELECT t.id, t.code, t.status, t.prize_id, b.status AS batch_status, b.starts_at, b.ends_at
               FROM tickets t JOIN batches b ON b.id = t.batch_id
              WHERE t.code = ? FOR UPDATE',
            [$code]
        )->fetch();

        $fail = null;
        if (!$t || $t['status'] === 'void') {
            $fail = ['invalid', 'هذا الرمز غير صحيح. تأكد من كتابته كما في التذكرة.'];
        } elseif ($t['status'] === 'used') {
            $fail = ['used', 'هذه التذكرة استُخدمت من قبل.'];
        } else {
            $now = time();
            $notStarted = $t['starts_at'] && strtotime($t['starts_at']) > $now;
            $ended = $t['ends_at'] && strtotime($t['ends_at']) < $now;
            if ($t['batch_status'] !== 'active' || $notStarted || $ended) {
                $fail = ['inactive', $notStarted ? 'الحملة لم تبدأ بعد، انتظرنا قريباً!' : 'انتهت هذه الحملة. ترقّب حملتنا القادمة!'];
            }
        }
        if ($fail) {
            $pdo->rollBack();
            record_attempt($actor, $code, $fail[0], $source);
            return ['ok' => false, 'result' => $fail[0], 'message' => $fail[1]];
        }

        $usedBy = $source === 'whatsapp' ? $actor : client_ip();
        q("UPDATE tickets SET status = 'used', used_at = NOW(), used_ip = ? WHERE id = ?", [substr($usedBy, 0, 45), $t['id']]);

        if (!$t['prize_id']) {
            $pdo->commit();
            record_attempt($actor, $code, 'lose', $source);
            return ['ok' => true, 'result' => 'lose'];
        }

        $prize = q('SELECT id, name, image FROM prizes WHERE id = ?', [$t['prize_id']])->fetch();
        q('UPDATE prizes SET won_count = won_count + 1 WHERE id = ?', [$t['prize_id']]);
        $token = bin2hex(random_bytes(16));
        q('INSERT INTO winners (ticket_id, claim_token, source) VALUES (?, ?, ?)', [$t['id'], $token, $source]);
        $winnerId = (int)$pdo->lastInsertId();
        $pdo->commit();
        record_attempt($actor, $code, 'win', $source);

        return [
            'ok'          => true,
            'result'      => 'win',
            'winner_id'   => $winnerId,
            'prize'       => ['name' => $prize['name'], 'image' => prize_image_url($prize['image'])],
            'claim_token' => $token,
            'claim_no'    => format_code($code),
        ];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[redeem] ' . $ex->getMessage());
        return ['ok' => false, 'result' => 'error', 'message' => 'حدث خطأ غير متوقع، حاول بعد قليل.'];
    }
}

/** يجلب بيانات الفائز مع الجائزة ورمز التذكرة */
function find_winner(int $winnerId): ?array
{
    return q(
        'SELECT w.*, p.name AS prize_name, t.code
           FROM winners w JOIN tickets t ON t.id = w.ticket_id LEFT JOIN prizes p ON p.id = t.prize_id
          WHERE w.id = ?',
        [$winnerId]
    )->fetch() ?: null;
}

/** يثبّت بيانات الفائز بعد التحقق من رقم ولي الأمر */
function complete_winner(int $winnerId, string $childName, string $phone): ?array
{
    q('UPDATE winners SET child_name = ?, guardian_phone = ?, phone_verified_at = COALESCE(phone_verified_at, NOW()) WHERE id = ?', [
        trim(mb_substr($childName, 0, 60)), $phone, $winnerId,
    ]);
    return find_winner($winnerId);
}

function winner_vars(array $w): array
{
    return ['child' => first_name($w['child_name']), 'prize' => (string)$w['prize_name'], 'claim' => format_code((string)$w['code'])];
}
