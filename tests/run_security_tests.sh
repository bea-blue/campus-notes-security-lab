#!/usr/bin/env bash
# Automated security checks for the repaired Campus Notes app.
# Starts PHP's built-in server against a throwaway SQLite copy of the schema,
# runs web + API checks with two synthetic users in separate cookie jars,
# and prints PASS/FAIL per check. Never prints passwords, cookies or tokens.
#
# Usage: bash tests/run_security_tests.sh
set -u
cd "$(dirname "$0")/.."
ROOT=$(pwd)
TMP="$ROOT/tests/tmp"; rm -rf "$TMP"; mkdir -p "$TMP"
DB="$TMP/notes.sqlite"
sqlite3 "$DB" < tests/sqlite_schema.sql 2>/dev/null || php -r '$p=new PDO("sqlite:'"$DB"'");$p->exec(file_get_contents("tests/sqlite_schema.sql"));'

PORT=8765
BASE="http://127.0.0.1:$PORT"
LAB_DB_DSN="sqlite:$DB" php -S 127.0.0.1:$PORT -t public >"$TMP/server.log" 2>&1 &
SRV=$!
trap 'kill $SRV 2>/dev/null' EXIT
sleep 1

# Synthetic README credentials (public, lab-only)
ALEX_PW='LabOnly-Alex-123'; SAM_PW='LabOnly-Sam-456'
A="$TMP/alex.jar"; S="$TMP/sam.jar"; N="$TMP/anon.jar"
pass=0; fail=0
check() { # name, condition-result (0 = pass)
  if [ "$2" -eq 0 ]; then echo "PASS  $1"; pass=$((pass+1)); else echo "FAIL  $1"; fail=$((fail+1)); fi
}
token() { curl -s -b "$1" -c "$1" "$BASE/" | sed -n 's/.*name="csrf-token" content="\([0-9a-f]*\)".*/\1/p'; }
sid()   { [ -f "$1" ] && awk '$6=="campus_notes_sid"{print $7}' "$1"; }
note_count() { sqlite3 "$DB" "$1" 2>/dev/null || php -r '$p=new PDO("sqlite:'"$DB"'");echo $p->query("'"$1"'")->fetchColumn();'; }
login() { # jar email pw -> http code
  local t; t=$(token "$1")
  curl -s -o /dev/null -w '%{http_code}' -b "$1" -c "$1" -H "Origin: $BASE" \
    --data-urlencode "csrf_token=$t" --data-urlencode "action=login" \
    --data-urlencode "email=$2" --data-urlencode "password=$3" "$BASE/"
}

echo "== Login and sessions"
T=$(token "$N"); PRE=$(sid "$N")
code=$(login "$N" 'alex@example.test' 'wrong-password-123')
page=$(curl -s -b "$N" -c "$N" "$BASE/")
echo "$page" | grep -q 'Invalid email or password' && ! echo "$page" | grep -q 'My notes'; check "Invalid login is rejected with a generic message" $?

code=$(login "$N" "' OR '1'='1' -- " 'x')
curl -s -b "$N" -c "$N" "$BASE/" | grep -q 'My notes'; [ $? -ne 0 ]; check "SQL injection in login email does not log in" $?

PRE=$(sid "$A"); [ -z "$PRE" ] && token "$A" >/dev/null && PRE=$(sid "$A")
code=$(login "$A" 'alex@example.test' "$ALEX_PW")
POST=$(sid "$A")
[ "$code" = "303" ] && curl -s -b "$A" "$BASE/" | grep -q 'My notes'; check "Valid login (alex) succeeds" $?
[ -n "$PRE" ] && [ "$PRE" != "$POST" ]; check "Session ID is regenerated on login" $?
login "$S" 'sam@example.test' "$SAM_PW" >/dev/null
curl -s -b "$S" "$BASE/" | grep -q 'Sam: study plan'; check "Valid login (sam) succeeds in a separate session" $?

curl -s -D - -o /dev/null "$BASE/" | grep -i '^set-cookie' | grep -qi 'httponly' && \
curl -s -D - -o /dev/null "$BASE/" | grep -i '^set-cookie' | grep -qi 'samesite=lax'; check "Session cookie has HttpOnly and SameSite=Lax" $?

