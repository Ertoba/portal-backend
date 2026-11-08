# MILI Android production and debug builds

All local paths below are relative to the sole MILI workspace. Do not recreate
the obsolete Desktop/build or Desktop/6ammart directories. M: is only the ASCII
alias of that workspace, not another copy.

## Strict directory contract

| Purpose | Exclusive location |
|---|---|
| Production source exports, Flutter/Gradle outputs, logs | `build-workspace/production/android/<run>/<app>/` |
| Production final signed AABs and verification manifest | `artifacts/production/android/<run>/` |
| Debug source exports, Flutter/Gradle outputs, logs | `build-workspace/debug/android/<run>/<app>/` |
| Debug final APKs and verification manifest | `artifacts/debug/android/<run>/` |
| Shared dependency/tool caches | `build-workspace/cache/` |
| Private upload keystores and generated signing properties | `ops/secrets/android-signing/<app>/<key-run>/` |
| Public upload certificates and non-secret signing metadata | `ops/config/android-signing/<key-run>/` |

`<app>` is customer, vendor or delivery. Each run is immutable: use a fresh run
identifier, never overwrite another run. Historical 2026-11-01 debug APKs in
`artifacts/debug/` remain evidence; do not move or delete them automatically.
The older empty `artifacts/release/` directories are not upload sources.

The canonical [staging helper](../scripts/workstation/Stage-MiliAndroidBuild.ps1)
requires a clean application `main` equal to stored and live remote `main`.
It exports the exact SHA without Git metadata, private key containers, signing
properties, environment files or credentials. Build only this sanitized export;
do not compile production in an application checkout's old debug build tree.
The canonical [debug runner](../scripts/workstation/Build-MiliDebugApps.ps1)
uses the same staging contract. Thin local entry points are under `ops/scripts`.
Do not change global Flutter build-directory settings to switch modes.

Release signing temporarily attaches the canonical protected `key.properties`
through an NTFS hard link in the sanitized export's Android directory. It keeps
the private file ACL, creates no separate plaintext copy, is absent from the
source ZIP, and is removed in the runner's `finally` cleanup. Never archive that
temporary build directory as a distributable; only verified AABs/manifests/symbols
belong in final artifact directories.

## Upload signing is not Play App Signing

Google Play holds the app signing key used for installed store applications.
The local upload key signs the AAB submitted to Play. Reset only the upload key
when its private keystore is lost; do not use App signing > Upgrade key for that
purpose. A public PEM/DER certificate or its fingerprint cannot reconstruct the
private keystore.

On 2026-11-07 the owner explicitly instructed moving to new upload keys instead
of searching for previous private keys. Keep new keys restricted to the Windows
owner and SYSTEM, outside Git and all final build artifacts. Generate a distinct
upload key per application; never fall back to the Android debug certificate or
the historical tracked template JKS. Do not print passwords or private material.
Keep a separate owner-controlled secure backup; do not copy one outside MILI
without the owner's explicit destination/instructions.

Public certificates supplied while inspecting Vendor's Console page establish:

- Play app signing SHA-256: `44:68:4F:3C:B9:97:8F:DD:12:3F:64:E9:C7:24:97:DE:C2:D5:A9:95:60:5B:3B:47:D3:98:4E:25:0F:39:9D:D6`.
- Pre-reset upload SHA-256: `25:62:DC:21:59:E3:E9:C9:E1:6A:FC:37:A8:98:F7:73:3E:3B:0A:E8:ED:99:27:0A:54:58:30:5F:7D:9E:49:E5`.

Both DER files contain public certificates only. The Vendor app-signing value
matches the owner's Digital Asset Links JSON. An upload-key reset does not change
that Play app-signing fingerprint or require replacing it with the upload key in
Digital Asset Links / API provider configuration.

## Install verified production payloads on an ADB device

