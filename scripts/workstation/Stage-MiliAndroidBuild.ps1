[CmdletBinding()]
param(
    [string]$WorkspaceRoot = $env:MILI_PHYSICAL_WORKSPACE,
    [Parameter(Mandatory = $true)]
    [ValidateSet('production', 'debug')]
    [string]$Mode,
    [Parameter(Mandatory = $true)]
    [ValidateSet('customer', 'vendor', 'delivery')]
    [string]$Component,
    [ValidatePattern('^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$')]
    [string]$RunId = (Get-Date -Format 'yyyyMMdd-HHmmss')
)

$ErrorActionPreference = 'Stop'
if (-not $WorkspaceRoot) { throw 'Pass -WorkspaceRoot or load the MILI environment first.' }
$physicalWorkspace = (Resolve-Path -LiteralPath $WorkspaceRoot).Path
. (Join-Path $PSScriptRoot 'Enter-MiliEnvironment.ps1') -WorkspaceRoot $physicalWorkspace
$workspace = $env:MILI_WORKSPACE
$repoPath = Join-Path $workspace "repos\$Component-app"

function Get-GitText([string[]]$Arguments) {
    $value = & git -C $repoPath @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Git preflight failed for $Component." }
    return ($value -join "`n").Trim()
}

$branch = Get-GitText @('branch', '--show-current')
$head = Get-GitText @('rev-parse', 'HEAD')
$origin = Get-GitText @('rev-parse', 'origin/main')
$dirty = Get-GitText @('status', '--porcelain', '--untracked-files=normal')
$liveMain = Get-GitText @('ls-remote', '--exit-code', '--heads', 'origin', 'refs/heads/main')
if ($liveMain -notmatch '^([0-9a-f]{40})\s+refs/heads/main$') {
    throw "Cannot establish live origin/main for $Component."
}
$liveSha = $Matches[1]
if ($branch -ne 'main' -or $dirty -or $head -ne $origin -or $head -ne $liveSha) {
    throw "$Component must be clean main exactly equal to stored and live origin/main."
}

$runRoot = Join-Path $workspace "build-workspace\$Mode\android\$RunId"
$workRoot = Join-Path $runRoot $Component
$sourceRoot = Join-Path $workRoot 'source'
$artifactRoot = Join-Path $workspace "artifacts\$Mode\android\$RunId"
$logRoot = Join-Path $workRoot 'logs'
if (Test-Path -LiteralPath $workRoot) {
    throw "Refusing to overwrite an existing staging directory: $workRoot"
}
$expectedPrefix = [IO.Path]::GetFullPath((Join-Path $workspace 'build-workspace')) + '\'
if (-not ([IO.Path]::GetFullPath($workRoot)).StartsWith($expectedPrefix, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Build staging escaped the MILI build workspace.'
}
New-Item -ItemType Directory -Path $sourceRoot, $artifactRoot, $logRoot -Force | Out-Null
$archivePath = Join-Path $workRoot 'source.zip'

# Exclude private material before the ZIP is created, not after extraction.
# Google/Firebase application config is public client config and is retained.
$excludedNames = @(
    '*.jks', '*.keystore', '*.p12', '*.pfx', '*.pem', '*.key',
    '.env*', 'key.properties', 'id_rsa*', 'id_ed25519*'
)
# Credential-related class names are legitimate dependency source, for example
# http_auth_credentials_database.dart. Exclude credential DATA/configuration,
# not all source files whose name happens to contain "credentials".
foreach ($stem in @('*credentials*', '*service-account*', '*service_account*', '*serviceAccount*', '*private-key*', '*private_key*', '*secrets*')) {
    foreach ($extension in @('json', 'yaml', 'yml', 'properties', 'toml', 'ini')) {
        $excludedNames += "$stem.$extension"
    }
}
$excludedDirectoryNames = @('.secrets', 'secrets', 'credentials', 'service-accounts', 'service_accounts', 'private-keys', 'private_keys')
$pathspecs = @('.')
foreach ($name in $excludedNames) {
    $pathspecs += ":(glob,icase,exclude)**/$name"
    $pathspecs += ":(glob,icase,exclude)**/$name/**"
}
foreach ($directoryName in $excludedDirectoryNames) {
    $pathspecs += ":(glob,icase,exclude)**/$directoryName"
    $pathspecs += ":(glob,icase,exclude)**/$directoryName/**"
}
& git -C $repoPath archive --format=zip "--output=$archivePath" $head -- @pathspecs
if ($LASTEXITCODE -ne 0) {
    throw 'Git archive with exclusion pathspecs failed; no fallback export is allowed.'
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [IO.Compression.ZipFile]::OpenRead($archivePath)
$entryCount = 0
$sourcePrefix = [IO.Path]::GetFullPath($sourceRoot) + '\'
try {
    foreach ($entry in $zip.Entries) {
        $name = $entry.FullName.Replace('\', '/')
        $parts = $name.TrimEnd('/').Split('/')
        if (-not $name -or $name.StartsWith('/') -or $name.Contains(':') -or
            ($parts | Where-Object { $_ -eq '..' -or $_ -eq '.' -or $_ -eq '.git' })) {
            throw "Unsafe ZIP entry rejected: $name"
        }
        foreach ($part in $parts) {
            if ($excludedDirectoryNames -contains $part) {
                throw "Private-material ZIP directory rejected: $name"
            }
            foreach ($pattern in $excludedNames) {
                if ($part -like $pattern) { throw "Private-material ZIP entry rejected: $name" }
            }
        }
        if ((($entry.ExternalAttributes -shr 16) -band 61440) -eq 40960) {
            throw "Symbolic-link ZIP entry rejected: $name"
        }
        $destination = [IO.Path]::GetFullPath((Join-Path $sourceRoot $name))
        if (-not $destination.StartsWith($sourcePrefix, [StringComparison]::OrdinalIgnoreCase)) {
            throw "ZIP entry escaped source directory: $name"
        }
        $entryCount++
    }
}
finally { $zip.Dispose() }
if ($entryCount -eq 0) { throw 'The source archive is empty.' }
[IO.Compression.ZipFile]::ExtractToDirectory($archivePath, $sourceRoot)
if (-not (Test-Path -LiteralPath (Join-Path $sourceRoot 'pubspec.lock') -PathType Leaf)) {
    throw 'Staged source has no locked Flutter dependencies.'
}
if ((Get-GitText @('rev-parse', 'HEAD')) -ne $head -or
    (Get-GitText @('status', '--porcelain', '--untracked-files=normal'))) {
    throw 'The original repository changed during staging.'
}

$metadata = [pscustomobject]@{
    mode = $Mode
    component = $Component
    runId = $RunId
    sha = $head
    package = "ge.mili.$Component"
    repository = $repoPath
    source = $sourceRoot
    work = $workRoot
    build = Join-Path $sourceRoot 'build'
    artifact = $artifactRoot
    log = $logRoot
    archive = $archivePath
    archiveSha256 = (Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash
    archiveEntries = $entryCount
    excludedNames = $excludedNames
    excludedDirectoryNames = $excludedDirectoryNames
    stagedAt = (Get-Date).ToString('o')
}
$metadata | ConvertTo-Json -Depth 4 |
    Set-Content -LiteralPath (Join-Path $workRoot 'stage.json') -Encoding UTF8
return $metadata
