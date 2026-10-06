<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

const ADMIN_IDLE_TIMEOUT = 2 * 3600;

function current_admin(): ?array
{
    static $admin = false;
    if ($admin === false) {
        $admin = null;
        if (!empty($_SESSION['admin_id'])) {
            if (time() - (int)($_SESSION['admin_seen'] ?? 0) > ADMIN_IDLE_TIMEOUT) {
                unset($_SESSION['admin_id']);
                return null;
            }
            $_SESSION['admin_seen'] = time();
            $admin = q('SELECT id, name, email, role FROM admins WHERE id = ? AND is_active = 1', [$_SESSION['admin_id']])->fetch() ?: null;
        }
    }
    return $admin;
}

function require_admin(bool $ownerOnly = false): array
{
    $a = current_admin();
    if (!$a) redirect('login.php');
    if ($ownerOnly && $a['role'] !== 'owner') {
        http_response_code(403);
        admin_header('غير مسموح', '');
        echo '<div class="card"><p>هذه الصفحة لمالك الحساب فقط.</p></div>';
        admin_footer();
        exit;
    }
    return $a;
}

function admin_header(string $title, string $active): void
{
    $a = current_admin();
    $site = setting('site_name', 'تذكرة الحظ');
    $nav = [
        'dashboard' => ['index.php', 'الرئيسية', 'M3 12l9-8 9 8M5 10v10h14V10'],
        'batches'   => ['batches.php', 'دفعات التذاكر', 'M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4z'],
        'tickets'   => ['tickets.php', 'البحث عن تذكرة', 'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM21 21l-5-5'],
        'winners'   => ['winners.php', 'الرابحون', 'M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0zM7 6H4a3 3 0 0 0 3 4M17 6h3a3 3 0 0 1-3 4'],
        'messages'  => ['messages.php', 'سجل الرسائل', 'M4 5h16v11H8l-4 4z'],
        'settings'  => ['settings.php', 'الإعدادات', 'M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z'],
    ];
    if ($a && $a['role'] === 'owner') {
        $nav['admins'] = ['admins.php', 'المشرفون', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8'];
    }
    $flash = flash();
    ?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · لوحة التحكم · <?= e($site) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
<link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="admin<?= $a ? '' : ' admin--guest' ?>">
<?php if ($a): ?>
<aside class="side" id="side">
  <a class="side__brand" href="index.php"><span class="side__logo">★</span><span><?= e($site) ?><small>لوحة التحكم</small></span></a>
  <nav>
    <?php foreach ($nav as $key => [$href, $label, $icon]): ?>
      <a href="<?= $href ?>" class="<?= $key === $active ? 'is-active' : '' ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= $icon ?>"/></svg><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="side__foot">
    <div class="side__user"><strong><?= e($a['name']) ?></strong><small><?= e($a['email']) ?></small></div>
    <a href="../" target="_blank" rel="noopener">عرض الموقع ↗</a>
    <form method="post" action="logout.php"><?= csrf_field() ?><button type="submit" class="linklike">تسجيل الخروج</button></form>
  </div>
</aside>
<button class="side-toggle" type="button" onclick="document.getElementById('side').classList.toggle('open')" aria-label="القائمة">☰</button>
<?php endif; ?>
<main class="main">
  <?php if ($a): ?><header class="page-head"><h1><?= e($title) ?></h1></header><?php endif; ?>
  <?php if ($flash): ?><div class="alert alert--<?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div><?php endif; ?>
<?php
}

function admin_footer(): void
{
    echo "\n</main>\n</body>\n</html>";
}

function status_badge(string $status): string
{
    $map = [
        'active' => ['نشطة', 'green'], 'paused' => ['موقوفة', 'amber'], 'ended' => ['منتهية', 'gray'],
        'unused' => ['لم تُستخدم', 'gray'], 'used' => ['مستخدمة', 'violet'], 'void' => ['ملغاة', 'red'],
        'pending' => ['لم تُسلَّم', 'amber'], 'delivered' => ['سُلِّمت', 'green'],
    ];
    [$label, $color] = $map[$status] ?? [$status, 'gray'];
    return '<span class="badge badge--' . $color . '">' . e($label) . '</span>';
}

function dt(?string $s): string
{
    return $s ? date('Y-m-d H:i', strtotime($s)) : '—';
}
