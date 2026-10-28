# MILI workstation production-management baseline

The only active workstation root is selected by the owner and must contain all
repositories, build worktrees, caches, tools, reports, context, and artifacts.
Do not create project clones or build directories elsewhere on the Desktop.

The current standard layout is:

- repos/ — Backend, Customer, Vendor, Delivery, and React repositories;
- build-workspace/ — detached build sources and every project-specific cache;
- artifacts/ — final APK/AAB/web/release artifacts and checksums;
- tools/ — pinned Flutter and portable PHP/Composer;
- ops/ — machine-local connection metadata, runbooks, and protected secrets;
- context/ and reports/ — historical evidence and dated verification reports.

On Windows, a non-ASCII workspace path can break Dart analysis-server LSP
framing. The generic scripts/workstation/Enter-MiliEnvironment.ps1 mounts an
ephemeral ASCII SUBST alias to the same directory and routes Pub, Gradle, Yarn,
npm, Composer, Playwright, and temporary caches under build-workspace/.

Example:

    . .\repos\backend-admin\scripts\workstation\Enter-MiliEnvironment.ps1 -WorkspaceRoot 'C:\Users\miro\Desktop\მილი'
    .\repos\backend-admin\scripts\workstation\Get-MiliRepoStatus.ps1 -Fetch

Production SSH keys, known-hosts, IP/user overrides, provider credentials, .env
files, official private-license archives, and generated reports must remain
machine-local and must never be committed to an application repository.

Official V4.2 code is merged semantically from changed-file packages.
Activation, license, purchase-code, installer, and updater-gate sources are
prohibited. Final source changes require component tests, a clean main, and an
exact remote SHA verification. A GitHub push is never proof of production
deployment.

The secret-free scripts/mili-production-preflight.sh is intended to be piped to
the owner-authorized server over pinned-host-key SSH. It accepts APP_ENV=live or
production, understands conventional KEY=VALUE and KEY = VALUE .env formatting,
hides all values, rejects world-readable .env permissions, and performs no
deployment or migration.
