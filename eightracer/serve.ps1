Write-Host "Menjalankan Backend Laravel Eightracer (PHP 8.4 di http://127.0.0.1:8000)..." -ForegroundColor Green
$php = Join-Path $PSScriptRoot ".tools\php84\php.exe"
$router = Join-Path $PSScriptRoot "vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php"
$public = Join-Path $PSScriptRoot "public"

Set-Location $public
& $php -S 127.0.0.1:8000 $router
