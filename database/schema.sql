-- LinuxQuest LMS schema
-- MariaDB 10.4+
-- Run with: mysql -u root -p < database/schema.sql   (or import via phpMyAdmin)

CREATE DATABASE IF NOT EXISTS linuxquest_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE linuxquest_lms;

-- ---------------------------------------------------------------- users
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  role ENUM('student','general','teacher') NOT NULL DEFAULT 'general',
  education_level VARCHAR(100) NULL,   -- e.g. ปวช./ปวส./ปริญญาตรี/มัธยม (role=student only)
  major VARCHAR(150) NULL,             -- สาขาที่เรียน (role=student only)
  school_name VARCHAR(200) NULL,       -- สถานศึกษาที่สังกัด (role=student only)
  xp INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- courses
CREATE TABLE courses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  title_th VARCHAR(200) NOT NULL,
  title_en VARCHAR(200) NOT NULL,
  description TEXT NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- lessons
CREATE TABLE lessons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  position INT UNSIGNED NOT NULL,
  commands_summary VARCHAR(100) NOT NULL,
  title_th VARCHAR(200) NOT NULL,
  title_en VARCHAR(200) NOT NULL,
  blurb VARCHAR(255) NOT NULL,
  intro TEXT NOT NULL,
  warn_text TEXT NOT NULL,
  xp_reward INT UNSIGNED NOT NULL DEFAULT 40,
  vfs_seed JSON NOT NULL,
  UNIQUE KEY uq_course_position (course_id, position),
  CONSTRAINT fk_lessons_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE lesson_points (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lesson_id INT UNSIGNED NOT NULL,
  position INT UNSIGNED NOT NULL,
  cmd VARCHAR(100) NOT NULL,
  description VARCHAR(255) NOT NULL,
  CONSTRAINT fk_points_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE lesson_examples (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lesson_id INT UNSIGNED NOT NULL,
  position INT UNSIGNED NOT NULL,
  text_line VARCHAR(255) NOT NULL,
  color_tag VARCHAR(20) NOT NULL DEFAULT 'txt', -- green|txt|dim|red|amber|cyan
  CONSTRAINT fk_examples_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE lesson_hints (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lesson_id INT UNSIGNED NOT NULL,
  position INT UNSIGNED NOT NULL,
  text_hint VARCHAR(150) NOT NULL,
  CONSTRAINT fk_hints_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- rule_type: history_regex | vfs_path_exists | vfs_path_absent | vfs_perm_check | vfs_path_exists_any | all_of
-- rule_params: JSON, shape depends on rule_type (documented in src/TerminalRules.php)
CREATE TABLE lesson_tasks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lesson_id INT UNSIGNED NOT NULL,
  position INT UNSIGNED NOT NULL,
  label VARCHAR(255) NOT NULL,
  rule_type ENUM('history_regex','vfs_path_exists','vfs_path_absent','vfs_perm_check','vfs_path_exists_any','all_of') NOT NULL,
  rule_params JSON NOT NULL,
  CONSTRAINT fk_tasks_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- quizzes
CREATE TABLE quizzes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('pretest','posttest','lesson') NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  lesson_id INT UNSIGNED NULL,
  title_th VARCHAR(200) NOT NULL,
  pass_threshold_pct TINYINT UNSIGNED NOT NULL DEFAULT 70,
  CONSTRAINT fk_quiz_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_quiz_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE quiz_questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT UNSIGNED NOT NULL,
  position INT UNSIGNED NOT NULL,
  question_text TEXT NOT NULL,
  code_snippet TEXT NULL,
  is_mono TINYINT(1) NOT NULL DEFAULT 0,
  explanation TEXT NOT NULL,
  CONSTRAINT fk_question_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE quiz_options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id INT UNSIGNED NOT NULL,
  position INT UNSIGNED NOT NULL,
  option_text VARCHAR(255) NOT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_option_question FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE quiz_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  quiz_id INT UNSIGNED NOT NULL,
  score INT UNSIGNED NOT NULL DEFAULT 0,
  total INT UNSIGNED NOT NULL DEFAULT 0,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  CONSTRAINT fk_attempt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_attempt_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE quiz_answers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  selected_option_id INT UNSIGNED NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_answer_attempt FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_answer_question FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- progress
CREATE TABLE user_lesson_progress (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  lesson_id INT UNSIGNED NOT NULL,
  status ENUM('locked','unlocked','completed') NOT NULL DEFAULT 'locked',
  tasks_done JSON NOT NULL DEFAULT (JSON_ARRAY()),
  lesson_quiz_attempt_id INT UNSIGNED NULL,
  completed_at DATETIME NULL,
  UNIQUE KEY uq_user_lesson (user_id, lesson_id),
  CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_progress_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
  CONSTRAINT fk_progress_attempt FOREIGN KEY (lesson_quiz_attempt_id) REFERENCES quiz_attempts(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- games
CREATE TABLE games (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  title_th VARCHAR(150) NOT NULL,
  title_en VARCHAR(150) NOT NULL,
  difficulty VARCHAR(30) NOT NULL,
  description TEXT NOT NULL,
  brief TEXT NULL,
  time_limit_sec INT UNSIGNED NOT NULL DEFAULT 0,
  -- ตำแหน่งบทเรียน (lessons.position) ที่ต้องผ่านครบก่อนถึงจะเล่นเกมนี้ได้
  -- เช่น [1,2,4,8] — ดู Progress::gameGate()
  required_lessons JSON NOT NULL
) ENGINE=InnoDB;

CREATE TABLE game_scores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  game_id INT UNSIGNED NOT NULL,
  score INT UNSIGNED NOT NULL DEFAULT 0,
  time_taken_sec INT UNSIGNED NOT NULL DEFAULT 0,
  played_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_score_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_score_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE drills (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prompt_th VARCHAR(255) NOT NULL,
  hint VARCHAR(150) NOT NULL,
  accepted_answers JSON NOT NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- migrations
-- บันทึกว่า database/migrations/* ตัวไหนถูกใช้กับฐานข้อมูลนี้แล้ว (ดู src/Migrator.php)
CREATE TABLE schema_migrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  version VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- certificates
CREATE TABLE certificates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  cert_code VARCHAR(40) NOT NULL UNIQUE,
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cert_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_cert_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;
