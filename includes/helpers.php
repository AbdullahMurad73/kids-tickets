<?php
declare(strict_types=1);

const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // بدون O/0/I/1/L لتجنب الالتباس
const CODE_LENGTH = 10;
const MAX_FAILED_PER_HOUR = 5;

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        if (is_json_request()) {
            json_out(['ok' => false, 'message' => 'انتهت صلاحية الصفحة، حدّثها وحاول مرة أخرى.'], 419);
        }
        exit('انتهت صلاحية الصفحة، ارجع وحدّثها.');
    }
}

function is_json_request(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(?string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function normalize_code(string $raw): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', normalize_arabic_digits($raw)) ?? '');
}

function normalize_arabic_digits(string $s): string
{
    return strtr($s, [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ]);
}

function generate_code(): string
{
    $out = '';
    $max = strlen(CODE_ALPHABET) - 1;
    for ($i = 0; $i < CODE_LENGTH; $i++) {
        $out .= CODE_ALPHABET[random_int(0, $max)];
    }
    return $out;
}

function format_code(string $code): string
{
    return implode('-', str_split($code, 4)) ;
}

/** يحوّل 05XXXXXXXX أو 5XXXXXXXX أو 9665XXXXXXXX إلى +9665XXXXXXXX، أو يعيد null */
function normalize_sa_phone(string $raw): ?string
{
    $p = preg_replace('/\D/', '', normalize_arabic_digits($raw)) ?? '';
    if (str_starts_with($p, '00966')) $p = substr($p, 5);
    elseif (str_starts_with($p, '966')) $p = substr($p, 3);
    if (str_starts_with($p, '0')) $p = substr($p, 1);
    return preg_match('/^5\d{8}$/', $p) ? '+966' . $p : null;
}

function mask_phone(string $p): string
{
    return strlen($p) > 6 ? substr($p, 0, 6) . str_repeat('•', max(0, strlen($p) - 9)) . substr($p, -3) : $p;
}

function first_name(?string $name): string
{
    $name = trim((string)$name);
    if ($name === '') return 'بطل';
    return explode(' ', preg_replace('/\s+/u', ' ', $name))[0];
}

function fill_template(string $tpl, array $vars): string
{
    foreach ($vars as $k => $v) {
        $tpl = str_replace('{' . $k . '}', (string)$v, $tpl);
    }
    return $tpl;
}

function base_url(string $path = ''): string
{
    global $CONFIG;
    return rtrim((string)($CONFIG['base_url'] ?? ''), '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = APP_ROOT . '/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    return base_url($path) . '?v=' . $v;
}

function prize_image_url(?string $image): ?string
{
    return $image ? base_url('uploads/' . $image) : null;
}

function log_activity(string $action, string $details = ''): void
{
    q('INSERT INTO activity_log (admin_id, action, details, ip) VALUES (?, ?, ?, ?)', [
        $_SESSION['admin_id'] ?? null, $action, mb_substr($details, 0, 500), client_ip(),
    ]);
}

function failed_attempts_last_hour(string $ip): int
{
    return (int)q(
        "SELECT COUNT(*) FROM attempts WHERE ip = ? AND result IN ('invalid','used','inactive') AND created_at > (NOW() - INTERVAL 1 HOUR)",
        [$ip]
    )->fetchColumn();
}

function record_attempt(string $ip, string $code, string $result, string $source = 'web'): void
{
    q('INSERT INTO attempts (ip, code_entered, result, source) VALUES (?, ?, ?, ?)', [substr($ip, 0, 45), mb_substr($code, 0, 40), $result, $source]);
}

/** يحفظ صورة جائزة مرفوعة بعد التحقق، ويعيد اسم الملف أو null */
function save_uploaded_image(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > 2 * 1024 * 1024) throw new RuntimeException('حجم الصورة أكبر من 2 ميجابايت.');
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) throw new RuntimeException('الصورة يجب أن تكون JPG أو PNG أو WEBP.');
    $name = bin2hex(random_bytes(12)) . '.' . $types[$info[2]];
    $dir = APP_ROOT . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('تعذّر حفظ الصورة.');
    return $name;
}
