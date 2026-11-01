# Runtime PHP lokal project; tidak mengganti PHP XAMPP/PATH global.
$projectPhp = Join-Path $PSScriptRoot '.tools\php84\php.exe'
if (-not (Test-Path -LiteralPath $projectPhp)) { throw 'Runtime .tools/php84 belum tersedia. Gunakan PHP 8.3+ atau siapkan runtime portabel sesuai docs/gemini-beasiswa.md.' }
& $projectPhp @args
exit $LASTEXITCODE