echo "== Authorization (web)"
page=$(curl -s -b "$A" "$BASE/")
echo "$page" | grep -q 'Alex: course reminder' && ! echo "$page" | grep -q 'Sam:'; check "Alex sees only Alex's notes" $?
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$A" "$BASE/?edit=3")
loc=$(curl -s -b "$A" -c "$A" "$BASE/" | grep -c 'Prepare questions\|Compare authentication')
[ "$code" = "303" ] && [ "$loc" = "0" ]; check "Alex cannot open Sam's note in the edit form (?edit=3)" $?
TA=$(token "$A")
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$A" -H "Origin: $BASE" --data "csrf_token=$TA&action=update&id=3&title=hacked&body=hacked" "$BASE/")
[ "$code" = "404" ] && [ "$(note_count "SELECT title FROM notes WHERE id=3")" = "Sam: study plan" ]; check "Alex cannot update Sam's note (404, unchanged)" $?
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$A" -H "Origin: $BASE" --data "csrf_token=$TA&action=delete&id=3" "$BASE/")
[ "$code" = "404" ] && [ "$(note_count "SELECT COUNT(*) FROM notes WHERE id=3")" = "1" ]; check "Alex cannot delete Sam's note (404, still exists)" $?
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$A" -H "Origin: $BASE" --data "csrf_token=$TA&action=save&user_id=2&title=owner-test&body=x" "$BASE/")
[ "$(note_count "SELECT user_id FROM notes WHERE title='owner-test'")" = "1" ]; check "Client-supplied user_id is ignored; note owned by session user" $?

echo "== CSRF and unsafe methods"
curl -s -o /dev/null -b "$A" "$BASE/?delete=1"
[ "$(note_count "SELECT COUNT(*) FROM notes WHERE id=1")" = "1" ]; check "GET ?delete=1 no longer deletes" $?
curl -s -o /dev/null -b "$A" "$BASE/?logout=1"
curl -s -b "$A" "$BASE/" | grep -q 'My notes'; check "GET ?logout=1 does not change state" $?
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$A" --data "action=delete&id=1" "$BASE/")
[ "$code" = "403" ] && [ "$(note_count "SELECT COUNT(*) FROM notes WHERE id=1")" = "1" ]; check "POST delete without CSRF token is rejected (403)" $?
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$A" --data "csrf_token=deadbeef&action=save&title=t&body=b" "$BASE/")
[ "$code" = "403" ]; check "POST with wrong CSRF token is rejected (403)" $?
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$A" -H "Origin: http://evil.example" --data "csrf_token=$TA&action=delete&id=1" "$BASE/")
[ "$code" = "403" ] && [ "$(note_count "SELECT COUNT(*) FROM notes WHERE id=1")" = "1" ]; check "POST from untrusted Origin is rejected even with token (403)" $?
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$A" -H "Origin: $BASE" --data "csrf_token=$TA&action=delete&id=2" "$BASE/")
[ "$code" = "303" ] && [ "$(note_count "SELECT COUNT(*) FROM notes WHERE id=2")" = "0" ]; check "Alex can delete own note with valid token (CRUD preserved)" $?

echo "== Output encoding (XSS)"
curl -s -o /dev/null -b "$A" -H "Origin: $BASE" --data-urlencode "csrf_token=$TA" --data-urlencode "action=save" \
  --data-urlencode 'title=<script>alert(1)</script>' --data-urlencode 'body=<img src=x onerror=alert(2)> "quoted"' "$BASE/"
page=$(curl -s -b "$A" "$BASE/")
echo "$page" | grep -q '&lt;script&gt;alert(1)&lt;/script&gt;' && ! echo "$page" | grep -q '<script>alert' && ! echo "$page" | grep -q '<img src=x'; check "HTML-like note text renders as text" $?
XID=$(note_count "SELECT id FROM notes WHERE title='<script>alert(1)</script>'")
curl -s -b "$A" "$BASE/?edit=$XID" | grep -q 'value="&lt;script&gt;'; check "Title is escaped inside the edit form attribute" $?
curl -s -b "$N" "$BASE/?message=%3Cscript%3Ealert(3)%3C/script%3E" | grep -q '<script>alert(3)'; [ $? -ne 0 ]; check "Reflected ?message= parameter is no longer echoed" $?
curl -s -D - -o /dev/null "$BASE/" | grep -qi "content-security-policy: default-src 'self'"; check "Content-Security-Policy header present" $?

