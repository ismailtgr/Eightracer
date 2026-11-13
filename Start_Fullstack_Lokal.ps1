$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath('C:\eightracer')
$backendRoot = Join-Path $projectRoot 'eightracer'
$frontendRoot = Join-Path $projectRoot 'frontend'
$stateRoot = Join-Path $projectRoot '.local\fullstack-local'
$phpBinary = Join-Path $backendRoot '.tools\php84\php.exe'
$routerFile = Join-Path $backendRoot 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
$nextCli = Join-Path $frontendRoot 'node_modules\next\dist\bin\next'

foreach ($required in @($phpBinary, $routerFile, $nextCli, (Join-Path $frontendRoot '.env.local'), (Join-Path $backendRoot '.env'))) {
    if (-not (Test-Path -LiteralPath $required)) { throw "Berkas yang diperlukan belum tersedia: $required" }
}
$nodeBinary = (Get-Command node.exe -ErrorAction Stop).Source
New-Item -ItemType Directory -Path $stateRoot -Force | Out-Null

function Test-LocalPort([int]$Port) {
    $client = New-Object Net.Sockets.TcpClient
    try {
        $attempt = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        if (-not $attempt.AsyncWaitHandle.WaitOne(500)) { return $false }
        $client.EndConnect($attempt)
        return $true
    } catch { return $false } finally { $client.Close() }
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$savedAppUrl = [Environment]::GetEnvironmentVariable('APP_URL', 'Process')
$savedApiUrl = [Environment]::GetEnvironmentVariable('API_BASE_URL', 'Process')
$savedPublicApiUrl = [Environment]::GetEnvironmentVariable('NEXT_PUBLIC_API_BASE_URL', 'Process')
try {
    if (-not (Test-LocalPort 8000)) {
        # Per-process local URL only: preserve the actual .env and Supabase settings.
        $env:APP_URL = 'http://127.0.0.1:8000'
        $backendProcess = Start-Process -FilePath $phpBinary -ArgumentList @('-d', 'display_errors=0', '-d', 'log_errors=1', '-S', '127.0.0.1:8000', '-t', (Join-Path $backendRoot 'public'), $routerFile) -WorkingDirectory (Join-Path $backendRoot 'public') -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $stateRoot "backend-$stamp.out.log") -RedirectStandardError (Join-Path $stateRoot "backend-$stamp.err.log")
        Write-Host "BE dijalankan, PID $($backendProcess.Id)."
    } else { Write-Host 'Port 8000 sudah aktif; proses yang ada dipertahankan.' }

    if (-not (Test-LocalPort 3000)) {
        $env:API_BASE_URL = 'http://127.0.0.1:8000/api'
        $env:NEXT_PUBLIC_API_BASE_URL = 'http://127.0.0.1:8000/api'
        $frontendProcess = Start-Process -FilePath $nodeBinary -ArgumentList @($nextCli, 'dev', '--hostname', '127.0.0.1', '--port', '3000') -WorkingDirectory $frontendRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $stateRoot "frontend-$stamp.out.log") -RedirectStandardError (Join-Path $stateRoot "frontend-$stamp.err.log")
        Write-Host "FE dijalankan, PID $($frontendProcess.Id)."
    } else { Write-Host 'Port 3000 sudah aktif; proses yang ada dipertahankan.' }
} finally {
    [Environment]::SetEnvironmentVariable('APP_URL', $savedAppUrl, 'Process')
    [Environment]::SetEnvironmentVariable('API_BASE_URL', $savedApiUrl, 'Process')
    [Environment]::SetEnvironmentVariable('NEXT_PUBLIC_API_BASE_URL', $savedPublicApiUrl, 'Process')
}

$deadline = [DateTime]::UtcNow.AddSeconds(25)
while ([DateTime]::UtcNow -lt $deadline -and (-not (Test-LocalPort 3000) -or -not (Test-LocalPort 8000))) { Start-Sleep -Milliseconds 500 }
if (-not (Test-LocalPort 8000)) { throw "BE belum berjalan. Periksa log di $stateRoot" }
if (-not (Test-LocalPort 3000)) { throw "FE belum berjalan. Periksa log di $stateRoot" }

try {
    $response = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:8000/api/faq' -Headers @{Accept='application/json'} -TimeoutSec 15
    $null = $response.Content | ConvertFrom-Json
    Write-Host "BE FAQ: HTTP $($response.StatusCode), JSON valid."
} catch { Write-Warning 'Port BE aktif, tetapi pemeriksaan FAQ belum berhasil. Jangan anggap login sudah teruji.' }

Write-Host ''
Write-Host 'Buka FE: http://127.0.0.1:3000/login'
Write-Host 'API BE: http://127.0.0.1:8000/api'
Write-Host 'Database: Supabase; internet tetap diperlukan untuk Supabase dan Gemini.'
Write-Host 'Tailscale dan XAMPP tidak diperlukan untuk akses lokal ini.'
Write-Host "Log proses: $stateRoot"
Write-Host 'PENGINGAT GIT: cek status kedua repo sebelum mengedit; amankan perubahan lokal sebelum pull.'
Write-Host 'Jangan commit .env, token, node_modules, vendor, atau hasil build.'
