/* صفحة التحدي: التسجيل، الدخول، الأسئلة اليومية، النقاط، والاحتفال بالمستويات */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const csrf = $('meta[name="csrf-token"]').content;
  const base = (window.KT && window.KT.base) || './';
  const fx = () => window.KTfx || { burst() {}, fireworks() {}, bumpPoints() {} };
  const nf = new Intl.NumberFormat('en-US');

  async function api(data) {
    const res = await fetch(base + 'api/play.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
      body: new URLSearchParams({ ...data, csrf }),
    });
    try { return await res.json(); } catch { return { ok: false, message: 'تعذّر الاتصال، حاول مرة أخرى.' }; }
  }
  const err = (f, m) => { const p = f.querySelector('.form-err'); if (p) p.textContent = m || ''; };

  /* ---------- التسجيل والدخول ---------- */
  const reg = $('#regForm'), login = $('#loginForm'), otp = $('#otpForm');
  let otpAction = null;
  if (reg) {
    document.querySelectorAll('[data-tab]').forEach((b) => b.addEventListener('click', () => {
      document.querySelectorAll('[data-tab]').forEach((x) => x.classList.toggle('on', x === b));
      reg.hidden = b.dataset.tab !== 'reg'; login.hidden = b.dataset.tab !== 'login'; otp.hidden = true;
    }));
    reg.addEventListener('submit', async (e) => {
      e.preventDefault(); err(reg);
      const fd = Object.fromEntries(new FormData(reg));
      if ((fd.full_name || '').trim().split(/\s+/).length < 4) return err(reg, 'اكتب الاسم الرباعي (أربعة أسماء).');
      if (!fd.consent) return err(reg, 'يلزم موافقة ولي الأمر.');
      const btn = reg.querySelector('button[type=submit]'); btn.disabled = true;
      const d = await api({ action: 'register', ...fd });
      btn.disabled = false;
      if (!d.ok) return err(reg, d.message);
      if (d.step === 'verify') { otpAction = 'verify_register'; showOtp(d.phone); return; }
      welcome();
    });
    login.addEventListener('submit', async (e) => {
      e.preventDefault(); err(login);
      const btn = login.querySelector('button[type=submit]'); btn.disabled = true;
      const d = await api({ action: 'login', ...Object.fromEntries(new FormData(login)) });
      btn.disabled = false;
      if (!d.ok) return err(login, d.message);
      if (d.step === 'verify') { otpAction = 'verify_login'; showOtp(d.phone); return; }
      location.reload();
    });
    otp.addEventListener('submit', async (e) => {
      e.preventDefault(); err(otp);
      const d = await api({ action: otpAction, otp: otp.otp.value });
      if (!d.ok) return err(otp, d.message);
      otpAction === 'verify_register' ? welcome() : location.reload();
    });
  }
  function showOtp(phone) {
    reg.hidden = true; login.hidden = true; otp.hidden = false;
    otp.querySelector('[data-phone]').textContent = phone; otp.otp.focus();
  }
  function welcome() { fx().fireworks(4); setTimeout(() => location.reload(), 1300); }

  /* ---------- لوحة المتسابق ---------- */
  const fill = $('.progress__fill');
  if (fill) requestAnimationFrame(() => { fill.style.width = fill.dataset.pct + '%'; });

  function renderMe(p) {
    if (!p) return;
    $('[data-points]').textContent = nf.format(p.points);
    $('[data-streak]').textContent = p.streak;
    $('[data-left]').textContent = p.left;
    $('[data-level]').textContent = p.level;
    fill.style.width = p.pct + '%';
    $('[data-pcttxt]').textContent = p.pct + '%';
    $('[data-next]').textContent = p.next ? `باقي ${nf.format(p.to_next)} نقطة للمستوى ${p.next}` : 'وصلت أعلى مستوى! 🏆';
    const navPts = $('.nav__pts'); if (navPts) navPts.textContent = nf.format(p.points);
    if (!p.left) { startBtn.disabled = true; startBtn.lastChild.textContent = 'خلصت تحديات اليوم'; }
  }

  /* ---------- الأسئلة ---------- */
  const startBtn = $('#startBtn'), quiz = $('#quiz');
  if (!startBtn) return;
  let timerId = null, busy = false;
  if ($('[data-left]').textContent.trim() === '0') startBtn.disabled = true;

  startBtn.addEventListener('click', () => { quiz.hidden = false; nextQuestion(); quiz.scrollIntoView({ behavior: 'smooth', block: 'start' }); });

  async function nextQuestion() {
    const fb = $('[data-fb]'); fb.hidden = true;
    const d = await api({ action: 'next' });
    if (!d.ok) { if (d.auth) location.reload(); return showDone(d.message || 'حدث خطأ.'); }
    if (d.done) { renderMe(d.player); return showDone(d.message); }
    const q = d.question;
    quiz.classList.remove('is-enter'); void quiz.offsetWidth; quiz.classList.add('is-enter');
    $('[data-count]').textContent = `سؤال ${q.n} من ${q.of}`;
    $('[data-cat]').textContent = q.category;
    $('[data-q]').textContent = q.body;
    const box = $('[data-opts]'); box.innerHTML = '';
    ['a', 'b', 'c', 'd'].forEach((k, i) => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'opt'; b.dataset.k = k;
      b.innerHTML = `<span class="opt__k">${'أبجد'[i]}</span><span></span>`;
      b.lastChild.textContent = q.options[k];
      b.addEventListener('click', () => answer(k));
      box.appendChild(b);
    });
    startTimer(q.seconds, q.remaining);
  }

  function startTimer(total, remaining) {
    clearInterval(timerId);
    const ring = $('#timer'), fg = ring.querySelector('.t-fg'), sec = ring.querySelector('[data-sec]');
    const C = 163.4;
    let left = remaining;
    const draw = () => { sec.textContent = left; fg.style.strokeDashoffset = String(C * (1 - left / total)); ring.classList.toggle('is-low', left <= 5); };
    draw();
    timerId = setInterval(() => { left--; draw(); if (left <= 0) { clearInterval(timerId); answer('timeout'); } }, 1000);
  }

  async function answer(k) {
    if (busy) return; busy = true;
    clearInterval(timerId);
    const btns = [...document.querySelectorAll('.opt')];
    btns.forEach((b) => { b.disabled = true; });
    const d = await api({ action: 'answer', choice: k });
    busy = false;
    if (!d.ok) { showFeedback(false, d.message); return; }
    btns.forEach((b) => {
      if (b.dataset.k === d.answer) b.classList.add('is-right');
      else if (b.dataset.k === k) b.classList.add('is-wrong');
    });
    if (d.correct) {
      const right = btns.find((b) => b.dataset.k === d.answer);
      if (right) { const r = right.getBoundingClientRect(); fx().burst(r.left + r.width / 2, r.top + r.height / 2, 34); }
      floatPts(d.points + (d.streak_bonus || 0));
      if (fx().coinPop) fx().coinPop(quiz, '+' + (d.points + (d.streak_bonus || 0)));
      fx().bumpPoints(d.points + (d.streak_bonus || 0));
    }
    let msg = d.correct ? `إجابة صحيحة! +${d.points} نقطة` : (d.timeout ? 'خلص الوقت! ⏰' : 'إجابة غلط، المرة الجاية أحسن 💪');
    if (d.streak_bonus) msg += ` · مكافأة السلسلة +${d.streak_bonus} 🔥`;
    showFeedback(d.correct, msg, true);
    renderMe(d.player);
    if (d.levels && d.levels.length) levelUp(d.levels[d.levels.length - 1]);
  }

  function showFeedback(ok, msg, next) {
    const fb = $('[data-fb]');
    fb.hidden = false; fb.className = 'feedback ' + (ok ? 'is-ok' : 'is-bad');
    fb.innerHTML = '<span></span>';
    fb.firstChild.textContent = msg;
    if (next) {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'btn btn--primary'; b.textContent = 'السؤال التالي';
      b.addEventListener('click', nextQuestion);
      fb.appendChild(b);
      setTimeout(() => b.focus(), 50);
    }
  }

  function floatPts(n) {
    const s = document.createElement('span'); s.className = 'floatpts'; s.textContent = '+' + n;
    quiz.appendChild(s); setTimeout(() => s.remove(), 1300);
  }

  function showDone(msg) {
    clearInterval(timerId);
    quiz.hidden = false;
    quiz.innerHTML = '';
    const box = document.createElement('div'); box.className = 'done-box';
    box.innerHTML = `<img alt=""><h2>أحسنت يا بطل!</h2><p></p><a class="btn btn--primary" href="./#leaders">شوف المتصدرين</a>`;
    box.querySelector('img').src = window.KT.mascot;
    box.querySelector('p').textContent = msg;
    quiz.appendChild(box);
    startBtn.disabled = true;
  }

  const lu = $('#levelUp');
  function levelUp(l) {
    const won = l.rank && l.limit && l.rank <= l.limit;
    lu.querySelector('[data-lu]').textContent = won
      ? `وصلت المستوى ${l.name} وترتيبك ${l.rank}! أنت من الفائزين بـ «${l.prize}» 🎉 سنتواصل مع ولي أمرك.`
      : `وصلت المستوى ${l.name}! كمّل وجمّع نقاط للمستوى اللي بعده.`;
    lu.hidden = false;
    fx().fireworks(6);
  }
  if (lu) lu.addEventListener('click', (e) => { if (e.target === lu || e.target.closest('[data-close-lu]')) lu.hidden = true; });

  const out = $('#logoutBtn');
  if (out) out.addEventListener('click', async () => { await api({ action: 'logout' }); location.href = './'; });
})();
