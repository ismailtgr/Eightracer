param([switch]$AktifkanTailscale, [switch]$TetapAktif)
$ErrorActionPreference = 'Stop'
$base = $PSScriptRoot
$php = Join-Path $base 'eightracer\.tools\php84\php.exe'
$private = Join-Path $base '.local\b4-test'
$marker = 'b4-test-20261105'
if (-not (Test-Path -LiteralPath (Join-Path $private 'environment.json'))) { throw 'Konfigurasi database uji belum tersedia.' }
try {
    $health = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:8001/up' -TimeoutSec 3
    if ($health.Headers['X-Eightracer-Environment'] -notcontains $marker) { throw 'Port 8001 dipakai layanan lain.' }
} catch {
    if ($_.Exception.Message -eq 'Port 8001 dipakai layanan lain.') { throw }
    $server = Start-Process -FilePath $php -ArgumentList @('-S','127.0.0.1:8001','-t',"`"$(Join-Path $base 'eightracer\public')`"", "`"$(Join-Path $private 'router.php')`"") -WorkingDirectory (Join-Path $base 'eightracer') -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $private 'server.stdout.log') -RedirectStandardError (Join-Path $private 'server.stderr.log')
    $server.Id | Set-Content -LiteralPath (Join-Path $private 'server.pid')
    Start-Sleep -Seconds 2
    $health = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:8001/up' -TimeoutSec 10
    if ($health.Headers['X-Eightracer-Environment'] -notcontains $marker) { throw 'API uji belum siap.' }
}
Write-Host 'API uji aktif: http://127.0.0.1:8001/api'
Write-Host 'Database: eightracer_b4_uji_20261105 (hanya data sintetis).'
if ($AktifkanTailscale) {
    $ts = 'C:\Program Files\Tailscale\tailscale.exe'
    $statusJson = & $ts status --json
    if ($LASTEXITCODE -ne 0) { throw 'Akses layanan Tailscale ditolak. Jalankan PowerShell sebagai Administrator, lalu ulangi script ini dengan -AktifkanTailscale.' }
    $status = $statusJson | ConvertFrom-Json
    if ($status.BackendState -ne 'Running') { throw 'Tailscale belum tersambung.' }
    $serveJson = & $ts serve status --json
    if ($LASTEXITCODE -ne 0) { throw 'Konfigurasi Tailscale belum dapat dibaca.' }
    $serve = $serveJson | ConvertFrom-Json
    $serveJson | Set-Content -LiteralPath (Join-Path $private 'tailscale-serve-before.json') -Encoding utf8
    foreach ($site in $serve.Web.PSObject.Properties) {
        foreach ($handler in $site.Value.Handlers.PSObject.Properties) {
            if ($handler.Name.TrimEnd('/') -eq '/b4-test' -and $handler.Value.Proxy -ne 'http://127.0.0.1:8001') { throw 'Path /b4-test sudah dipakai layanan lain; konfigurasi tidak diubah.' }
        }
    }
    & $ts serve --bg --https=443 --set-path=/b4-test http://127.0.0.1:8001
    if ($LASTEXITCODE -ne 0) { throw 'Tailscale Serve belum berhasil diaktifkan.' }
    $domain = $status.Self.DNSName.TrimEnd('.')
    $url = "https://$domain/b4-test/up"
    $remoteHealth = Invoke-WebRequest -UseBasicParsing -Uri $url -TimeoutSec 15
    if ($remoteHealth.Headers['X-Eightracer-Environment'] -notcontains $marker) { throw 'URL privat belum mengarah ke database uji. Jangan jalankan request tulis.' }
    Write-Host "API privat uji terverifikasi dari laptop BE: https://$domain/b4-test/api"
    Write-Host 'Teman FE tetap harus menguji akses dari laptopnya sendiri.'
}
if ($TetapAktif) {
    Write-Host 'Biarkan jendela ini terbuka selama pengujian. Ctrl+C menghentikan penantian.'
    while ($true) { Start-Sleep -Seconds 30 }
}
