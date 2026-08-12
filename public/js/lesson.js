(function () {
  'use strict';
  const cfg = window.LQ_LESSON;
  const T = window.LinuxQuestTerminal;
  const term = new T.Terminal(JSON.parse(JSON.stringify(cfg.vfsSeed)), ['home', 'student']);

  const linesEl = document.getElementById('termLines');
  const inputEl = document.getElementById('termInput');
  const cwdLabel = document.getElementById('termCwdLabel');
  const promptEl = document.getElementById('termPrompt');
  const hintsEl = document.getElementById('termHints');
  const treeBox = document.getElementById('treeBox');
  const treePath = document.getElementById('treePath');
  const treeCaret = document.getElementById('treeCaret');
  const treeToggle = document.getElementById('treeToggle');
  const termWrap = document.getElementById('termWrap');
  const taskProgress = document.getElementById('taskProgress');
  const taskList = document.getElementById('taskList');
  const taskDoneBanner = document.getElementById('taskDoneBanner');
  const quizLink = document.getElementById('quizLink');

  let renderedLines = [];
  let histIdx = -1;
  let treeOpen = true;

  function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  function appendLines(newLines) {
    newLines.forEach(l => {
      const div = document.createElement('div');
      div.style.color = l.color;
      div.textContent = l.text;
      linesEl.appendChild(div);
    });
    linesEl.scrollTop = linesEl.scrollHeight;
  }

  function renderPrompt() {
    const disp = term.disp(term.cwd);
    cwdLabel.textContent = 'student@linuxquest: ' + disp;
    promptEl.textContent = disp + ' $';
  }

  function renderTree() {
    treePath.textContent = '/' + term.cwd.join('/');
    treeBox.innerHTML = '';
    if (!treeOpen) { treeBox.style.display = 'none'; return; }
    treeBox.style.display = '';
    term.treeRows().forEach(r => {
      const row = document.createElement('div');
      row.className = 'tree-row' + (r.isCwd ? ' cur' : '');
      row.style.paddingLeft = (8 + r.depth * 15) + 'px';
      row.innerHTML = '<span style="width:9px;display:inline-block">' + r.glyph + '</span><span>' + esc(r.name) + '</span>' + (r.isCwd ? '<span style="font-size:10.5px;margin-left:6px">← คุณอยู่ที่นี่</span>' : '');
      treeBox.appendChild(row);
    });
  }

  function renderHints() {
    hintsEl.querySelectorAll('.hint-chip').forEach(el => el.remove());
    (cfg.hints || []).forEach(h => {
      const chip = document.createElement('span');
      chip.className = 'hint-chip';
      chip.textContent = h;
      chip.addEventListener('click', () => { inputEl.value = h; runCurrent(); });
      hintsEl.appendChild(chip);
    });
  }

  function runCurrent() {
    const raw = inputEl.value;
    const before = term.disp(term.cwd);
    const r = term.run(raw);
    if (r.clear) { linesEl.innerHTML = ''; }
    else {
      appendLines([{ text: before + ' $ ' + raw, color: T.COLORS.GREEN }].concat(r.out));
    }
    inputEl.value = '';
    histIdx = -1;
    renderPrompt();
    renderTree();
    postProgress();
  }

  inputEl.addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); runCurrent(); }
    else if (e.key === 'Tab') {
      e.preventDefault();
      const res = term.complete(inputEl.value, []);
      if (res) {
        if (res.multi) appendLines([{ text: term.disp(term.cwd) + ' $ ' + inputEl.value, color: T.COLORS.GREEN }, { text: res.multi.join('   '), color: '#38bdf8' }]);
        inputEl.value = res.filled;
      }
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      if (!term.history.length) return;
      histIdx = histIdx < 0 ? term.history.length - 1 : Math.max(0, histIdx - 1);
      inputEl.value = term.history[histIdx];
    } else if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (histIdx < 0) return;
      histIdx++;
      if (histIdx >= term.history.length) { histIdx = -1; inputEl.value = ''; }
      else inputEl.value = term.history[histIdx];
    }
  });

  document.getElementById('termClear').addEventListener('click', () => { linesEl.innerHTML = ''; });
  termWrap.addEventListener('click', () => inputEl.focus());
  treeToggle.addEventListener('click', () => { treeOpen = !treeOpen; treeCaret.textContent = treeOpen ? '▾' : '▸'; renderTree(); });

  function postProgress() {
    fetch('/lmln/api/submit_task.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ lesson_id: cfg.lessonId, history: term.history, vfs: term.vfs, csrf_token: cfg.csrf }),
    }).then(r => r.json()).then(data => {
      if (!data.tasks) return;
      let doneCount = 0;
      data.tasks.forEach(t => {
        const row = taskList.querySelector('[data-task-id="' + t.id + '"]');
        if (!row) return;
        const check = row.querySelector('.task-check');
        const label = row.querySelector('.task-label');
        check.classList.toggle('done', t.done);
        check.textContent = t.done ? '✓' : '';
        label.classList.toggle('done', t.done);
        if (t.done) doneCount++;
      });
      taskProgress.textContent = doneCount + ' / ' + data.tasks.length;
      if (data.allDone) {
        taskDoneBanner.style.display = '';
        if (quizLink) { quizLink.style.pointerEvents = ''; quizLink.style.opacity = ''; }
      }
    }).catch(() => {});
  }

  appendLines([{ text: 'LinuxQuest Terminal จำลอง — พิมพ์ help เพื่อดูคำสั่งที่ใช้ได้', color: T.COLORS.MUT }]);
  renderPrompt();
  renderTree();
  renderHints();
  inputEl.focus();
})();
