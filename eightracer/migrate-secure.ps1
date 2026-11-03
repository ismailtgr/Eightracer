param([switch]$Status, [string]$DatabaseUser = 'root')
$projectPhp = Join-Path $PSScriptRoot '.tools\php84\php.exe'
if ($Status) { & $projectPhp (Join-Path $PSScriptRoot 'artisan') migrate:status; exit $LASTEXITCODE }
$securePassword = Read-Host 'Password akun pengelola database (tidak disimpan)' -AsSecureString
$handle = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
try {
    $password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($handle)
    $start = [Diagnostics.ProcessStartInfo]::new()
    $start.FileName = $projectPhp
    $start.Arguments = '"' + (Join-Path $PSScriptRoot 'scripts\migrate-secure.php') + '"'
    $start.WorkingDirectory = $PSScriptRoot
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardInput = $true
    $process = [Diagnostics.Process]::Start($start)
    $process.StandardInput.WriteLine((@{username=$DatabaseUser;password=$password} | ConvertTo-Json -Compress))
    $process.StandardInput.Close()
    $password = $null
    $process.WaitForExit()
    exit $process.ExitCode
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($handle)
    $securePassword.Dispose()
}