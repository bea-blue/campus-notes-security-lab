# Campus Notes: repaired version

This started as intentionally vulnerable educational material for the IPT security code review lab. This version fixes the eleven listed issues. It is still a lab app: run it only on a machine you control, with synthetic data and a throwaway local database. Do not deploy it.

## Requirements

- PHP 8.1 or newer (tested with PHP 8.3.6) with `pdo_mysql`. Check with `php -m`.
- MySQL 8 / MariaDB 10.6+ for normal use.
- No Composer packages are used.

## Local setup

1. As a local MySQL administrator, import `schema.sql` into a fresh server or database. It creates `notes_lab`, its tables and two synthetic accounts with **bcrypt** password hashes.
   - If you already imported the starter schema (MD5 hashes), run `reset_passwords.sql` instead so the synthetic accounts work with `password_verify()`.
2. Create a dedicated least-privilege account, for example:
   ```sql
   CREATE USER 'notes_app'@'localhost' IDENTIFIED BY '<choose-a-local-password>';
   GRANT SELECT, INSERT, UPDATE, DELETE ON notes_lab.* TO 'notes_app'@'localhost';
   ```
3. Set the credentials in your shell (never commit them). The app refuses to start without them; there is no `root` fallback.
   - PowerShell: `$env:LAB_DB_USER = 'notes_app'; $env:LAB_DB_PASSWORD = '...'`
   - bash: `export LAB_DB_USER=notes_app LAB_DB_PASSWORD=...`
   - Optional: `LAB_DB_HOST` (default `127.0.0.1`), `LAB_DB_NAME` (default `notes_lab`), `LAB_ALLOWED_ORIGINS` (comma-separated, default is the site's own origin).
4. From this folder run `php -S 127.0.0.1:8000 -t public` and visit `http://127.0.0.1:8000`. **Note the `-t public`**: only `public/` is web-accessible, so `schema.sql`, `src/` and `.git` cannot be downloaded.
5. Synthetic accounts (public, lab-only, never reuse):
   - `alex@example.test` / `LabOnly-Alex-123`
   - `sam@example.test` / `LabOnly-Sam-456`

   Use two separate browser profiles to test the two users.

## File layout

```
public/            web root (only folder the server exposes)
  index.php        web controller: routes POST actions, renders the page
  api.php          JSON API (GET list own notes, POST update own note)
  style.css        styles (moved out of the page so CSP can block inline code)
src/               application code, not web-accessible
  bootstrap.php    loads everything, sets baseline security headers
  config.php       environment-based configuration, allowed origins
  errors.php       generic error responses + sanitized logging
  database.php     single PDO connection from environment variables
  session.php      hardened session start, idle timeout, full destroy
  csrf.php         CSRF token + Origin/Referer check
  auth.php         register, login (password_hash/verify), logout
  NoteRepository.php  all note SQL, always scoped to the session owner
  view.php         e() escaping, render(), flash messages, redirect
templates/page.php HTML template (every value escaped with e())
schema.sql         MySQL schema + synthetic data (bcrypt hashes)
reset_passwords.sql converts an existing starter DB from MD5 to bcrypt
tests/             automated security checks (see below)
```

## API

All requests need a signed-in session cookie from the same origin.

- `GET /api.php` returns `{"notes": [...]}` for the signed-in user only. `?user_id=` is accepted only if it equals your own ID (otherwise 403).
- `POST /api.php` with `Content-Type: application/json`, header `X-CSRF-Token: <token>` and body `{"id": 1, "title": "...", "body": "..."}` updates one of **your** notes. The token is in the page's `<meta name="csrf-token">`. Errors: 400 malformed JSON, 401 not signed in, 403 bad token/origin, 404 not found or not yours, 415 wrong content type, 422 invalid fields.
- No CORS headers are sent; other origins are refused.

## Tests

`bash tests/run_security_tests.sh` starts PHP's built-in server against a temporary **SQLite** copy of the schema (`tests/sqlite_schema.sql`, via `LAB_DB_DSN`) and runs 46 web and API checks with two users. SQLite was used because no MySQL server was available where the tests were written; re-run the manual checks against MySQL before relying on them.

## Production notes (not covered by this lab)

HTTPS with `Secure` cookies (the app sets `Secure` automatically when served over HTTPS), HSTS, a real web server instead of `php -S`, secrets from a secret manager, login rate limiting/lockout, log rotation and monitoring.
