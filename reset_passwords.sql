-- Run this instead of re-importing schema.sql if you already imported the
-- starter database. It replaces the old MD5 hashes with bcrypt hashes of the
-- same synthetic README passwords.
USE notes_lab;
UPDATE users SET password_hash = '$2y$10$QIS4NhEXkYpAPRhfVxGk6.HNIuwaWvM.mXM7YXDWlOKM5/UjEmJTe' WHERE email = 'alex@example.test';
UPDATE users SET password_hash = '$2y$10$PnDfr8dc1HW64VJKDdwyTuhdI.ez40EVbEXCbExZX/mErfP4XGFx6' WHERE email = 'sam@example.test';
