$ErrorActionPreference = 'Stop'
$freshclam = Join-Path $PSScriptRoot '.tools\security-bin\clamav\freshclam.exe'
$config = Join-Path $PSScriptRoot 'deployment\local\freshclam.conf'
$database = Join-Path $PSScriptRoot '.tools\security-state\clamav-db'
New-Item -ItemType Directory -Path $database -Force | Out-Null
Push-Location -LiteralPath $database
try { & $freshclam ("--config-file=" + $config) '--datadir=.' '--quiet'; $status = $LASTEXITCODE }
finally { Pop-Location }
exit $status
