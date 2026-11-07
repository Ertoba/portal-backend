[CmdletBinding()]
param(
    [string]$WorkspaceRoot = $env:MILI_PHYSICAL_WORKSPACE,
    [ValidatePattern('^[A-Za-z0-9_-]+$')][string]$DeviceId = '29301JEGR18798',
    [ValidatePattern('^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$')][string]$RunId = '20261107-device-new-signature-install',
    [Parameter(Mandatory = $true)][switch]$UninstallExisting
)

# This is an explicitly destructive local-device install, not a Google Play release.
# All three APK sets are verified before any uninstall; no old signing key is used.
$ErrorActionPreference = 'Stop'
if (-not $UninstallExisting) { throw 'Explicit -UninstallExisting is required: all three local app data stores will be deleted.' }
if (-not $WorkspaceRoot) { throw 'Pass -WorkspaceRoot.' }
. (Join-Path $PSScriptRoot 'Enter-MiliEnvironment.ps1') -WorkspaceRoot $WorkspaceRoot
$workspace = $env:MILI_WORKSPACE
$work = Join-Path $workspace "build-workspace\production\android\$RunId\device-apks"
$artifacts = Join-Path $workspace "artifacts\production\android\$RunId\device-apks"
$secret = Join-Path $workspace "ops\secrets\android-signing\local-device\$RunId"
$productionRoot = Join-Path $workspace 'artifacts\production\android\20261107-production'
$bundletool = Join-Path $workspace 'tools\bundletool\bundletool-all-1.18.3.jar'
$bundletoolHash = 'A099CFA1543F55593BC2ED16A70A7C67FE54B1747BB7301F37FDFD6D91028E29'
$jbr = 'C:\Program Files\Android\Android Studio\jbr\bin'
$java = Join-Path $jbr 'java.exe'
$keytool = Join-Path $jbr 'keytool.exe'
$sdk = 'C:\Users\miro\AppData\Local\Android\sdk'
$adb = Join-Path $sdk 'platform-tools\adb.exe'
$aapt = Join-Path $sdk 'build-tools\36.1.0\aapt.exe'
$apksigner = Join-Path $sdk 'build-tools\36.1.0\apksigner.bat'
$manifestPath = Join-Path $artifacts 'device-install-manifest.json'
$state = [ordered]@{ schemaVersion = 1; runId = $RunId; mode = 'release-payload-new-upload-certificate-local-install'; deviceId = $DeviceId; localDataDeletionAuthorized = $true; googlePlayAppSigning = $false; startedAtUtc = [DateTimeOffset]::UtcNow.ToString('o'); status = 'preflight'; components = @() }
$passwordFiles = @()

