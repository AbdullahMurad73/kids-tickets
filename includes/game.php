<?php
declare(strict_types=1);

/**
 * نظام المستويات: الطفل يجمع نقاطاً من الأسئلة والتذاكر، ويصعد البرونزي ← الفضي ← الذهبي.
 * الترتيب العادل = من وصل حد المستوى أولاً (level_progress.reached_at بدقة الملي ثانية).
 * لا توجد أي طريقة لشراء النقاط.
 */

function current_player(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;
    $cache = null;
    if (!empty($_SESSION['player_id'])) {
        $cache = q('SELECT * FROM players WHERE id = ? AND is_blocked = 0', [$_SESSION['player_id']])->fetch() ?: null;
        if (!$cache) unset($_SESSION['player_id']);
    }
    return $cache;
}

function player_login(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['player_id'] = $id;
}

/** الاسم الرباعي: أربع كلمات على الأقل، حروف عربية أو إنجليزية فقط */
function valid_full_name(string $name): bool
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if (mb_strlen($name) < 8 || mb_strlen($name) > 120) return false;
    if (!preg_match('/^[\p{Arabic}A-Za-z\s\'-]+$/u', $name)) return false;
    return count(explode(' ', $name)) >= 4;
}

function clean_name(string $name): string
{
    return trim(preg_replace('/\s+/u', ' ', $name) ?? '');
}

function levels_all(bool $activeOnly = true): array
{
    return q('SELECT * FROM levels' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort, min_points')->fetchAll();
}

/** المستوى الحالي والتالي ونسبة التقدم */
function level_state(int $points): array
{
    $cur = null; $next = null;
    foreach (levels_all() as $lv) {
        if ($points >= (int)$lv['min_points']) $cur = $lv;
        elseif ($next === null) $next = $lv;
    }
    $from = $cur ? (int)$cur['min_points'] : 0;
    $to = $next ? (int)$next['min_points'] : $from;
    $pct = $next ? (int)round(100 * ($points - $from) / max(1, $to - $from)) : 100;
    return ['current' => $cur, 'next' => $next, 'pct' => max(0, min(100, $pct)), 'to_next' => $next ? $to - $points : 0];
}

/**
 * يضيف نقاطاً للمتسابق داخل معاملة، ويسجّل وصوله للمستويات الجديدة.
 * يعيد المستويات التي وصلها للتو.
 */
function award_points(int $playerId, int $points, string $reason, string $ref = ''): array
{
    if ($points === 0) return [];
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        $before = (int)q('SELECT points FROM players WHERE id = ? FOR UPDATE', [$playerId])->fetchColumn();
        q('INSERT INTO points_ledger (player_id, points, reason, ref) VALUES (?, ?, ?, ?)', [$playerId, $points, $reason, mb_substr($ref, 0, 60)]);
        q('UPDATE players SET points = points + ? WHERE id = ?', [$points, $playerId]);
        $after = $before + $points;
        $reached = [];
        foreach (levels_all() as $lv) {
            if ($after >= (int)$lv['min_points'] && $before < (int)$lv['min_points']) {
                q('INSERT IGNORE INTO level_progress (player_id, level_id) VALUES (?, ?)', [$playerId, $lv['id']]);
                $lv['rank'] = (int)q('SELECT COUNT(*) FROM level_progress WHERE level_id = ? AND reached_at <= (SELECT reached_at FROM level_progress WHERE player_id = ? AND level_id = ?)', [$lv['id'], $playerId, $lv['id']])->fetchColumn();
                $reached[] = $lv;
            }
        }
        if ($own) $pdo->commit();
        return $reached;
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** سلسلة الأيام: تستمر إذا رجع خلال مهلة grace_days، وتعطي مكافأة أول إجابة في اليوم */
function touch_streak(array $player): int
{
    $today = date('Y-m-d');
    if ($player['last_play_date'] === $today) return 0;
    $grace = max(1, (int)setting('grace_days', '3'));
    $streak = 1;
    if ($player['last_play_date']) {
        $gap = (int)((strtotime($today) - strtotime($player['last_play_date'])) / 86400);
        if ($gap <= $grace) $streak = (int)$player['streak'] + 1;
    }
    q('UPDATE players SET streak = ?, last_play_date = ? WHERE id = ?', [$streak, $today, $player['id']]);
    return $streak >= 2 ? (int)setting('points_streak', '20') : 0;
}

function answered_today(int $playerId): int
{
    return (int)q('SELECT COUNT(*) FROM answers WHERE player_id = ? AND created_at >= CURDATE()', [$playerId])->fetchColumn();
}

function daily_limit(): int
{
    return max(1, (int)setting('daily_questions', '10'));
}

/** سؤال لم يُجب عليه بعد، يناسب عمر المتسابق إن أمكن */
function pick_question(array $player): ?array
{
    $diffMax = ($player['age'] && (int)$player['age'] < 9) ? 1 : 3;
    $q = q(
        'SELECT id, body, opt_a, opt_b, opt_c, opt_d, category FROM questions
          WHERE is_active = 1 AND difficulty <= ? AND id NOT IN (SELECT question_id FROM answers WHERE player_id = ?)
          ORDER BY RAND() LIMIT 1',
        [$diffMax, $player['id']]
    )->fetch();
    return $q ?: null;
}

/** أفضل المتسابقين: النقاط ثم الأسبق بالوصول لآخر نقطة */
function leaderboard(int $limit = 10): array
{
    return q(
        'SELECT p.id, p.full_name, p.avatar, p.points,
                (SELECT MAX(created_at) FROM points_ledger l WHERE l.player_id = p.id) AS last_at
           FROM players p WHERE p.is_blocked = 0 AND p.points > 0
          ORDER BY p.points DESC, last_at ASC LIMIT ' . max(1, min(100, $limit))
    )->fetchAll();
}

function level_name_for(int $points): string
{
    $s = level_state($points);
    return $s['current']['name'] ?? 'مبتدئ';
}

const PLAYER_AVATARS = ['a1', 'a2', 'a3', 'a4', 'a5', 'a6'];

function avatar_url(string $a): string
{
    $a = in_array($a, PLAYER_AVATARS, true) ? $a : 'a1';
    return asset('assets/img/avatars/' . $a . '.svg');
}
