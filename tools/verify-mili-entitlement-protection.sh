#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
required_files=(
  "app/Services/MiliEntitlementService.php"
  "config/mili.php"
  "app/Http/Middleware/MiliFeatureAccessMiddleware.php"
  "app/Http/Middleware/InstallationMiddleware.php"
  "app/Services/AddonService.php"
)
protected_files=(
  "app/CentralLogics/Helpers.php"
  "app/Services/MiliEntitlementService.php"
  "app/Services/AddonService.php"
  "app/Http/Middleware/MiliFeatureAccessMiddleware.php"
  "app/Http/Middleware/InstallationMiddleware.php"
  "app/Http/Controllers/InstallController.php"
  "app/Http/Controllers/UpdateController.php"
  "app/Http/Controllers/Admin/System/AddonController.php"
  "bootstrap/app.php"
  "routes/install.php"
  "routes/web.php"
  "routes/admin.php"
  "routes/vendor.php"
  "routes/api/v1/api.php"
)

for file in "${required_files[@]}"; do
  if [[ ! -f "$ROOT/$file" ]]; then
    echo "MISSING protected entitlement file: $file" >&2
    exit 1
  fi
done

legacy_files=(
  "app/Http/Middleware/ActivationCheckMiddleware.php"
  "app/Traits/ActivationTrait.php"
)
for file in "${legacy_files[@]}"; do
  if [[ -e "$ROOT/$file" ]]; then
    echo "Forbidden legacy activation file returned: $file" >&2
    exit 1
  fi
done

if ! grep -q "MiliEntitlementService" "$ROOT/app/Http/Middleware/MiliFeatureAccessMiddleware.php"; then
  echo "MiliFeatureAccessMiddleware is not backed by MiliEntitlementService" >&2
  exit 1
fi

if ! grep -q "'mili.feature' => MiliFeatureAccessMiddleware::class" "$ROOT/bootstrap/app.php"; then
  echo "mili.feature middleware alias is missing" >&2
  exit 1
fi

scan_roots=(app bootstrap config routes Modules)
if grep -RInE \
  --exclude-dir=.git \
  --exclude='*.map' \
  'activation\.6amtech\.com|store\.6amtech\.com/api|check\.6amtech\.com|license-check|ActivationCheckMiddleware|ActivationTrait|actch:' \
  "${scan_roots[@]/#/$ROOT/}" >/tmp/mili-entitlement-forbidden.txt 2>/dev/null; then
  cat /tmp/mili-entitlement-forbidden.txt >&2
  echo "Forbidden legacy activation reference found." >&2
  exit 1
fi

for file in "${protected_files[@]}"; do
  if [[ -f "$ROOT/$file" ]] && grep -Einq \
    'activation\.6amtech\.com|store\.6amtech\.com/api|check\.6amtech\.com|license-check|purchase[ _-]?code|envato.*valid|codecanyon.*valid' \
    "$ROOT/$file"; then
    echo "Forbidden legacy activation reference found in protected file: $file" >&2
    exit 1
  fi
done

echo "MILI entitlement protection check passed."
