<?php
declare(strict_types=1);

/**
 * صور الموقع القابلة للتبديل من لوحة التحكم.
 * كل مكان له مقاس موصى به، والصورة المرفوعة تُصغَّر تلقائياً إن كانت أكبر.
 * القيمة محفوظة في settings باسم media_<key> = اسم الملف داخل uploads/.
 */
const MEDIA_SLOTS = [
    'logo'     => ['الشعار في الشريط العلوي', 1200, 520, 'assets/img/brand/logo-horizontal-dark.png', 'PNG شفاف، نسخة الشعار المخصصة للخلفية الداكنة'],
    'mascot'   => ['المسكوت (الشخصية التي تقفز وتظهر في شاشة الافتتاح)', 600, 600, 'assets/img/brand/mascot.png', 'PNG شفاف، الشخصية وحدها بدون نص'],
    'favicon'  => ['أيقونة الموقع في المتصفح', 512, 512, 'assets/img/favicon.png', 'PNG مربع'],
    'og'       => ['صورة المشاركة (تظهر عند إرسال الرابط في واتساب)', 1200, 630, 'assets/img/brand/logo-mark.png', 'JPG أو PNG بعرض 1200 وارتفاع 630'],
    'avatar'   => ['صورة الزر الدائري أعلى الصفحة', 256, 256, 'assets/img/art/avatar.svg', 'PNG شفاف مربع'],
    'how_1'    => ['بطاقة «كيف تلعب» 1: سجّل', 600, 400, 'assets/img/art/card-get.svg', 'PNG أو WEBP'],
    'how_2'    => ['بطاقة «كيف تلعب» 2: جاوب', 600, 400, 'assets/img/art/card-enter.svg', 'PNG أو WEBP'],
    'how_3'    => ['بطاقة «كيف تلعب» 3: اجمع النقاط', 600, 400, 'assets/img/art/card-reveal.svg', 'PNG أو WEBP'],
    'how_4'    => ['بطاقة «كيف تلعب» 4: اربح', 600, 400, 'assets/img/art/card-claim.svg', 'PNG أو WEBP'],
    'level_1'  => ['صورة جائزة المستوى البرونزي', 800, 500, 'assets/img/art/prize-ball.svg', 'PNG شفاف، الجائزة في الوسط'],
    'level_2'  => ['صورة جائزة المستوى الفضي', 800, 500, 'assets/img/art/prize-game.svg', 'PNG شفاف، الجائزة في الوسط'],
    'level_3'  => ['صورة جائزة المستوى الذهبي', 800, 500, 'assets/img/art/prize-bike.svg', 'PNG شفاف، الجائزة في الوسط'],
];

function media_slot_url(string $key): string
{
    $slot = MEDIA_SLOTS[$key] ?? null;
    $file = setting('media_' . $key);
    if ($file !== '' && is_file(APP_ROOT . '/uploads/' . basename($file))) {
        return base_url('uploads/' . basename($file)) . '?v=' . filemtime(APP_ROOT . '/uploads/' . basename($file));
    }
    return $slot ? asset($slot[3]) : '';
}

/** يحفظ صورة مرفوعة ويصغّرها لتناسب المقاس الأقصى. يحافظ على الشفافية. */
function save_slot_image(array $file, int $maxW, int $maxH): ?string
{
    $name = save_uploaded_image($file);
    if (!$name || !function_exists('imagecreatefromstring')) return $name;
    $path = APP_ROOT . '/uploads/' . $name;
    [$w, $h] = getimagesize($path) ?: [0, 0];
    if ($w <= $maxW && $h <= $maxH) return $name;
    $src = @imagecreatefromstring((string)file_get_contents($path));
    if (!$src) return $name;
    $scale = min($maxW / $w, $maxH / $h);
    $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $ok = match ($ext) {
        'png'  => imagepng($dst, $path, 8),
        'webp' => function_exists('imagewebp') ? imagewebp($dst, $path, 85) : false,
        default => imagejpeg($dst, $path, 86),
    };
    imagedestroy($src); imagedestroy($dst);
    return $name;
}
