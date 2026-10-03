# MILI License Protection

The current MILI entitlement implementation is the authoritative licensing boundary and must survive every upstream 6amMart update.

Protected local files include:

- `app/Services/MiliEntitlementService.php`
- `config/mili.php`
- `app/Http/Middleware/MiliFeatureAccessMiddleware.php`
- `app/Http/Middleware/InstallationMiddleware.php`
- `app/Services/AddonService.php`
- installer/update controllers and routes
- admin add-on management controllers/views
- MILI feature-gated admin, vendor and API routes

The legacy external activation stack must remain absent. In particular, do not restore `ActivationCheckMiddleware`, `ActivationTrait`, remote 6amTech activation/license validation, purchase-code requirements, add-on external activation, or `actch:*` runtime middleware.

Before every backend merge/deploy, run:

```bash
bash tools/verify-mili-entitlement-protection.sh
```

A merge must fail if the legacy activation stack reappears. Fresh install, rebuild, deploy, cache clear and restart must continue without any external activation dependency.
