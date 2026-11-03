$ErrorActionPreference = 'Stop'
$recordPath = Join-Path $PSScriptRoot '.tools\security-state\processes.json'
if (-not (Test-Path -LiteralPath $recordPath)) { Write-Output 'Tidak ada proses tercatat.'; exit 0 }
$records = @(Get-Content -LiteralPath $recordPath -Raw | ConvertFrom-Json)
foreach ($record in $records) {
    $process = Get-Process -Id $record.Id -ErrorAction SilentlyContinue
    if ($process -and $process.Path -eq $record.Executable) { Stop-Process -Id $record.Id; Write-Output ($record.Name + ' dihentikan.') }
}
'[]' | Set-Content -LiteralPath $recordPath -Encoding utf8
