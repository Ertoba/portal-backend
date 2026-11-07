[CmdletBinding()]
param(
    [string]$WorkspaceRoot = $env:MILI_PHYSICAL_WORKSPACE,
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$')]
    [string]$SigningRunId,
    [ValidatePattern('^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$')]
    [string]$RunId = (Get-Date -Format 'yyyyMMdd-HHmmss'),
    [Parameter(Mandatory = $true)]
    [hashtable]$VersionCodes,
    [Parameter(Mandatory = $true)]
    [hashtable]$PlayMaxVersionCodes,
    [ValidateSet('customer', 'vendor', 'delivery')]
    [string[]]$Components = @('customer', 'vendor', 'delivery'),
    [Parameter(Mandatory = $true)]
    [string]$BundletoolJar,
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[0-9A-Fa-f]{64}$')]
    [string]$BundletoolSha256,
    [hashtable]$PlayRegistrationEvidence = @{},
    [switch]$Resume
)

$ErrorActionPreference = 'Stop'
if (-not $WorkspaceRoot) { throw 'Pass -WorkspaceRoot or load the MILI environment first.' }
if ($Components.Count -eq 0 -or @($Components | Select-Object -Unique).Count -ne $Components.Count) {
    throw 'Select at least one component, with no duplicate components.'
}
$physicalWorkspace = (Resolve-Path -LiteralPath $WorkspaceRoot).Path
. (Join-Path $PSScriptRoot 'Enter-MiliEnvironment.ps1') -WorkspaceRoot $physicalWorkspace
$workspace = $env:MILI_WORKSPACE

