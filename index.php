<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$siteName = setting('site_name', 'تذكرة الحظ');
$tagline  = setting('site_tagline', 'أدخل رمز تذكرتك واكتشف إذا ربحت!');
$botOn    = bot_enabled();

$banners = q('SELECT * FROM banners WHERE is_active = 1 ORDER BY sort, id')->fetchAll();
if (!$banners) {
    $banners = [['eyebrow' => 'اكشف · افرح · اربح', 'title1' => 'جرّب حظك', 'title2' => 'واكشف تذكرتك', 'body' => $tagline, 'image' => 'art:hero-city.svg', 'btn_text' => 'اكشف تذكرتك']];
}

$prizes = q(
    "SELECT p.name, p.image, p.quantity, p.won_count
       FROM prizes p JOIN batches b ON b.id = p.batch_id
      WHERE b.status = 'active' AND (b.ends_at IS NULL OR b.ends_at > NOW())
      ORDER BY (p.quantity <= p.won_count) ASC, p.quantity ASC, p.id DESC LIMIT 12"
)->fetchAll();

$winners = q(
    "SELECT w.child_name, w.created_at, p.name AS prize, p.image
       FROM winners w JOIN tickets t ON t.id = w.ticket_id JOIN prizes p ON p.id = t.prize_id
      WHERE w.child_name IS NOT NULL
      ORDER BY w.id DESC LIMIT 5"
)->fetchAll();

$prefill = isset($_GET['c']) ? format_code(substr(normalize_code((string)$_GET['c']), 0, CODE_LENGTH)) : '';
$support = preg_replace('/\D/', '', setting('support_whatsapp')) ?? '';

function time_ago_ar(string $dt): string
{
    $d = max(0, time() - strtotime($dt));
    if ($d < 3600) return 'قبل ' . max(1, intdiv($d, 60)) . ' د';
    if ($d < 86400) return 'قبل ' . intdiv($d, 3600) . ' س';
    return 'قبل ' . intdiv($d, 86400) . ' يوم';
}

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
        'arrowL'  => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'sparkle' => '<path d="M12 2l2.2 6.3L20 10l-5.8 1.7L12 18l-2.2-6.3L4 10l5.8-1.7zM19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z" fill="currentColor" stroke="none"/>',
        'fire'    => '<path d="M12 2c1 4 6 6 6 11a6 6 0 0 1-12 0c0-2.5 1.2-4 2.5-5 0 2 1 3.5 2.5 3.5 0-4-1-6.5 1-9.5z" fill="currentColor" stroke="none"/>',
        'star'    => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z" fill="currentColor" stroke="none"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'whatsapp'=> '<path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z" fill="currentColor" stroke="none"/>',
    ];
    return '<svg class="i" viewBox="0 0 24 24" aria-hidden="true">' . ($p[$name] ?? '') . '</svg>';
}

$howCards = [
    ['card-get.svg',    'احصل على تذكرة', 'كل تذكرة فيها رمز سري خاص بك', 'violet', 'scroll:#prizes'],
    ['card-enter.svg',  'أدخل الرمز',     $botOn ? 'اكتبه هنا، امسح QR، أو أرسله واتساب' : 'اكتبه هنا أو امسح رمز QR', 'pink', 'enter'],
    ['card-reveal.svg', 'اكشف النتيجة',   'افتح صندوق المفاجأة واعرف حظك', 'sky', 'enter'],
    ['card-claim.svg',  'استلم جائزتك',   'نرسل التفاصيل لولي أمرك على واتساب', 'teal', 'scroll:#winners'],
];
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($siteName) ?> — اكشف تذكرتك</title>
<meta name="description" content="<?= e($tagline) ?>">
<meta name="theme-color" content="#0b0820">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body>
<div class="bg" aria-hidden="true"></div>