echo "== Registration and roles"
TN=$(token "$N")
curl -s -o /dev/null -b "$N" -c "$N" -H "Origin: $BASE" --data-urlencode "csrf_token=$TN" --data-urlencode "action=register" \
  --data-urlencode "email=eve@example.test" --data-urlencode "password=Synthetic-Eve-789" --data-urlencode "role=admin" "$BASE/"
[ "$(note_count "SELECT role FROM users WHERE email='eve@example.test'")" = "student" ]; check "Registering with role=admin creates a student" $?
case "$(note_count "SELECT password_hash FROM users WHERE email='eve@example.test'")" in '$2y$'*) r=0;; *) r=1;; esac; check "New password stored as bcrypt hash (not MD5)" $r
TN=$(token "$N")
curl -s -o /dev/null -b "$N" -c "$N" -H "Origin: $BASE" --data "csrf_token=$TN&action=register&email=short@example.test&password=short" "$BASE/"
[ "$(note_count "SELECT COUNT(*) FROM users WHERE email='short@example.test'")" = "0" ]; check "Short password is rejected at registration" $?

echo "== API"
code=$(curl -s -o "$TMP/r" -w '%{http_code}' "$BASE/api.php?user_id=1")
[ "$code" = "401" ] && ! grep -q 'Alex' "$TMP/r"; check "API GET with no session -> 401, no notes" $?
code=$(curl -s -o "$TMP/r" -w '%{http_code}' -b "$A" "$BASE/api.php?user_id=2")
[ "$code" = "403" ] && ! grep -q 'Sam' "$TMP/r"; check "API GET another user's ID -> 403, no notes" $?
code=$(curl -s -o "$TMP/r" -w '%{http_code}' -b "$A" "$BASE/api.php")
[ "$code" = "200" ] && grep -q 'Alex: course reminder' "$TMP/r" && ! grep -q 'Sam' "$TMP/r"; check "API GET own notes -> 200, only own notes" $?
TA=$(token "$A")
code=$(curl -s -o /dev/null -w '%{http_code}' -H 'Content-Type: application/json' --data '{"id":3,"title":"x","body":"y"}' "$BASE/api.php")
[ "$code" = "401" ]; check "API POST with no session -> 401" $?
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$A" -H 'Content-Type: application/json' --data '{"id":1,"title":"x","body":"y"}' "$BASE/api.php")
[ "$code" = "403" ] && [ "$(note_count "SELECT title FROM notes WHERE id=1")" = "Alex: course reminder" ]; check "API POST without CSRF header -> 403, unchanged" $?
code=$(curl -s -o "$TMP/r" -w '%{http_code}' -b "$A" -H "X-CSRF-Token: $TA" -H 'Content-Type: application/json' --data '{"id":1,"title":' "$BASE/api.php")
[ "$code" = "400" ] && grep -q 'Malformed JSON' "$TMP/r" && ! grep -qi 'exception\|stack\|\.php' "$TMP/r"; check "API malformed JSON -> 400 generic error" $?
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$A" -H "X-CSRF-Token: $TA" -H 'Content-Type: application/json' --data '[1,2]' "$BASE/api.php")
[ "$code" = "400" ]; check "API JSON array instead of object -> 400" $?
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$A" -H "X-CSRF-Token: $TA" -H 'Content-Type: application/json' --data '{"id":"1 OR 1=1","title":"x","body":"y"}' "$BASE/api.php")
[ "$code" = "422" ]; check "API non-integer id -> 422" $?
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$A" -H "X-CSRF-Token: $TA" -H 'Content-Type: application/json' --data '{"id":1,"title":["x"],"body":"y"}' "$BASE/api.php")
[ "$code" = "422" ]; check "API wrong field type -> 422" $?
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$A" -H "X-CSRF-Token: $TA" -H 'Content-Type: application/json' --data '{"id":1,"title":"x","body":"y","user_id":2}' "$BASE/api.php")
[ "$code" = "422" ] && [ "$(note_count "SELECT user_id FROM notes WHERE id=1")" = "1" ]; check "API extra user_id field -> 422, owner unchanged" $?
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$A" -H "X-CSRF-Token: $TA" -H 'Content-Type: text/plain' --data '{"id":1,"title":"x","body":"y"}' "$BASE/api.php")
[ "$code" = "415" ]; check "API text/plain body -> 415" $?
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$A" -H "X-CSRF-Token: $TA" -H 'Content-Type: application/json' --data '{"id":3,"title":"hacked","body":"hacked"}' "$BASE/api.php")
[ "$code" = "404" ] && [ "$(note_count "SELECT title FROM notes WHERE id=3")" = "Sam: study plan" ]; check "API update of another user's note -> 404, unchanged" $?
hdr=$(curl -s -D - -o /dev/null -b "$A" -H "Origin: http://evil.example" -H "X-CSRF-Token: $TA" -H 'Content-Type: application/json' --data '{"id":1,"title":"evil","body":"evil"}' "$BASE/api.php")
echo "$hdr" | grep -q ' 403' && ! echo "$hdr" | grep -qi 'access-control-allow' && [ "$(note_count "SELECT title FROM notes WHERE id=1")" = "Alex: course reminder" ]; check "API POST from untrusted Origin -> 403, no CORS headers, unchanged" $?
hdr=$(curl -s -D - -o /dev/null -b "$A" -H "Origin: http://evil.example" "$BASE/api.php")
echo "$hdr" | grep -q ' 403' && ! echo "$hdr" | grep -qi 'access-control-allow'; check "API GET from untrusted Origin -> 403, no CORS headers" $?
hdr=$(curl -s -D - -o /dev/null -X OPTIONS -H "Origin: http://evil.example" -H "Access-Control-Request-Method: POST" "$BASE/api.php")
! echo "$hdr" | grep -qi 'access-control-allow'; check "CORS preflight from untrusted Origin is not approved" $?
code=$(curl -s -o "$TMP/r" -w '%{http_code}' -b "$A" -H "Origin: $BASE" -H "X-CSRF-Token: $TA" -H 'Content-Type: application/json' --data '{"id":1,"title":"API edit","body":"ok"}' "$BASE/api.php")
[ "$code" = "200" ] && [ "$(note_count "SELECT title FROM notes WHERE id=1")" = "API edit" ]; check "API valid update of own note -> 200 (feature preserved)" $?

