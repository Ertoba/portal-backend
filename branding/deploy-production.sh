#!/usr/bin/env bash
# Surgical MILI branding deployment: no full backend merge and no payment file changes.
set -Eeuo pipefail
if [[ "$(id -u)" != "0" ]]; then echo 'STOP: run as VPS root'; exit 1; fi
cd /var/www/mili
if [[ ! -d .git ]]; then echo 'STOP: missing production backend Git repo'; exit 1; fi
umask 022
git fetch origin main
BACKUP="/root/mili-brand-deploy-$(date +%Y%m%d-%H%M%S)"
paths=(
  public/assets/admin/img/mili-mark-20261013.svg
  public/assets/admin/img/6m-logo.png
  public/assets/admin/img/favicon.png
  public/assets/admin/img/invoice-logo.png
  public/assets/admin/img/invoice/logo.png
  public/favicon.ico
  public/assets/landing/img/logo.svg
  public/assets/landing/img/footer-logo.svg
  public/assets/landing/img/favicon.svg
  public/assets/landing/img/banner/logo.svg
  public/assets/installation/assets/img/logo.svg
  public/assets/installation/assets/img/favicon.svg
  resources/views/layouts/admin/app.blade.php
  resources/views/layouts/admin/partials/_header_v2.blade.php
  resources/views/layouts/admin/partials/_sidebar.blade.php
  resources/views/auth/login.blade.php
  resources/views/auth/reset-password.blade.php
  app/Console/Commands/ApplyMiliBranding.php
)
for path in "${paths[@]}"; do
  git cat-file -e "origin/main:${path}" || { echo "STOP: missing upstream ${path}"; exit 1; }
done
mkdir -p "$BACKUP"
printf 'backup=%s\n' "$BACKUP"
for path in "${paths[@]}"; do
  if [[ -f "$path" ]]; then
    mkdir -p "$BACKUP/$(dirname "$path")"
    cp -a "$path" "$BACKUP/$path"
  fi
  mkdir -p "$(dirname "$path")"
  tmp="$(mktemp "${path}.brand-XXXXXX")"
  if ! git show "origin/main:$path" > "$tmp"; then rm -f "$tmp"; exit 1; fi
  chmod 0644 "$tmp"
  mv -f "$tmp" "$path"
  echo "UPDATED $path"
done
php -l app/Console/Commands/ApplyMiliBranding.php
php artisan view:clear
systemctl reload php8.2-fpm
php artisan mili:apply-branding
printf '\nBRANDING_FILES_DEPLOYED=OK\nDB_BRAND_SETTINGS=NOT_CHANGED_DRY_RUN\nBACKUP=%s\n' "$BACKUP"
echo 'When verified, update dynamic API logo/icon with: php artisan mili:apply-branding --apply'