<!-- ===== الشريط العلوي ===== -->
<header class="nav">
  <div class="wrap nav__inner">
    <a class="brand" href="./" aria-label="<?= e($siteName) ?>">
      <svg class="brand__mark" viewBox="0 0 48 48" aria-hidden="true"><defs><linearGradient id="bm" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#c06bff"/><stop offset="1" stop-color="#4fd8ff"/></linearGradient></defs><ellipse cx="24" cy="25" rx="21" ry="7" fill="none" stroke="url(#bm)" stroke-width="3" transform="rotate(-18 24 25)"/><circle cx="24" cy="24" r="11" fill="url(#bm)"/><path d="M38 6l1.4 3.6L43 11l-3.6 1.4L38 16l-1.4-3.6L33 11l3.6-1.4z" fill="#d9a8ff"/></svg>
      <span class="brand__text"><?php $parts = explode(' ', $siteName, 2); ?><?= e($parts[0]) ?><?php if (isset($parts[1])): ?> <b><?= e($parts[1]) ?></b><?php endif; ?></span>
    </a>

    <nav class="nav__links" aria-label="القائمة">
      <a href="#top" class="is-active"><?= icon('home') ?>الرئيسية</a>
      <a href="#prizes"><?= icon('gift') ?>الجوائز</a>
      <a href="#how"><?= icon('grid') ?>كيف تلعب</a>
      <a href="#winners"><?= icon('trophy') ?>الرابحون</a>
      <?php if ($support): ?><a href="https://wa.me/<?= e($support) ?>" target="_blank" rel="noopener"><?= icon('chat') ?>تواصل معنا</a><?php endif; ?>
    </nav>

    <form class="nav__code" id="navCode" autocomplete="off" role="search">
      <?= icon('ticket') ?>
      <label for="navCodeInput" class="sr-only">رمز التذكرة</label>
      <input id="navCodeInput" dir="ltr" maxlength="14" spellcheck="false" placeholder="أدخل رمز التذكرة…">
      <button type="submit" aria-label="اكشف"><?= icon('search') ?></button>
    </form>

    <button type="button" class="nav__avatar" data-open-enter aria-label="اكشف تذكرتك">
      <img src="<?= e(asset('assets/img/art/avatar.svg')) ?>" alt="">
    </button>
  </div>
</header>

