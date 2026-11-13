$ErrorActionPreference = 'Stop'
foreach ($gitRoot in @('C:\eightracer', 'C:\eightracer\frontend')) {
    Write-Host "Repo: $gitRoot"
    & git -C $gitRoot status --short --branch
    if ($LASTEXITCODE -ne 0) { throw 'Status Git tidak terbaca. Jangan pull/push sebelum masalah akses selesai.' }
    & git -C $gitRoot fetch origin --prune
    if ($LASTEXITCODE -ne 0) { Write-Warning 'Fetch belum berhasil; status remote mungkin belum terbaru.'; continue }
    & git -C $gitRoot status --short --branch
    $gitChanges = & git -C $gitRoot status --porcelain
    if ($gitChanges) { Write-Warning 'Ada perubahan lokal. Tinjau dan commit/stash yang sesuai sebelum pull; jangan menimpa perubahan.' }
    else { Write-Host 'Working tree bersih. Periksa behind/ahead; bila perlu pull gunakan --ff-only pada branch yang benar.' }
}
Write-Host 'Script ini hanya status/fetch. Tidak menjalankan pull, merge, commit, push, reset, atau pergantian branch.'
