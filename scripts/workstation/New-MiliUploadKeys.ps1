[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$WorkspaceRoot,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z0-9][A-Za-z0-9._-]{0,79}$')]
    [string]$RunId,

    [ValidateSet('customer', 'vendor', 'delivery')]
    [string[]]$Components = @('customer', 'vendor', 'delivery'),

    [string]$KeytoolPath
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# This command creates NEW upload keys only. It does not recover an old key,
# register certificates with Google Play, reset a Play upload key, or change
# application signing. A generated key is unusable for an existing Play app
# until its upload certificate has been accepted by that app's Play Console.
if ($env:OS -ne 'Windows_NT') {
    throw 'Windows owner/SYSTEM ACL enforcement is required for this command.'
}
if ($Components.Count -eq 0 -or @($Components | Select-Object -Unique).Count -ne $Components.Count) {
    throw 'Specify at least one component, without duplicates.'
}

$physicalWorkspace = (Resolve-Path -LiteralPath $WorkspaceRoot).ProviderPath.TrimEnd('\')
if (-not (Test-Path -LiteralPath (Join-Path $physicalWorkspace 'repos\backend-admin\.git'))) {
    throw 'WorkspaceRoot must be the physical MILI workspace containing repos/backend-admin.'
}
. (Join-Path $PSScriptRoot 'Enter-MiliEnvironment.ps1') -WorkspaceRoot $physicalWorkspace

# A Java properties file must not contain an unescaped Unicode Windows path.
# Require the exact M: SUBST mapping rather than trusting an existing M: drive.
# Native subst.exe stdout can replace Georgian path characters with '?' under
# Windows PowerShell 5.1. Query the DOS device with the Unicode Win32 API instead.
if (-not ('MiliUploadKeyNativeMethods' -as [type])) {
    Add-Type -TypeDefinition @'
using System;
using System.Runtime.InteropServices;
using System.Text;
public static class MiliUploadKeyNativeMethods {
    [DllImport("kernel32.dll", EntryPoint = "QueryDosDeviceW", CharSet = CharSet.Unicode, SetLastError = true)]
    public static extern uint QueryDosDevice(string deviceName, StringBuilder targetPath, int maxLength);
}
'@
}
$deviceTarget = [Text.StringBuilder]::new(32768)
if ([MiliUploadKeyNativeMethods]::QueryDosDevice('M:', $deviceTarget, $deviceTarget.Capacity) -eq 0) {
    throw 'Unable to verify the M: workspace alias with QueryDosDeviceW.'
}
$deviceMapping = $deviceTarget.ToString()
if (-not $deviceMapping.StartsWith('\??\', [StringComparison]::Ordinal)) {
    throw 'The environment loader must provide M: as a SUBST alias to this workspace.'
}
$mappedWorkspace = (Resolve-Path -LiteralPath $deviceMapping.Substring(4)).ProviderPath.TrimEnd('\')
if (-not [string]::Equals($physicalWorkspace, $mappedWorkspace, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'M: does not point to the requested physical workspace.'
}

if (-not $KeytoolPath) {
    if ($env:JAVA_HOME -and (Test-Path -LiteralPath (Join-Path $env:JAVA_HOME 'bin\keytool.exe') -PathType Leaf)) {
        $KeytoolPath = Join-Path $env:JAVA_HOME 'bin\keytool.exe'
    } else {
        $keytoolCommand = Get-Command keytool.exe -CommandType Application -ErrorAction SilentlyContinue
        if ($keytoolCommand) {
            $KeytoolPath = $keytoolCommand.Source
        }
    }
}
if (-not $KeytoolPath -or -not (Test-Path -LiteralPath $KeytoolPath -PathType Leaf)) {
    throw 'Pass -KeytoolPath pointing to a trusted JDK keytool.exe, or configure JAVA_HOME.'
}
$KeytoolPath = (Resolve-Path -LiteralPath $KeytoolPath).ProviderPath

$ownerSid = [Security.Principal.WindowsIdentity]::GetCurrent().User
$systemSid = [Security.Principal.SecurityIdentifier]::new('S-1-5-18')
$allowedSids = @($ownerSid.Value, $systemSid.Value | Select-Object -Unique)
$inheritance = [Security.AccessControl.InheritanceFlags]::ContainerInherit -bor [Security.AccessControl.InheritanceFlags]::ObjectInherit
$fullControl = [Security.AccessControl.FileSystemRights]::FullControl

function Assert-WorkspacePath {
    param([Parameter(Mandatory = $true)][string]$Path)

    $fullPath = [IO.Path]::GetFullPath($Path)
    if (-not $fullPath.StartsWith($physicalWorkspace + '\', [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Refusing a generated path outside the physical MILI workspace.'
    }
    $current = $fullPath
    while ($current.Length -ge $physicalWorkspace.Length) {
        if (Test-Path -LiteralPath $current) {
            if (((Get-Item -LiteralPath $current -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
                throw 'Refusing a reparse point within a generated-path ancestor.'
            }
        }
        if ([string]::Equals($current, $physicalWorkspace, [StringComparison]::OrdinalIgnoreCase)) {
            break
        }
        $current = Split-Path -Parent $current
    }
}

function Assert-PrivateDirectory {
    param([Parameter(Mandatory = $true)][string]$Path)

    $acl = Get-Acl -LiteralPath $Path
    if (-not $acl.AreAccessRulesProtected -or $acl.GetOwner([Security.Principal.SecurityIdentifier]).Value -ne $ownerSid.Value) {
        throw 'A private signing directory does not have the required owner-only/SYSTEM protected ACL.'
    }
    $rules = @($acl.GetAccessRules($true, $true, [Security.Principal.SecurityIdentifier]))
    if ($rules.Count -ne $allowedSids.Count) {
        throw 'Unexpected access entries on a private signing directory.'
    }
    foreach ($sid in $allowedSids) {
        $matchingRules = @($rules | Where-Object { $_.IdentityReference.Value -eq $sid })
        if ($matchingRules.Count -ne 1) {
            throw 'A required owner/SYSTEM private-directory access entry is missing or duplicated.'
        }
        $rule = $matchingRules[0]
        if ($rule.IsInherited -or $rule.AccessControlType -ne [Security.AccessControl.AccessControlType]::Allow -or
            $rule.FileSystemRights -ne $fullControl -or $rule.InheritanceFlags -ne $inheritance -or
            $rule.PropagationFlags -ne [Security.AccessControl.PropagationFlags]::None) {
            throw 'Private signing directory ACL verification failed.'
        }
    }
}

function New-PrivateDirectory {
    param([Parameter(Mandatory = $true)][string]$Path)

    Assert-WorkspacePath -Path $Path
    if (Test-Path -LiteralPath $Path) {
        if (-not (Test-Path -LiteralPath $Path -PathType Container)) {
            throw 'A private directory path is occupied by a file.'
        }
        Assert-PrivateDirectory -Path $Path
        return
    }
    $acl = [Security.AccessControl.DirectorySecurity]::new()
    $acl.SetAccessRuleProtection($true, $false)
    $acl.SetOwner($ownerSid)
    foreach ($sid in $allowedSids) {
        $identity = [Security.Principal.SecurityIdentifier]::new($sid)
        $rule = [Security.AccessControl.FileSystemAccessRule]::new(
            $identity, $fullControl, $inheritance,
            [Security.AccessControl.PropagationFlags]::None,
            [Security.AccessControl.AccessControlType]::Allow
        )
        $acl.AddAccessRule($rule)
    }
    # Apply the restricted ACL during directory creation, before keytool starts.
    $null = [IO.Directory]::CreateDirectory($Path, $acl)
    Assert-PrivateDirectory -Path $Path
}

function Invoke-KeytoolSafely {
    param(
        [Parameter(Mandatory = $true)][string]$Operation,
        [Parameter(Mandatory = $true)][string[]]$Arguments
    )

    # Capture native stderr, including JKS-format advisories, without leaking
    # command output or secret-bearing configuration into the console/logs.
    $previousPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $capturedOutput = @(& $KeytoolPath @Arguments 2>&1)
        $nativeExitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousPreference
    }
    if ($nativeExitCode -ne 0) {
        throw "Keytool operation '$Operation' failed with exit code $nativeExitCode. No sensitive output was printed."
    }
    $capturedOutput = $null
}

function Write-NewTextFile {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Text,
        [Parameter(Mandatory = $true)][Text.Encoding]$Encoding
    )

    $bytes = $Encoding.GetBytes($Text)
    $stream = $null
    try {
        $stream = [IO.File]::Open($Path, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::None)
        $stream.Write($bytes, 0, $bytes.Length)
    } finally {
        if ($stream) { $stream.Dispose() }
        [Array]::Clear($bytes, 0, $bytes.Length)
    }
}

function Get-CertificateFingerprint {
    param([byte[]]$RawData, [ValidateSet('SHA1', 'SHA256')][string]$Algorithm)

    $hasher = [Security.Cryptography.HashAlgorithm]::Create($Algorithm)
    try {
        return [BitConverter]::ToString($hasher.ComputeHash($RawData)).Replace('-', ':')
    } finally {
        $hasher.Dispose()
    }
}

$privateBase = Join-Path $physicalWorkspace 'ops\secrets\android-signing'
$publicBase = Join-Path $physicalWorkspace 'ops\config\android-signing'
$publicRun = Join-Path $publicBase $RunId
Assert-WorkspacePath -Path $privateBase
Assert-WorkspacePath -Path $publicRun
if (Test-Path -LiteralPath $publicRun) {
    throw 'RunId already has a public output directory. Existing runs are never overwritten.'
}
$targets = foreach ($component in $Components) {
    $privateComponent = Join-Path $privateBase $component
    $privateRun = Join-Path $privateComponent $RunId
    Assert-WorkspacePath -Path $privateRun
    if (Test-Path -LiteralPath $privateRun) {
        throw "RunId already has private output for $component. Existing keys are never overwritten or rotated."
    }
    [pscustomobject]@{
        Component = $component
        PrivateComponent = $privateComponent
        PrivateRun = $privateRun
        PublicDirectory = Join-Path $publicRun $component
    }
}

# Every private parent and run directory must be protected before ANY key is
# generated. A pre-existing insufficient ACL is a blocker, never silently fixed.
New-PrivateDirectory -Path $privateBase
foreach ($target in $targets) {
    New-PrivateDirectory -Path $target.PrivateComponent
    New-PrivateDirectory -Path $target.PrivateRun
}
$null = New-Item -ItemType Directory -Path $publicRun
$metadata = @()
$asciiEncoding = [Text.ASCIIEncoding]::new()
$utf8Encoding = [Text.UTF8Encoding]::new($false)

foreach ($target in $targets) {
    $component = $target.Component
    $null = New-Item -ItemType Directory -Path $target.PublicDirectory
    $privateRelative = 'ops/secrets/android-signing/{0}/{1}' -f $component, $RunId
    $keystoreAliasPath = 'M:/{0}/upload-keystore.jks' -f $privateRelative
    $propertiesPath = Join-Path $target.PrivateRun 'key.properties'
    $pemPath = Join-Path $target.PublicDirectory 'upload-certificate.pem'
    $derPath = Join-Path $target.PublicDirectory 'upload-certificate.der'
    $pemAliasPath = 'M:/ops/config/android-signing/{0}/{1}/upload-certificate.pem' -f $RunId, $component
    $derAliasPath = 'M:/ops/config/android-signing/{0}/{1}/upload-certificate.der' -f $RunId, $component
    $passwordEnvironmentName = 'MILI_UPLOAD_PASSWORD_' + [Guid]::NewGuid().ToString('N').ToUpperInvariant()
    $randomBytes = New-Object byte[] 32
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    $certificate = $null
    $password = $null
    $propertiesText = $null
    try {
        $rng.GetBytes($randomBytes)
        $password = [BitConverter]::ToString($randomBytes).Replace('-', '')
        [Environment]::SetEnvironmentVariable($passwordEnvironmentName, $password, [EnvironmentVariableTarget]::Process)
        Invoke-KeytoolSafely -Operation "generate $component" -Arguments @(
            '-genkeypair', '-noprompt', '-storetype', 'JKS',
            '-keystore', $keystoreAliasPath, '-alias', 'upload',
            '-keyalg', 'RSA', '-keysize', '3072', '-sigalg', 'SHA256withRSA',
            '-validity', '10000', '-dname', "CN=MILI $component Upload, OU=MILI Mobile, O=MILI, C=GE",
            '-storepass:env', $passwordEnvironmentName, '-keypass:env', $passwordEnvironmentName
        )
        Invoke-KeytoolSafely -Operation "export PEM $component" -Arguments @(
            '-exportcert', '-rfc', '-keystore', $keystoreAliasPath, '-alias', 'upload',
            '-storetype', 'JKS', '-storepass:env', $passwordEnvironmentName,
            '-file', $pemAliasPath
        )
        Invoke-KeytoolSafely -Operation "export DER $component" -Arguments @(
            '-exportcert', '-keystore', $keystoreAliasPath, '-alias', 'upload',
            '-storetype', 'JKS', '-storepass:env', $passwordEnvironmentName,
            '-file', $derAliasPath
        )
        $certificate = [Security.Cryptography.X509Certificates.X509Certificate2]::new($derPath)
        if ($certificate.PublicKey.Key.KeySize -ne 3072 -or $certificate.SignatureAlgorithm.Value -ne '1.2.840.113549.1.1.11') {
            throw 'Generated certificate does not match the required RSA-3072/SHA256withRSA profile.'
        }
        Assert-PrivateDirectory -Path $target.PrivateRun
        $propertiesText = "storeFile=$keystoreAliasPath`r`nstorePassword=$password`r`nkeyAlias=upload`r`nkeyPassword=$password`r`n"
        Write-NewTextFile -Path $propertiesPath -Text $propertiesText -Encoding $asciiEncoding

        $entry = [pscustomobject][ordered]@{
            component = $component
            applicationId = "ge.mili.$component"
            runId = $RunId
            createdAtUtc = [DateTime]::UtcNow.ToString('o')
            registrationStatus = 'pending-registration'
            safeToUploadToExistingPlayApp = $false
            keyType = 'upload_key_not_play_app_signing_key'
            alias = 'upload'
            keystoreType = 'JKS'
            keyAlgorithm = 'RSA'
            keySize = 3072
            signatureAlgorithm = 'SHA256withRSA'
            validityDays = 10000
            certificateSubject = $certificate.Subject
            certificateSha1 = Get-CertificateFingerprint -RawData $certificate.RawData -Algorithm SHA1
            certificateSha256 = Get-CertificateFingerprint -RawData $certificate.RawData -Algorithm SHA256
            certificateNotBeforeUtc = $certificate.NotBefore.ToUniversalTime().ToString('o')
            certificateNotAfterUtc = $certificate.NotAfter.ToUniversalTime().ToString('o')
            publicPemPath = "ops/config/android-signing/$RunId/$component/upload-certificate.pem"
            publicDerPath = "ops/config/android-signing/$RunId/$component/upload-certificate.der"
            privateConfigurationPath = "$privateRelative/key.properties"
            signingProperties = "M:/$privateRelative/key.properties"
            keystore = $keystoreAliasPath
            publicDer = $derAliasPath
            publicPem = $pemAliasPath
        }
        Write-NewTextFile -Path (Join-Path $target.PublicDirectory 'metadata.json') -Text ($entry | ConvertTo-Json -Depth 5) -Encoding $utf8Encoding
        $metadata += $entry
        Write-Host "Created NEW $component upload key; Play registration/reset remains pending."
        Write-Host "Public certificate SHA-256: $($entry.certificateSha256)"
    } finally {
        [Environment]::SetEnvironmentVariable($passwordEnvironmentName, $null, [EnvironmentVariableTarget]::Process)
        [Array]::Clear($randomBytes, 0, $randomBytes.Length)
        $rng.Dispose()
        if ($certificate) { $certificate.Dispose() }
        $password = $null
        $propertiesText = $null
    }
}

$manifest = [pscustomobject][ordered]@{
    schemaVersion = 1
    runId = $RunId
    createdAtUtc = [DateTime]::UtcNow.ToString('o')
    registrationStatus = 'pending-registration'
    apps = @($metadata)
}
Write-NewTextFile -Path (Join-Path $publicRun 'upload-keys.json') -Text ($manifest | ConvertTo-Json -Depth 7) -Encoding $utf8Encoding
Write-Host "Public manifest: ops/config/android-signing/$RunId/upload-keys.json"
Write-Host 'Private keys/configuration remain under ACL-restricted ops/secrets/android-signing; do not commit or upload them.'