<main id="top">
  <!-- ===== البانر الرئيسي ===== -->
  <section class="wrap">
    <div class="hero" id="hero" data-count="<?= count($banners) ?>">
      <?php foreach ($banners as $i => $b): ?>
      <div class="hero__slide<?= $i === 0 ? ' is-on' : '' ?>" aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>">
        <div class="hero__art" style="background-image:url('<?= e(media_url($b['image'])) ?>')"></div>
        <div class="hero__text">
          <?php if ($b['eyebrow']): ?><p class="eyebrow"><?= e($b['eyebrow']) ?></p><?php endif; ?>
          <h<?= $i === 0 ? '1' : '2' ?> class="hero__title"><?= e($b['title1']) ?><?php if ($b['title2']): ?><br><span class="grad"><?= e($b['title2']) ?></span><?php endif; ?></h<?= $i === 0 ? '1' : '2' ?>>
          <span class="hero__bar" aria-hidden="true"></span>
          <?php if ($b['body']): ?><p class="hero__lead"><?= e($b['body']) ?></p><?php endif; ?>
          <div class="hero__btns">
            <button type="button" class="btn btn--primary" data-open-enter><?= icon('play') ?><?= e($b['btn_text'] ?: 'اكشف تذكرتك') ?></button>
            <a href="#prizes" class="btn btn--ghost"><?= icon('plus') ?>شاهد الجوائز</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (count($banners) > 1): ?>
      <div class="hero__dots" role="tablist" aria-label="البانرات">
        <?php foreach ($banners as $i => $b): ?><button type="button" role="tab" class="<?= $i === 0 ? 'on' : '' ?>" data-slide="<?= $i ?>" aria-label="بانر <?= $i + 1 ?>"></button><?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ===== جوائز الحملة ===== -->
  <section class="wrap section" id="prizes">
    <div class="section__head">
      <h2><?= icon('sparkle') ?>جوائز الحملة</h2>
      <a href="#winners" class="view-all">عرض الرابحين <?= icon('chevL') ?></a>
    </div>
    <?php if ($prizes): ?>
    <div class="carousel">
      <button type="button" class="carousel__arrow carousel__arrow--prev" data-scroll="prizeRow" data-dir="1" aria-label="السابق"><?= icon('chevR') ?></button>
      <div class="prize-row" id="prizeRow">
        <?php foreach ($prizes as $p):
            $left = max(0, (int)$p['quantity'] - (int)$p['won_count']);
            [$tierLabel, $tierIcon] = prize_tier((int)$p['quantity']); ?>
        <article class="pcard<?= $left === 0 ? ' is-out' : '' ?>">
          <img class="pcard__img" src="<?= e(prize_art_url($p['name'], $p['image'])) ?>" alt="" loading="lazy">
          <span class="pcard__badge" title="المتبقي"><?= $left > 0 ? '×' . number_format($left) : 'نفدت' ?></span>
          <div class="pcard__foot">
            <h3><?= e($p['name']) ?></h3>
            <span class="chip chip--<?= e($tierIcon) ?>"><?= icon($tierIcon) ?><?= e($tierLabel) ?></span>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <button type="button" class="carousel__arrow carousel__arrow--next" data-scroll="prizeRow" data-dir="-1" aria-label="التالي"><?= icon('chevL') ?></button>
    </div>
    <?php else: ?>
      <p class="empty">الجوائز تُعلن قريباً… ترقّبوا!</p>
    <?php endif; ?>
  </section>

  <div class="wrap split">
    <!-- ===== كيف تلعب ===== -->
    <section class="section" id="how">
      <div class="section__head"><h2><?= icon('grid') ?>كيف تلعب</h2></div>
      <div class="how-grid">
        <?php foreach ($howCards as $n => [$art, $title, $sub, $tint, $act]): ?>
        <button type="button" class="hcard hcard--<?= $tint ?>" <?= str_starts_with($act, 'scroll:') ? 'data-scroll-to="' . e(substr($act, 7)) . '"' : 'data-open-enter' ?>>
          <span class="hcard__art" style="background-image:url('<?= e(asset('assets/img/art/' . $art)) ?>')"></span>
          <span class="hcard__text"><strong><?= e($title) ?></strong><small><?= e($sub) ?></small></span>
          <span class="hcard__go"><?= icon('arrowL') ?></span>
        </button>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ===== آخر الرابحين ===== -->
    <section class="section panel" id="winners">
      <div class="section__head">
        <h2><?= icon('fire') ?><?= $winners ? 'آخر الرابحين' : 'أبرز الجوائز' ?></h2>
        <a href="#prizes" class="view-all">الكل <?= icon('chevL') ?></a>
      </div>
      <ol class="rank">
        <?php if ($winners): foreach ($winners as $i => $w): ?>
        <li>
          <span class="rank__n"><?= $i + 1 ?></span>
          <img class="rank__thumb" src="<?= e(prize_art_url($w['prize'], $w['image'])) ?>" alt="" loading="lazy">
          <span class="rank__body"><strong><?= e(first_name($w['child_name'])) ?></strong><small>ربح <?= e($w['prize']) ?></small></span>
          <span class="rank__meta"><?= icon('clock') ?><?= e(time_ago_ar($w['created_at'])) ?></span>
        </li>
        <?php endforeach; elseif ($prizes): foreach (array_slice($prizes, 0, 5) as $i => $p): [$tl, $ti] = prize_tier((int)$p['quantity']); ?>
        <li>
          <span class="rank__n"><?= $i + 1 ?></span>
          <img class="rank__thumb" src="<?= e(prize_art_url($p['name'], $p['image'])) ?>" alt="" loading="lazy">
          <span class="rank__body"><strong><?= e($p['name']) ?></strong><small><?= e($tl) ?> · كن أول الرابحين!</small></span>
          <span class="rank__meta rank__meta--<?= e($ti) ?>"><?= icon($ti) ?>×<?= number_format(max(0, (int)$p['quantity'] - (int)$p['won_count'])) ?></span>
        </li>
        <?php endforeach; else: ?>
          <li class="rank__empty">كن أول الرابحين! 🎉</li>
        <?php endif; ?>
      </ol>
    </section>
  </div>
