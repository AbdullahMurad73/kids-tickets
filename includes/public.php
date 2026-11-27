<?php
declare(strict_types=1);

/** عناصر مشتركة للصفحات العامة: الرأس، الشريط العلوي، التذييل، والأيقونات */

function icon(string $name): string
{
    $p = [
        'home'    => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
        'gift'    => '<rect x="3" y="8" width="18" height="13" rx="2"/><path d="M3 12h18M12 8v13"/><path d="M12 8S10.5 3 7.5 3a2.5 2.5 0 0 0 0 5zM12 8s1.5-5 4.5-5a2.5 2.5 0 0 1 0 5z"/>',
        'grid'    => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'trophy'  => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 6H4a3 3 0 0 0 3 4M17 6h3a3 3 0 0 1-3 4"/>',
        'chat'    => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/>',
        'ticket'  => '<path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"/><path d="M14 6v12" stroke-dasharray="2 2"/>',
        'search'  => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'play'    => '<path d="M7 4.5v15l13-7.5z" fill="currentColor"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'qr'      => '<path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 14h2v2h-2zM14 18h2v2h-2zM18 18h2v2h-2z"/>',
        'chevL'   => '<path d="M15 6l-6 6 6 6"/>',
        'chevR'   => '<path d="M9 6l6 6-6 6"/>',
        'chevD'   => '<path d="M6 9l6 6 6-6"/>',
        'arrowL'  => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'sparkle' => '<path d="M12 2l2.2 6.3L20 10l-5.8 1.7L12 18l-2.2-6.3L4 10l5.8-1.7zM19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z" fill="currentColor" stroke="none"/>',
        'fire'    => '<path d="M12 2c1 4 6 6 6 11a6 6 0 0 1-12 0c0-2.5 1.2-4 2.5-5 0 2 1 3.5 2.5 3.5 0-4-1-6.5 1-9.5z" fill="currentColor" stroke="none"/>',
        'star'    => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z" fill="currentColor" stroke="none"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'whatsapp'=> '<path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z" fill="currentColor" stroke="none"/>',
        'brain'   => '<path d="M9 3a3 3 0 0 0-3 3 3 3 0 0 0-2 5 3 3 0 0 0 2 5 3 3 0 0 0 3 3h1V3zM15 3a3 3 0 0 1 3 3 3 3 0 0 1 2 5 3 3 0 0 1-2 5 3 3 0 0 1-3 3h-1V3z"/>',
        'medal'   => '<circle cx="12" cy="15" r="6"/><path d="M8 3l3 6M16 3l-3 6M12 12.5l.9 1.8 2 .3-1.4 1.4.3 2-1.8-1-1.8 1 .3-2-1.4-1.4 2-.3z"/>',
        'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'bolt'    => '<path d="M13 2L4 14h7l-1 8 9-12h-7z" fill="currentColor" stroke="none"/>',
        'shield'  => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'check'   => '<path d="M5 12l5 5 9-10"/>',
        'x'       => '<path d="M6 6l12 12M18 6L6 18"/>',
    ];
    return '<svg class="i" viewBox="0 0 24 24" aria-hidden="true">' . ($p[$name] ?? '') . '</svg>';
}

function public_head(string $title, string $description = ''): void
{
    $site = setting('site_name', 'أكوما مايند');
    $desc = $description ?: setting('site_tagline', 'تحديات وأسئلة ومستويات وجوائز للأطفال');
    ?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?> · <?= e($site) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="#0b0820">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<link rel="icon" href="<?= e(media_slot_url('favicon')) ?>">
<link rel="apple-touch-icon" href="<?= e(media_slot_url('favicon')) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($site) ?> — <?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:image" content="<?= e(media_slot_url('og')) ?>">
<meta name="twitter:card" content="summary_large_image">
</head>
<?php
}

