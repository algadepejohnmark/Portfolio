-- ============================================================
-- Portfolio Database - JM Algadepe
-- Import: phpMyAdmin > select your database > Import > this file
-- Charset: utf8mb4 (supports all characters incl. emoji)
-- NOTE: No admin account is included on purpose. Create it with
--       your own setup_admin.php using password_hash().
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS admins, profile, settings, skills, projects,
  education, experience, achievements, interests, messages;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Administrator accounts (passwords stored ONLY as hashes)
-- failed_attempts + locked_until = brute-force protection
-- ------------------------------------------------------------
CREATE TABLE admins (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username        VARCHAR(50)  NOT NULL UNIQUE,
  email           VARCHAR(255) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until    DATETIME NULL,
  last_login      DATETIME NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Personal information (single row, id = 1)
-- ------------------------------------------------------------
CREATE TABLE profile (
  id            TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  full_name     VARCHAR(100) NOT NULL,
  short_name    VARCHAR(50)  NOT NULL,
  typing_words  VARCHAR(255) NULL COMMENT 'Comma-separated words for the typing effect',
  intro         TEXT NULL,
  background    TEXT NULL,
  goals         TEXT NULL,
  email         VARCHAR(255) NULL,
  phone         VARCHAR(30)  NULL,
  location      VARCHAR(100) NULL,
  github_url    VARCHAR(255) NULL,
  linkedin_url  VARCHAR(255) NULL,
  profile_image VARCHAR(255) NULL,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Website settings (single row, id = 1)
-- ------------------------------------------------------------
CREATE TABLE settings (
  id            TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  site_title    VARCHAR(100) NOT NULL,
  logo_text     VARCHAR(50)  NOT NULL,
  logo_image    VARCHAR(255) NULL,
  default_theme ENUM('light','dark') NOT NULL DEFAULT 'light',
  accent_color  VARCHAR(7)   NOT NULL DEFAULT '#2563eb',
  footer_text   VARCHAR(255) NULL,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Skills (category matches the tabs on skills page)
-- ------------------------------------------------------------
CREATE TABLE skills (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  category    ENUM('frontend','programming','workflow') NOT NULL,
  level       TINYINT UNSIGNED NOT NULL DEFAULT 50 CHECK (level <= 100),
  sort_order  INT NOT NULL DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Projects (category matches the filter buttons)
-- ------------------------------------------------------------
CREATE TABLE projects (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  tech_stack  VARCHAR(255) NULL,
  category    ENUM('web','python','data') NOT NULL,
  image       VARCHAR(255) NULL COMMENT 'Filename only, file lives in uploads/',
  project_url VARCHAR(255) NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Education (end_year NULL = "Present")
-- ------------------------------------------------------------
CREATE TABLE education (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  degree     VARCHAR(150) NULL,
  school     VARCHAR(150) NOT NULL,
  start_year YEAR NOT NULL,
  end_year   YEAR NULL,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Experience & activities
-- ------------------------------------------------------------
CREATE TABLE experience (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role         VARCHAR(150) NOT NULL,
  organization VARCHAR(150) NULL,
  description  TEXT NULL,
  start_year   YEAR NOT NULL,
  end_year     YEAR NULL,
  sort_order   INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Achievements, trainings, certificates
-- ------------------------------------------------------------
CREATE TABLE achievements (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(150) NOT NULL,
  issuer      VARCHAR(150) NULL,
  year        YEAR NOT NULL,
  description TEXT NULL,
  image       VARCHAR(255) NULL,
  sort_order  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Interests (tag list on About page)
-- ------------------------------------------------------------
CREATE TABLE interests (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(50) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Contact form messages
-- ip_address is used for spam / rate limiting
-- ------------------------------------------------------------
CREATE TABLE messages (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  email      VARCHAR(255) NOT NULL,
  subject    VARCHAR(150) NOT NULL,
  message    TEXT NOT NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_is_read (is_read),
  INDEX idx_ip_created (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- SAMPLE DATA (taken from your current midterm site)
-- ============================================================

INSERT INTO profile (id, full_name, short_name, typing_words, intro, background, goals,
  email, github_url, linkedin_url, profile_image) VALUES
(1, 'John Mark Algadepe', 'JM Algadepe',
 'Web Developer,IT Student,Problem Solver',
 'Web developer who likes building simple and useful websites. I’m still learning, improving my skills, and gaining experience by working on different projects.',
 'I got interested in technology through school projects. I enjoy learning new things and creating simple websites.',
 'I want to improve my skills, gain more experience, and build useful projects.',
 'youremail@example.com', 'https://github.com/', 'https://www.linkedin.com/', 'profile.jpg');

INSERT INTO settings (id, site_title, logo_text, default_theme, footer_text) VALUES
(1, 'John Mark Algadepe | Portfolio', 'JM Algadepe', 'light', 'JM Algadepe. All rights reserved.');

INSERT INTO skills (name, description, category, level, sort_order) VALUES
('HTML5', 'Semantic page structure', 'frontend', 90, 1),
('CSS3', 'Flexbox, Grid, animations', 'frontend', 85, 2),
('JavaScript', 'DOM and interactivity', 'frontend', 75, 3),
('Responsive Design', 'Mobile-first layouts', 'frontend', 85, 4),
('Python', 'Scripts and data tasks', 'programming', 80, 5),
('Data Analysis', 'Survey and statistics work', 'programming', 70, 6),
('Git & GitHub', 'Version control and hosting', 'workflow', 70, 7),
('Problem Solving', 'Debugging and planning', 'workflow', 80, 8);

INSERT INTO projects (title, description, tech_stack, category, image, sort_order) VALUES
('Personal Budget Tracker', 'A simple web app for logging income and expenses with a running balance.', 'HTML, CSS, JavaScript', 'web', 'project1.jpg', 1),
('Survey Data Dashboard', 'Charts and summary tables built from survey results for a class research paper.', 'Python, Pandas, Matplotlib', 'data', 'project2.jpg', 2),
('Student Records System', 'A command-line program that adds, searches, and updates student records.', 'Python, SQLite', 'python', 'project3.jpg', 3),
('Event Landing Page', 'A responsive landing page for a school event with a schedule and sign-up form.', 'HTML, CSS, Flexbox', 'web', 'project4.jpg', 4);

INSERT INTO education (degree, school, start_year, end_year, sort_order) VALUES
('BS in Information Technology', 'Negros Oriental State University', 2026, NULL, 1),
(NULL, 'Santa Catalina National High School', 2017, 2024, 2);

INSERT INTO experience (role, organization, start_year, end_year, sort_order) VALUES
('Member', 'Student Organization', 2024, 2024, 1),
('Volunteer', 'Community Activity', 2024, 2024, 2);

INSERT INTO achievements (title, year, sort_order) VALUES
('Web Development Training', 2025, 1),
('Python Basics Training', 2025, 2);

INSERT INTO interests (name, sort_order) VALUES
('Web Development', 1), ('Data Analysis', 2), ('Reading', 3), ('Photography', 4), ('Music', 5);

INSERT INTO messages (name, email, subject, message, is_read) VALUES
('Sample Visitor', 'visitor@example.com', 'Hello', 'This is a sample message for testing the inbox.', 0);