function Assert-WithinPath([string]$Path, [string]$Parent) {
    $absolute = [IO.Path]::GetFullPath($Path)
    $prefix = [IO.Path]::GetFullPath($Parent).TrimEnd('\', '/') + '\'
    if (-not $absolute.StartsWith($prefix, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'A path escaped its designated MILI directory.'
    }
    return $absolute
}

function Assert-NoReparsePoint([string]$Path) {
    $entry = Get-Item -LiteralPath $Path -Force
    while ($null -ne $entry) {
        if (($entry.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw 'A reparse point is not permitted for a production tool or signing path.'
        }
        if ($entry -is [IO.FileInfo]) { $entry = $entry.Directory }
        else { $entry = $entry.Parent }
    }
}

function Get-NormalizedFingerprint([string]$Value) {
    $normalized = $Value.Replace(':', '').Replace(' ', '').ToUpperInvariant()
    if ($normalized -notmatch '^[0-9A-F]{64}$') { throw 'A SHA-256 certificate fingerprint is invalid.' }
    return $normalized
}

function Get-CertificateFingerprint([byte[]]$RawData) {
    $sha = [Security.Cryptography.SHA256]::Create()
    try { return ([BitConverter]::ToString($sha.ComputeHash($RawData))).Replace('-', '') }
    finally { $sha.Dispose() }
}

function Invoke-NativeLogged {
    param([string]$Tool, [string[]]$Arguments, [string]$LogPath, [switch]$Capture)
    $savedPreference = $ErrorActionPreference
    try {
        # Windows PowerShell wraps native stderr warnings as ErrorRecords.
        $ErrorActionPreference = 'Continue'
        $lines = @(& $Tool @Arguments 2>&1 | ForEach-Object { "$_" } |
            Tee-Object -FilePath $LogPath -Append)
        $nativeExit = $LASTEXITCODE
    }
    finally { $ErrorActionPreference = $savedPreference }
    if ($nativeExit -ne 0) {
        throw "Production command failed (exit $nativeExit); inspect $LogPath. No debug fallback is allowed."
    }
    if ($Capture) { return ($lines -join "`n") }
    $lines | ForEach-Object { Write-Host $_ }
}

function Get-GitText([string]$Repository, [string[]]$Arguments) {
    $output = & git -C $Repository @Arguments
    if ($LASTEXITCODE -ne 0) { throw 'A production Git preflight failed.' }
    return ($output -join "`n").Trim()
}

function Get-JbrHome([string]$Repository) {
    $candidates = @()
    foreach ($relative in @('android\local.properties', 'android\gradle.properties')) {
        $properties = Join-Path $Repository $relative
        if (Test-Path -LiteralPath $properties -PathType Leaf) {
            foreach ($line in Get-Content -LiteralPath $properties) {
                if ($line -match '^\s*(?:java\.home|org\.gradle\.java\.home)\s*=\s*(.+?)\s*$') {
                    $candidates += $Matches[1].Replace('\\', '\').Replace('\:', ':')
                }
            }
        }
    }
    $candidates += 'C:\Program Files\Android\Android Studio\jbr'
    foreach ($candidate in $candidates) {
        if ((Test-Path -LiteralPath (Join-Path $candidate 'bin\java.exe') -PathType Leaf) -and
            (Test-Path -LiteralPath (Join-Path $candidate 'bin\jarsigner.exe') -PathType Leaf) -and
            (Test-Path -LiteralPath (Join-Path $candidate 'bin\keytool.exe') -PathType Leaf)) {
            return (Resolve-Path -LiteralPath $candidate).Path
        }
    }
    throw 'Android Studio JBR (java, jarsigner and keytool) was not found; no SDK updates are performed.'
}

if (-not [IO.Path]::IsPathRooted($BundletoolJar) -or $BundletoolJar.StartsWith('\\')) {
    throw 'BundletoolJar must be an absolute local path to the trusted workspace tool.'
}
$toolInput = [IO.Path]::GetFullPath($BundletoolJar)
$physicalPrefix = $physicalWorkspace.TrimEnd('\', '/') + '\'
if ($toolInput.StartsWith($physicalPrefix, [StringComparison]::OrdinalIgnoreCase)) {
    $toolInput = Join-Path $workspace $toolInput.Substring($physicalPrefix.Length)
}
$bundletool = Assert-WithinPath $toolInput (Join-Path $workspace 'tools')
if (-not (Test-Path -LiteralPath $bundletool -PathType Leaf) -or
    [IO.Path]::GetExtension($bundletool) -ne '.jar') { throw 'The pinned bundletool JAR is missing.' }
Assert-NoReparsePoint $bundletool
if ((Get-FileHash -LiteralPath $bundletool -Algorithm SHA256).Hash -ne $BundletoolSha256) {
    throw 'The bundletool SHA-256 pin does not match.'
}

$signingRoot = Join-Path $workspace "ops\config\android-signing\$SigningRunId"
$signingManifest = Join-Path $signingRoot 'upload-keys.json'
Assert-NoReparsePoint $signingManifest
$signing = Get-Content -LiteralPath $signingManifest -Raw | ConvertFrom-Json
if ($signing.schemaVersion -ne 1 -or $signing.runId -ne $SigningRunId) {
    throw 'The upload-key metadata schema or signing run does not match.'
}

$plans = @()
foreach ($component in $Components) {
    if (-not $VersionCodes.ContainsKey($component) -or -not $PlayMaxVersionCodes.ContainsKey($component)) {
        throw "Supply explicit new and confirmed Play maximum version codes for $component."
    }
    $newCode = 0L
    $playMax = 0L
    if (-not [long]::TryParse([string]$VersionCodes[$component], [ref]$newCode) -or
        -not [long]::TryParse([string]$PlayMaxVersionCodes[$component], [ref]$playMax) -or
        $newCode -lt 1 -or $newCode -gt 2100000000 -or $playMax -lt 0 -or $newCode -le $playMax) {
        throw "$component requires an Android version code strictly above the confirmed Play maximum."
    }
    $repo = Join-Path $workspace "repos\$component-app"
    $plannedSha = Get-GitText $repo @('rev-parse', 'HEAD')
    $pubspec = Get-Content -LiteralPath (Join-Path $repo 'pubspec.yaml') -Raw
    if ($pubspec -notmatch '(?m)^version:\s*([0-9]+\.[0-9]+\.[0-9]+(?:[-.A-Za-z0-9]*)?)\+([0-9]+)\s*$') {
        throw "$component has no unambiguous Git version in pubspec.yaml."
    }
    $versionName = $Matches[1]
    $gitVersionCode = [long]$Matches[2]
    if ($newCode -le $gitVersionCode) { throw "$component version code must also exceed its current Git version." }
    $appMetadata = @($signing.apps | Where-Object { $_.component -eq $component })
    if ($appMetadata.Count -ne 1) { throw "$component requires exactly one upload-key metadata entry." }
    $appMetadata = $appMetadata[0]
    $expectedPackage = "ge.mili.$component"
    if ($appMetadata.applicationId -ne $expectedPackage -or $appMetadata.runId -ne $SigningRunId -or
        $appMetadata.alias -ne 'upload' -or $appMetadata.keystoreType -ne 'JKS') {
        throw "$component upload-key identity does not match the requested production app."
    }
    $privateRoot = Join-Path $workspace "ops\secrets\android-signing\$component\$SigningRunId"
    $propertiesPath = Join-Path $privateRoot 'key.properties'
    $keystorePath = Join-Path $privateRoot 'upload-keystore.jks'
    $certificatePath = Join-Path $signingRoot "$component\upload-certificate.der"
    foreach ($pair in @(
        @($appMetadata.signingProperties, $propertiesPath),
        @($appMetadata.keystore, $keystorePath),
        @($appMetadata.publicDer, $certificatePath)
    )) {
        if ([IO.Path]::GetFullPath($pair[0]) -ne [IO.Path]::GetFullPath($pair[1]) -or
            -not (Test-Path -LiteralPath $pair[1] -PathType Leaf)) {
            throw "$component signing metadata must reference the exact new signing run paths."
        }
        Assert-NoReparsePoint $pair[1]
    }
    $fingerprint = Get-NormalizedFingerprint $appMetadata.certificateSha256
    $certificate = [Security.Cryptography.X509Certificates.X509Certificate2]::new([byte[]][IO.File]::ReadAllBytes($certificatePath))
    try {
        if ((Get-CertificateFingerprint $certificate.RawData) -ne $fingerprint) {
            throw "$component public upload certificate differs from signing metadata."
        }
    }
    finally { $certificate.Dispose() }
    $publicAppMetadata = Join-Path $signingRoot "$component\metadata.json"
    $perApp = Get-Content -LiteralPath $publicAppMetadata -Raw | ConvertFrom-Json
    if ($perApp.component -ne $component -or $perApp.applicationId -ne $expectedPackage -or
        $perApp.runId -ne $SigningRunId -or
        (Get-NormalizedFingerprint $perApp.certificateSha256) -ne $fingerprint) {
        throw "$component aggregate and per-app signing metadata disagree."
    }
    $registrationStatus = 'pending-registration'
    $registrationEvidence = $null
    if ($PlayRegistrationEvidence.ContainsKey($component)) {
        $evidence = $PlayRegistrationEvidence[$component]
        $verifiedTime = [DateTimeOffset]::MinValue
        if ((Get-NormalizedFingerprint $evidence.certificateSha256) -ne $fingerprint -or
            -not [DateTimeOffset]::TryParse([string]$evidence.verifiedAtUtc, [ref]$verifiedTime) -or
            $verifiedTime -gt [DateTimeOffset]::UtcNow -or -not $evidence.evidenceReference) {
            throw "$component Play registration evidence must identify the matching certificate and actual verification."
        }
        $registrationStatus = 'registered-verified'
        $registrationEvidence = $evidence
    }
    $plans += [pscustomobject]@{
        component = $component; repository = $repo; applicationId = $expectedPackage
        plannedSha = $plannedSha
        versionCode = $newCode; versionName = $versionName; gitVersionCode = $gitVersionCode
        playMaximumVersionCode = $playMax; properties = $propertiesPath; keystore = $keystorePath
        certificate = $certificatePath; fingerprint = $fingerprint
        registrationStatus = $registrationStatus; registrationEvidence = $registrationEvidence
        javaHome = Get-JbrHome $repo
    }
}

$runRoot = Assert-WithinPath (Join-Path $workspace "build-workspace\production\android\$RunId") (Join-Path $workspace 'build-workspace\production')
$artifactRoot = Assert-WithinPath (Join-Path $workspace "artifacts\production\android\$RunId") (Join-Path $workspace 'artifacts\production')
$manifestPath = Join-Path $artifactRoot 'production-build-manifest.json'
$previousManifest = $null
$failedManifestBackup = $null
if ($Resume) {
    $previousManifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
    if ($previousManifest.status -ne 'failed' -or $previousManifest.mode -ne 'production' -or
        $previousManifest.runId -ne $RunId -or $previousManifest.signingRunId -ne $SigningRunId -or
        $previousManifest.bundletoolSha256 -ne $BundletoolSha256 -or
        [IO.Path]::GetFullPath($previousManifest.bundletool) -ne $bundletool -or
        @($previousManifest.components | Where-Object { $_.component -notin $Components }).Count -ne 0 -or
        @($previousManifest.components | Select-Object -ExpandProperty component -Unique).Count -ne @($previousManifest.components).Count) {
        throw 'Resume requires the matching failed run and all previously finalized components selected exactly once.'
    }
    $failedManifestBackup = Join-Path $artifactRoot ("production-build-manifest.failed-" + (Get-Date -Format 'yyyyMMdd-HHmmss-fffffff') + '.json')
    Copy-Item -LiteralPath $manifestPath -Destination $failedManifestBackup -ErrorAction Stop
}
elseif ((Test-Path -LiteralPath $runRoot) -or (Test-Path -LiteralPath $artifactRoot)) {
    throw 'Production run and artifact directories must be fresh; existing runs are never overwritten.'
}
$tempRoot = Join-Path $runRoot 'tmp'
New-Item -ItemType Directory -Path $tempRoot, $artifactRoot -Force | Out-Null
$oldTemp = $env:TEMP
$oldTmp = $env:TMP
$oldJavaHome = $env:JAVA_HOME
$env:TEMP = $tempRoot
$env:TMP = $tempRoot
$results = if ($Resume) { @($previousManifest.components) } else { @() }
$manifest = [ordered]@{
    schemaVersion = 1; mode = 'production'; runId = $RunId; signingRunId = $SigningRunId
    status = 'running'; startedAtUtc = [DateTimeOffset]::UtcNow.ToString('o')
    bundletool = $bundletool; bundletoolSha256 = $BundletoolSha256.ToUpperInvariant()
    productionWorkRoot = $runRoot; productionArtifactRoot = $artifactRoot
    debugWorkRoot = Join-Path $workspace 'build-workspace\debug\android'
    debugArtifactRoot = Join-Path $workspace 'artifacts\debug\android'
    components = @(); safeToUploadAllToExistingPlayApps = $false
    resumed = [bool]$Resume; previousFailedManifest = $failedManifestBackup
}
if ($Resume) {
    $manifest.startedAtUtc = $previousManifest.startedAtUtc
    $manifest.resumedAtUtc = [DateTimeOffset]::UtcNow.ToString('o')
    $manifest.previousFailedAtUtc = $previousManifest.failedAtUtc
}

function Save-RunManifest {
    $manifest.components = @($results)
    $manifest | ConvertTo-Json -Depth 10 | Set-Content -LiteralPath $manifestPath -Encoding UTF8
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
try {
    Save-RunManifest
    foreach ($plan in $plans) {
        $component = $plan.component
        $existingWork = Join-Path $runRoot $component
        $existingStage = $Resume -and (Test-Path -LiteralPath $existingWork)
        if ($existingStage) {
            $stage = Get-Content -LiteralPath (Join-Path $existingWork 'stage.json') -Raw | ConvertFrom-Json
            $expectedSource = Join-Path $existingWork 'source'
            $expectedArchive = Join-Path $existingWork 'source.zip'
            $liveMain = Get-GitText $plan.repository @('ls-remote', '--exit-code', '--heads', 'origin', 'refs/heads/main')
            if ($stage.mode -ne 'production' -or $stage.runId -ne $RunId -or $stage.component -ne $component -or
                $stage.package -ne $plan.applicationId -or $stage.repository -ne $plan.repository -or
                $stage.source -ne $expectedSource -or $stage.work -ne $existingWork -or $stage.archive -ne $expectedArchive -or
                $stage.log -ne (Join-Path $existingWork 'logs') -or $stage.artifact -ne $artifactRoot -or
                (Get-FileHash -LiteralPath $expectedArchive -Algorithm SHA256).Hash -ne $stage.archiveSha256 -or
                (Get-GitText $plan.repository @('branch', '--show-current')) -ne 'main' -or
                (Get-GitText $plan.repository @('status', '--porcelain', '--untracked-files=normal')) -or
                (Get-GitText $plan.repository @('rev-parse', 'origin/main')) -ne $plan.plannedSha -or
                $liveMain -notmatch ('^' + $plan.plannedSha + '\s+refs/heads/main$') -or
                (Get-FileHash -LiteralPath (Join-Path $expectedSource 'pubspec.lock') -Algorithm SHA256).Hash -ne
                (Get-FileHash -LiteralPath (Join-Path $plan.repository 'pubspec.lock') -Algorithm SHA256).Hash) {
                throw "$component existing stage does not match this clean, current-main production plan."
            }
        }
        else {
            $stage = & (Join-Path $PSScriptRoot 'Stage-MiliAndroidBuild.ps1') `
                -WorkspaceRoot $physicalWorkspace -Mode production -Component $component -RunId $RunId
        }
        if ($stage.sha -ne $plan.plannedSha) {
            throw "$component main changed after version/signing preflight; start a new production run."
        }
        $stagedAab = Join-Path $stage.source 'build\app\outputs\bundle\release\app-release.aab'
        $compiledAabReused = $existingStage -and (Test-Path -LiteralPath $stagedAab -PathType Leaf)
        $retained = if ($Resume) { @($previousManifest.components | Where-Object { $_.component -eq $component }) } else { @() }
        $retained = if ($retained.Count -eq 1) { $retained[0] } else { $null }
        if ($retained -and -not $compiledAabReused) {
            throw "$component already has a finalized artifact; its compiled source AAB must still exist. No recompilation is permitted."
        }
        # The staging helper reloads the common environment; restore this run's temp root.
        $env:TEMP = $tempRoot
        $env:TMP = $tempRoot
        $env:JAVA_HOME = $plan.javaHome
        $java = Join-Path $plan.javaHome 'bin\java.exe'
        $keytool = Join-Path $plan.javaHome 'bin\keytool.exe'
        $jarsigner = Join-Path $plan.javaHome 'bin\jarsigner.exe'
        $source = $stage.source
        $androidRoot = Join-Path $source 'android'
        $signingLink = Assert-WithinPath (Join-Path $androidRoot 'key.properties') $androidRoot
        $logPath = Join-Path $stage.log 'flutter-production-aab.log'
        $verifyLog = Join-Path $stage.log 'aab-verification.log'
        $started = if ($compiledAabReused) { [DateTimeOffset]::Parse($stage.stagedAt).LocalDateTime } else { Get-Date }
        $lockFile = Join-Path $source 'pubspec.lock'
        $lockHash = (Get-FileHash -LiteralPath $lockFile -Algorithm SHA256).Hash
        $propertiesHash = (Get-FileHash -LiteralPath $plan.properties -Algorithm SHA256).Hash
        $keyHash = (Get-FileHash -LiteralPath $plan.keystore -Algorithm SHA256).Hash
        $linkCreated = $false
        $locationPushed = $false
        Write-Host "PRODUCTION_BUILD_START component=$component sha=$($stage.sha) versionCode=$($plan.versionCode)"
        try {
            if (Test-Path -LiteralPath $signingLink) { throw 'Staging unexpectedly contains signing properties.' }
            if (-not $compiledAabReused) {
                # A hard link preserves the restrictive secret-file ACL; no plaintext copy is created.
                New-Item -ItemType HardLink -Path $signingLink -Target $plan.properties | Out-Null
                $linkCreated = $true
                Push-Location $source
                $locationPushed = $true
                Invoke-NativeLogged -Tool flutter -Arguments @('pub', 'get', '--enforce-lockfile') -LogPath $logPath
                if ((Get-FileHash -LiteralPath $lockFile -Algorithm SHA256).Hash -ne $lockHash) {
                    throw "$component locked dependencies changed during resolution."
                }
                # All Flutter release ABIs are retained. No --target-platform or debug signing fallback.
                Invoke-NativeLogged -Tool flutter -Arguments @('build', 'appbundle', '--release', '--no-pub', '--build-number', [string]$plan.versionCode) -LogPath $logPath
            }
            if ((Get-FileHash -LiteralPath $lockFile -Algorithm SHA256).Hash -ne $lockHash) {
                throw "$component dependency lock changed during compilation."
            }
            $aab = $stagedAab
            if (-not (Test-Path -LiteralPath $aab -PathType Leaf)) { throw "$component AAB is missing." }
            $built = Get-Item -LiteralPath $aab
            if ($built.LastWriteTime -lt $started.AddSeconds(-2) -or $built.Length -le 0) {
                throw "$component AAB is stale or empty."
            }
            $signerOutput = Invoke-NativeLogged -Tool $jarsigner -Arguments @('-J-Duser.language=en', '-J-Duser.country=US', '-verify', '-verbose', '-certs', $aab) -LogPath $verifyLog -Capture
            if ($signerOutput -notmatch '(?m)^\s*jar verified\.\s*$') { throw "$component has no verified JAR signature." }
            $certOutput = Invoke-NativeLogged -Tool $keytool -Arguments @('-J-Duser.language=en', '-printcert', '-jarfile', $aab, '-rfc') -LogPath $verifyLog -Capture
            if ($certOutput -notmatch '(?s)-----BEGIN CERTIFICATE-----\s*(.+?)\s*-----END CERTIFICATE-----') {
                throw "$component signed AAB certificate could not be read."
            }
            $signerBytes = [Convert]::FromBase64String(($Matches[1] -replace '\s', ''))
            if ((Get-CertificateFingerprint $signerBytes) -ne $plan.fingerprint) {
                throw "$component AAB signer is not the NEW requested upload key."
            }
            Invoke-NativeLogged -Tool $keytool -Arguments @('-J-Duser.language=en', '-printcert', '-file', $plan.certificate) -LogPath $verifyLog
            Invoke-NativeLogged -Tool $java -Arguments @('-jar', $bundletool, 'validate', "--bundle=$aab") -LogPath $verifyLog -Capture | Out-Null
            $manifestXml = Invoke-NativeLogged -Tool $java -Arguments @('-jar', $bundletool, 'dump', 'manifest', "--bundle=$aab", '--module=base') -LogPath $verifyLog -Capture
            $xml = New-Object Xml.XmlDocument
            $xml.XmlResolver = $null
            $xml.LoadXml($manifestXml)
            $androidNamespace = 'http://schemas.android.com/apk/res/android'
            $appManifest = $xml.DocumentElement
            $manifestPackage = $appManifest.GetAttribute('package')
            $manifestCode = $appManifest.GetAttribute('versionCode', $androidNamespace)
            $manifestName = $appManifest.GetAttribute('versionName', $androidNamespace)
            $application = $appManifest.SelectSingleNode('application')
            $sdk = $appManifest.SelectSingleNode('uses-sdk')
            if ($null -eq $application -or $null -eq $sdk) { throw "$component AAB manifest is incomplete." }
            $minSdk = 0
            $targetSdk = 0
            $gradle = Get-Content -LiteralPath (Join-Path $source 'android\app\build.gradle.kts') -Raw
            if ($gradle -notmatch '(?m)^\s*targetSdk\s*=\s*([0-9]+)\s*$') { throw 'The source target SDK is not explicit.' }
            $expectedTarget = [int]$Matches[1]
            if ($manifestPackage -ne $plan.applicationId -or $manifestCode -ne [string]$plan.versionCode -or
                $manifestName -ne $plan.versionName -or
                -not [int]::TryParse($sdk.GetAttribute('minSdkVersion', $androidNamespace), [ref]$minSdk) -or
                -not [int]::TryParse($sdk.GetAttribute('targetSdkVersion', $androidNamespace), [ref]$targetSdk) -or
                $minSdk -lt 21 -or $targetSdk -ne $expectedTarget -or $targetSdk -lt 35 -or $minSdk -gt $targetSdk -or
                $application.GetAttribute('debuggable', $androidNamespace) -notin @('', 'false')) {
                throw "$component AAB package/version/SDK/debuggable checks failed."
            }
            $zip = [IO.Compression.ZipFile]::OpenRead($aab)
            try {
                $entryNames = @($zip.Entries | ForEach-Object { $_.FullName.Replace('\', '/') })
                $privatePatterns = @('*.jks', '*.keystore', '*.p12', '*.pfx', '*.pem', '*.key', '.env*', 'key.properties', 'id_rsa*', 'id_ed25519*')
                foreach ($stem in @('*credentials*', '*service-account*', '*service_account*', '*serviceAccount*', '*private-key*', '*private_key*', '*secrets*')) {
                    foreach ($extension in @('json', 'yaml', 'yml', 'properties', 'toml', 'ini')) {
                        $privatePatterns += "$stem.$extension"
                    }
                }
                $privateDirectories = @('.secrets', 'secrets', 'credentials', 'service-accounts', 'service_accounts', 'private-keys', 'private_keys')
                foreach ($entryName in $entryNames) {
                    if ($entryName -ceq 'base/root/play-services-identity-credentials.properties') {
                        $metadataEntry = $zip.GetEntry($entryName)
                        if ($metadataEntry.Length -gt 1024) { throw 'Unexpected Google identity library metadata size.' }
                        $reader = New-Object IO.StreamReader($metadataEntry.Open())
                        try { $publicMetadata = $reader.ReadToEnd() } finally { $reader.Dispose() }
                        if ($publicMetadata -notmatch '\Aversion=([0-9]+\.[0-9]+\.[0-9]+(?:[-.A-Za-z0-9]*)?)\r?\nclient=play-services-identity-credentials\r?\nplay-services-identity-credentials_client=\1\r?\n?\z') {
                            throw 'Unexpected Google identity library metadata contents.'
                        }
                        continue
                    }
                    foreach ($part in $entryName.Split('/')) {
                        if ($privateDirectories -contains $part) { throw "$component AAB contains a forbidden private directory." }
                        foreach ($pattern in $privatePatterns) {
                            if ($part -like $pattern) { throw "$component AAB contains a forbidden private/config entry." }
                        }
                    }
                }
                $requiredAbis = @('armeabi-v7a', 'arm64-v8a', 'x86_64')
                foreach ($abi in $requiredAbis) {
                    foreach ($library in @('libapp.so', 'libflutter.so')) {
                        if ($entryNames -notcontains "base/lib/$abi/$library") { throw "$component AAB is missing release ABI $abi/$library." }
                    }
                }
                if (@($entryNames | Where-Object { $_ -match '^META-INF/[^/]+\.(RSA|DSA|EC)$' }).Count -ne 1) {
                    throw "$component AAB requires exactly one signing identity."
                }
            }
            finally { $zip.Dispose() }
            if ((Get-GitText $stage.repository @('rev-parse', 'HEAD')) -ne $stage.sha -or
                (Get-GitText $stage.repository @('branch', '--show-current')) -ne 'main' -or
                (Get-GitText $stage.repository @('status', '--porcelain', '--untracked-files=normal'))) {
                throw "$component original main changed during compilation."
            }
            if ((Get-FileHash -LiteralPath $plan.properties -Algorithm SHA256).Hash -ne $propertiesHash -or
                (Get-FileHash -LiteralPath $plan.keystore -Algorithm SHA256).Hash -ne $keyHash) {
                throw "$component canonical signing material changed during the build."
            }
            if ($retained) {
                $retainedPath = Assert-WithinPath $retained.artifact $artifactRoot
                $allowedName = '^mili-' + $component + '-main-' + $stage.sha.Substring(0, 7) + '-v' + $plan.versionCode + '-(release|pending-upload-key-reset)\.aab$'
                $verifiedHash = (Get-FileHash -LiteralPath $aab -Algorithm SHA256).Hash
                if ([IO.Path]::GetDirectoryName($retainedPath) -ne [IO.Path]::GetFullPath($artifactRoot) -or
                    [IO.Path]::GetFileName($retainedPath) -notmatch $allowedName -or
                    $retained.sha -ne $stage.sha -or $retained.package -ne $manifestPackage -or
                    $retained.versionCode -ne $plan.versionCode -or $retained.versionName -ne $manifestName -or
                    $retained.signingRunId -ne $SigningRunId -or
                    (Get-NormalizedFingerprint $retained.uploadCertificateSha256) -ne $plan.fingerprint -or
                    $retained.stageArchiveSha256 -ne $stage.archiveSha256 -or $retained.lockSha256 -ne $lockHash -or
                    $retained.bytes -ne $built.Length -or $retained.sha256 -ne $verifiedHash -or
                    (Get-FileHash -LiteralPath $retainedPath -Algorithm SHA256).Hash -ne $verifiedHash) {
                    throw "$component finalized artifact identity/hash differs from its fully reverified compiled AAB."
                }
                $expectedSymbols = @{}
                $expectedSymbols["mili-$component-v$($plan.versionCode)-mapping.txt"] = Join-Path $source 'build\app\outputs\mapping\release\mapping.txt'
                $expectedSymbols["mili-$component-v$($plan.versionCode)-native-debug-symbols.zip"] = Join-Path $source 'build\app\outputs\native-debug-symbols\release\native-debug-symbols.zip'
                $retainedSymbolNames = @()
                foreach ($symbol in $retained.symbols) {
                    $symbolPath = Assert-WithinPath $symbol.artifact $artifactRoot
                    $symbolName = [IO.Path]::GetFileName($symbolPath)
                    if ([IO.Path]::GetDirectoryName($symbolPath) -ne [IO.Path]::GetFullPath($artifactRoot) -or
                        -not $expectedSymbols.ContainsKey($symbolName) -or $symbolName -in $retainedSymbolNames -or
                        (Get-FileHash -LiteralPath $symbolPath -Algorithm SHA256).Hash -ne $symbol.sha256 -or
                        (Get-FileHash -LiteralPath $expectedSymbols[$symbolName] -Algorithm SHA256).Hash -ne $symbol.sha256) {
                        throw "$component finalized mapping/symbol identity or hash changed."
                    }
                    $retainedSymbolNames += $symbolName
                }
                foreach ($name in $expectedSymbols.Keys) {
                    if ((Test-Path -LiteralPath $expectedSymbols[$name] -PathType Leaf) -and $name -notin $retainedSymbolNames) {
                        throw "$component generated mapping/symbols are missing from the previous finalized metadata."
                    }
                }
                $retained | Add-Member -NotePropertyName 'finalArtifactRetained' -NotePropertyValue $true -Force
                $retained | Add-Member -NotePropertyName 'compiledAabReusedOnResume' -NotePropertyValue $true -Force
                $retained | Add-Member -NotePropertyName 'reverifiedAtUtc' -NotePropertyValue ([DateTimeOffset]::UtcNow.ToString('o')) -Force
                $results = @($results | Where-Object { $_.component -ne $component }) + @($retained)
                Save-RunManifest
                Write-Host "PRODUCTION_ARTIFACT_REVERIFIED component=$component sha=$($stage.sha) artifact=$retainedPath unchanged=true"
                continue
            }
            $ready = $plan.registrationStatus -eq 'registered-verified'
            $qualifier = if ($ready) { 'release' } else { 'pending-upload-key-reset' }
            $filename = "mili-$component-main-$($stage.sha.Substring(0, 7))-v$($plan.versionCode)-$qualifier.aab"
            $artifact = Join-Path $artifactRoot $filename
            if (Test-Path -LiteralPath $artifact) { throw 'Refusing to replace a production artifact.' }
            $aabHash = (Get-FileHash -LiteralPath $aab -Algorithm SHA256).Hash
            Copy-Item -LiteralPath $aab -Destination $artifact
            if ((Get-FileHash -LiteralPath $artifact -Algorithm SHA256).Hash -ne $aabHash) {
                throw "$component copied AAB checksum mismatch."
            }
            $symbols = @()
            foreach ($symbol in @(
                @{ relative = 'build\app\outputs\mapping\release\mapping.txt'; name = "mili-$component-v$($plan.versionCode)-mapping.txt" },
                @{ relative = 'build\app\outputs\native-debug-symbols\release\native-debug-symbols.zip'; name = "mili-$component-v$($plan.versionCode)-native-debug-symbols.zip" }
            )) {
                $symbolSource = Join-Path $source $symbol.relative
                if (Test-Path -LiteralPath $symbolSource -PathType Leaf) {
                    $symbolArtifact = Join-Path $artifactRoot $symbol.name
                    if (Test-Path -LiteralPath $symbolArtifact) { throw 'Refusing to replace a symbol artifact.' }
                    $symbolHash = (Get-FileHash -LiteralPath $symbolSource -Algorithm SHA256).Hash
                    Copy-Item -LiteralPath $symbolSource -Destination $symbolArtifact
                    if ((Get-FileHash -LiteralPath $symbolArtifact -Algorithm SHA256).Hash -ne $symbolHash) {
                        throw 'Copied release symbol checksum mismatch.'
                    }
                    $symbols += [pscustomobject]@{ artifact = $symbolArtifact; sha256 = $symbolHash }
                }
            }
            $results += [pscustomobject]@{
                component = $component; sha = $stage.sha; package = $manifestPackage
                versionCode = $plan.versionCode; versionName = $manifestName
                gitVersionCode = $plan.gitVersionCode; confirmedPlayMaximumVersionCode = $plan.playMaximumVersionCode
                minSdk = $minSdk; targetSdk = $targetSdk; debuggable = $false; abis = $requiredAbis
                signingRunId = $SigningRunId; uploadCertificateSha256 = $plan.fingerprint
                registrationStatus = $plan.registrationStatus; playRegistrationEvidence = $plan.registrationEvidence
                safeToUploadToExistingPlayApp = $ready; source = $source; stageArchiveSha256 = $stage.archiveSha256
                artifact = $artifact; bytes = $built.Length; sha256 = $aabHash; symbols = @($symbols)
                lockSha256 = $lockHash; startedAt = $started.ToString('o'); completedAtUtc = [DateTimeOffset]::UtcNow.ToString('o')
                compiledAabReused = [bool]$compiledAabReused; stagedAt = $stage.stagedAt
                compiledAabCreatedAt = $built.CreationTime.ToString('o'); compiledAabWrittenAt = $built.LastWriteTime.ToString('o')
                buildLog = $logPath; verificationLog = $verifyLog
                verified = @('jarsigner', 'new-upload-certificate', 'bundletool-validate', 'manifest', 'all-flutter-release-abis', 'no-private-config-entries')
            }
            Save-RunManifest
            Write-Host "PRODUCTION_BUILD_VERIFIED component=$component sha=$($stage.sha) registration=$($plan.registrationStatus) safeToUpload=$ready artifact=$artifact"
        }
        finally {
            if ($locationPushed) { Pop-Location }
            try {
                if ($linkCreated) {
                    $validatedLink = Assert-WithinPath $signingLink $androidRoot
                    if ($validatedLink -ne [IO.Path]::GetFullPath((Join-Path $androidRoot 'key.properties'))) {
                        throw 'Refusing to remove an unexpected signing link.'
                    }
                    if (Test-Path -LiteralPath $validatedLink -PathType Leaf) {
                        # Remove only the known staging hard link, never its canonical private target.
                        Remove-Item -LiteralPath $validatedLink -Force
                    }
                }
            }
            finally {
                # Stop Gradle even when signing-link cleanup itself reports a failure.
                Push-Location $androidRoot
                $savedPreference = $ErrorActionPreference
                try {
                    $ErrorActionPreference = 'Continue'
                    & .\gradlew.bat --stop 2>&1 | ForEach-Object { Write-Host "$_" }
                }
                finally {
                    $ErrorActionPreference = $savedPreference
                    Pop-Location
                }
            }
        }
    }
    $manifest.status = 'verified-builds-complete'
    $manifest.safeToUploadAllToExistingPlayApps = @($results | Where-Object { -not $_.safeToUploadToExistingPlayApp }).Count -eq 0
    $manifest.completedAtUtc = [DateTimeOffset]::UtcNow.ToString('o')
    Save-RunManifest
    Write-Host "ALL_PRODUCTION_AABS=VERIFIED safeToUploadAll=$($manifest.safeToUploadAllToExistingPlayApps) manifest=$manifestPath"
}
catch {
    $manifest.status = 'failed'
    $manifest.failedAtUtc = [DateTimeOffset]::UtcNow.ToString('o')
    Save-RunManifest
    throw
}
finally {
    $env:TEMP = $oldTemp
    $env:TMP = $oldTmp
    $env:JAVA_HOME = $oldJavaHome
}
