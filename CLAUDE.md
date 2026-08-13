# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

**LinuxQuest LMS** — a Thai-language learning platform for teaching Linux CLI, built as plain PHP 8 + MariaDB on XAMPP. No Composer, no build step, no framework, no test suite. Every page is a top-level `.php` file served directly by Apache from `C:\xampp\htdocs\lmln`, so **all URLs are absolute and `/lmln/`-prefixed** (`/lmln/dashboard.php`, `/lmln/public/css/style.css`). Changing the deploy directory means rewriting those paths everywhere.

The UI is Thai-first (labels, error strings, seed content); English appears only as secondary/eyebrow text. Keep new user-facing strings Thai.

## Setup / running

Preferred: open `http://localhost/lmln/install.php`. [install.php](install.php) is a self-contained web installer — env checks, creates the database, drops any existing tables, runs `schema.sql`, writes `config/db.local.php`, runs `seed.php`, then replaces the seeded sample teacher with the admin account entered in the form (role `teacher`, so it reaches `teacher/dashboard.php`). It is **re-runnable**: a second install wipes and rebuilds, but only after an explicit confirmation checkbox, and it refuses non-localhost requests. It deliberately does *not* `require bootstrap.php`, because the `DB_*` constants must not be defined before the new connection config is written. All form validation runs before the first destructive statement, so a bad admin email can't leave a half-dropped database.

`config/installed.lock` records install time, DB name, and admin name/email (never the password) — it drives the re-install warning and pre-fills the form.

Manual equivalent:

```bash
mysql -u root -p < database/schema.sql
```

```bash
php database/seed.php
```

`seed.php` **truncates every table** and re-inserts all content; it is a content-authoring tool, not a migration. Any schema change means editing `database/schema.sql` and re-importing — there is no migration system. Seeding prints a teacher login (`teacher@linuxquest.local`); student accounts come from `register.php`.

DB connection precedence in `config/config.php`: `LQ_DB_HOST` / `LQ_DB_PORT` / `LQ_DB_NAME` / `LQ_DB_USER` / `LQ_DB_PASS` env vars > `config/db.local.php` (installer-written, gitignored) > built-in XAMPP defaults. That file also defines the gating constants (`LESSON_COUNT`, `PASS_PCT_LESSON_QUIZ`, `PASS_PCT_POSTTEST`) and turns `display_errors` on — it is a dev config.

Verify changes by loading pages in a browser (`http://localhost/lmln/`); there is nothing to lint or test.

## Architecture

Every entry point starts with `require __DIR__ . '/bootstrap.php'`, which loads config + all `src/` classes in dependency order. There is no autoloader — a new `src/` class must be added to [bootstrap.php](bootstrap.php).

`src/` holds static-only final classes; there are no models or ORM. All DB access is PDO prepared statements through `Database::get()` (singleton, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, real prepares). Page files query directly for their own view data and render inline PHP/HTML between `Layout::start()` and `Layout::end()`.

- [src/Layout.php](src/Layout.php) — the *only* chrome: `<head>`, header, sidebar nav, XP/level pill, progress bar, and the logout confirmation modal emitted by `Layout::end()`. Pages pass a nav key (`home`/`course`/`games`/`board`/`cert`/`teacher`) for the active state; a 4th arg adds a class to `<main>` (used by the landing page to opt out of the centered auth layout). Nav items live in `Layout::mainNav()`. Logging out is a POST + CSRF from that modal — [logout.php](logout.php) bounces plain GETs back, so nothing logs a user out via a link.
- [src/Auth.php](src/Auth.php) — session auth, `requireLogin()` / `requireTeacher()`. Roles are `student` | `general` | `teacher`; `student` additionally requires education level / major / school. Registration **also seeds `user_lesson_progress`** — lesson 1 `unlocked`, the rest `locked` — in the same transaction. New users get no progress rows any other way.
- [src/Progress.php](src/Progress.php) — the gating state machine (below).
- [src/QuizGrader.php](src/QuizGrader.php) — quiz lookup, attempt lifecycle, per-answer grading, pass computation against `quizzes.pass_threshold_pct`.
- [src/TerminalRules.php](src/TerminalRules.php) — server-side verification of terminal exercises.
- [src/Csrf.php](src/Csrf.php) — session token; `Csrf::field()` in forms, `Csrf::requireValid()` on POST, `Csrf::verify()` in the JSON APIs (which check the token from the JSON body, not a header).

### The gating chain (the core domain logic)

The whole app is one sequential course (`course_id = 1`, hardcoded in the page files). A lesson completes only when **both** halves pass:

1. All `lesson_tasks` for the lesson are satisfied by terminal work, and
2. The lesson's 3-question quiz attempt passes.

`Progress::tryCompleteLesson()` is the single place that flips `user_lesson_progress.status` to `completed`, unlocks the next lesson by `position`, and awards `lessons.xp_reward`. It is called from [quiz.php](quiz.php) when the final question of a lesson quiz is graded. Course completion + a posttest at ≥ `PASS_PCT_POSTTEST` makes `Progress::certificateEligible()` true, and `issueCertificateIfEligible()` mints the `cert_code`.

