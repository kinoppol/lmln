/* LinuxQuest — เกมคลายเครียด "โยนไข่" (Egg Toss)
 *
 * วาดทุกอย่างบน canvas ด้วยรูปทรงพื้นฐาน ไม่มีไฟล์ภาพ ไม่พึ่ง Terminal จำลอง
 * เดินเวลาด้วย delta time เพื่อให้ความเร็วเท่ากันทุกอัตราเฟรม และหยุดเองเมื่อ
 * ผู้เล่นสลับแท็บ (แท็บที่ไม่ได้แสดงจะไม่ได้รับ requestAnimationFrame)
 */
(function () {
  'use strict';

  const cfg = window.LQ_GAME;
  const canvas = document.getElementById('eggCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const W = canvas.width, H = canvas.height;

  const GREEN = '#4ade80', AMBER = '#fbbf24', RED = '#f87171', DIM = '#9bb0a8', MUT = '#6f837c';
  const GROUND_Y = H - 54;          // เส้นพื้น ตะกร้าวางอยู่บนนี้
  const BASKET_W = 96, BASKET_H = 42;
  const LIVES_START = 3;

  const startOverlay = document.getElementById('eggStart');
  const pauseOverlay = document.getElementById('eggPause');
  const overOverlay = document.getElementById('eggOver');
  const hud = document.getElementById('gameHud');

  let state = null;
  let lastTs = 0;
  let rafId = 0;
  let frames = 0;
  let intervalId = 0;

  function newState() {
    return {
      basketX: W / 2,
      targetX: W / 2,
      eggs: [],
      splats: [],
      score: 0,
      caught: 0,
      missed: 0,
      combo: 0,
      bestCombo: 0,
      lives: LIVES_START,
      spawnIn: 0.7,
      elapsed: 0,
      running: true,
      over: false,
      henX: W / 2,
      henDir: 1,
      flash: 0,
    };
  }

  // ---------------------------------------------------------------- ไข่
  function spawnEgg(s) {
    const golden = Math.random() < 0.12;
    // ยิ่งเล่นนานยิ่งเร็วขึ้น แต่หยุดเพิ่มที่เพดานหนึ่ง เพื่อให้ยังเป็นเกมพักสมอง
    const speed = Math.min(150 + s.elapsed * 7, 420) * (golden ? 1.25 : 1);
    s.eggs.push({
      x: 40 + Math.random() * (W - 80),
      y: -18,
      vy: speed,
      vx: (Math.random() - 0.5) * 50,
      golden: golden,
      rot: (Math.random() - 0.5) * 0.6,
    });
  }

  function drawEgg(x, y, rot, golden) {
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(rot);
    const grad = ctx.createLinearGradient(-9, -13, 9, 13);
    if (golden) {
      grad.addColorStop(0, '#fde68a');
      grad.addColorStop(1, '#d97706');
    } else {
      grad.addColorStop(0, '#fffaf0');
      grad.addColorStop(1, '#e3d3bd');
    }
    ctx.fillStyle = grad;
    ctx.beginPath();
    ctx.ellipse(0, 0, 11, 15, 0, 0, Math.PI * 2);
    ctx.fill();
    if (golden) {
      ctx.strokeStyle = 'rgba(251,191,36,.9)';
      ctx.lineWidth = 2;
      ctx.stroke();
    }
    ctx.restore();
  }

  function drawBasket(x) {
    const left = x - BASKET_W / 2, top = GROUND_Y - BASKET_H;
    ctx.fillStyle = '#7c4a21';
    ctx.beginPath();
    ctx.moveTo(left, top);
    ctx.lineTo(left + BASKET_W, top);
    ctx.lineTo(left + BASKET_W - 12, top + BASKET_H);
    ctx.lineTo(left + 12, top + BASKET_H);
    ctx.closePath();
    ctx.fill();

    ctx.strokeStyle = 'rgba(0,0,0,.22)';
    ctx.lineWidth = 2;
    for (let i = 1; i < 4; i++) {
      const y = top + (BASKET_H / 4) * i;
      ctx.beginPath();
      ctx.moveTo(left + 3 + i, y);
      ctx.lineTo(left + BASKET_W - 3 - i, y);
      ctx.stroke();
    }
    // ขอบตะกร้า = โซนรับไข่
    ctx.fillStyle = '#a3672f';
    ctx.fillRect(left - 4, top - 7, BASKET_W + 8, 8);
  }

  function drawHen(x) {
    ctx.save();
    ctx.translate(x, 46);
    ctx.fillStyle = '#f4f4f5';
    ctx.beginPath();
    ctx.ellipse(0, 0, 26, 20, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.beginPath();                       // หัว
    ctx.arc(18, -13, 11, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = RED;                   // หงอน
    ctx.beginPath();
    ctx.arc(15, -24, 4, 0, Math.PI * 2);
    ctx.arc(21, -26, 4, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = AMBER;                 // จมูก
    ctx.beginPath();
    ctx.moveTo(28, -12);
    ctx.lineTo(37, -9);
    ctx.lineTo(28, -6);
    ctx.closePath();
    ctx.fill();
    ctx.fillStyle = '#111';                // ตา
    ctx.beginPath();
    ctx.arc(21, -16, 1.8, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
  }

  function drawScene(s) {
    ctx.clearRect(0, 0, W, H);

    // พื้นหลัง
    const sky = ctx.createLinearGradient(0, 0, 0, H);
    sky.addColorStop(0, '#0a1512');
    sky.addColorStop(1, '#060a09');
    ctx.fillStyle = sky;
    ctx.fillRect(0, 0, W, H);

    // ฟางบนพื้น
    ctx.fillStyle = 'rgba(74,222,128,.07)';
    ctx.fillRect(0, GROUND_Y, W, H - GROUND_Y);
    ctx.strokeStyle = 'rgba(74,222,128,.25)';
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.moveTo(0, GROUND_Y);
    ctx.lineTo(W, GROUND_Y);
    ctx.stroke();

    if (s.flash > 0) {                     // แดงวูบตอนไข่แตก
      ctx.fillStyle = 'rgba(248,113,113,' + (s.flash * 0.18).toFixed(3) + ')';
      ctx.fillRect(0, 0, W, H);
    }

    drawHen(s.henX);
    s.splats.forEach(function (p) {
      ctx.globalAlpha = Math.max(0, p.life);
      ctx.fillStyle = p.golden ? AMBER : '#fde9c8';
      ctx.beginPath();
      ctx.ellipse(p.x, GROUND_Y + 4, 16 * (1.4 - p.life), 5, 0, 0, Math.PI * 2);
      ctx.fill();
      ctx.globalAlpha = 1;
    });
    s.eggs.forEach(function (e) { drawEgg(e.x, e.y, e.rot, e.golden); });
    drawBasket(s.basketX);

    if (s.combo >= 3) {
      ctx.fillStyle = AMBER;
      ctx.font = '700 15px "JetBrains Mono", monospace';
      ctx.textAlign = 'center';
      ctx.fillText('COMBO x' + s.combo, s.basketX, GROUND_Y - BASKET_H - 18);
    }
  }

  function renderHud(s) {
    let hearts = '';
    for (let i = 0; i < LIVES_START; i++) hearts += i < s.lives ? '♥' : '♡';
    hud.innerHTML =
      '<div class="game-hud"><div class="label">คะแนน</div><div class="value" style="color:var(--green)">' + s.score + '</div></div>' +
      '<div class="game-hud"><div class="label">รับได้</div><div class="value" style="color:var(--fg)">' + s.caught + '</div></div>' +
      '<div class="game-hud"><div class="label">คอมโบสูงสุด</div><div class="value" style="color:var(--amber)">' + s.bestCombo + '</div></div>' +
      '<div class="game-hud"><div class="label">ชีวิต</div><div class="value" style="color:' + (s.lives > 1 ? 'var(--green)' : 'var(--red)') + '">' + hearts + '</div></div>';
  }

  // ---------------------------------------------------------------- ลูปเกม
  function step(dt, s) {
    s.elapsed += dt;

    // แม่ไก่เดินไปมาแล้วโยนไข่จากตำแหน่งที่ยืน
    s.henX += s.henDir * 70 * dt;
    if (s.henX > W - 50) { s.henX = W - 50; s.henDir = -1; }
    if (s.henX < 50) { s.henX = 50; s.henDir = 1; }

    s.spawnIn -= dt;
    if (s.spawnIn <= 0) {
      spawnEgg(s);
      s.eggs[s.eggs.length - 1].x = s.henX;
      s.spawnIn = Math.max(0.42, 1.15 - s.elapsed * 0.02);
    }

    // ตะกร้าไล่ตามตำแหน่งเป้าหมายแบบนุ่ม ๆ
    s.basketX += (s.targetX - s.basketX) * Math.min(1, dt * 14);
    s.basketX = Math.max(BASKET_W / 2, Math.min(W - BASKET_W / 2, s.basketX));

    const catchTop = GROUND_Y - BASKET_H - 7;
    for (let i = s.eggs.length - 1; i >= 0; i--) {
      const e = s.eggs[i];
      e.y += e.vy * dt;
      e.x += e.vx * dt;
      e.rot += dt * 0.8;
      if (e.x < 14 || e.x > W - 14) e.vx *= -1;

      const inBasket = Math.abs(e.x - s.basketX) < BASKET_W / 2 + 4;
      if (e.y >= catchTop && e.y <= catchTop + 26 && inBasket) {
        s.eggs.splice(i, 1);
        s.caught++;
        s.combo++;
        s.bestCombo = Math.max(s.bestCombo, s.combo);
        const comboBonus = s.combo >= 3 ? Math.min(5, Math.floor(s.combo / 3)) : 0;
        s.score += (e.golden ? 5 : 1) + comboBonus;
        continue;
      }
      if (e.y > GROUND_Y + 6) {
        s.eggs.splice(i, 1);
        s.splats.push({ x: e.x, life: 1, golden: e.golden });
        s.missed++;
        s.combo = 0;
        s.lives--;
        s.flash = 1;
        if (s.lives <= 0) gameOver(s);
      }
    }

    for (let i = s.splats.length - 1; i >= 0; i--) {
      s.splats[i].life -= dt * 0.8;
      if (s.splats[i].life <= 0) s.splats.splice(i, 1);
    }
    if (s.flash > 0) s.flash = Math.max(0, s.flash - dt * 3);
  }

  function tick(ts) {
    frames++;
    if (!state) return;

    const dt = lastTs ? Math.min((ts - lastTs) / 1000, 0.05) : 0; // เพดานกันกระโดดตอนสลับแท็บกลับมา
    lastTs = ts;

    if (state.running && !state.over) {
      step(dt, state);
      renderHud(state);
    }
    drawScene(state);
  }

  function frame(ts) {
    rafId = requestAnimationFrame(frame);
    tick(ts);
  }

  /**
   * บางบริบท (แท็บที่ไม่ได้วาดภาพ, webview บางตัว) ไม่เรียก requestAnimationFrame เลย
   * ถ้าเริ่มเกมแล้วยังไม่มีเฟรมเกิดขึ้น ให้สลับไปขับด้วย setInterval เพื่อให้เกมยังเล่นได้
   */
  function watchdog() {
    const before = frames;
    setTimeout(function () {
      if (frames === before && !intervalId) {
        intervalId = setInterval(function () { tick(performance.now()); }, 1000 / 60);
      }
    }, 600);
  }

  function gameOver(s) {
    s.over = true;
    s.running = false;

    // XP ให้ตามผลงานแต่จำกัดเพดาน เพราะเกมนี้ไม่ได้วัดทักษะ Linux
    const xp = Math.min(60, Math.floor(s.score / 2));
    document.getElementById('eggFinalScore').textContent = s.score;
    document.getElementById('eggFinalDetail').textContent =
      'รับได้ ' + s.caught + ' ฟอง · พลาด ' + s.missed + ' ฟอง · คอมโบสูงสุด x' + s.bestCombo;
    document.getElementById('eggFinalMsg').textContent =
      (s.score >= 60 ? 'มือฉมัง! ' : s.score >= 25 ? 'ไม่เลวเลย ' : 'ลองอีกรอบไหม ') + '+' + xp + ' XP';
    overOverlay.hidden = false;

    fetch(cfg.base + '/api/save_game_score.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code: cfg.code, score: s.score, time_taken_sec: Math.round(s.elapsed), xp: xp, csrf_token: cfg.csrf }),
    }).catch(function () {});
  }

  // ---------------------------------------------------------------- อินพุต
  function pointerX(clientX) {
    const r = canvas.getBoundingClientRect();
    return (clientX - r.left) * (W / r.width);
  }

  canvas.addEventListener('mousemove', function (e) {
    if (state) state.targetX = pointerX(e.clientX);
  });
  canvas.addEventListener('touchmove', function (e) {
    if (state && e.touches[0]) {
      state.targetX = pointerX(e.touches[0].clientX);
      e.preventDefault();
    }
  }, { passive: false });

  document.addEventListener('keydown', function (e) {
    if (!state) return;
    if (e.key === 'ArrowLeft') { state.targetX = Math.max(BASKET_W / 2, state.targetX - 46); e.preventDefault(); }
    else if (e.key === 'ArrowRight') { state.targetX = Math.min(W - BASKET_W / 2, state.targetX + 46); e.preventDefault(); }
    else if (e.key === ' ' && !state.over) { e.preventDefault(); togglePause(); }
  });

  function togglePause() {
    state.running = !state.running;
    pauseOverlay.hidden = state.running;
  }

  function start() {
    state = newState();
    lastTs = 0;
    startOverlay.hidden = true;
    pauseOverlay.hidden = true;
    overOverlay.hidden = true;
    renderHud(state);
    if (!rafId) rafId = requestAnimationFrame(frame);
    watchdog();
  }

  document.getElementById('eggStartBtn').addEventListener('click', start);
  document.getElementById('eggAgainBtn').addEventListener('click', start);
  document.getElementById('eggResumeBtn').addEventListener('click', togglePause);

  // สลับแท็บออกไประหว่างเล่น ให้หยุดค้างไว้ ไม่ใช่ตายเพราะไม่ได้ขยับ
  document.addEventListener('visibilitychange', function () {
    if (document.hidden && state && state.running && !state.over) togglePause();
  });

  // วาดฉากนิ่งไว้ให้เห็นก่อนกดเริ่ม
  drawScene(newState());
})();
