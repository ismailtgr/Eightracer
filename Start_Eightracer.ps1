$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$state = Join-Path $root '.local'
New-Item -ItemType Directory -Path $state -Force | Out-Null
& (Join-Path $root 'eightracer\start-secure-local.ps1')
$node = (Get-Command node -ErrorAction Stop).Source
$listening = Get-NetTCPConnection -LocalPort 3000 -State Listen -ErrorAction SilentlyContinue
if (-not $listening) {
    $process = Start-Process -FilePath $node -ArgumentList @('node_modules/next/dist/bin/next','dev','--hostname','127.0.0.1','--port','3000') -WorkingDirectory (Join-Path $root 'frontend') -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $state 'frontend.out.log') -RedirectStandardError (Join-Path $state 'frontend.err.log')
    [PSCustomObject]@{Id=$process.Id;Executable=$node;Project=(Join-Path $root 'frontend')} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $state 'frontend-process.json') -Encoding utf8
}
Write-Output 'Eightracer: http://127.0.0.1:3000/admin'
Write-Output 'Backend membaca database XAMPP port 3306. Akun uji ada di .local/akun-uji/Akun_Sementara.txt.'
