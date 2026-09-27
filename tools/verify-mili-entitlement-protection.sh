#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
required_files=(
  "app/Services/MiliEntitlementService.php"
  "config/mili.php"
  "app/Http/Middleware/ActivationCheckMiddleware.php"
)
protected_files=(
  "app/CentralLogics/Helpers.php"
  "app/Services/MiliEntitlementService.php"
  "app/Services/AddonService.php"
  "app/Http/Middleware/ActivationCheckMiddleware.php"
  "app/Http/Middleware/InstallationMiddleware.php"
  "app/Http/Controllers/InstallController.php"
  "app/Http/Controllers/UpdateController.php"
  "app/Http/Controllers/Admin/AddonActivationController.php"
  "app/Http/Controllers/Admin/System/AddonController.php"
  "routes/install.php"
  "routes/web.php"
)

for file in "${required_files[@]}"; do
  if [[ ! -f "$ROOT/$file" ]]; then
    echo "MISSING protected entitlement file: $file" >&2
    exit 1
  fi
done

if ! grep -q "MiliEntitlementService" "$ROOT/app/Http/Middleware/ActivationCheckMiddleware.php"; then
  echo "ActivationCheckMiddleware is not backed by MiliEntitlementService" >&2
  exit 1
fi

for file in "${protected_files[@]}"; do
  if [[ -f "$ROOT/$file" ]] && grep -Einq 'store\.6amtech\.com|check\.6amtech\.com|license-check|purchase[ _-]?code' "$ROOT/$file"; then
    echo "Forbidden legacy activation reference found in protected file: $file" >&2
    exit 1
  fi
done

echo "MILI entitlement protection check passed."
