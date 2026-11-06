-- SQLite equivalent of ../schema.sql, used only by tests/run_security_tests.sh
-- because the grading environment had no MySQL server. Same data and hashes.
CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email VARCHAR(254) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'student'
);
CREATE TABLE notes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  title VARCHAR(120) NOT NULL,
  body TEXT NOT NULL
);
INSERT INTO users (id, email, password_hash, role) VALUES
  (1, 'alex@example.test', '$2y$10$QIS4NhEXkYpAPRhfVxGk6.HNIuwaWvM.mXM7YXDWlOKM5/UjEmJTe', 'student'),
  (2, 'sam@example.test', '$2y$10$PnDfr8dc1HW64VJKDdwyTuhdI.ez40EVbEXCbExZX/mErfP4XGFx6', 'student');
INSERT INTO notes (user_id, title, body) VALUES
  (1, 'Alex: course reminder', 'Review the security reading before class.'),
  (1, 'Alex: project idea', 'Build a small student directory.'),
  (2, 'Sam: study plan', 'Compare authentication and authorization.'),
  (2, 'Sam: meeting', 'Prepare questions for the next group discussion.');