function Invoke-Checked([string]$Tool, [string[]]$Arguments) {
    $saved = $ErrorActionPreference
    try { $ErrorActionPreference = 'Continue'; $lines = @(& $Tool @Arguments 2>&1 | ForEach-Object { "$_" }); $code = $LASTEXITCODE }
    finally { $ErrorActionPreference = $saved }
    if ($code -ne 0) { throw "A device-install command failed (exit $code): $([IO.Path]::GetFileName($Tool)). No automatic recovery performed." }
    return ($lines -join "`n")
}
function Save-State { $state | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath $manifestPath -Encoding UTF8 }
function Hash([string]$Path) { return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash }
function Assert-NoReparse([string]$Path) {
    $entry = Get-Item -LiteralPath $Path -Force
    while ($null -ne $entry) {
        if (($entry.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { throw 'Reparse paths are prohibited.' }
        if ($entry -is [IO.FileInfo]) { $entry = $entry.Directory } else { $entry = $entry.Parent }
    }
}
function Assert-Apk([string]$Path, [string]$Fingerprint) {
    $output = Invoke-Checked $apksigner @('verify', '--print-certs', $Path)
    $digests = @([regex]::Matches($output, '(?m)^Signer #\d+ certificate SHA-256 digest: ([0-9a-fA-F]+)') | ForEach-Object { $_.Groups[1].Value.ToUpperInvariant() })
    if ($digests.Count -ne 1 -or $digests[0] -ne $Fingerprint) { throw 'APK signer does not match the new upload certificate.' }
}
function Device-State([string]$Package) {
    $paths = @((Invoke-Checked $adb @('-s', $DeviceId, 'shell', 'pm', 'path', $Package)) -split '\r?\n' | Where-Object { $_ -match '^package:' } | ForEach-Object { $_.Substring(8).Trim() })
    if ($paths.Count -eq 0) { throw "Expected installed package is absent: $Package" }
    $dump = Invoke-Checked $adb @('-s', $DeviceId, 'shell', 'dumpsys', 'package', $Package)
    $version = [regex]::Match($dump, '(?m)^\s+versionCode=(\d+)')
    $installedAt = [regex]::Match($dump, '(?m)^\s+firstInstallTime=(.+)$')
    if (-not $version.Success -or -not $installedAt.Success) { throw 'Installed package metadata is incomplete.' }
    $hashes = @(foreach ($path in $paths) {
        $line = Invoke-Checked $adb @('-s', $DeviceId, 'shell', 'sha256sum', $path)
        if ($line -notmatch '^([0-9a-fA-F]{64})\s') { throw 'Device APK hash could not be read.' }
        [pscustomobject]@{ name = [IO.Path]::GetFileName($path); sha256 = $Matches[1].ToUpperInvariant() }
    })
    return [pscustomobject]@{ versionCode = [long]$version.Groups[1].Value; firstInstallTime = $installedAt.Groups[1].Value.Trim(); apks = $hashes }
}

foreach ($path in @($java, $keytool, $adb, $aapt, $apksigner, $bundletool)) { Assert-NoReparse $path }
if ((Hash $bundletool) -ne $bundletoolHash) { throw 'Pinned bundletool hash mismatch.' }
if ((Invoke-Checked $java @('-version')) -notmatch 'version "21\.') { throw 'Android Studio JBR21 is required.' }
foreach ($path in @($work, $artifacts, $secret)) {
    if (Test-Path -LiteralPath $path) { throw 'Choose a fresh RunId; existing run output will not be overwritten.' }
    $ancestor = Split-Path -Parent $path
    while (-not (Test-Path -LiteralPath $ancestor)) { $ancestor = Split-Path -Parent $ancestor }
    Assert-NoReparse $ancestor
}
New-Item -ItemType Directory -Path $work, $artifacts -Force | Out-Null
$secretParent = Split-Path -Parent $secret
if (-not (Test-Path -LiteralPath $secretParent)) { New-Item -ItemType Directory -Path $secretParent | Out-Null }
$owner = [Security.Principal.WindowsIdentity]::GetCurrent().User
$acl = New-Object Security.AccessControl.DirectorySecurity
$acl.SetOwner($owner); $acl.SetAccessRuleProtection($true, $false)
foreach ($sid in @($owner, [Security.Principal.SecurityIdentifier]::new('S-1-5-18'))) {
    $rule = [Security.AccessControl.FileSystemAccessRule]::new($sid, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow')
    $acl.AddAccessRule($rule)
}
[IO.Directory]::CreateDirectory($secret, $acl) | Out-Null
$secretAcl = Get-Acl -LiteralPath $secret
$secretRules = @($secretAcl.GetAccessRules($true, $true, [Security.Principal.SecurityIdentifier]))
if (-not $secretAcl.AreAccessRulesProtected -or $secretRules.Count -ne 2 -or @($secretRules | Where-Object { $_.IdentityReference.Value -notin @($owner.Value, 'S-1-5-18') }).Count -ne 0) { throw 'Private password directory ACL is not owner/SYSTEM-only.' }
$env:JAVA_HOME = Split-Path -Parent $jbr
$env:PATH = "$jbr;$env:PATH"
$env:TEMP = $work; $env:TMP = $work
$javaPrefix = @("-Djava.io.tmpdir=$work", '-jar', $bundletool)
Add-Type -AssemblyName System.IO.Compression.FileSystem
try {
    if ((Invoke-Checked $adb @('-s', $DeviceId, 'get-state')).Trim() -ne 'device') { throw 'The explicit device is not connected/authorized.' }
    $spec = Join-Path $work 'pixel-device-spec.json'
    Invoke-Checked $java ($javaPrefix + @('get-device-spec', "--output=$spec", "--device-id=$DeviceId", "--adb=$adb")) | Out-Null
    $production = Get-Content -LiteralPath (Join-Path $productionRoot 'production-build-manifest.json') -Raw | ConvertFrom-Json
    $signing = Get-Content -LiteralPath (Join-Path $workspace 'ops\config\android-signing\20261107-upload-reset\upload-keys.json') -Raw | ConvertFrom-Json
    if ($production.status -ne 'verified-builds-complete' -or $production.signingRunId -ne '20261107-upload-reset' -or $signing.runId -ne '20261107-upload-reset') { throw 'Unexpected production/signing metadata.' }
    $versions = @{ customer = 55; vendor = 39; delivery = 41 }
    $pins = @{ customer = 'FEB2A59267A90F789D8E97C548BB2A70F97F3E150CEB748B7356D365EAF494EB'; vendor = 'FB2E46955EEAC16F51B3E171138F4E34DEDF2B00B659B9271D970B88ACA64169'; delivery = '9D9B99B0462C676070D2FEEA5E2D813849BF97124EF8CF6E883663AA57FC343E' }
    foreach ($component in @('customer', 'vendor', 'delivery')) {
        Write-Host "PREPARING $component (new upload certificate, release payload)"
        $entries = @($production.components | Where-Object { $_.component -eq $component }); $keys = @($signing.apps | Where-Object { $_.component -eq $component })
        if ($entries.Count -ne 1 -or $keys.Count -ne 1) { throw 'Expected exactly one app metadata entry.' }
        $entry = $entries[0]; $key = $keys[0]; $package = "ge.mili.$component"; $fingerprint = $pins[$component]
        $aab = Join-Path $productionRoot ([IO.Path]::GetFileName($entry.artifact))
        if ($entry.package -ne $package -or $entry.versionCode -ne $versions[$component] -or $entry.debuggable -ne $false -or $key.applicationId -ne $package -or $key.alias -ne 'upload' -or $key.certificateSha256.Replace(':', '') -ne $fingerprint -or (Hash $aab) -ne $entry.sha256) { throw 'AAB/key identity or hash mismatch.' }
        $appWork = Join-Path $work $component; New-Item -ItemType Directory -Path $appWork | Out-Null
        $private = Join-Path $workspace "ops\secrets\android-signing\$component\20261107-upload-reset"
        $propertiesPath = Join-Path $private 'key.properties'; $keystore = Join-Path $private 'upload-keystore.jks'
        Assert-NoReparse $propertiesPath; Assert-NoReparse $keystore
        $properties = @{}
        foreach ($line in Get-Content -LiteralPath $propertiesPath) { if ($line -match '^\s*([^#!][^=]*?)\s*=\s*(.*)$') { $properties[$Matches[1].Trim()] = $Matches[2] } }
        if ($properties.keyAlias -ne 'upload' -or -not $properties.storePassword -or -not $properties.keyPassword) { throw 'New signing configuration is incomplete.' }
        $storeFile = Join-Path $secret "$component-store.pwd"; $keyFile = Join-Path $secret "$component-key.pwd"
        foreach ($pair in @(@($storeFile, $properties.storePassword), @($keyFile, $properties.keyPassword))) {
            $passwordFiles += $pair[0]; [IO.File]::WriteAllText($pair[0], [string]$pair[1] + "`n", [Text.UTF8Encoding]::new($false))
        }
        $properties.Clear()
        $der = Join-Path $appWork 'local-upload-certificate.der'
        Invoke-Checked $keytool @('-exportcert', '-keystore', $keystore, '-alias', 'upload', '-storepass:file', $storeFile, '-file', $der) | Out-Null
        if ((Hash $der) -ne $fingerprint) { throw 'Actual keystore certificate differs from the pinned new upload certificate.' }
        $apks = Join-Path $artifacts "mili-$component-v$($entry.versionCode)-new-upload-cert.apks"
        Invoke-Checked $java ($javaPrefix + @('build-apks', "--bundle=$aab", "--output=$apks", "--device-spec=$spec", "--ks=$keystore", '--ks-key-alias=upload', "--ks-pass=file:$storeFile", "--key-pass=file:$keyFile")) | Out-Null
        $zip = [IO.Compression.ZipFile]::OpenRead($apks)
        try {
            $apkFiles = @(foreach ($apkEntry in @($zip.Entries | Where-Object { $_.FullName.EndsWith('.apk') })) {
                $apk = Join-Path $appWork $apkEntry.Name
                if (Test-Path -LiteralPath $apk) { throw 'Duplicate APK filename in generated set.' }
                [IO.Compression.ZipFileExtensions]::ExtractToFile($apkEntry, $apk)
                Assert-Apk $apk $fingerprint
                [pscustomobject]@{ name = $apkEntry.Name; sha256 = Hash $apk }
            })
        } finally { $zip.Dispose() }
        $baseFiles = @($apkFiles | Where-Object { $_.name -eq 'base-master.apk' })
        if ($baseFiles.Count -ne 1 -or $apkFiles.Count -lt 2) { throw 'Expected device-specific base and split APKs.' }
        $badging = Invoke-Checked $aapt @('dump', 'badging', (Join-Path $appWork 'base-master.apk'))
        if ($badging -notmatch "package: name='$([regex]::Escape($package))' versionCode='$($entry.versionCode)' versionName='4\.2\.0'" -or $badging -match '(?m)^application-debuggable') { throw 'Generated base package/version/release manifest mismatch.' }
        if ((Hash $aab) -ne $entry.sha256) { throw 'Original production AAB changed.' }
        $state.components += [ordered]@{ component = $component; package = $package; sourceSha = $entry.sha; versionCode = $entry.versionCode; versionName = $entry.versionName; originalAab = $aab; originalAabSha256 = $entry.sha256; apksArtifact = $apks; apksSha256 = Hash $apks; apkSignerSha256 = $fingerprint; generatedApks = $apkFiles; before = Device-State $package; status = 'verified-ready' }
        Save-State; Write-Host "VERIFIED $component $($entry.versionCode)"
    }
    if ($state.components.Count -ne 3) { throw 'All three verified APK sets are required before deleting any app.' }
    $state.status = 'all-three-verified-before-uninstall'; Save-State
    foreach ($app in $state.components) {
        $current = Device-State $app.package
        if (($current.apks | ConvertTo-Json -Compress) -ne ($app.before.apks | ConvertTo-Json -Compress)) { throw 'Device app changed after preflight; refusing uninstall.' }
        Write-Host "UNINSTALLING $($app.package): authorized local app data deletion"
        $app.status = 'uninstall-started'; Save-State
        if ((Invoke-Checked $adb @('-s', $DeviceId, 'uninstall', $app.package)).Trim() -ne 'Success') { throw 'Package uninstall did not report success.' }
        $app.status = 'uninstalled'; Save-State
        Invoke-Checked $java ($javaPrefix + @('install-apks', "--apks=$($app.apksArtifact)", "--device-id=$DeviceId", "--adb=$adb")) | Out-Null
        $app.after = Device-State $app.package
        $actualHashes = @($app.after.apks | ForEach-Object { $_.sha256 } | Sort-Object)
        $expectedHashes = @($app.generatedApks | ForEach-Object { $_.sha256 } | Sort-Object)
        if ($app.after.versionCode -ne $app.versionCode -or ($actualHashes -join ',') -ne ($expectedHashes -join ',')) { throw 'Installed version/APK hashes differ from verified payload.' }
        $app.originalAabUnchanged = (Hash $app.originalAab) -eq $app.originalAabSha256
        if (-not $app.originalAabUnchanged) { throw 'Original AAB changed after installation.' }
        $app.status = 'installed-verified-new-signature'; Save-State
        Write-Host "INSTALLED $($app.package) $($app.versionCode) (new upload signature; local data reset)"
    }
    $state.status = 'all-three-installed-verified'; $state.completedAtUtc = [DateTimeOffset]::UtcNow.ToString('o'); Save-State
    Write-Host "Manifest: $manifestPath"
} catch {
    $state.status = 'failed-stopped-no-automatic-recovery'; $state.failedAtUtc = [DateTimeOffset]::UtcNow.ToString('o'); Save-State
    throw
} finally {
    foreach ($passwordFile in $passwordFiles) { if (Test-Path -LiteralPath $passwordFile) { Remove-Item -LiteralPath $passwordFile -Force } }
}
