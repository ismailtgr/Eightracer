param([switch]$KeepAlive)
$ErrorActionPreference = 'Stop'
$projectRoot = $PSScriptRoot
$state = Join-Path $projectRoot '.tools\security-state'
New-Item -ItemType Directory -Path $state -Force | Out-Null
$env:EIGHTRACER_ROOT = $projectRoot.Replace('\','/')
$records = @()
$recordPath = Join-Path $state 'processes.json'
if (Test-Path -LiteralPath $recordPath) { $records = @(Get-Content -LiteralPath $recordPath -Raw | ConvertFrom-Json) }
function Start-LocalProcess([string]$Name, [string]$Executable, [string[]]$Arguments, [string]$Directory) {
    $old = $records | Where-Object Name -eq $Name | Select-Object -First 1
    if ($old) {
        $running = Get-Process -Id $old.Id -ErrorAction SilentlyContinue
        if ($running -and $running.Path -eq $Executable) { Write-Output ($Name + ' sudah berjalan.'); return }
    }
    $process = Start-Process -FilePath $Executable -ArgumentList $Arguments -WorkingDirectory $Directory -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $state ($Name + '.out.log')) -RedirectStandardError (Join-Path $state ($Name + '.err.log'))
    $script:records = @($script:records | Where-Object Name -ne $Name) + @([PSCustomObject]@{Name=$Name;Id=$process.Id;Executable=$Executable})
    $script:records | ConvertTo-Json | Set-Content -LiteralPath $recordPath -Encoding utf8
    Write-Output ($Name + ' dimulai.')
}
$clamav = Join-Path $projectRoot '.tools\security-bin\clamav'
$db = Join-Path $state 'clamav-db'
$hasSignatures = (Test-Path -LiteralPath (Join-Path $db 'daily.cvd')) -or (Test-Path -LiteralPath (Join-Path $db 'daily.cld'))
if ($hasSignatures) {
Start-LocalProcess 'clamd' (Join-Path $clamav 'clamd.exe') @('--foreground','--fail-if-cvd-older-than=3', ('--config-file=' + (Join-Path $projectRoot 'deployment\local\clamd.conf'))) $db
Start-LocalProcess 'freshclam' (Join-Path $clamav 'freshclam.exe') @('--daemon',('--config-file=' + (Join-Path $projectRoot 'deployment\local\freshclam.conf'))) $db
} else { Write-Warning 'Antivirus belum aktif karena signature belum tersedia; unggahan akan ditolak. Jalankan .\update-antivirus.ps1.' }
Start-LocalProcess 'mailpit' (Join-Path $projectRoot '.tools\security-bin\mailpit\mailpit.exe') @('--listen=127.0.0.1:8025','--smtp=127.0.0.1:1025',('--database=' + (Join-Path $state 'mailpit.db')),'--max=100','--max-age=24h','--disable-version-check','--smtp-disable-rdns','--block-remote-css-and-fonts','--smtp-allowed-recipients=@(eightracer[.]test|example[.]test)$','--allowed-hosts=localhost,127.0.0.1,localhost:8445,127.0.0.1:8445,localhost:8025,127.0.0.1:8025') $projectRoot
Start-LocalProcess 'php-local' (Join-Path $projectRoot '.tools\php84\php.exe') @('-S','127.0.0.1:8000','../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php') (Join-Path $projectRoot 'public')
Start-LocalProcess 'queue-worker' (Join-Path $projectRoot '.tools\php84\php.exe') @('artisan','queue:work','--queue=mail,default','--tries=1','--timeout=300','--sleep=2') $projectRoot
Start-LocalProcess 'scheduler' (Join-Path $projectRoot '.tools\php84\php.exe') @('artisan','schedule:work','--no-interaction') $projectRoot
$previousXdgData = [Environment]::GetEnvironmentVariable('XDG_DATA_HOME', 'Process')
$previousXdgConfig = [Environment]::GetEnvironmentVariable('XDG_CONFIG_HOME', 'Process')
try {
$env:XDG_DATA_HOME = Join-Path $state 'xdg-data'
$env:XDG_CONFIG_HOME = Join-Path $state 'xdg-config'
Start-LocalProcess 'caddy' (Join-Path $projectRoot '.tools\security-bin\caddy\caddy.exe') @('run','--config',(Join-Path $projectRoot 'deployment\local\Caddyfile'),'--adapter','caddyfile') $projectRoot
} finally {
[Environment]::SetEnvironmentVariable('XDG_DATA_HOME', $previousXdgData, 'Process')
[Environment]::SetEnvironmentVariable('XDG_CONFIG_HOME', $previousXdgConfig, 'Process')
}
Write-Output 'Backend: https://localhost:8443/api'
Write-Output 'Email uji lokal: https://localhost:8445 (bukan pengiriman email internet).'
Write-Output 'CA lokal disimpan di .tools/security-state/caddy/pki/authorities/local/root.crt. Sistem tidak diubah untuk mempercayai CA ini secara otomatis.'
if ($KeepAlive) {
    $backend = $script:records | Where-Object Name -eq 'php-local' | Select-Object -First 1
    if ($backend) { Wait-Process -Id $backend.Id }
}
