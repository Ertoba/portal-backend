# MILI read-only production preflight

Run on the intended versioned release before deployment (from an authorized server shell):

```bash
sudo bash /var/www/mili/scripts/mili-production-preflight.sh /var/www/mili
```

A nonzero exit code blocks deployment. A zero exit code only proves local static checks; it does not certify real orders, payment refunds, FCM, Apple Push, routes billing, signed AAB/IPA, migrations or rollback.

The script does not print credentials, source `.env` into the shell, execute migrations, restart workers, alter permissions, rebuild caches, make paid API calls or write application files.

After static preflight: back up database/uploads; verify exact deployed Git SHA and rollback; check Google Routes key restrictions, quota/billing and real route prices in staging; verify Keepz sandbox callbacks/retries/splits/refunds/reconciliation; align Firebase project and app registrations; test real-device foreground/background notifications; review pending migrations; complete web+3 Flutter E2E. Obtain explicit production deployment approval after these checks.

Do not copy service-account, signing, API or payment secrets into GitHub or issue comments.