An AAB is not directly installable with `adb install`. Use the pinned official
bundletool to generate a device-specific APK set from each verified production
AAB; do not recompile Flutter merely to install an already-built payload.
The [device installer](../scripts/workstation/Install-MiliAabPayloadOnDevice.ps1)
requires exactly one explicit mode: `-UpdateExisting` preserves existing data
after checking the installed certificate and a strictly greater version code;
`-UninstallExisting` is only for owner-authorized local data deletion. Supply
`-ProductionRunId`, selected `-Components` and their `-VersionCodes` for a newer
bundle run. Update mode never uninstalls and checks preserved first-install times.
Keep the original AAB bytes and hashes unchanged. Device conversion work/logs
belong under `build-workspace/production/android/<device-run>/device-apks/`;
final APK sets and the installation manifest belong under
`artifacts/production/android/<device-run>/device-apks/`. These are release
payloads, not debug APKs, even when installed on the test device.

Use the selected new per-app upload keystore explicitly. A locally upload-key
signed APK is **not** Google Play's app-signing identity and does not establish
Play acceptance, App Links/API-provider acceptance or financial-flow QA.
Do not replace Play app-signing fingerprints in provider configuration.
Play upload-key activation is not a prerequisite for this local ADB installation.

Prepare and verify all three APK sets before removing any installed application:
source AAB hashes, package/version, release status and every APK's expected new
certificate must match. Different signing identities cannot update the existing
installation in place. Uninstall only the three explicit MILI package IDs after
the owner authorizes local data loss; do not use keep-data or downgrade options.
Uninstall removes local sessions/preferences, not server accounts or orders.

Read signing passwords only in memory. Bundletool password files must be created
under an owner-and-SYSTEM-only private directory beneath `ops/secrets/` and removed
in `finally`; never print passwords or embed them in native command arguments.
Record the exact device serial, authorization, versions, certificate fingerprints,
installation times, generated/installed APK hashes and cold-launch results.
Keep historical debug installation evidence separately dated, not labeled current.

## Production acceptance gates

1. Read all already-used version codes in Play, including drafts/test releases;
   choose a new version code greater than both Play's maximum and source version.
   A CLI build-number override is recorded without altering application `main`.
2. Register the new PEM certificate through Upload key > Request reset for each
   application. Record the actual Console status/effective date; request submission
   alone is not approval and does not make an AAB upload-ready.
3. Use pinned Flutter, locked dependencies and the production source export.
   Build a release app bundle with default supported ABIs, not the ARM64-only
   debug command. No activation/private-license source is imported.
4. Verify bundletool validation, package/version/SDK, release debuggable status,
   native Flutter libraries, JAR signature and the exact expected upload certificate.
   Preserve generated R8 mappings and checksum every final copied AAB.
5. Record source SHA, overridden version code, tool versions, signing fingerprint,
   byte count, SHA-256, test/build results and Play key-registration state in the
   run manifest and authoritative final context.
6. Do not publish existing Console changes or roll out a production release merely
   because the owner requested rebuild artifacts and an upload-key reset.

Signed AABs may be prepared before a reset becomes active, but they must remain
clearly labeled pending registration and must not be described as upload-ready.

The [static 16KB checker](../scripts/workstation/Test-MiliAndroid16Kb.ps1)
checks completed AAB ELF64 LOAD alignment/congruence and records packaging intent.
RELRO header warnings are advisory because headers alone cannot distinguish data
from padding. This is not generated-APK ZIP alignment, device/runtime acceptance
or Play approval. Keep these scopes separate in release records.

An explicit `-Resume` can recover a failed run: preserve its failed manifest and
recheck source/archive identity. Previously finalized components must remain
selected; their source/final AABs and mapping files are fully reverified, never
rewritten or recompiled. A missing AAB in an existing validated stage can resume
incremental compilation. Never overwrite an already-published artifact. Google's exact
`play-services-identity-credentials.properties` library-version metadata is allowed
only after a size limit and strict three-field/client/version structure check;
this is not a general credential-file exemption.

## Primary references

- [Google Play App Signing and upload-key resets](https://support.google.com/googleplay/android-developer/answer/9842756?hl=en)
- [Flutter Android release procedure](https://docs.flutter.dev/deployment/android)

This workstation tooling/documentation does not deploy Backend or React runtime
changes. Production mapping and historical mobile debugging evidence remain
separately dated in the authoritative final context.