</main>

<footer class="footer">
  <div class="wrap footer__inner">
    <span>© <?= date('Y') ?> <?= e($siteName) ?></span>
    <span>نحفظ الاسم الأول وجوال ولي الأمر للرابحين فقط لتسليم الجائزة.</span>
    <?php if ($support): ?><a href="https://wa.me/<?= e($support) ?>" target="_blank" rel="noopener">تواصل معنا على واتساب</a><?php endif; ?>
  </div>
</footer>

<!-- ===== نافذة الكشف ===== -->
<div class="modal" id="resultModal" hidden role="dialog" aria-modal="true" aria-labelledby="enterTitle">
  <div class="modal__panel">
    <button type="button" class="modal__close" data-close aria-label="إغلاق">×</button>

    <div class="stage" data-stage="enter">
      <img class="enter-mascot" src="<?= e(asset('assets/img/art/avatar.svg')) ?>" alt="">
      <h2 id="enterTitle" class="enter-title">اكشف تذكرتك</h2>
      <p class="enter-sub">اكتب الرمز المكتوب على تذكرتك (10 حروف وأرقام)</p>
      <form class="code-form" id="codeForm" autocomplete="off" novalidate>
        <label for="code" class="sr-only">رمز التذكرة</label>
        <input id="code" name="code" inputmode="text" maxlength="14" dir="ltr" spellcheck="false" placeholder="XXXX-XXXX-XX" value="<?= e($prefill) ?>">
        <p class="code-form__error" id="codeError" role="alert"></p>
        <button type="submit" class="btn btn--primary btn--block"><?= icon('gift') ?>اكشف تذكرتي</button>
        <button type="button" class="btn btn--ghost btn--block" id="scanBtn"><?= icon('qr') ?>امسح رمز QR</button>
        <?php if ($botOn): ?><a class="wa-link" href="<?= e(bot_chat_link()) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?>أو أرسل الرمز على واتساب</a><?php endif; ?>
      </form>
    </div>

    <div class="stage stage--suspense" data-stage="suspense" hidden>
      <svg viewBox="0 0 200 200" class="giftbox" aria-hidden="true">
        <defs><linearGradient id="gb" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#c46bff"/><stop offset="1" stop-color="#6a5cff"/></linearGradient></defs>
        <rect x="40" y="88" width="120" height="86" rx="10" fill="url(#gb)"/>
        <rect x="92" y="88" width="16" height="86" fill="#ffe27a"/>
        <g class="giftbox__lid"><rect x="32" y="66" width="136" height="28" rx="8" fill="#d68cff"/><rect x="92" y="66" width="16" height="28" fill="#ffd34f"/>
        <path d="M100 66c-10-22-38-26-38-10 0 10 22 10 38 10zm0 0c10-22 38-26 38-10 0 10-22 10-38 10z" fill="none" stroke="#ffd34f" stroke-width="8" stroke-linejoin="round"/></g>
      </svg>
      <p class="stage__text">نفتح صندوق المفاجأة…</p>
    </div>

    <div class="stage" data-stage="win" hidden>
      <p class="eyebrow">مبروووك!</p>
      <h2 class="win-title">ألف مبروك! 🎉</h2>
      <p class="win-sub">ربحت <strong id="prizeName"></strong></p>
      <div class="win-prize" id="prizeMedia"></div>
      <p class="claim-no">رقم المطالبة: <bdi id="claimNo" dir="ltr"></bdi></p>

      <form id="claimForm" class="claim-form" novalidate>
        <p class="claim-form__note">اطلب من بابا أو ماما يكملون معك لاستلام الجائزة:</p>
        <label>اسمك<input name="child_name" maxlength="60" required placeholder="مثال: سارة"></label>
        <label>جوال ولي الأمر<input name="phone" inputmode="tel" dir="ltr" required placeholder="05XXXXXXXX"></label>
        <label class="check"><input type="checkbox" name="consent" value="1" required> أنا ولي الأمر وأوافق على استخدام الرقم لتسليم الجائزة</label>
        <button type="submit" class="btn btn--primary btn--block">تأكيد الاستلام</button>
        <p class="form-msg" role="alert"></p>
      </form>

      <form id="otpForm" class="claim-form" hidden novalidate>
        <p class="claim-form__note">أرسلنا رمز تحقق إلى <bdi id="otpPhone" dir="ltr"></bdi></p>
        <label>رمز التحقق<input name="otp" inputmode="numeric" dir="ltr" maxlength="8" required placeholder="••••••" autocomplete="one-time-code"></label>
        <button type="submit" class="btn btn--primary btn--block">تحقق</button>
        <p class="form-msg" role="alert"></p>
      </form>

      <div class="claim-done" id="claimDone" hidden>
        <svg viewBox="0 0 52 52" aria-hidden="true"><circle cx="26" cy="26" r="24" fill="#2fd08a"/><path d="M15 27l7 7 15-15" stroke="#fff" stroke-width="5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <p id="claimDoneMsg"></p>
        <button type="button" class="btn btn--ghost" data-close>تم</button>
      </div>
    </div>

    <div class="stage" data-stage="lose" hidden>
      <svg viewBox="0 0 160 120" class="lose-art" aria-hidden="true"><path d="M20 30a10 10 0 0 1 10-10h100a10 10 0 0 1 10 10v20a12 12 0 0 0 0 24v20a10 10 0 0 1-10 10H30a10 10 0 0 1-10-10V74a12 12 0 0 0 0-24z" fill="#4b3a8f"/><ellipse cx="62" cy="54" rx="7" ry="9" fill="#1b0f3d"/><ellipse cx="98" cy="54" rx="7" ry="9" fill="#1b0f3d"/><circle cx="64" cy="51" r="2.6" fill="#fff"/><circle cx="100" cy="51" r="2.6" fill="#fff"/><path d="M66 80q14 -10 28 0" stroke="#1b0f3d" stroke-width="5" fill="none" stroke-linecap="round"/><path d="M108 64q3 8 0 12q-3-4 0-12z" fill="#6fd8ff"/></svg>
      <h2 class="lose-title">حظ أوفر المرة القادمة</h2>
      <p class="lose-sub">لا تزعل يا بطل! كل تذكرة فرصة جديدة.</p>
      <button type="button" class="btn btn--primary" data-again>جرّب تذكرة أخرى</button>
    </div>

    <div class="stage" data-stage="error" hidden>
      <h2 class="lose-title">أوبس!</h2>
      <p class="lose-sub" id="errorMsg"></p>
      <button type="button" class="btn btn--primary" data-again>حاول مرة أخرى</button>
    </div>
  </div>
</div>

<!-- ===== نافذة مسح QR ===== -->
<div class="modal" id="scanModal" hidden role="dialog" aria-modal="true" aria-label="مسح رمز QR">
  <div class="modal__panel">
    <button type="button" class="modal__close" data-close aria-label="إغلاق">×</button>
    <h2 class="scan-title">وجّه الكاميرا على رمز QR</h2>
    <div id="qrReader" class="qr-reader"></div>
    <p class="form-msg" id="scanMsg"></p>
  </div>
</div>

<canvas id="confetti" aria-hidden="true"></canvas>
<script>window.KT = { base: <?= json_encode(rtrim(base_url(''), '/') . '/', JSON_UNESCAPED_SLASHES) ?>, prefill: <?= $prefill !== '' ? 'true' : 'false' ?> };</script>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
