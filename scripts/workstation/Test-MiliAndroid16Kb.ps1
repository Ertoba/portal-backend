# Static ELF and packaging-intent checks only; not APK ZIP or runtime acceptance.
# https://developer.android.com/guide/practices/page-sizes
# https://github.com/google/bundletool/blob/1.18.3/src/main/proto/config.proto
# RELRO tail overlap is advisory: headers alone do not locate writable objects.
# https://android.googlesource.com/platform/bionic/+/main/linker/linker_phdr.cpp
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string]$WorkspaceRoot,
    [Parameter(Mandatory = $true)][string]$BundlePath,
    [Parameter(Mandatory = $true)][string]$ReportDirectory,
    [string]$ReadelfPath = "$env:LOCALAPPDATA\Android\sdk\ndk\28.2.13676358\toolchains\llvm\prebuilt\windows-x86_64\bin\llvm-readelf.exe",
    [string]$BundletoolJar,
    [ValidatePattern('^[A-Fa-f0-9]{64}$')]
    [string]$BundletoolSha256 = 'A099CFA1543F55593BC2ED16A70A7C67FE54B1747BB7301F37FDFD6D91028E29'
)
$ErrorActionPreference = 'Stop'
$physical = (Resolve-Path -LiteralPath $WorkspaceRoot).Path.TrimEnd('\')
. (Join-Path $PSScriptRoot 'Enter-MiliEnvironment.ps1') -WorkspaceRoot $physical
$workspace = $env:MILI_WORKSPACE.TrimEnd('\')
function Get-WorkspacePath([string]$Path) {
    $full = [IO.Path]::GetFullPath($Path)
    foreach ($root in @($physical, $workspace)) {
        if ($full.StartsWith($root + '\', [StringComparison]::OrdinalIgnoreCase)) {
            return Join-Path $workspace $full.Substring($root.Length + 1)
        }
    }
    throw 'Bundle/report/tool paths must remain inside the MILI workspace.'
}
$bundle = Get-WorkspacePath $BundlePath
$report = Get-WorkspacePath $ReportDirectory
if (-not $bundle.EndsWith('.aab', [StringComparison]::OrdinalIgnoreCase) -or
    -not (Test-Path -LiteralPath $bundle -PathType Leaf)) { throw 'An existing final AAB is required.' }
$production = Join-Path $workspace 'build-workspace\production'
if (-not $report.StartsWith($production + '\', [StringComparison]::OrdinalIgnoreCase) -or
    (Test-Path -LiteralPath $report)) { throw 'Use a new verification directory below build-workspace/production.' }
if (-not $BundletoolJar) { $BundletoolJar = Join-Path $workspace 'tools\bundletool\bundletool-all-1.18.3.jar' }
$BundletoolJar = Get-WorkspacePath $BundletoolJar
foreach ($tool in @($ReadelfPath, $BundletoolJar)) {
    if (-not (Test-Path -LiteralPath $tool -PathType Leaf)) { throw "Required verification tool is missing: $tool" }
}
if ((Split-Path -Leaf $ReadelfPath) -ne 'llvm-readelf.exe') { throw 'Supply the trusted Android NDK llvm-readelf executable.' }
if ((Get-FileHash -LiteralPath $BundletoolJar -Algorithm SHA256).Hash -ne $BundletoolSha256) {
    throw 'Bundletool does not match the trusted release SHA-256.'
}
$java = Join-Path $env:ProgramFiles 'Android\Android Studio\jbr\bin\java.exe'
if (-not (Test-Path -LiteralPath $java -PathType Leaf)) { throw 'The existing Android Studio JBR is required.' }
$initialHash = (Get-FileHash -LiteralPath $bundle -Algorithm SHA256).Hash
$nativeRoot = Join-Path $report 'native'
New-Item -ItemType Directory -Path $nativeRoot -Force | Out-Null
function Invoke-BundleDump([string]$Kind) {
    $extra = @()
    if ($Kind -eq 'manifest') { $extra = @('--module=base') }
    $output = & $java "-Djava.io.tmpdir=$env:TEMP" -jar $BundletoolJar dump $Kind "--bundle=$bundle" @extra
    if ($LASTEXITCODE -ne 0) { throw "Bundletool dump $Kind failed." }
    return ($output -join "`n")
}
$configText = Invoke-BundleDump 'config'
$configText | Set-Content -LiteralPath (Join-Path $report 'bundle-config.json') -Encoding UTF8
$config = $configText | ConvertFrom-Json
$manifestText = Invoke-BundleDump 'manifest'
$manifestText | Set-Content -LiteralPath (Join-Path $report 'base-manifest.xml') -Encoding UTF8
[xml]$manifest = $manifestText
$extract = $manifest.manifest.application.GetAttribute('extractNativeLibs', 'http://schemas.android.com/apk/res/android')
$nativePolicy = $config.optimizations.uncompressNativeLibraries
$alignment = [string]$nativePolicy.alignment
$nativeGlobs = @($config.compression.uncompressedGlob)
$results = @()
$seen = New-Object 'Collections.Generic.HashSet[string]' ([StringComparer]::OrdinalIgnoreCase)
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [IO.Compression.ZipFile]::OpenRead($bundle)
try {
    foreach ($entry in $zip.Entries) {
        if (-not $entry.FullName.EndsWith('.so', [StringComparison]::OrdinalIgnoreCase)) { continue }
        $stream = $entry.Open()
        try { $header = New-Object byte[] 5; $count = $stream.Read($header, 0, 5) } finally { $stream.Dispose() }
        if ($count -ne 5 -or $header[0] -ne 127 -or $header[1] -ne 69 -or $header[2] -ne 76 -or $header[3] -ne 70) {
            throw "A .so entry is not a valid ELF: $($entry.FullName)"
        }
        if ($header[4] -eq 1) { continue }
        if ($header[4] -ne 2) { throw 'Unknown ELF class.' }
        $name = $entry.FullName.Replace('\', '/')
        $parts = $name.Split('/')
        if ($name.StartsWith('/') -or $name.Contains(':') -or ($parts | Where-Object { $_ -eq '..' -or $_ -eq '.' -or -not $_ }) -or
            ((($entry.ExternalAttributes -shr 16) -band 61440) -eq 40960)) { throw "Unsafe native entry: $name" }
        $target = [IO.Path]::GetFullPath((Join-Path $nativeRoot $name))
        if (-not $target.StartsWith($nativeRoot + '\', [StringComparison]::OrdinalIgnoreCase) -or -not $seen.Add($target)) {
            throw "Escaped or duplicate native path: $name"
        }
        New-Item -ItemType Directory -Path (Split-Path -Parent $target) -Force | Out-Null
        [IO.Compression.ZipFileExtensions]::ExtractToFile($entry, $target, $false)
        $headers = & $ReadelfPath --wide --program-headers $target
        if ($LASTEXITCODE -ne 0) { throw "readelf failed: $name" }
        $headers | Set-Content -LiteralPath ($target + '.headers.txt') -Encoding UTF8
        $segments = @(foreach ($line in $headers) {
            if ($line -match '^\s*(LOAD|GNU_RELRO)\s+') {
                $f = $line.Trim() -split '\s+'
                [pscustomobject]@{ type = $f[0]; offset = [Convert]::ToUInt64($f[1].Substring(2), 16)
                    address = [Convert]::ToUInt64($f[2].Substring(2), 16); size = [Convert]::ToUInt64($f[5].Substring(2), 16)
                    flags = ($f[6..($f.Length - 2)] -join ''); alignment = [Convert]::ToUInt64($f[-1].Substring(2), 16) }
            }
        })
        $loads = @($segments | Where-Object { $_.type -eq 'LOAD' })
        $badLoads = @($loads | Where-Object { $_.alignment -lt 16384 -or ($_.address % 16384) -ne ($_.offset % 16384) })
        $relroWarnings = @(foreach ($relro in @($segments | Where-Object { $_.type -eq 'GNU_RELRO' })) {
            $end = $relro.address + $relro.size
            if ($end % 16384 -ne 0) {
                $loads | Where-Object { $_.flags.Contains('W') -and $_.address -le $relro.address -and ($_.address + $_.size) -gt $end }
            }
        })
        $results += [pscustomobject]@{ entry = $name; sha256 = (Get-FileHash -LiteralPath $target -Algorithm SHA256).Hash
            loadAlignments = @($loads | ForEach-Object { $_.alignment }); relroPotentialTailOverlap = ($relroWarnings.Count -gt 0)
            pass = ($loads.Count -gt 0 -and $badLoads.Count -eq 0) }
    }
}
finally { $zip.Dispose() }
if ((Get-FileHash -LiteralPath $bundle -Algorithm SHA256).Hash -ne $initialHash) { throw 'AAB changed during verification.' }
$explicitUncompressedNative = @($nativeGlobs | Where-Object { $_ -match 'lib|\.so' }).Count -gt 0
$packaging = 'Unverified packaging intent; generated APK inspection required.'
if ($alignment -in @('PAGE_ALIGNMENT_16K', 'PAGE_ALIGNMENT_64K')) { $packaging = 'At least 16KB alignment requested for generated uncompressed native libraries.' }
elseif ($extract -eq 'true' -and -not $nativePolicy.enabled -and -not $explicitUncompressedNative) {
    $packaging = 'Legacy compressed/extracted-native alternative requested; generated APK inspection still required.'
}
$passed = $results.Count -gt 0 -and @($results | Where-Object { -not $_.pass }).Count -eq 0
$result = [pscustomobject]@{ bundle = $bundle; bundleSha256 = $initialHash; readelf = $ReadelfPath
    readelfSha256 = (Get-FileHash -LiteralPath $ReadelfPath -Algorithm SHA256).Hash; bundletoolSha256 = $BundletoolSha256
    alignment = $alignment; uncompressNativeLibraries = $nativePolicy; uncompressedGlobs = $nativeGlobs
    manifestExtractNativeLibs = $extract; packagingIntent = $packaging; libraries = $results; staticElfPass = $passed
    relroPolicy = 'Header-only tail-overlap advisory; writable-object placement/runtime behavior not established; not an ELF alignment gate.'
    apkZipAlignmentVerified = $false; runtime16KbTested = $false; playAcceptanceVerified = $false
    scope = 'ELF64 and AAB packaging intent only; AAB ZIP offsets do not prove generated APK alignment.' }
$result | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $report '16kb-static-report.json') -Encoding UTF8
if (-not $passed) { throw "Static ELF64 16KB verification failed; see $report" }
return $result
