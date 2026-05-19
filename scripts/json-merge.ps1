param(
  [Parameter(Mandatory=$true)][string]$BasePath,
  [Parameter(Mandatory=$true)][string]$OursPath,
  [Parameter(Mandatory=$true)][string]$TheirsPath,
  [Parameter(Mandatory=$false)][int]$MarkerSize = 7,
  [Parameter(Mandatory=$false)][string]$Pathname = "unknown"
)

$ErrorActionPreference = "Stop"

function Read-JsonObj([string]$path) {
  if (-not (Test-Path $path)) { return $null }
  $raw = Get-Content -Raw -Encoding UTF8 -Path $path
  if ([string]::IsNullOrWhiteSpace($raw)) { return $null }
  return ($raw | ConvertFrom-Json)
}

try {
  $ours   = Read-JsonObj $OursPath
  $theirs = Read-JsonObj $TheirsPath
} catch {
  exit 1
}

$result = [ordered]@{}
if ($ours) {
  foreach ($p in $ours.PSObject.Properties) {
    $result[$p.Name] = $p.Value
  }
}
if ($theirs) {
  foreach ($p in $theirs.PSObject.Properties) {
    if (-not $result.Contains($p.Name)) {
      $result[$p.Name] = $p.Value
    }
  }
}

$obj = New-Object PSObject
foreach ($k in $result.Keys) {
  $obj | Add-Member -NotePropertyName $k -NotePropertyValue $result[$k]
}
$json = $obj | ConvertTo-Json -Depth 100
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($OursPath, $json + [Environment]::NewLine, $utf8NoBom)
exit 0
