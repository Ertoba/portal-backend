#!/usr/bin/env bash
# MILI preflight is read-only. Never source .env, print secrets, deploy, or migrate.
set -u
APP_ROOT="/var/www/mili"
if [[ $# -gt 0 ]]; then APP_ROOT="$1"; fi
WEB_USER="www-data"
if [[ -n "$MILI_WEB_USER" ]]; then WEB_USER="$MILI_WEB_USER"; fi
ERRORS=0
WARNINGS=0
pass() { printf 'PASS: %s\n' "$1"; }
fail() { printf 'BLOCKED: %s\n' "$1" >&2; ERRORS=$((ERRORS + 1)); }
warn() { printf 'REVIEW: %s\n' "$1"; WARNINGS=$((WARNINGS + 1)); }

if [[ ! -d "$APP_ROOT" ]]; then fail "Laravel release directory absent"; exit 1; fi
APP_ROOT="$(cd "$APP_ROOT" && pwd -P)"
ENV_FILE="$APP_ROOT/.env"

# Only exact un-commented KEY=VALUE entries; secrets stay inside command substitution.
read_setting() {
  [[ -r "$ENV_FILE" ]] || return 1
  awk -v k="$1" '
    index($0, k "=") == 1 {
      v = substr($0, length(k) + 2)
      sub(/\r$/, "", v)
      gsub(/^[[:space:]]+|[[:space:]]+$/, "", v)
      if (v ~ /^".*"$/ || v ~ /^\047.*\047$/) v = substr(v, 2, length(v)-2)
      print v; exit
    }
  ' "$ENV_FILE"
}
required_setting() {
  local v
  v="$(read_setting "$1")"
  if [[ -n "$v" && "$v" != "null" ]]; then
    pass "$1 configured (value hidden)"
  else
    fail "$1 missing or blank"
  fi
}

if [[ -f "$APP_ROOT/artisan" ]]; then pass "Laravel entry point exists"; else fail "Laravel entry point missing"; fi
if [[ -f "$ENV_FILE" && -r "$ENV_FILE" ]]; then pass ".env readable (contents hidden)"; else fail ".env missing/unreadable"; fi
required_setting APP_KEY
required_setting DB_CONNECTION
required_setting DB_DATABASE
required_setting GOOGLE_ROUTES_API_KEY

if [[ "$(read_setting APP_ENV)" == "production" ]]; then pass "APP_ENV=production"; else fail "APP_ENV must be production"; fi
case "$(read_setting APP_DEBUG)" in false|0) pass "APP_DEBUG disabled";; *) fail "APP_DEBUG must be false or 0";; esac
case "$(read_setting APP_URL)" in https://*) pass "APP_URL uses HTTPS";; *) fail "APP_URL must use HTTPS";; esac
if command -v php >/dev/null 2>&1; then pass "PHP CLI available"; else fail "PHP CLI missing"; fi

for relative in storage/framework/views storage/framework/sessions storage/framework/cache storage/logs bootstrap/cache; do
  path="$APP_ROOT/$relative"
  if [[ ! -d "$path" ]]; then fail "$relative missing"; continue; fi
  if [[ "$(id -un)" == "$WEB_USER" ]]; then
    if [[ -w "$path" ]]; then pass "$relative writable by $WEB_USER"; else fail "$relative not writable by $WEB_USER"; fi
  elif [[ "$(id -u)" -eq 0 ]] && command -v runuser >/dev/null 2>&1; then
    if runuser -u "$WEB_USER" -- test -w "$path" >/dev/null 2>&1; then
      pass "$relative writable by $WEB_USER"
    else
      fail "$relative not writable by $WEB_USER"
    fi
  else
    fail "Cannot verify $relative as $WEB_USER; run as root or web user"
  fi
done

if [[ -f "$APP_ROOT/bootstrap/cache/config.php" ]]; then
  pass "Laravel config cache exists"
  if [[ -f "$ENV_FILE" && "$ENV_FILE" -nt "$APP_ROOT/bootstrap/cache/config.php" ]]; then
    warn ".env newer than Laravel config cache; verify effective config"
  fi
else
  warn "Laravel config cache absent; verify intended deployment caching"
fi

printf 'RESULT: %d blocker(s), %d review item(s)\n' "$ERRORS" "$WARNINGS"
printf '%s\n' 'NOT CERTIFIED: Routes API validity/billing, Keepz/refunds, FCM/APNs, signed devices, live E2E, migrations, active release SHA and rollback still require separate checks.'
if (( ERRORS > 0 )); then exit 1; fi
exit 0
