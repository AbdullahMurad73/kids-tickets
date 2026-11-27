<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/public.php';

$siteName = setting('site_name', 'أكوما مايند');
$tagline  = setting('site_tagline', 'تحديات يومية وأسئلة ممتعة ومستويات وجوائز للأبطال!');
$botOn    = bot_enabled();
$player   = current_player();

$banners = q('SELECT * FROM banners WHERE is_active = 1 ORDER BY sort, id')->fetchAll();
if (!$banners) {
    $banners = [['eyebrow' => 'تحدَّ · اجمع · اربح', 'title1' => 'كن بطل', 'title2' => 'أكوما مايند', 'body' => $tagline, 'image' => 'art:hero-city.svg', 'btn_text' => 'ابدأ التحدي']];
}

$prizes = q(
    "SELECT p.name, p.image, p.quantity, p.won_count
       FROM prizes p JOIN batches b ON b.id = p.batch_id
      WHERE b.status = 'active' AND (b.ends_at IS NULL OR b.ends_at > NOW())
      ORDER BY (p.quantity <= p.won_count) ASC, p.quantity ASC, p.id DESC LIMIT 12"
)->fetchAll();

$winners = q(
    "SELECT w.child_name, w.created_at, p.name AS prize
       FROM winners w JOIN tickets t ON t.id = w.ticket_id JOIN prizes p ON p.id = t.prize_id
      WHERE w.child_name IS NOT NULL ORDER BY w.id DESC LIMIT 8"
)->fetchAll();

$levels = levels_all();
$reachedCounts = [];
foreach (q('SELECT level_id, COUNT(*) n FROM level_progress GROUP BY level_id')->fetchAll() as $r) $reachedCounts[(int)$r['level_id']] = (int)$r['n'];
$leaders = leaderboard(10);

$stats = [
    ['user',   (int)q('SELECT COUNT(*) FROM players')->fetchColumn(), 'بطل مشترك'],
    ['brain',  (int)q('SELECT COUNT(*) FROM answers')->fetchColumn(), 'إجابة حتى الآن'],
    ['gift',   (int)q("SELECT COALESCE(SUM(GREATEST(p.quantity - p.won_count, 0)), 0) FROM prizes p JOIN batches b ON b.id = p.batch_id WHERE b.status = 'active' AND (b.ends_at IS NULL OR b.ends_at > NOW())")->fetchColumn(), 'جائزة متبقية'],
    ['trophy', (int)q('SELECT COUNT(*) FROM winners WHERE child_name IS NOT NULL')->fetchColumn() + array_sum($reachedCounts), 'فوز ومستوى محقق'],
];

$prefill = isset($_GET['c']) ? format_code(substr(normalize_code((string)$_GET['c']), 0, CODE_LENGTH)) : '';

$howCards = [
    ['how_1', 'سجّل مرة وحدة', 'اسمك الرباعي وجوال ولي أمرك', 'violet'],
    ['how_2', 'جاوب تحدي اليوم', 'أسئلة ممتعة كل يوم، مجاناً', 'pink'],
    ['how_3', 'اجمع النقاط', 'إجابة صحيحة + سرعة + أيام متتالية', 'sky'],
    ['how_4', 'اصعد واربح', 'برونزي ثم فضي ثم الذهبي الكبير', 'teal'],
];

$faq = [
    ['هل المشاركة مجانية؟', 'نعم. التسجيل والتحديات اليومية مجانية بالكامل، ولا يلزم أي شراء للوصول لأي مستوى أو للفوز بأي جائزة.'],
    ['كيف أفوز بالجائزة الذهبية؟', 'تجمع النقاط من الإجابات الصحيحة وسرعة الإجابة والدخول اليومي. أول من يصل حد المستوى الذهبي يفوز بحسب عدد الجوائز المعلن.'],
    ['وش يصير إذا غبت يومين؟', 'نقاطك محفوظة دائماً. ولو رجعت خلال ' . (int)setting('grace_days', '3') . ' أيام تكمل سلسلتك بدون ما تنقطع.'],
    ['كم سؤال في اليوم؟', 'عندك ' . daily_limit() . ' أسئلة كل يوم، وكل سؤال له ' . (int)setting('question_seconds', '20') . ' ثانية.'],
    ['وش فايدة رمز التذكرة؟', 'كل رمز تذكرة صحيح يعطيك ' . (int)setting('points_ticket', '50') . ' نقطة إضافية، وبعض التذاكر فيها جوائز فورية.'],
    ['كيف أستلم جائزتي؟', 'نتواصل مع ولي الأمر على الجوال المسجّل، ونسلّم الجائزة خلال 7 أيام من الفوز.'],
];

