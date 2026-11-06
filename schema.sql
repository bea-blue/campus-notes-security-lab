CREATE DATABASE IF NOT EXISTS notes_lab CHARACTER SET utf8mb4;
USE notes_lab;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(254) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'student'
);

CREATE TABLE notes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  body TEXT NOT NULL,
  INDEX idx_notes_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Synthetic accounts for local use only (passwords are listed in README.md).
-- Hashes were generated with PHP password_hash(..., PASSWORD_DEFAULT) (bcrypt);
-- the app verifies them with password_verify(). MD5 is no longer used.
INSERT INTO users (id, email, password_hash, role) VALUES
  (1, 'alex@example.test', '$2y$10$QIS4NhEXkYpAPRhfVxGk6.HNIuwaWvM.mXM7YXDWlOKM5/UjEmJTe', 'student'),
  (2, 'sam@example.test', '$2y$10$PnDfr8dc1HW64VJKDdwyTuhdI.ez40EVbEXCbExZX/mErfP4XGFx6', 'student');

INSERT INTO notes (user_id, title, body) VALUES
  (1, 'Alex: course reminder', 'Review the security reading before class.'),
  (1, 'Alex: project idea', 'Build a small student directory.'),
  (2, 'Sam: study plan', 'Compare authentication and authorization.'),
  (2, 'Sam: meeting', 'Prepare questions for the next group discussion.');