function public_nav(string $active): void
{
    $site = setting('site_name', 'أكوما مايند');
    $support = preg_replace('/\D/', '', setting('support_whatsapp')) ?? '';
    $player = current_player();
    $links = [
        'home'    => ['./', 'home', 'الرئيسية'],
        'play'    => ['play.php', 'brain', 'التحدي'],
        'levels'  => ['./#levels', 'medal', 'المستويات'],
        'prizes'  => ['./#prizes', 'gift', 'الجوائز'],
        'leaders' => ['./#leaders', 'trophy', 'المتصدرون'],
    ];
    ?>
<header class="nav">
  <div class="wrap nav__inner">
    <a class="brand" href="./" aria-label="<?= e($site) ?>">
      <img class="brand__logo" src="<?= e(media_slot_url('logo')) ?>" alt="<?= e($site) ?>">
    </a>
    <nav class="nav__links" aria-label="القائمة">
      <?php foreach ($links as $k => [$href, $ic, $label]): ?>
        <a href="<?= e($href) ?>" class="<?= $k === $active ? 'is-active' : '' ?>"><?= icon($ic) ?><?= e($label) ?></a>
      <?php endforeach; ?>
      <?php if ($support): ?><a href="https://wa.me/<?= e($support) ?>" target="_blank" rel="noopener"><?= icon('chat') ?>تواصل</a><?php endif; ?>
    </nav>
    <form class="nav__code" id="navCode" autocomplete="off" role="search" action="./">
      <?= icon('ticket') ?>
      <label for="navCodeInput" class="sr-only">رمز التذكرة</label>
      <input id="navCodeInput" name="c" dir="ltr" maxlength="14" spellcheck="false" placeholder="رمز التذكرة…">
      <button type="submit" aria-label="اكشف"><?= icon('search') ?></button>
    </form>
    <a class="nav__avatar<?= $player ? ' is-in' : '' ?>" href="play.php" aria-label="<?= $player ? 'حسابي' : 'سجّل وابدأ التحدي' ?>">
      <img src="<?= e($player ? avatar_url($player['avatar']) : media_slot_url('avatar')) ?>" alt="">
      <?php if ($player): ?><span class="nav__pts"><?= number_format((int)$player['points']) ?></span><?php endif; ?>
    </a>
  </div>
</header>
  <nav class="tabbar" aria-label="القائمة السفلية">
    <?php foreach (['home', 'play', 'levels', 'leaders'] as $k): [$href, $ic, $label] = $links[$k]; ?>
      <a href="<?= e($href) ?>" class="<?= $k === $active ? 'is-active' : '' ?>"><?= icon($ic) ?><span><?= e($label) ?></span></a>
    <?php endforeach; ?>
  </nav>
<?php
}

function public_footer(): void
{
    $site = setting('site_name', 'أكوما مايند');
    $support = preg_replace('/\D/', '', setting('support_whatsapp')) ?? '';
    $license = setting('contest_license');
    ?>
<footer class="footer">
  <div class="wrap footer__inner">
    <div class="footer__brand">
      <img src="<?= e(media_slot_url('logo')) ?>" alt="<?= e($site) ?>">
      <p>تحديات يومية وأسئلة ممتعة ومستويات وجوائز للأطفال. الفوز بالنقاط والاجتهاد، والمشاركة مجانية.</p>
    </div>
    <div class="footer__links">
      <a href="play.php">ابدأ التحدي</a>
      <a href="./#levels">المستويات والجوائز</a>
      <a href="./#faq">الأسئلة الشائعة</a>
      <a href="terms.php">الشروط والأحكام</a>
      <a href="privacy.php">سياسة الخصوصية</a>
      <?php if ($support): ?><a href="https://wa.me/<?= e($support) ?>" target="_blank" rel="noopener">تواصل معنا على واتساب</a><?php endif; ?>
    </div>
    <div class="footer__legal">
      <span>© <?= date('Y') ?> <?= e($site) ?></span>
      <?php if ($license): ?><span>رقم ترخيص المسابقة: <bdi><?= e($license) ?></bdi></span><?php endif; ?>
      <span>المشاركة مجانية ولا يلزم أي شراء للفوز.</span>
    </div>
  </div>
</footer>
<?php
}

/** مكعب ثلاثي الأبعاد بستة وجوه (CSS) */
function cube3d(array $faces, string $mod = ''): string
{
    $names = ['front', 'back', 'right', 'left', 'top', 'bottom'];
    $h = '<div class="cube3d' . ($mod ? ' ' . e($mod) : '') . '" aria-hidden="true"><div class="cube3d__body">';
    foreach ($names as $i => $n) {
        $h .= '<span class="cube3d__f cube3d__f--' . $n . '">' . e($faces[$i % count($faces)]) . '</span>';
    }
    return $h . '</div></div>';
}

/** عملة/ميدالية ثلاثية الأبعاد بوجهين وحافة */
function coin3d(string $front, string $back, string $tint): string
{
    $edge = '';
    for ($i = 1; $i <= 8; $i++) $edge .= '<i style="--z:' . $i . '"></i>';
    return '<div class="coin3d coin3d--' . e($tint) . '" aria-hidden="true"><div class="coin3d__body">' . $edge
        . '<span class="coin3d__face">' . e($front) . '</span><span class="coin3d__face coin3d__face--back">' . e($back) . '</span></div></div>';
}
