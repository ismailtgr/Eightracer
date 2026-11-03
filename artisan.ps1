$php = Join-Path $PSScriptRoot "eightracer\.tools\php84\php.exe"
$artisan = Join-Path $PSScriptRoot "eightracer\artisan"
& $php $artisan @args

