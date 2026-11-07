[CmdletBinding()]
param(
    [string]$WorkspaceRoot = $env:MILI_PHYSICAL_WORKSPACE,
    [ValidateSet('customer', 'vendor', 'delivery')]
    [string[]]$Components = @('customer', 'vendor', 'delivery'),
    [ValidatePattern('^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$')]
    [string]$RunId = (Get-Date -Format 'yyyyMMdd-HHmmss')
)

$ErrorActionPreference = 'Stop'
if (-not $WorkspaceRoot) { throw 'Pass -WorkspaceRoot or load the MILI environment first.' }
if (@($Components | Select-Object -Unique).Count -ne $Components.Count) {
    throw 'Each component may be built only once per run.'
}
$results = @()
$manifestPath = $null
foreach ($component in $Components) {
    $stage = & (Join-Path $PSScriptRoot 'Stage-MiliAndroidBuild.ps1') `
        -WorkspaceRoot $WorkspaceRoot -Mode debug -Component $component -RunId $RunId
    $head = $stage.sha
    $sourcePath = $stage.source
    $logPath = Join-Path $stage.log 'flutter-debug.log'
    $manifestPath = Join-Path $stage.artifact 'debug-build-manifest.json'
    $started = Get-Date
    Write-Host "BUILD_START component=$component sha=$head source=$sourcePath"
    Push-Location $sourcePath
    try {
        # PS5.1 wraps native stderr warnings as errors; exit codes decide success.
        $ErrorActionPreference = 'Continue'
        & flutter pub get --enforce-lockfile 2>&1 | Tee-Object -FilePath $logPath
        $dependencyExit = $LASTEXITCODE
        $ErrorActionPreference = 'Stop'
        if ($dependencyExit -ne 0) { throw "$component locked dependencies failed; see $logPath" }
        $lockHash = (Get-FileHash -LiteralPath 'pubspec.lock' -Algorithm SHA256).Hash
        $ErrorActionPreference = 'Continue'
        & flutter build apk --debug --no-pub --target-platform android-arm64 2>&1 |
            Tee-Object -FilePath $logPath -Append
        $buildExit = $LASTEXITCODE
        $ErrorActionPreference = 'Stop'
        if ($buildExit -ne 0) { throw "$component debug build failed; see $logPath" }
        if ((Get-FileHash -LiteralPath 'pubspec.lock' -Algorithm SHA256).Hash -ne $lockHash) {
            throw "$component dependency lock changed during compilation."
        }
        $apk = Join-Path $sourcePath 'build\app\outputs\flutter-apk\app-debug.apk'
        if (-not (Test-Path -LiteralPath $apk -PathType Leaf)) { throw "$component APK is missing." }
        $sourceApk = Get-Item -LiteralPath $apk
        if ($sourceApk.LastWriteTime -lt $started.AddSeconds(-2)) { throw "$component APK is stale." }
        $hash = (Get-FileHash -Algorithm SHA256 -LiteralPath $apk).Hash
        $filename = "mili-$component-main-$($head.Substring(0, 7))-arm64-debug.apk"
        $artifact = Join-Path $stage.artifact $filename
        if (Test-Path -LiteralPath $artifact) { throw "Refusing to replace $artifact" }
        Copy-Item -LiteralPath $apk -Destination $artifact
        if ((Get-FileHash -Algorithm SHA256 -LiteralPath $artifact).Hash -ne $hash) {
            throw "$component artifact checksum mismatch."
        }
        $results += [pscustomobject]@{
            component = $component
            sha = $head
            package = $stage.package
            architecture = 'android-arm64'
            source = $sourcePath
            artifact = $artifact
            bytes = $sourceApk.Length
            sha256 = $hash
            startedAt = $started.ToString('o')
            completedAt = (Get-Date).ToString('o')
            log = $logPath
        }
        ConvertTo-Json -InputObject @($results) -Depth 4 |
            Set-Content -LiteralPath $manifestPath -Encoding UTF8
        Write-Host "BUILD_SUCCESS component=$component sha=$head bytes=$($sourceApk.Length) artifact=$artifact"
    }
    finally {
        $ErrorActionPreference = 'Stop'
        Pop-Location
        Push-Location (Join-Path $sourcePath 'android')
        try { & .\gradlew.bat --stop | Out-Host } finally { Pop-Location }
    }
}
Write-Host "ALL_DEBUG_BUILDS=SUCCESS manifest=$manifestPath"