$levelTint = ['bronze' => 'bronze', 'silver' => 'silver', 'gold' => 'gold'];

public_head('تحديات وجوائز للأبطال', $tagline);
?>
<body class="home">
<!-- ===== شاشة الافتتاح: نجمة تسقط وتنفجر ===== -->
<div class="intro" id="intro" aria-hidden="true">
  <div class="intro__star"></div>
  <div class="intro__burst"></div>
  <div class="intro__brand">
    <img class="intro__logo" src="<?= e(media_slot_url('logo')) ?>" alt="">
  </div>
  <button type="button" class="intro__skip" id="introSkip">تخطي</button>
</div>

<div class="bg" aria-hidden="true"></div>
<canvas class="sky" id="sky" aria-hidden="true"></canvas>

<?php public_nav('home'); ?>

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
            <a href="play.php" class="btn btn--primary"><?= icon('play') ?><?= $player ? 'كمّل تحدي اليوم' : 'ابدأ التحدي مجاناً' ?></a>
            <button type="button" class="btn btn--ghost" data-open-enter><?= icon('ticket') ?><?= e($b['btn_text'] ?: 'اكشف تذكرتك') ?></button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <div class="hero__hopper" aria-hidden="true">
        <span class="hero__shadow"></span>
        <span class="hero__jump"><img src="<?= e(media_slot_url('mascot')) ?>" alt=""><i class="hero__wand"></i></span>
      </div>
      <div class="floaters" aria-hidden="true">
        <?= cube3d(['؟', '★', '!', '؟', '✦', '★'], 'cube3d--a') ?>
        <?= cube3d(['★', '؟', '✦', '!', '★', '؟'], 'cube3d--b') ?>
        <?= coin3d('★', '+10', 'gold') ?>
      </div>
      <?php if (count($banners) > 1): ?>
      <div class="hero__dots" role="tablist" aria-label="البانرات">
        <?php foreach ($banners as $i => $b): ?><button type="button" role="tab" class="<?= $i === 0 ? 'on' : '' ?>" data-slide="<?= $i ?>" aria-label="بانر <?= $i + 1 ?>"></button><?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ===== أرقام حية ===== -->
  <section class="wrap stats stats--4" aria-label="أرقام المنصة">
    <?php foreach ($stats as [$ic, $n, $label]): ?>
    <div class="stat reveal"><?= icon($ic) ?><div><span class="stat__n" data-num="<?= $n ?>">0</span><span class="stat__l"><?= e($label) ?></span></div></div>
    <?php endforeach; ?>
  </section>

  <?php if ($winners): ?>
  <div class="ticker" id="winners" aria-label="آخر الرابحين">
    <div class="ticker__track">
      <?php for ($set = 0; $set < 2; $set++): ?>
      <div class="ticker__set"<?= $set ? ' aria-hidden="true"' : '' ?>>
        <?php foreach ($winners as $w): ?>
        <span class="ticker__item"><?= icon('star') ?><b><?= e(first_name($w['child_name'])) ?></b> ربح <?= e($w['prize']) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ===== المستويات ===== -->
  <section class="wrap section" id="levels">
    <div class="section__head">
      <h2><?= icon('medal') ?>المستويات والجوائز</h2>
      <a href="play.php" class="view-all">ابدأ الصعود <?= icon('chevL') ?></a>
    </div>
    <p class="section__lead">اجمع النقاط واصعد درجة درجة. أول من يوصل لكل مستوى يفوز بجائزته، والسبق بالاجتهاد مو بالشراء.</p>
    <div class="ladder">
      <?php foreach ($levels as $i => $lv):
          $tint = $levelTint[$lv['color']] ?? 'gold';
          $taken = $reachedCounts[(int)$lv['id']] ?? 0;
          $seats = max(0, (int)$lv['winners_limit'] - $taken);
          $mine = $player && (int)$player['points'] >= (int)$lv['min_points']; ?>
      <article class="lvl lvl--<?= e($tint) ?> reveal<?= $mine ? ' is-mine' : '' ?>" style="--i:<?= $i ?>" data-tilt>
        <?= coin3d((string)($i + 1), '★', $tint) ?>
        <img class="lvl__img" src="<?= e(media_slot_url('level_' . min(3, $i + 1))) ?>" alt="" loading="lazy">
        <h3 class="lvl__name">المستوى <?= e($lv['name']) ?></h3>
        <p class="lvl__prize"><?= e($lv['prize']) ?></p>
        <div class="lvl__meta">
          <span><?= icon('star') ?><?= number_format((int)$lv['min_points']) ?> نقطة</span>
          <span><?= icon('trophy') ?><?= $seats > 0 ? 'باقي ' . number_format($seats) . ' فائز' : 'اكتمل الفائزون' ?></span>
        </div>
        <?php if ($mine): ?><span class="lvl__badge"><?= icon('check') ?>وصلته!</span><?php endif; ?>
      </article>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ===== جوائز التذاكر ===== -->
  <section class="wrap section" id="prizes">
    <div class="section__head">
      <h2><?= icon('sparkle') ?>جوائز التذاكر الفورية</h2>
      <button type="button" class="view-all" data-open-enter>اكشف تذكرة <?= icon('chevL') ?></button>
    </div>
    <?php if ($prizes): ?>
    <div class="carousel">
      <button type="button" class="carousel__arrow carousel__arrow--prev" data-scroll="prizeRow" data-dir="1" aria-label="السابق"><?= icon('chevR') ?></button>
      <div class="prize-row" id="prizeRow">
        <?php foreach ($prizes as $p):
            $left = max(0, (int)$p['quantity'] - (int)$p['won_count']);
            [$tierLabel, $tierIcon] = prize_tier((int)$p['quantity']); ?>
        <article class="pcard flip<?= $left === 0 ? ' is-out' : '' ?><?= $tierIcon === 'fire' && $left > 0 ? ' pcard--rare' : '' ?>" data-tilt tabindex="0" role="button" aria-label="<?= e($p['name']) ?>: اقلب البطاقة للتفاصيل">
          <div class="flip__inner">
            <div class="flip__face flip__face--front">
              <img class="pcard__img" src="<?= e(prize_art_url($p['name'], $p['image'])) ?>" alt="" loading="lazy">
              <span class="pcard__badge" title="المتبقي"><?= $left > 0 ? '×' . number_format($left) : 'نفدت' ?></span>
              <div class="pcard__foot">
                <h3><?= e($p['name']) ?></h3>
                <span class="chip chip--<?= e($tierIcon) ?>"><?= icon($tierIcon) ?><?= e($tierLabel) ?></span>
              </div>
            </div>
            <div class="flip__face flip__face--back">
              <strong><?= e($p['name']) ?></strong>
              <span class="flip__big"><?= number_format($left) ?></span>
              <span>متبقية من <?= number_format((int)$p['quantity']) ?></span>
              <span class="flip__bar"><i style="width:<?= (int)round(100 * $left / max(1, (int)$p['quantity'])) ?>%"></i></span>
              <small>اكشف رمز تذكرتك، وكل رمز صحيح يعطيك <?= (int)setting('points_ticket', '50') ?> نقطة</small>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <button type="button" class="carousel__arrow carousel__arrow--next" data-scroll="prizeRow" data-dir="-1" aria-label="التالي"><?= icon('chevL') ?></button>
    </div>
    <?php else: ?>
      <p class="empty">جوائز التذاكر تُعلن قريباً… ترقّبوا!</p>
    <?php endif; ?>
  </section>

  <div class="wrap split">
    <!-- ===== كيف تلعب ===== -->
    <section class="section" id="how">
      <div class="section__head"><h2><?= icon('grid') ?>كيف تلعب</h2></div>
      <div class="how-grid">
        <?php foreach ($howCards as $n => [$slot, $title, $sub, $tint]): ?>
        <a href="play.php" class="hcard hcard--<?= $tint ?> reveal" style="--i:<?= $n ?>">
          <span class="hcard__art" style="background-image:url('<?= e(media_slot_url($slot)) ?>')"></span>
          <span class="hcard__text"><strong><?= $n + 1 ?>. <?= e($title) ?></strong><small><?= e($sub) ?></small></span>
          <span class="hcard__go"><?= icon('arrowL') ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ===== المتصدرون ===== -->
    <section class="section panel" id="leaders">
      <div class="section__head">
        <h2><?= icon('fire') ?>المتصدرون الآن</h2>
        <a href="play.php" class="view-all">نافسهم <?= icon('chevL') ?></a>
      </div>
      <?php if ($leaders): $podium = array_slice($leaders, 0, 3); ?>
      <div class="podium" aria-label="أول ثلاثة">
        <?php foreach ([1, 0, 2] as $pos): if (!isset($podium[$pos])) continue; $l = $podium[$pos]; ?>
        <div class="podium__col podium__col--<?= $pos + 1 ?> reveal" style="--i:<?= $pos ?>">
          <img class="podium__av" src="<?= e(avatar_url($l['avatar'])) ?>" alt="">
          <strong><?= e(first_name($l['full_name'])) ?></strong>
          <span class="podium__pts"><?= icon('star') ?><?= number_format((int)$l['points']) ?></span>
          <div class="podium__block"><span class="podium__top"></span><span class="podium__front"><?= $pos + 1 ?></span><span class="podium__side"></span></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <ol class="rank" start="4">
        <?php if ($leaders): foreach (array_slice($leaders, 3, 5) as $i => $l): ?>
        <li class="reveal" style="--i:<?= $i ?>">
          <span class="rank__n"><?= $i + 4 ?></span>
          <img class="rank__thumb rank__thumb--round" src="<?= e(avatar_url($l['avatar'])) ?>" alt="" loading="lazy">
          <span class="rank__body"><strong><?= e(first_name($l['full_name'])) ?></strong><small>المستوى <?= e(level_name_for((int)$l['points'])) ?></small></span>
          <span class="rank__meta rank__meta--star"><?= icon('star') ?><?= number_format((int)$l['points']) ?></span>
        </li>
        <?php endforeach; else: ?>
          <li class="rank__empty">الصدارة فاضية… كن أول بطل! <a href="play.php">ابدأ الآن</a></li>
        <?php endif; ?>
      </ol>
    </section>
  </div>

  <!-- ===== الأسئلة الشائعة ===== -->
  <section class="wrap section" id="faq">
    <div class="section__head"><h2><?= icon('chat') ?>أسئلة يسألها الأبطال</h2></div>
    <div class="faq">
      <?php foreach ($faq as [$qq, $aa]): ?>
      <details class="faq__item reveal"><summary><?= e($qq) ?><?= icon('chevD') ?></summary><p><?= e($aa) ?></p></details>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ===== دعوة أخيرة ===== -->
  <section class="wrap">
    <div class="cta reveal">
      <img class="cta__mascot" src="<?= e(media_slot_url('mascot')) ?>" alt="">
      <?= cube3d(['؟', '★', '!', '✦', '؟', '★'], 'cube3d--cta') ?>
      <div class="cta__text">
        <h2>تحدي اليوم ينتظرك!</h2>
        <p><?= daily_limit() ?> أسئلة، <?= (int)setting('question_seconds', '20') ?> ثانية لكل سؤال، ونقاط تقرّبك من الجائزة الذهبية.</p>
      </div>
      <a href="play.php" class="btn btn--primary btn--lg"><?= icon('bolt') ?>يلا نبدأ</a>
    </div>
  </section>