echo "== Logout"
OLD=$(sid "$A"); TA=$(token "$A")
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$A" -c "$A" -H "Origin: $BASE" --data "csrf_token=$TA&action=logout" "$BASE/")
curl -s -b "$A" "$BASE/" | grep -q 'My notes'; r1=$?
code2=$(curl -s -o /dev/null -w '%{http_code}' -H "Cookie: campus_notes_sid=$OLD" "$BASE/api.php")
[ "$r1" -ne 0 ] && [ "$code2" = "401" ]; check "Logout ends access; replaying old session cookie -> 401" $?
curl -s -b "$S" "$BASE/" | grep -q 'Sam: study plan'; check "Sam's separate session is unaffected by Alex's logout" $?

echo "== Configuration and errors"
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/schema.sql"); c2=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/src/database.php")
[ "$code" = "404" ] && [ "$c2" = "404" ]; check "schema.sql and src/ are not web-accessible (public/ docroot)" $?
kill $SRV 2>/dev/null; wait $SRV 2>/dev/null
env -u LAB_DB_USER -u LAB_DB_PASSWORD -u LAB_DB_DSN php -S 127.0.0.1:$PORT -t public >"$TMP/server2.log" 2>&1 &
SRV=$!; sleep 1
body=$(curl -s -w '\n%{http_code}' "$BASE/")
echo "$body" | tail -1 | grep -q 500 && ! echo "$body" | grep -qi 'PDO\|SQLSTATE\|credentials\|root\|\.php\|Stack'; check "Missing DB credentials -> generic 500, no details, no root fallback" $?
body=$(curl -s -b "$S" "$BASE/api.php")  # signed-in session, DB unavailable
echo "$body" | grep -q '"Internal server error"' && ! echo "$body" | grep -qi 'PDO\|SQLSTATE'; check "API DB failure -> generic JSON error" $?
grep -q 'campus-notes' "$TMP/server2.log" && ! grep -qi 'password' "$TMP/server2.log"; check "Failure logged server-side with reference, no secrets" $?

echo
echo "Result: $pass passed, $fail failed"
[ "$fail" -eq 0 ]
