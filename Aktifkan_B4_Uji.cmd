@echo off
powershell.exe -NoProfile -File "%~dp0Start_B4_Uji.ps1" -AktifkanTailscale -TetapAktif
if errorlevel 1 (
  echo Aktivasi belum selesai. Simpan pesan terakhir untuk diperiksa.
)
pause
