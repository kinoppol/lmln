/* LinuxQuest — เกมคลายเครียด "บล็อกสีเดียวกัน" (SameGame)
 *
 * ตารางเป็น DOM ล้วน ไม่มี canvas และไม่มีลูปแอนิเมชัน สถานะทั้งหมดอยู่ในอาเรย์
 * grid[row][col] แล้ววาดใหม่ทุกครั้งที่เปลี่ยน — เล็กพอ (12x10) ที่จะทำแบบนี้ได้
 *
 * กติกา: กดกลุ่มบล็อกสีเดียวกันที่ติดกัน (แนวตั้ง/นอน) ตั้งแต่ 2 ช่อง จะถูกลบ
 * ได้คะแนน (n-2)^2 บล็อกด้านบนร่วงลงมาแทน คอลัมน์ที่ว่างหมดจะถูกบีบไปทางซ้าย
 * จบเมื่อไม่มีกลุ่มที่กดได้เหลือ เคลียร์หมดกระดานได้โบนัส
 */
(function () {
  'use strict';

  const cfg = window.LQ_GAME;
  const boardEl = document.getElementById('sameBoard');
  if (!boardEl) return;

  const COLS = 12, ROWS = 10;
  const COLORS = ['g', 'c', 'a', 'r'];        // เขียว ฟ้า เหลือง แดง (ตามพาเลตของระบบ)
  const CLEAR_BONUS = 300;

  const scoreEl = document.getElementById('sameScore');
  const leftEl = document.getElementById('sameLeft');
  const selEl = document.getElementById('sameSel');
  const overEl = document.getElementById('sameOver');

  let grid = [];        // grid[r][c] = สีหรือ null
  let score = 0;
  let removed = 0;
  let cells = [];       // อ้างอิง element ตามตำแหน่ง เพื่อไม่ต้องสร้าง DOM ใหม่ทุกตา
  let finished = false;

  function newGrid() {
    grid = [];
    for (let r = 0; r < ROWS; r++) {
      const row = [];
      for (let c = 0; c < COLS; c++) {
        row.push(COLORS[Math.floor(Math.random() * COLORS.length)]);
      }
      grid.push(row);
    }
  }

  function buildDom() {
    boardEl.style.gridTemplateColumns = 'repeat(' + COLS + ', 1fr)';
    boardEl.innerHTML = '';
    cells = [];
    for (let r = 0; r < ROWS; r++) {
      const row = [];
      for (let c = 0; c < COLS; c++) {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = 'same-cell';
        el.dataset.r = String(r);
        el.dataset.c = String(c);
        el.addEventListener('click', function () { onClick(r, c); });
        el.addEventListener('mouseenter', function () { preview(r, c); });
        el.addEventListener('mouseleave', clearPreview);
        el.addEventListener('focus', function () { preview(r, c); });
        boardEl.appendChild(el);
        row.push(el);
      }
      cells.push(row);
    }
  }

  /** ทุกช่องในกลุ่มสีเดียวกันที่ติดกันกับ (r,c) — flood fill 4 ทิศ */
  function groupAt(r, c) {
    const color = grid[r][c];
    if (!color) return [];
    const seen = {};
    const stack = [[r, c]];
    const out = [];
    while (stack.length) {
      const p = stack.pop();
      const key = p[0] + ',' + p[1];
      if (seen[key]) continue;
      if (p[0] < 0 || p[0] >= ROWS || p[1] < 0 || p[1] >= COLS) continue;
      if (grid[p[0]][p[1]] !== color) continue;
      seen[key] = true;
      out.push(p);
      stack.push([p[0] + 1, p[1]], [p[0] - 1, p[1]], [p[0], p[1] + 1], [p[0], p[1] - 1]);
    }
    return out;
  }

  function groupScore(n) {
    return n < 2 ? 0 : (n - 2) * (n - 2);
  }

  function preview(r, c) {
    if (finished) return;
    const group = groupAt(r, c);
    clearPreview();
    if (group.length < 2) {
      selEl.textContent = grid[r][c] ? '1 ช่อง (กดไม่ได้)' : '—';
      return;
    }
    group.forEach(function (p) { cells[p[0]][p[1]].classList.add('sel'); });
    selEl.textContent = group.length + ' ช่อง · +' + groupScore(group.length);
  }

  function clearPreview() {
    for (let r = 0; r < ROWS; r++) {
      for (let c = 0; c < COLS; c++) cells[r][c].classList.remove('sel');
    }
  }

  function onClick(r, c) {
    if (finished) return;
    const group = groupAt(r, c);
    if (group.length < 2) return;

    group.forEach(function (p) { grid[p[0]][p[1]] = null; });
    score += groupScore(group.length);
    removed += group.length;
    collapse();
    render();

    if (!hasMoves()) finish();
  }

  /** บล็อกร่วงลงเติมช่องว่าง แล้วบีบคอลัมน์ที่ว่างทั้งแถวไปทางซ้าย */
  function collapse() {
    for (let c = 0; c < COLS; c++) {
      const stack = [];
      for (let r = ROWS - 1; r >= 0; r--) {
        if (grid[r][c]) stack.push(grid[r][c]);
      }
      for (let r = ROWS - 1, i = 0; r >= 0; r--, i++) {
        grid[r][c] = i < stack.length ? stack[i] : null;
      }
    }

    const kept = [];
    for (let c = 0; c < COLS; c++) {
      let empty = true;
      for (let r = 0; r < ROWS; r++) {
        if (grid[r][c]) { empty = false; break; }
      }
      if (!empty) kept.push(c);
    }
    const next = [];
    for (let r = 0; r < ROWS; r++) {
      const row = [];
      for (let i = 0; i < COLS; i++) row.push(i < kept.length ? grid[r][kept[i]] : null);
      next.push(row);
    }
    grid = next;
  }

  function countLeft() {
    let n = 0;
    for (let r = 0; r < ROWS; r++) {
      for (let c = 0; c < COLS; c++) if (grid[r][c]) n++;
    }
    return n;
  }

  function hasMoves() {
    for (let r = 0; r < ROWS; r++) {
      for (let c = 0; c < COLS; c++) {
        if (!grid[r][c]) continue;
        if (c + 1 < COLS && grid[r][c + 1] === grid[r][c]) return true;
        if (r + 1 < ROWS && grid[r + 1][c] === grid[r][c]) return true;
      }
    }
    return false;
  }

  function render() {
    for (let r = 0; r < ROWS; r++) {
      for (let c = 0; c < COLS; c++) {
        const el = cells[r][c];
        const v = grid[r][c];
        el.className = 'same-cell' + (v ? ' color-' + v : ' empty');
        el.disabled = !v;
      }
    }
    scoreEl.textContent = String(score);
    leftEl.textContent = String(countLeft());
    selEl.textContent = '—';
  }

  function finish() {
    finished = true;
    const left = countLeft();
    if (left === 0) score += CLEAR_BONUS;

    // XP จำกัดเพดานเพราะเป็นเกมพักสมอง ไม่ได้วัดทักษะ Linux
    const xp = Math.min(60, Math.floor(score / 12));
    document.getElementById('sameFinalScore').textContent = String(score);
    document.getElementById('sameFinalDetail').textContent =
      'ลบไปทั้งหมด ' + removed + ' ช่อง · เหลือบนกระดาน ' + left + ' ช่อง' + (left === 0 ? ' · โบนัสเคลียร์หมด +' + CLEAR_BONUS : '');
    document.getElementById('sameFinalMsg').textContent =
      (left === 0 ? 'เคลียร์หมดกระดาน! ' : left <= 12 ? 'เก็บได้เกือบหมดเลย ' : 'ลองวางแผนกลุ่มใหญ่ดูอีกรอบ ') + '+' + xp + ' XP';
    overEl.hidden = false;

    fetch(cfg.base + '/api/save_game_score.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code: cfg.code, score: score, time_taken_sec: 0, xp: xp, csrf_token: cfg.csrf }),
    }).catch(function () {});
  }

  function start() {
    score = 0;
    removed = 0;
    finished = false;
    overEl.hidden = true;
    newGrid();
    // กระดานที่สุ่มมาแล้วเดินต่อไม่ได้ตั้งแต่ต้น (เกิดได้ยากแต่เป็นไปได้) ให้สุ่มใหม่
    let guard = 0;
    while (!hasMoves() && guard++ < 50) newGrid();
    render();
  }

  document.getElementById('sameRestart').addEventListener('click', start);
  document.getElementById('sameAgainBtn').addEventListener('click', start);

  buildDom();
  start();
})();