XP is a plain counter on `users.xp`; level is derived (`Progress::level()` = 200 XP per level), never stored.

The arcade has its own two-layer gate, both in `Progress`:

- `arcadeGate()` — the zone is shut until the learner has "started": pretest completed **and** ≥1 lesson passed.
- `gameGate()` — each game additionally needs every lesson in its `games.required_lessons` (a JSON array of `lessons.position`, authored in `seed.php` from the commands the game actually uses) to be `completed`.

Both return the *missing* items, not just a boolean, because [games.php](games.php) renders them as the explanation on each locked card. [game.php](game.php) re-checks both and redirects to `games.php?locked=<code>` — the listing page is the only place that explains what is missing. Adding a game means giving it a `required_lessons` set; there is no implicit default.

### Terminal simulator (client engine, server verification)

[public/js/terminal-engine.js](public/js/terminal-engine.js) is a framework-free in-browser Linux shell over a JSON virtual filesystem, seeded per lesson from `lessons.vfs_seed`. VFS node shape:

```
directory  {"t":"d","ch":{name:node,...},"p":"rwxr-xr-x"}
file       {"t":"f","c":"contents","p":"rw-r--r--"}
```

It exists purely for instant keystroke feedback and **is not trusted**. After every command, [public/js/lesson.js](public/js/lesson.js) POSTs the full command history + VFS snapshot to [api/submit_task.php](api/submit_task.php), which re-evaluates each task's rule server-side via `TerminalRules::evaluate()` and persists the result to `user_lesson_progress.tasks_done` (a JSON array of task ids). Task rule types (`history_regex`, `vfs_path_exists`, `vfs_path_absent`, `vfs_perm_check`, `vfs_path_exists_any`, `all_of`) are documented at the top of [src/TerminalRules.php](src/TerminalRules.php) and must stay in sync with the `lesson_tasks.rule_type` comment in the schema.

**If you add a shell command to the JS engine, add the matching rule support server-side** — a task only counts when PHP can independently confirm it.

The games in [public/js/game.js](public/js/game.js) reuse the same `Terminal` class, injecting scenario-specific commands (`av`, `door`, `sys`) through `term.custom`. [api/save_game_score.php](api/save_game_score.php) records scores and awards XP; it trusts the client's reported score, unlike lesson progress.

### Quiz flow

[quiz.php](quiz.php) is one page handling all three kinds (`pretest` / `posttest` / `lesson`), driven by query string (`?kind=&lesson_id=&attempt=&q=&picked=`). It is a POST-redirect-GET loop, and `$revealPerQuestion` (true only for `lesson`) decides where that redirect lands:

- **Lesson quizzes** are practice, so they redirect back to the same `q` with `picked` set — options lock, the correct one is marked, and the explanation shows before a link advances.
- **Pre/post-tests** are measurement, so they redirect straight to `q + 1` with nothing revealed. The whole answer key — each question, the learner's choice, the correct option, and the explanation — appears once in [quiz_result.php](quiz_result.php).

When `q > total` the attempt is graded and it redirects to `quiz_result.php`. `QuizGrader::reviewRows()` drives both review styles and is built `FROM quiz_questions` with a `LEFT JOIN` onto the answers, so skipped questions still appear (with `is_correct` NULL, rendered as "ไม่ได้ตอบ"). Lesson quizzes are unreachable until `Progress::tasksAllDone()` — enforced server-side in `quiz.php`, with the link merely dimmed in `lesson.php`.

[index.php](index.php) is the public landing page (features, learning path, login/register CTAs) and redirects logged-in users straight to the dashboard. It is the one page that must survive an uninstalled database — its stat queries are wrapped in a `try`/`catch` that falls back to constants and surfaces a link to `install.php`.

## Styling

All styles are in one hand-written [public/css/style.css](public/css/style.css) using CSS custom properties (`--green`, `--panel`, `--mono`, …) defined in `:root`. Dark terminal aesthetic, IBM Plex Sans Thai + JetBrains Mono from Google Fonts. Inline `style="…"` for one-off page tweaks is the established idiom here — match it rather than adding new class hierarchies for single uses. Colors referenced from PHP (e.g. the `$colorMap` in `lesson.php`) and JS (`terminal-engine.js` constants) duplicate the CSS variable values; change all three together.

## MariaDB constraint

The XAMPP target runs MariaDB 10.4, which **cannot use correlated subqueries inside a derived table**. Per-user "best score per game, summed" aggregations in [leaderboard.php](leaderboard.php) and [teacher/dashboard.php](teacher/dashboard.php) are therefore computed by looping in PHP. Don't "simplify" them back into a single query.

## The design prototype

`project/` holds the original Claude Design handoff — [project/LinuxQuest LMS.dc.html](project/LinuxQuest%20LMS.dc.html) (a `<x-dc>`-templated HTML/CSS mockup) plus its `support.js` React runtime. It is the **visual and content source of truth** that the PHP app was built from; lesson/quiz/game/drill content in `database/seed.php` is transcribed from its `LESSONS`/`PRE`/`POST`/`GAMES`/`DRILLS` constants. Read it when you need original design intent, but never import its template syntax or runtime into the app. Per [README.md](README.md), read the source rather than rendering or screenshotting it.
