# MILI License Protection

The production entitlement implementation is private and must survive every
6amMart update. The following files are protected merge boundaries:

- `app/Services/MiliEntitlementService.php`
- `config/mili.php`
- `app/Http/Middleware/ActivationCheckMiddleware.php`
- `app/CentralLogics/Helpers.php`
- `app/Services/AddonService.php`
- installer/update middleware and controllers
- admin addon activation controllers/views
- `routes/install.php` and `routes/web.php`

Before a backend or Admin merge, run:

```bash
bash tools/verify-mili-entitlement-protection.sh
```

The merge must fail if protected files contain legacy external activation
hosts, `license-check`, or purchase-code checks. No production rebuild or
cache clear should run until this guard passes and the protected-file diff has
been reviewed.
