# Sesi admin bersama — 4 November 2026

Keputusan terbaru pemilik proyek menggantikan aturan single-device pada PRD awal. Satu akun admin dapat dipakai BE dan FE secara bersamaan di laptop/browser berbeda.

- POST /login dan login Google yang sah membuat token baru tanpa mencabut token perangkat lain.
- Setiap token admin/Guru BK tetap berlaku maksimal 72 jam sejak diterbitkan. Token siswa tetap 168 jam.
- Login baru tidak memperpanjang masa berlaku token yang lebih lama.
- POST /logout hanya mencabut token yang dipakai untuk permintaan tersebut.
- Ganti/reset password tetap mencabut semua token akun.
- Izin admin, verifikasi akun, pembatasan percobaan login, dan larangan register publik tetap berlaku.
- Cookie FE HttpOnly mengikuti expires_at dari BE. Tidak perlu perubahan UI atau migrasi database.

Token yang sudah dicabut sebelum pembaruan ini tidak dihidupkan kembali; masuk ulang jika sesi lama sudah tidak berlaku. Gunakan akun admin hanya pada penguji yang diberi izin. Kredensial tidak dimasukkan ke dokumentasi atau Git.
