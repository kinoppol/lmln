/* LinuxQuest — เกมคลายเครียด "หอคอยฮานอย" (Tower of Hanoi)
 *
 * DOM ล้วน ไม่มี canvas สถานะคือ pegs[3] แต่ละเสาเป็นอาเรย์ขนาดแผ่น (ล่าง → บน)
 * กดเสาครั้งแรก = หยิบแผ่นบนสุด กดเสาที่สอง = วาง (ห้ามวางทับแผ่นที่เล็กกว่า)
 * จบเมื่อย้ายครบทุกแผ่นไปเสาขวาสุด คะแนนเทียบกับจำนวนครั้งน้อยที่สุด 2^n − 1
 */
(function () {
  'use strict';

  const cfg = window.LQ_GAME;
  const boardEl = document.getElementById('hanoiBoard');
  if (!boardEl) return;

  // สีไล่ตามพาเลตของระบบ แผ่นเล็กไปใหญ่
  const COLORS = ['#4ade80', '#38bdf8', '#fbbf24', '#f87171', '#a78bfa', '#2dd4bf', '#fb923c'];

  const disksEl = document.getElementById('hanoiDisks');
  const movesEl = document.getElementById('hanoiMoves');
  const minEl = document.getElementById('hanoiMin');
  const overEl = document.getElementById('hanoiOver');

  let n = 4;
  let pegs = [[], [], []];
  let moves = 0;
  let picked = null;    // index เสาที่หยิบไว้ หรือ null
  let finished = false;
  const pegEls = [];

  for (let i = 0; i < 3; i++) {
    const el = document.createElement('button');
    el.type = 'button';
    el.className = 'hanoi-peg';
    el.setAttribute('aria-label', 'เสาที่ ' + (i + 1));
    el.addEventListener('click', function () { onPeg(i); });
    boardEl.appendChild(el);
    pegEls.push(el);
  }

  function top(i) {
    const p = pegs[i];
    return p.length ? p[p.length - 1] : Infinity;
  }

  function onPeg(i) {
    if (finished) return;
    if (picked === null) {
      if (pegs[i].length) picked = i;
    } else if (picked === i) {
      picked = null;
    } else if (top(picked) < top(i)) {
      pegs[i].push(pegs[picked].pop());
      moves++;
      picked = null;
    } else {
      shake(i);
      return;
    }
    render();
    if (pegs[2].length === n) finish();
  }

  function shake(i) {
    const el = pegEls[i];
    el.classList.remove('bad');
    void el.offsetWidth; // รีสตาร์ทแอนิเมชัน
    el.classList.add('bad');
  }

  function render() {
    for (let i = 0; i < 3; i++) {
      const el = pegEls[i];
      el.innerHTML = '';
      el.classList.toggle('sel', picked === i);
      pegs[i].forEach(function (size) {
        const d = document.createElement('div');
        d.className = 'hanoi-disk';
        d.style.width = (28 + size * (68 / n)) + '%';
        d.style.background = COLORS[(size - 1) % COLORS.length];
        el.appendChild(d);
      });
    }
    movesEl.textContent = String(moves);
  }

  function finish() {
    finished = true;
    const min = Math.pow(2, n) - 1;
    // คะแนนตามความยาก (จำนวนแผ่น) คูณด้วยประสิทธิภาพ min/moves
    const score = Math.round(n * 100 * (min / moves));
    // XP จำกัดเพดานเพราะเป็นเกมพักสมอง ไม่ได้วัดทักษะ Linux
    const xp = Math.min(60, Math.floor(score / 12));
    document.getElementById('hanoiFinalScore').textContent = String(score);
    document.getElementById('hanoiFinalDetail').textContent =
      n + ' แผ่น · ใช้ ' + moves + ' ครั้ง · น้อยที่สุด ' + min + ' ครั้ง';
    document.getElementById('hanoiFinalMsg').textContent =
      (moves === min ? 'สมบูรณ์แบบ! ' : moves <= min * 1.5 ? 'เยี่ยมมาก เกือบเป๊ะแล้ว ' : 'สำเร็จ! ลองลดจำนวนครั้งดูอีกรอบ ') + '+' + xp + ' XP';
    overEl.hidden = false;

    fetch(cfg.base + '/api/save_game_score.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code: cfg.code, score: score, time_taken_sec: 0, xp: xp, csrf_token: cfg.csrf }),
    }).catch(function () {});
  }

  function start() {
    n = parseInt(disksEl.value, 10) || 4;
    pegs = [[], [], []];
    for (let s = n; s >= 1; s--) pegs[0].push(s);
    moves = 0;
    picked = null;
    finished = false;
    overEl.hidden = true;
    minEl.textContent = String(Math.pow(2, n) - 1);
    render();
  }

  disksEl.addEventListener('change', start);
  document.getElementById('hanoiRestart').addEventListener('click', start);
  document.getElementById('hanoiAgainBtn').addEventListener('click', start);

  start();
})();
