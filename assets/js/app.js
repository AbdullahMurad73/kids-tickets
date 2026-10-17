(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const csrf = $('meta[name="csrf-token"]').content;
  const base = (window.KT && window.KT.base) || './';

  const form = $('#codeForm');
  const input = $('#code');
  const err = $('#codeError');
  const modal = $('#resultModal');
  const scanModal = $('#scanModal');
  let claimToken = null;
  let lastFocus = null;

  /* ---------- أدوات ---------- */
  const toLatinDigits = (s) => s.replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
  const cleanCode = (s) => toLatinDigits(s).toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10);
  const fmt = (c) => c.replace(/(.{4})(?=.)/g, '$1-');
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

  async function post(url, data) {
    const body = new URLSearchParams({ ...data, csrf });
    const res = await fetch(base + url, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
      body, credentials: 'same-origin',
    });
    try { return await res.json(); } catch { return { ok: false, message: 'تعذّر الاتصال، حاول مرة أخرى.' }; }
  }

  /* ---------- إدخال الرمز ---------- */
  const navForm = $('#navCode');
  const navInput = $('#navCodeInput');

  function bindFormat(el) {
    el.addEventListener('input', () => { el.value = fmt(cleanCode(el.value)); err.textContent = ''; });
  }
  bindFormat(input);
  if (navInput) bindFormat(navInput);

  function shake(el, msg) {
    err.textContent = msg;
    el.classList.remove('shake'); void el.offsetWidth; el.classList.add('shake');
    el.focus();
  }

  function openEnter(prefill) {
    if (typeof prefill === 'string') input.value = fmt(cleanCode(prefill));
    err.textContent = '';
    openModal(modal);
    showStage('enter');
    setTimeout(() => input.focus(), 60);
  }

  async function reveal(code) {
    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    if (modal.hidden) openModal(modal);
    showStage('suspense');
    const box = $('.giftbox', modal);
    box.classList.remove('open');

    const [data] = await Promise.all([post('api/redeem.php', { code }), sleep(1900)]);
    btn.disabled = false;

    if (data.result === 'win' || data.result === 'lose') {
      box.classList.add('open');
      await sleep(550);
    }
    input.value = '';
    if (navInput) navInput.value = '';
    if (data.result === 'win') return showWin(data);
    if (data.result === 'lose') return showStage('lose');
    $('#errorMsg').textContent = data.message || 'حدث خطأ، حاول مرة أخرى.';
    showStage('error');
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const code = cleanCode(input.value);
    if (code.length !== 10) return shake(input, 'الرمز 10 حروف وأرقام، تأكد منه.');
    reveal(code);
  });

  if (navForm) navForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const code = cleanCode(navInput.value);
    if (code.length === 10) return reveal(code);
    openEnter(navInput.value);
    if (code.length) err.textContent = 'الرمز 10 حروف وأرقام، تأكد منه.';
  });

  document.querySelectorAll('[data-open-enter]').forEach((b) => b.addEventListener('click', () => openEnter()));
  document.querySelectorAll('[data-scroll-to]').forEach((b) => b.addEventListener('click', () => {
    const t = document.querySelector(b.dataset.scrollTo);
    if (t) t.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }));

  // رابط QR المطبوع (?c=CODE): نفتح النافذة والرمز جاهز، والطفل يضغط "اكشف" بنفسه
  // (لا نكشف تلقائياً حتى لا تُستهلك التذكرة لو فتح الرابط شخص آخر، مثل المطبعة)
  if (window.KT && window.KT.prefill) openEnter();

  /* ---------- البانر المتحرك ---------- */
  const hero = $('#hero');
  if (hero) {
    const slides = [...hero.querySelectorAll('.hero__slide')];
    const dots = [...hero.querySelectorAll('.hero__dots button')];
    let cur = 0, timer = null;
    const go = (n) => {
      cur = (n + slides.length) % slides.length;
      slides.forEach((s, i) => { s.classList.toggle('is-on', i === cur); s.setAttribute('aria-hidden', i === cur ? 'false' : 'true'); });
      dots.forEach((d, i) => d.classList.toggle('on', i === cur));
    };
    const play = () => { if (slides.length > 1 && !matchMedia('(prefers-reduced-motion: reduce)').matches) { clearInterval(timer); timer = setInterval(() => go(cur + 1), 6000); } };
    dots.forEach((d) => d.addEventListener('click', () => { go(+d.dataset.slide); play(); }));
    hero.addEventListener('mouseenter', () => clearInterval(timer));
    hero.addEventListener('mouseleave', play);
    let sx = null;
    hero.addEventListener('touchstart', (e) => { sx = e.touches[0].clientX; }, { passive: true });
    hero.addEventListener('touchend', (e) => {
      if (sx === null) return;
      const dx = e.changedTouches[0].clientX - sx; sx = null;
      if (Math.abs(dx) > 40) { go(cur + (dx > 0 ? 1 : -1)); play(); }
    });
    play();
  }

  /* ---------- تمييز رابط القسم الحالي ---------- */
  const links = [...document.querySelectorAll('.nav__links a[href^="#"]')];
  const io = 'IntersectionObserver' in window ? new IntersectionObserver((entries) => {
    entries.forEach((en) => {
      if (!en.isIntersecting) return;
      const id = '#' + en.target.id;
      links.forEach((a) => a.classList.toggle('is-active', a.getAttribute('href') === id));
    });
  }, { rootMargin: '-45% 0px -50% 0px' }) : null;
  if (io) ['top', 'prizes', 'how', 'winners'].forEach((id) => { const el = document.getElementById(id); if (el) io.observe(el); });

  /* ---------- النتيجة ---------- */
  function showStage(name) {
    modal.querySelectorAll('.stage').forEach((s) => { s.hidden = s.dataset.stage !== name; });
    const target = modal.querySelector(`.stage[data-stage="${name}"] button, .stage[data-stage="${name}"] input`);
    if (target && name !== 'suspense') setTimeout(() => target.focus(), 50);
  }

  function showWin(data) {
    claimToken = data.claim_token;
    $('#prizeName').textContent = data.prize.name;
    $('#claimNo').textContent = data.claim_no;
    const media = $('#prizeMedia');
    media.innerHTML = '';
    const src = data.prize.art || data.prize.image;
    if (src) { const img = new Image(); img.src = src; img.alt = data.prize.name; media.appendChild(img); }
    $('#claimForm').hidden = false;
    $('#claimForm').reset();
    $('#otpForm').hidden = true;
    $('#claimDone').hidden = true;
    modal.querySelectorAll('.form-msg').forEach((m) => { m.textContent = ''; });
    showStage('win');
    input.value = '';
    confetti();
    cheer();
  }

  $('#claimForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = e.currentTarget, msg = f.querySelector('.form-msg'), btn = f.querySelector('button');
    msg.textContent = '';
    if (!f.consent.checked) { msg.textContent = 'يلزم موافقة ولي الأمر.'; return; }
    btn.disabled = true;
    const data = await post('api/claim.php', { action: 'start', claim_token: claimToken, child_name: f.child_name.value, phone: f.phone.value, consent: '1' });
    btn.disabled = false;
    if (!data.ok) { msg.textContent = data.message; return; }
    if (data.step === 'verify') {
      f.hidden = true;
      $('#otpPhone').textContent = data.phone;
      $('#otpForm').hidden = false;
      $('#otpForm').otp.focus();
      return;
    }
    done(data.message);
  });

  $('#otpForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = e.currentTarget, msg = f.querySelector('.form-msg'), btn = f.querySelector('button');
    msg.textContent = '';
    btn.disabled = true;
    const data = await post('api/claim.php', { action: 'verify', claim_token: claimToken, otp: f.otp.value });
    btn.disabled = false;
    if (!data.ok) { msg.textContent = data.message; return; }
    done(data.message);
  });

  function done(message) {
    $('#claimForm').hidden = true;
    $('#otpForm').hidden = true;
    $('#claimDoneMsg').textContent = message;
    $('#claimDone').hidden = false;
    claimToken = null;
  }

  /* ---------- النوافذ ---------- */
  function openModal(m) { lastFocus = document.activeElement; m.hidden = false; document.body.style.overflow = 'hidden'; }
  function closeModal(m) {
    const claimPending = claimToken && (!$('#claimForm').hidden || !$('#otpForm').hidden);
    if (m === modal && claimPending && !$('.stage[data-stage="win"]', modal).hidden) {
      if (!confirm('لم تكمل تسجيل الجائزة بعد. هل تريد الإغلاق؟ احتفظ برقم المطالبة.')) return;
    }
    m.hidden = true; document.body.style.overflow = '';
    if (m === scanModal) stopScanner();
    if (lastFocus) lastFocus.focus();
  }
  document.querySelectorAll('.modal').forEach((m) => {
    m.addEventListener('click', (e) => {
      if (e.target === m || e.target.closest('[data-close]')) closeModal(m);
      if (e.target.closest('[data-again]')) { showStage('enter'); setTimeout(() => input.focus(), 60); }
    });
  });
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.modal:not([hidden])').forEach(closeModal);
  });

  /* ---------- شرائح الجوائز ---------- */
  document.querySelectorAll('[data-scroll]').forEach((b) => b.addEventListener('click', () => {
    const row = document.getElementById(b.dataset.scroll);
    if (row) row.scrollBy({ left: Number(b.dataset.dir) * row.clientWidth * 0.8, behavior: 'smooth' });
  }));

  /* ---------- مسح QR ---------- */
  let scanner = null;
  function loadScript(src) {
    return new Promise((res, rej) => { const s = document.createElement('script'); s.src = src; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
  }
  $('#scanBtn').addEventListener('click', async () => {
    openModal(scanModal);
    const msg = $('#scanMsg');
    msg.textContent = '';
    try {
      if (!window.Html5Qrcode) await loadScript('https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js');
      scanner = new window.Html5Qrcode('qrReader');
      await scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 220, height: 220 } }, (text) => {
        let code = text;
        try { const u = new URL(text); code = u.searchParams.get('c') || text; } catch { /* نص عادي */ }
        code = cleanCode(code);
        if (code.length === 10) {
          input.value = fmt(code);
          closeModal(scanModal);
          form.requestSubmit();
        }
      });
    } catch (ex) {
      msg.textContent = 'تعذّر تشغيل الكاميرا. اسمح بالوصول للكاميرا أو اكتب الرمز يدوياً.';
    }
  });
  function stopScanner() {
    if (scanner) { scanner.stop().catch(() => {}).finally(() => { try { scanner.clear(); } catch {} scanner = null; }); }
  }

  /* ---------- احتفال ---------- */
  function cheer() {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      [523.25, 659.25, 783.99, 1046.5].forEach((f, i) => {
        const o = ctx.createOscillator(), g = ctx.createGain();
        o.type = 'triangle'; o.frequency.value = f;
        g.gain.setValueAtTime(0.0001, ctx.currentTime + i * 0.12);
        g.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + i * 0.12 + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + i * 0.12 + 0.35);
        o.connect(g).connect(ctx.destination); o.start(ctx.currentTime + i * 0.12); o.stop(ctx.currentTime + i * 0.12 + 0.4);
      });
    } catch { /* بدون صوت */ }
  }

  function confetti() {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const c = $('#confetti'), ctx = c.getContext('2d');
    const dpr = Math.min(2, window.devicePixelRatio || 1);
    c.width = innerWidth * dpr; c.height = innerHeight * dpr; ctx.scale(dpr, dpr);
    const colors = ['#c084fc', '#ff5cad', '#4fc3ff', '#ffe27a', '#2dd4bf', '#ffffff'];
    const parts = Array.from({ length: 170 }, () => ({
      x: innerWidth / 2 + (Math.random() - 0.5) * 120, y: innerHeight * 0.38,
      vx: (Math.random() - 0.5) * 16, vy: -Math.random() * 15 - 5,
      w: 6 + Math.random() * 7, h: 4 + Math.random() * 6, r: Math.random() * Math.PI, vr: (Math.random() - 0.5) * 0.35,
      color: colors[(Math.random() * colors.length) | 0], life: 0,
    }));
    let frame = 0;
    (function tick() {
      ctx.clearRect(0, 0, innerWidth, innerHeight);
      let alive = 0;
      for (const p of parts) {
        p.vy += 0.38; p.vx *= 0.99; p.x += p.vx; p.y += p.vy; p.r += p.vr; p.life++;
        if (p.y < innerHeight + 20) alive++;
        ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.r);
        ctx.globalAlpha = Math.max(0, 1 - p.life / 190);
        ctx.fillStyle = p.color; ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h); ctx.restore();
      }
      if (alive && ++frame < 220) requestAnimationFrame(tick); else ctx.clearRect(0, 0, innerWidth, innerHeight);
    })();
  }
})();
