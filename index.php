<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$siteName = setting('site_name', 'تذكرة الحظ');
$tagline  = setting('site_tagline', 'أدخل رمز تذكرتك واكتشف إذا ربحت!');

$prizes = q(
    "SELECT p.name, p.image, p.quantity, p.won_count
       FROM prizes p JOIN batches b ON b.id = p.batch_id
      WHERE b.status = 'active' AND (b.ends_at IS NULL OR b.ends_at > NOW())
      ORDER BY p.quantity ASC, p.id DESC LIMIT 10"
)->fetchAll();

$winners = q(
    "SELECT w.child_name, p.name AS prize, w.created_at
       FROM winners w JOIN tickets t ON t.id = w.ticket_id JOIN prizes p ON p.id = t.prize_id
      WHERE w.child_name IS NOT NULL
      ORDER BY w.id DESC LIMIT 5"
)->fetchAll();

$prefill = isset($_GET['c']) ? format_code(substr(normalize_code((string)$_GET['c']), 0, CODE_LENGTH)) : '';
$prizeTints = ['violet', 'pink', 'teal', 'sky', 'amber'];
$botOn = bot_enabled();

function time_ago_ar(string $dt): string
{
    $d = max(0, time() - strtotime($dt));
    if ($d < 3600) return 'قبل ' . max(1, intdiv($d, 60)) . ' دقيقة';
    if ($d < 86400) return 'قبل ' . intdiv($d, 3600) . ' ساعة';
    return 'قبل ' . intdiv($d, 86400) . ' يوم';
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($siteName) ?> — اكشف تذكرتك</title>
<meta name="description" content="<?= e($tagline) ?>">
<meta name="theme-color" content="#0d0a24">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body>
<div class="sky" aria-hidden="true"><span></span><span></span><span></span></div>

<header class="nav">
  <div class="wrap nav__inner">
    <a class="brand" href="./">
      <svg class="brand__mark" viewBox="0 0 48 48" aria-hidden="true"><defs><linearGradient id="bm" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#c06bff"/><stop offset="1" stop-color="#4fd8ff"/></linearGradient></defs><path d="M8 12a4 4 0 0 1 4-4h24a4 4 0 0 1 4 4v6a6 6 0 0 0 0 12v6a4 4 0 0 1-4 4H12a4 4 0 0 1-4-4v-6a6 6 0 0 0 0-12z" fill="url(#bm)"/><path d="M24 15l2.6 5.4 5.9.8-4.3 4.1 1 5.8L24 28.4l-5.2 2.7 1-5.8-4.3-4.1 5.9-.8z" fill="#fff"/></svg>
      <span><?= e($siteName) ?></span>
    </a>
    <nav class="nav__links" aria-label="القائمة">
      <a href="#top" class="is-active">الرئيسية</a>
      <a href="#prizes">الجوائز</a>
      <a href="#how">كيف تلعب</a>
      <a href="#winners">الرابحون</a>
    </nav>
    <a href="#top" class="nav__cta" data-focus-code>اكشف تذكرتك</a>
  </div>
</header>

<main id="top">
  <!-- البانر الرئيسي -->
  <section class="wrap hero">
    <div class="hero__card">
      <div class="hero__text">
        <p class="eyebrow">اكشف · افرح · اربح</p>
        <h1>جرّب حظك<br><span class="grad">واكشف تذكرتك</span></h1>
        <p class="hero__lead"><?= e($tagline) ?></p>

        <form class="code-form" id="codeForm" autocomplete="off" novalidate>
          <label for="code" class="sr-only">رمز التذكرة</label>
          <input id="code" name="code" inputmode="text" maxlength="14" dir="ltr" spellcheck="false"
                 placeholder="XXXX-XXXX-XX" value="<?= e($prefill) ?>" aria-describedby="codeHint">
          <div class="code-form__actions">
            <button type="submit" class="btn btn--primary">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"/></svg>
              اكشف تذكرتي
            </button>
            <button type="button" class="btn btn--ghost" id="scanBtn">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10"/></svg>
              امسح QR
            </button>
          </div>
          <p id="codeHint" class="code-form__hint">الرمز مكتوب على تذكرتك: 10 حروف وأرقام</p>
          <?php if ($botOn): ?>
          <a class="wa-link" href="<?= e(bot_chat_link()) ?>" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.5-3.9-4.7-4.1-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.6-.4.4c-.1.2-.3.3-.1.6.2.3.8 1.3 1.6 2.1 1.1 1 2 1.3 2.3 1.4.3.2.5.1.6 0l.9-1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3.1.1.1.6-.1 1.2z"/></svg>
            أو أرسل الرمز على واتساب
          </a>
          <?php endif; ?>
          <p class="code-form__error" id="codeError" role="alert"></p>
        </form>
        <div class="hero__dots" aria-hidden="true"><i class="on"></i><i></i><i></i><i></i></div>
      </div>

      <div class="hero__art" aria-hidden="true">
        <svg viewBox="0 0 420 380" class="mascot">
          <defs>
            <linearGradient id="tk" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#b65cff"/><stop offset=".55" stop-color="#7a5cff"/><stop offset="1" stop-color="#45c8ff"/></linearGradient>
            <radialGradient id="glow" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#c88cff" stop-opacity=".55"/><stop offset="1" stop-color="#c88cff" stop-opacity="0"/></radialGradient>
            <linearGradient id="bal1" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ff7ac8"/><stop offset="1" stop-color="#ff4fa0"/></linearGradient>
            <linearGradient id="bal2" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#6ff0ff"/><stop offset="1" stop-color="#2fb8ff"/></linearGradient>
            <linearGradient id="bal3" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffe17a"/><stop offset="1" stop-color="#ffb84f"/></linearGradient>
          </defs>
          <circle cx="215" cy="200" r="170" fill="url(#glow)"/>
          <g class="float-a"><path d="M88 52c0 22 -16 34 -16 34s-16-12-16-34a16 16 0 0 1 32 0z" fill="url(#bal1)"/><path d="M72 86q4 30 -6 60" stroke="#ff9bd6" stroke-width="1.5" fill="none" opacity=".7"/></g>
          <g class="float-b"><path d="M372 92c0 24 -18 37 -18 37s-18-13-18-37a18 18 0 0 1 36 0z" fill="url(#bal2)"/><path d="M354 129q-6 30 4 62" stroke="#8fe8ff" stroke-width="1.5" fill="none" opacity=".7"/></g>
          <g class="float-c"><path d="M330 22c0 16 -12 25 -12 25s-12-9-12-25a12 12 0 0 1 24 0z" fill="url(#bal3)"/><path d="M318 47q3 20 -3 40" stroke="#ffe5a0" stroke-width="1.2" fill="none" opacity=".7"/></g>
          <!-- التذكرة الشخصية "تيكو" -->
          <g class="mascot__body">
            <g transform="rotate(-8 215 210)">
              <path d="M110 120a16 16 0 0 1 16-16h178a16 16 0 0 1 16 16v44a26 26 0 0 0 0 52v44a16 16 0 0 1-16 16H126a16 16 0 0 1-16-16v-44a26 26 0 0 0 0-52z" fill="url(#tk)"/>
              <path d="M110 120a16 16 0 0 1 16-16h178a16 16 0 0 1 16 16v44a26 26 0 0 0 0 52v44a16 16 0 0 1-16 16H126a16 16 0 0 1-16-16v-44a26 26 0 0 0 0-52z" fill="none" stroke="#fff" stroke-opacity=".35" stroke-width="2"/>
              <line x1="262" y1="114" x2="262" y2="266" stroke="#fff" stroke-opacity=".45" stroke-width="3" stroke-dasharray="2 9" stroke-linecap="round"/>
              <ellipse cx="160" cy="176" rx="14" ry="17" fill="#1b0f3d"/><ellipse cx="216" cy="176" rx="14" ry="17" fill="#1b0f3d"/>
              <circle cx="165" cy="170" r="5" fill="#fff"/><circle cx="221" cy="170" r="5" fill="#fff"/>
              <ellipse cx="146" cy="204" rx="11" ry="6" fill="#ff8fcf" opacity=".7"/><ellipse cx="230" cy="204" rx="11" ry="6" fill="#ff8fcf" opacity=".7"/>
              <path d="M170 206q18 20 36 0" stroke="#1b0f3d" stroke-width="6" fill="none" stroke-linecap="round"/>
              <path d="M290 172l5.5 11.2 12.3 1.8-8.9 8.7 2.1 12.3-11-5.8-11 5.8 2.1-12.3-8.9-8.7 12.3-1.8z" fill="#ffe27a"/>
              <path d="M110 238q-34 8 -46 -14" stroke="url(#tk)" stroke-width="12" stroke-linecap="round" fill="none"/>
              <path d="M320 150q36 -18 40 -50" stroke="url(#tk)" stroke-width="12" stroke-linecap="round" fill="none"/>
            </g>
          </g>
          <g class="sparkles" fill="#fff"><path d="M60 250l4 10 10 4-10 4-4 10-4-10-10-4 10-4z"/><path d="M380 260l3 7 7 3-7 3-3 7-3-7-7-3 7-3z" opacity=".8"/><path d="M250 40l3 7 7 3-7 3-3 7-3-7-7-3 7-3z" opacity=".9"/><circle cx="120" cy="330" r="3" opacity=".7"/><circle cx="340" cy="330" r="2.5" opacity=".6"/></g>
        </svg>
      </div>
    </div>
  </section>

  <!-- الجوائز -->
  <section class="wrap section" id="prizes">
    <div class="section__head">
      <h2><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l2.4 6.9H22l-6 4.6 2.3 7L12 16.2 5.7 20.5 8 13.5 2 8.9h7.6z"/></svg>جوائز الحملة</h2>
      <div class="scroller-ctrl">
        <button type="button" class="circle-btn" data-scroll="prizeRow" data-dir="1" aria-label="السابق"><svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></button>
        <button type="button" class="circle-btn" data-scroll="prizeRow" data-dir="-1" aria-label="التالي"><svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg></button>
      </div>
    </div>
    <?php if ($prizes): ?>
    <div class="prize-row" id="prizeRow">
      <?php foreach ($prizes as $i => $p): $left = max(0, (int)$p['quantity'] - (int)$p['won_count']); ?>
      <article class="prize-card tint-<?= $prizeTints[$i % count($prizeTints)] ?>">
        <div class="prize-card__media">
          <?php if ($p['image']): ?>
            <img src="<?= e(prize_image_url($p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
          <?php else: ?>
            <svg viewBox="0 0 64 64" aria-hidden="true"><rect x="10" y="28" width="44" height="28" rx="4" fill="rgba(255,255,255,.9)"/><rect x="6" y="20" width="52" height="12" rx="3" fill="#fff"/><rect x="29" y="20" width="6" height="36" fill="currentColor"/><path d="M32 20c-4-8-14-10-14-3 0 4 8 3 14 3zm0 0c4-8 14-10 14-3 0 4-8 3-14 3z" fill="none" stroke="#fff" stroke-width="3"/></svg>
          <?php endif; ?>
          <span class="badge"><?= $left > 0 ? 'متبقي ' . number_format($left) : 'نفدت' ?></span>
        </div>
        <h3><?= e($p['name']) ?></h3>
      </article>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <p class="empty">الجوائز تُعلن قريباً… ترقّبوا!</p>
    <?php endif; ?>
  </section>

  <div class="wrap split">
    <!-- كيف تلعب -->
    <section class="section" id="how">
      <div class="section__head"><h2><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg>كيف تلعب</h2></div>
      <div class="how-grid">
        <div class="how-card tint-violet"><span class="how-card__n">1</span><h3>احصل على تذكرة</h3><p>كل تذكرة فيها رمز سري خاص بك</p></div>
        <div class="how-card tint-pink"><span class="how-card__n">2</span><h3>أدخل الرمز</h3><p><?= $botOn ? 'اكتبه هنا، امسح QR، أو أرسله على واتساب' : 'اكتبه هنا أو امسح رمز QR' ?></p></div>
        <div class="how-card tint-sky"><span class="how-card__n">3</span><h3>اكشف النتيجة</h3><p>افتح صندوق المفاجأة واعرف حظك</p></div>
        <div class="how-card tint-teal"><span class="how-card__n">4</span><h3>استلم جائزتك</h3><p>نرسل التفاصيل لولي أمرك على واتساب</p></div>
      </div>
    </section>

    <!-- آخر الرابحين -->
    <section class="section winners" id="winners">
      <div class="section__head"><h2><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2c1 4 5 5 5 10a5 5 0 0 1-10 0c0-2 1-3 2-4 0 2 1 3 2 3 0-4-1-6 1-9z"/></svg>آخر الرابحين</h2></div>
      <?php if ($winners): ?>
      <ol class="winner-list">
        <?php foreach ($winners as $i => $w): ?>
        <li>
          <span class="winner-list__n"><?= $i + 1 ?></span>
          <span class="winner-list__avatar" aria-hidden="true"><?= e(mb_substr(first_name($w['child_name']), 0, 1)) ?></span>
          <span class="winner-list__body"><strong><?= e(first_name($w['child_name'])) ?></strong><small>ربح <?= e($w['prize']) ?></small></span>
          <span class="winner-list__time"><?= e(time_ago_ar($w['created_at'])) ?></span>
        </li>
        <?php endforeach; ?>
      </ol>
      <?php else: ?>
        <p class="empty">كن أول الرابحين! 🎉</p>
      <?php endif; ?>
    </section>
  </div>
</main>

<footer class="footer">
  <div class="wrap footer__inner">
    <span>© <?= date('Y') ?> <?= e($siteName) ?></span>
    <span>نحفظ الاسم الأول وجوال ولي الأمر للرابحين فقط لتسليم الجائزة.</span>
    <?php if ($wa = preg_replace('/\D/', '', setting('support_whatsapp'))): ?>
      <a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">تواصل معنا على واتساب</a>
    <?php endif; ?>
  </div>
</footer>

<!-- نافذة النتيجة -->
<div class="modal" id="resultModal" hidden role="dialog" aria-modal="true" aria-labelledby="resultTitle">
  <div class="modal__panel">
    <button type="button" class="modal__close" data-close aria-label="إغلاق">×</button>

    <div class="stage stage--suspense" data-stage="suspense">
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
      <h2 id="resultTitle" class="win-title">ألف مبروك! 🎉</h2>
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
      <button type="button" class="btn btn--primary" data-again>حسناً</button>
    </div>
  </div>
</div>

<!-- نافذة مسح QR -->
<div class="modal" id="scanModal" hidden role="dialog" aria-modal="true" aria-label="مسح رمز QR">
  <div class="modal__panel">
    <button type="button" class="modal__close" data-close aria-label="إغلاق">×</button>
    <h2 class="scan-title">وجّه الكاميرا على رمز QR</h2>
    <div id="qrReader" class="qr-reader"></div>
    <p class="form-msg" id="scanMsg"></p>
  </div>
</div>

<canvas id="confetti" aria-hidden="true"></canvas>
<script>window.KT = { base: <?= json_encode(rtrim(base_url(''), '/') . '/', JSON_UNESCAPED_SLASHES) ?>, autoReveal: <?= $prefill !== '' ? 'true' : 'false' ?> };</script>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
