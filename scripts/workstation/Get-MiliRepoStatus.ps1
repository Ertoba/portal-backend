[CmdletBinding()]
param(
    [string]$WorkspaceRoot = $env:MILI_PHYSICAL_WORKSPACE,
    [switch]$Fetch
)

if (-not $WorkspaceRoot) {
    throw 'Pass -WorkspaceRoot or dot-source Enter-MiliEnvironment.ps1 first.'
}
$workspace = (Resolve-Path -LiteralPath $WorkspaceRoot).Path
$repositories = [ordered]@{
    backend = 'backend-admin'
    customer = 'customer-app'
    vendor = 'vendor-app'
    delivery = 'delivery-app'
    react = 'react-web'
}

$rows = foreach ($entry in $repositories.GetEnumerator()) {
    $path = Join-Path $workspace (Join-Path 'repos' $entry.Value)
    if ($Fetch) {
        & git -C $path fetch --quiet --prune origin main
        if ($LASTEXITCODE -ne 0) {
            throw "Fetch failed for $($entry.Key)."
        }
    }

    $head = (& git -C $path rev-parse HEAD).Trim()
    $origin = (& git -C $path rev-parse origin/main).Trim()
    [pscustomobject]@{
        Component = $entry.Key
        Branch = (& git -C $path branch --show-current).Trim()
        Head = $head
        OriginMain = $origin
        MatchesOrigin = $head -eq $origin
        Dirty = [bool](& git -C $path status --porcelain)
    }
}

$rows | Format-Table -AutoSize

