[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$WorkspaceRoot,
    [string]$AliasDrive = 'M:'
)

$physicalWorkspace = (Resolve-Path -LiteralPath $WorkspaceRoot).Path
$workspace = $physicalWorkspace

if ($physicalWorkspace -match '[^\x00-\x7F]') {
    $aliasRoot = "$AliasDrive\"
    if (-not (Test-Path -LiteralPath $aliasRoot)) {
        & subst $AliasDrive $physicalWorkspace
        if ($LASTEXITCODE -ne 0) {
            throw "Unable to mount the $AliasDrive workspace alias."
        }
    }

    $expectedRepository = Join-Path $aliasRoot 'repos\backend-admin\.git'
    if (-not (Test-Path -LiteralPath $expectedRepository)) {
        throw "$AliasDrive is already in use and does not point to the MILI workspace."
    }
    $workspace = $aliasRoot
}

$buildRoot = Join-Path $workspace 'build-workspace'
$cacheRoot = Join-Path $buildRoot 'cache'
$toolsRoot = Join-Path $workspace 'tools'
$directories = @(
    (Join-Path $cacheRoot 'pub'),
    (Join-Path $cacheRoot 'gradle'),
    (Join-Path $cacheRoot 'yarn'),
    (Join-Path $cacheRoot 'npm'),
    (Join-Path $cacheRoot 'composer'),
    (Join-Path $cacheRoot 'playwright'),
    (Join-Path $buildRoot 'tmp'),
    (Join-Path $buildRoot 'logs')
)
foreach ($directory in $directories) {
    New-Item -ItemType Directory -Path $directory -Force | Out-Null
}

$env:MILI_WORKSPACE = $workspace
$env:MILI_PHYSICAL_WORKSPACE = $physicalWorkspace
$env:MILI_REPOS = Join-Path $workspace 'repos'
$env:MILI_BUILD_ROOT = $buildRoot
$env:MILI_ARTIFACT_ROOT = Join-Path $workspace 'artifacts'
$env:FLUTTER_ROOT = Join-Path $toolsRoot 'flutter-3.47.5'
$env:PUB_CACHE = Join-Path $cacheRoot 'pub'
$env:GRADLE_USER_HOME = Join-Path $cacheRoot 'gradle'
$env:YARN_CACHE_FOLDER = Join-Path $cacheRoot 'yarn'
$env:npm_config_cache = Join-Path $cacheRoot 'npm'
$env:COMPOSER_HOME = Join-Path $cacheRoot 'composer'
$env:COMPOSER_CACHE_DIR = Join-Path $cacheRoot 'composer\cache'
$env:PLAYWRIGHT_BROWSERS_PATH = Join-Path $cacheRoot 'playwright'
$env:TEMP = Join-Path $buildRoot 'tmp'
$env:TMP = $env:TEMP
$env:FLUTTER_SUPPRESS_ANALYTICS = 'true'
$env:NEXT_TELEMETRY_DISABLED = '1'
$env:CI = 'true'

$phpHome = Join-Path $toolsRoot 'php-8.3.36-nts'
$composerHome = Join-Path $toolsRoot 'composer'
$env:PHPRC = Join-Path $phpHome 'php.ini'
$env:PATH = (@(
    (Join-Path $env:FLUTTER_ROOT 'bin'),
    $phpHome,
    $composerHome,
    $env:PATH
) -join [IO.Path]::PathSeparator)

Write-Host "MILI workspace: $workspace"
Write-Host "Build root:     $buildRoot"

