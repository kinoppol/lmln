/* LinuxQuest — client-side virtual filesystem + terminal command interpreter.
 * Ported from the Claude Design prototype (project/LinuxQuest LMS.dc.html)
 * into a plain, framework-free JS module. Runs entirely in the browser for
 * instant keystroke feedback; the server independently re-verifies lesson
 * task completion from the submitted history + VFS snapshot (see
 * src/TerminalRules.php) before granting progress/XP.
 */
(function (global) {
  'use strict';

  const GREEN = '#4ade80', DIM = '#9bb0a8', TXT = '#d7e4de', RED = '#f87171',
    AMBER = '#fbbf24', CYAN = '#38bdf8', MUT = '#6f837c';

  function d(ch, p) { return { t: 'd', ch: ch || {}, p: p || 'rwxr-xr-x' }; }
  function f(c, p) { return { t: 'f', c: c || '', p: p || 'rw-r--r--' }; }
  function clone(n) { return JSON.parse(JSON.stringify(n)); }

  class Terminal {
    constructor(vfs, cwd) {
      this.vfs = vfs;
      this.cwd = cwd || ['home', 'student'];
      this.history = []; // raw command strings, in order
      this.custom = null; // (cmd,args,rest,flags,cwd,push) => bool
      this.toolLine = null;
    }

    node(parts) {
      let cur = this.vfs;
      for (const p of parts) {
        if (!cur || cur.t !== 'd') return null;
        cur = cur.ch[p];
        if (!cur) return null;
      }
      return cur;
    }

    resolve(cwd, arg) {
      if (!arg || arg === '.') return cwd.slice();
      let parts;
      if (arg === '~' || arg.indexOf('~/') === 0) parts = ['home', 'student'].concat(arg.slice(2).split('/'));
      else if (arg[0] === '/') parts = arg.split('/');
      else parts = cwd.concat(arg.split('/'));
      const out = [];
      for (const p of parts) {
        if (!p || p === '.') continue;
        if (p === '..') { out.pop(); continue; }
        out.push(p);
      }
      return out;
    }

    disp(cwd) {
      const s = '/' + cwd.join('/');
      return s.indexOf('/home/student') === 0 ? ('~' + s.slice(13) || '~') : s;
    }

    perms(node) { return (node.t === 'd' ? 'd' : '-') + node.p; }

    exec(raw) {
      const out = [], line = raw.trim();
      const push = (t, c) => out.push({ text: t, color: c || TXT });
      if (!line) return { out, cwd: this.cwd };
      const parts = line.match(/"[^"]*"|'[^']*'|\S+/g) || [];
      const cmd = parts[0];
      const args = parts.slice(1).map(a => a.replace(/^["']|["']$/g, ''));
      const flags = args.filter(a => a[0] === '-');
      const rest = args.filter(a => a[0] !== '-');
      let cwd = this.cwd.slice();
      const vfs = this.vfs;
      const at = p => this.node(p);
      const parentOf = p => this.node(p.slice(0, -1));

      switch (cmd) {
        case 'help':
          push('คำสั่งที่ใช้ได้ในระบบจำลองนี้:', GREEN);
          push('  pwd  ls  cd  mkdir  touch  cat  head  tail  cp  mv  rm  rmdir  chmod  grep  find  echo  man  clear', DIM);
          if (this.toolLine) push('  ' + this.toolLine, CYAN);
          push('ทางลัด: Tab = เติมชื่อไฟล์/คำสั่งอัตโนมัติ · ↑ ↓ = เรียกคำสั่งเดิม', MUT);
          break;
        case 'pwd': push('/' + cwd.join('/')); break;
        case 'clear': return { out: [], cwd, clear: true };
        case 'ls': {
          const target = this.resolve(cwd, rest[0]); const n = at(target);
          if (!n) { push('ls: cannot access \'' + rest[0] + '\': No such file or directory', RED); break; }
          if (n.t === 'f') { push(rest[0]); break; }
          let names = Object.keys(n.ch);
          if (!flags.some(fl => fl.indexOf('a') > -1)) names = names.filter(x => x[0] !== '.');
          names.sort();
          if (!names.length) { push('(ว่าง)', MUT); break; }
          if (flags.some(fl => fl.indexOf('l') > -1)) {
            push('total ' + names.length, MUT);
            names.forEach(x => { const c = n.ch[x]; push(this.perms(c) + '  student  ' + (c.t === 'd' ? '4096' : String((c.c || '').length).padStart(4)) + '  ' + x, c.t === 'd' ? CYAN : TXT); });
          } else push(names.map(x => n.ch[x].t === 'd' ? x + '/' : x).join('   '));
          break;
        }
        case 'cd': {
          const target = this.resolve(cwd, rest[0] || '~'); const n = at(target);
          if (!n) { push('cd: ' + rest[0] + ': No such file or directory', RED); break; }
          if (n.t !== 'd') { push('cd: ' + rest[0] + ': Not a directory', RED); break; }
          cwd = target; break;
        }
        case 'mkdir': {
          if (!rest.length) { push('mkdir: missing operand', RED); break; }
          for (const r of rest) {
            const target = this.resolve(cwd, r);
            if (flags.some(fl => fl.indexOf('p') > -1)) {
              let cur = vfs; for (const seg of target) { if (!cur.ch[seg]) cur.ch[seg] = d({}); cur = cur.ch[seg]; }
            } else {
              const par = parentOf(target);
              if (!par || par.t !== 'd') { push('mkdir: cannot create \'' + r + '\': No such file or directory', RED); continue; }
              if (par.ch[target[target.length - 1]]) { push('mkdir: cannot create directory \'' + r + '\': File exists', RED); continue; }
              par.ch[target[target.length - 1]] = d({});
            }
          }
          break;
        }
        case 'touch': {
          for (const r of rest) {
            const target = this.resolve(cwd, r); const par = parentOf(target);
            if (!par || par.t !== 'd') { push('touch: cannot touch \'' + r + '\': No such file or directory', RED); continue; }
            if (!par.ch[target[target.length - 1]]) par.ch[target[target.length - 1]] = f('');
          }
          if (!rest.length) push('touch: missing file operand', RED);
          break;
        }
        case 'echo': {
          const gt = line.indexOf('>');
          if (gt > -1) {
            const text = line.slice(5, gt).trim().replace(/^["']|["']$/g, ''); const fn = line.slice(gt + 1).trim();
            const target = this.resolve(cwd, fn); const par = parentOf(target);
            if (!par) { push('bash: ' + fn + ': No such file or directory', RED); break; }
            par.ch[target[target.length - 1]] = f(text);
          } else push(args.join(' ').replace(/^["']|["']$/g, ''));
          break;
        }
        case 'cat': case 'less': case 'more': {
          if (!rest.length) { push(cmd + ': missing operand', RED); break; }
          for (const r of rest) {
            const n = at(this.resolve(cwd, r));
            if (!n) { push(cmd + ': ' + r + ': No such file or directory', RED); continue; }
            if (n.t === 'd') { push(cmd + ': ' + r + ': Is a directory', RED); continue; }
            if (n.p[0] !== 'r') { push(cmd + ': ' + r + ': Permission denied', RED); continue; }
            (n.c || '').split('\n').forEach(l => push(l, /eval|curl|base64|payload/.test(l) ? RED : TXT));
          }
          break;
        }
        case 'head': case 'tail': {
          const cnt = (() => { const i = args.indexOf('-n'); return i > -1 && args[i + 1] ? parseInt(args[i + 1]) : 10; })();
          const fn = rest.filter(r => !/^\d+$/.test(r))[0];
          const n = at(this.resolve(cwd, fn || ''));
          if (!n || n.t !== 'f') { push(cmd + ': ' + (fn || '') + ': No such file', RED); break; }
          const ls = (n.c || '').split('\n');
          (cmd === 'head' ? ls.slice(0, cnt) : ls.slice(-cnt)).forEach(l => push(l));
          break;
        }
        case 'cp': case 'mv': {
          if (rest.length < 2) { push(cmd + ': missing destination file operand', RED); break; }
          const src = this.resolve(cwd, rest[0]), sn = at(src);
          if (!sn) { push(cmd + ': cannot stat \'' + rest[0] + '\': No such file or directory', RED); break; }
          if (cmd === 'cp' && sn.t === 'd' && !flags.some(fl => fl.indexOf('r') > -1)) { push('cp: -r not specified; omitting directory \'' + rest[0] + '\'', RED); break; }
          let dst = this.resolve(cwd, rest[1]); const dn = at(dst);
          if (dn && dn.t === 'd') dst = dst.concat(src[src.length - 1]);
          const dpar = this.node(dst.slice(0, -1));
          if (!dpar || dpar.t !== 'd') { push(cmd + ': cannot create \'' + rest[1] + '\': No such file or directory', RED); break; }
          dpar.ch[dst[dst.length - 1]] = clone(sn);
          if (cmd === 'mv') { const spar = parentOf(src); delete spar.ch[src[src.length - 1]]; }
          break;
        }
        case 'rm': {
          if (!rest.length) { push('rm: missing operand', RED); break; }
          for (const r of rest) {
            const target = this.resolve(cwd, r), n = at(target);
            if (!n) { push('rm: cannot remove \'' + r + '\': No such file or directory', RED); continue; }
            if (n.t === 'd' && !flags.some(fl => fl.indexOf('r') > -1)) { push('rm: cannot remove \'' + r + '\': Is a directory', RED); continue; }
            const par = parentOf(target); delete par.ch[target[target.length - 1]]; push('ลบ ' + r + ' แล้ว', MUT);
          }
          break;
        }
        case 'rmdir': {
          for (const r of rest) {
            const target = this.resolve(cwd, r), n = at(target);
            if (!n) { push('rmdir: failed to remove \'' + r + '\': No such file or directory', RED); continue; }
            if (n.t !== 'd') { push('rmdir: failed to remove \'' + r + '\': Not a directory', RED); continue; }
            if (Object.keys(n.ch).length) { push('rmdir: failed to remove \'' + r + '\': Directory not empty', RED); continue; }
            delete parentOf(target).ch[target[target.length - 1]]; push('ลบโฟลเดอร์ ' + r + ' แล้ว', MUT);
          }
          break;
        }
        case 'chmod': {
          if (rest.length < 1 || args.length < 2) { push('chmod: missing operand', RED); break; }
          const mode = args[0], n = at(this.resolve(cwd, args[args.length - 1]));
          if (!n) { push('chmod: cannot access \'' + args[args.length - 1] + '\': No such file or directory', RED); break; }
          const oct = m => ['---', '--x', '-w-', '-wx', 'r--', 'r-x', 'rw-', 'rwx'][m];
          if (/^[0-7]{3}$/.test(mode)) n.p = oct(+mode[0]) + oct(+mode[1]) + oct(+mode[2]);
          else if (/^[+-]x$/.test(mode) || /^[ugoa]*[+-][rwx]+$/.test(mode)) {
            const add = mode.indexOf('+') > -1; const letters = mode.split(/[+-]/)[1] || '';
            let p = n.p.split('');
            'rwx'.split('').forEach((L, i) => { if (letters.indexOf(L) > -1) [0, 3, 6].forEach(b => { p[b + i] = add ? L : '-'; }); });
            n.p = p.join('');
          } else { push('chmod: invalid mode: \'' + mode + '\'', RED); break; }
          push('เปลี่ยนสิทธิ์ ' + args[args.length - 1] + ' เป็น ' + n.p, MUT);
          break;
        }
        case 'grep': {
          const rec = flags.some(fl => fl.indexOf('r') > -1);
          const pat = rest[0]; if (!pat) { push('grep: missing pattern', RED); break; }
          let hits = 0;
          const scan = (node, path) => {
            if (node.t === 'f') { (node.c || '').split('\n').forEach(l => { if (l.toLowerCase().indexOf(pat.toLowerCase()) > -1) { hits++; push((rec ? path + ': ' : '') + l, /eval|curl|base64|payload/.test(l) ? RED : TXT); } }); return; }
            if (rec) Object.keys(node.ch).forEach(k => scan(node.ch[k], path + '/' + k));
          };
          const startArg = rest[1] || '.'; const start = at(this.resolve(cwd, startArg));
          if (!start) { push('grep: ' + startArg + ': No such file or directory', RED); break; }
          scan(start, startArg === '.' ? '.' : startArg);
          if (!hits) push('(ไม่พบข้อความที่ตรงกับ "' + pat + '")', MUT);
          break;
        }
        case 'find': {
          const i = args.indexOf('-name'); const pat = i > -1 ? args[i + 1] : '*';
          const rx = new RegExp('^' + pat.replace(/[.+^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*') + '$', 'i');
          const start = this.resolve(cwd, rest[0] || '.'); const sn = at(start);
          if (!sn) { push('find: \'' + (rest[0] || '.') + '\': No such file or directory', RED); break; }
          let hits = 0;
          const walk = (node, path) => {
            const nm = path.split('/').pop(); if (rx.test(nm)) { hits++; push(path, node.t === 'd' ? CYAN : TXT); }
            if (node.t === 'd') Object.keys(node.ch).forEach(k => walk(node.ch[k], path + '/' + k));
          };
          walk(sn, rest[0] || '.');
          if (!hits) push('(ไม่พบไฟล์ที่ตรงกับ ' + pat + ')', MUT);
          break;
        }
        case 'man': case 'whatis': {
          const t = rest[0]; if (!t) { push('What manual page do you want?', RED); break; }
          const MAN = {
            ls: ['ls - list directory contents', '  -l  ใช้รูปแบบรายการยาว แสดงสิทธิ์และขนาด', '  -a  แสดงไฟล์ซ่อนที่ขึ้นต้นด้วยจุดด้วย'],
            cd: ['cd - change the working directory', '  cd ..  ขึ้นไปหนึ่งระดับ', '  cd ~   กลับ home directory'],
            rm: ['rm - remove files or directories', '  -r  ลบไล่ลงไปทุกชั้น (recursive)', '  -i  ถามยืนยันก่อนลบทุกไฟล์'],
            chmod: ['chmod - change file mode bits', '  chmod 755 file   rwxr-xr-x', '  chmod +x file    เพิ่มสิทธิ์รัน'],
            grep: ['grep - print lines that match patterns', '  -r  ค้นหาไล่ลงทุกโฟลเดอร์', '  -i  ไม่สนตัวพิมพ์ใหญ่เล็ก'],
            find: ['find - search for files in a directory hierarchy', '  find . -name "*.log"  ค้นตามชื่อไฟล์'],
            cp: ['cp - copy files and directories', '  -r  คัดลอกทั้งโฟลเดอร์'],
            mv: ['mv - move (rename) files'],
            mkdir: ['mkdir - make directories', '  -p  สร้างโฟลเดอร์แม่ให้ด้วยถ้ายังไม่มี'],
            cat: ['cat - concatenate files and print on the standard output'],
            pwd: ['pwd - print name of current/working directory'],
            touch: ['touch - change file timestamps (สร้างไฟล์เปล่าถ้ายังไม่มี)'],
          };
          const body = MAN[t];
          if (!body) { push('No manual entry for ' + t, RED); break; }
          push('NAME', DIM); push('    ' + body[0]);
          if (body.length > 1) { push('', TXT); push('OPTIONS', DIM); body.slice(1).forEach(l => push('  ' + l)); }
          push('', TXT); push('(กด q เพื่อออกจากคู่มือ)', MUT);
          break;
        }
        default:
          if (args.indexOf('--help') > -1 || line.indexOf('--help') > -1) { push('Usage: ' + cmd + ' [OPTION]... [FILE]...', DIM); push('ลองใช้ man ' + cmd + ' เพื่อดูคู่มือฉบับเต็ม', MUT); break; }
          if (this.custom) { const r = this.custom(cmd, args, rest, flags, cwd, push); if (r) break; }
          push('bash: ' + cmd + ': command not found', RED);
          push('พิมพ์ help เพื่อดูคำสั่งที่ใช้ได้', MUT);
      }
      return { out, cwd };
    }

    run(raw) {
      const r = this.exec(raw);
      this.cwd = r.cwd;
      this.history.push(raw);
      return r;
    }

    complete(input, extraCmds) {
      const CMDS = ['pwd', 'ls', 'cd', 'mkdir', 'touch', 'cat', 'head', 'tail', 'cp', 'mv', 'rm', 'rmdir', 'chmod', 'grep', 'find', 'echo', 'man', 'less', 'clear', 'help'].concat(extraCmds || []);
      const parts = input.split(/\s+/);
      const isFirst = parts.length === 1 && input.indexOf(' ') === -1;
      const token = parts[parts.length - 1] || '';
      let pool = [], prefix = '', dirPrefix = '';
      if (isFirst) { pool = CMDS.filter(c => c.indexOf(token) === 0); prefix = token; }
      else {
        const slash = token.lastIndexOf('/');
        dirPrefix = slash > -1 ? token.slice(0, slash + 1) : '';
        prefix = slash > -1 ? token.slice(slash + 1) : token;
        const base = this.node(this.resolve(this.cwd, dirPrefix || '.'));
        if (!base || base.t !== 'd') return null;
        pool = Object.keys(base.ch).filter(k => k.indexOf(prefix) === 0 && (prefix[0] === '.' || k[0] !== '.')).map(k => base.ch[k].t === 'd' ? k + '/' : k);
      }
      if (!pool.length) return null;
      let common = pool[0];
      pool.forEach(p => { let i = 0; while (i < common.length && i < p.length && common[i] === p[i]) i++; common = common.slice(0, i); });
      const head = parts.slice(0, parts.length - 1).join(' ');
      let filled = (isFirst ? '' : head + ' ') + dirPrefix + common;
      if (pool.length === 1 && !/\/$/.test(common)) filled += ' ';
      if (pool.length > 1 && common === prefix) return { filled, multi: pool };
      return { filled, multi: null };
    }

    /** Build tree rows for the "where am I" map panel. */
    treeRows() {
      const rows = []; const cwdKey = this.cwd.join('/');
      const walk = (node, path, depth) => {
        const key = path.join('/');
        const isCwd = key === cwdKey;
        const onPath = this.cwd.slice(0, path.length).join('/') === key && path.length < this.cwd.length;
        const isDir = node.t === 'd';
        const name = path.length ? path[path.length - 1] : '/';
        rows.push({ name: isDir && path.length ? name + '/' : name, isCwd, isDir, depth, glyph: isDir ? ((isCwd || onPath) ? '▾' : '▸') : '·' });
        if (!isDir) return;
        if (isCwd || onPath) {
          const keys = Object.keys(node.ch).filter(k => isCwd ? true : node.ch[k].t === 'd').sort();
          keys.forEach(k => walk(node.ch[k], path.concat(k), depth + 1));
        }
      };
      walk(this.vfs, [], 0);
      return rows;
    }
  }

  global.LinuxQuestTerminal = { Terminal, d, f, clone, COLORS: { GREEN, DIM, TXT, RED, AMBER, CYAN, MUT } };
})(window);
