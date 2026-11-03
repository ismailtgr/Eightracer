Write-Host "Menjalankan Backend Laravel Eightracer (PHP 8.4 di http://127.0.0.1:8000)..." -ForegroundColor Green
$eightracerDir = Join-Path $PSScriptRoot "eightracer"
$php = Join-Path $eightracerDir ".tools\php84\php.exe"
$router = Join-Path $eightracerDir "vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php"
$public = Join-Path $eightracerDir "public"

Set-Location $public
& $php -S 127.0.0.1:8000 $router
