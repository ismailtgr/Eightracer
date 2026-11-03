@echo off
echo Menjalankan Backend Laravel Eightracer (PHP 8.4 di http://127.0.0.1:8000)...
cd /d "%~dp0public"
"..\.tools\php84\php.exe" -S 127.0.0.1:8000 "..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php"