</main>

<?php public_footer(); ?>

<!-- ===== نافذة كشف التذكرة ===== -->
<div class="modal" id="resultModal" hidden role="dialog" aria-modal="true" aria-labelledby="enterTitle">
  <div class="modal__panel">
    <button type="button" class="modal__close" data-close aria-label="إغلاق">×</button>

    <div class="stage" data-stage="enter">
      <img class="enter-mascot" src="<?= e(media_slot_url('mascot')) ?>" alt="">
      <h2 id="enterTitle" class="enter-title">اكشف تذكرتك</h2>
      <p class="enter-sub">اكتب الرمز المكتوب على تذكرتك (10 حروف وأرقام)<?= $player ? '' : '، وسجّل في التحدي عشان تاخذ نقاطه' ?></p>
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
      <p class="pts-note" data-pts hidden></p>

      <form id="claimForm" class="claim-form" novalidate>
        <p class="claim-form__note">اطلب من بابا أو ماما يكملون معك لاستلام الجائزة:</p>
        <label>اسمك<input name="child_name" maxlength="60" required placeholder="مثال: سارة" value="<?= $player ? e(first_name($player['full_name'])) : '' ?>"></label>
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
      <p class="pts-note" data-pts hidden></p>
      <?php if (!$player): ?><a class="btn btn--ghost" href="play.php"><?= icon('brain') ?>سجّل في التحدي وخذ نقاط على كل تذكرة</a><?php endif; ?>
      <button type="button" class="btn btn--primary" data-again>جرّب تذكرة أخرى</button>
    </div>

    <div class="stage" data-stage="error" hidden>
      <h2 class="lose-title">أوبس!</h2>
      <p class="lose-sub" id="errorMsg"></p>
      <button type="button" class="btn btn--primary" data-again>حاول مرة أخرى</button>
    </div>
  </div>
</div>

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
<script src="<?= e(asset('assets/js/fx.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
