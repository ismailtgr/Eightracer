# Hasil pemeriksaan — 4 November 2026

- **Terpasang di proyek utama:** GET detail katalog `/api/beasiswa/{id}` beserta pengujian edit/arsip/aktifkan dan penolakan akses siswa.
- **Database uji aktif:** `eightracer_b4_uji_20261105`, 52 migrasi, dua akun terverifikasi, tiga profil sintetis. Akun SQL runtime hanya memiliki SELECT/INSERT/UPDATE/DELETE pada database uji.
- **Base URL uji HTTPS:** `https://laptop-3qspir74.tail5f273f.ts.net/b4-test/api`.
- Health check Tailscale: 200 dengan penanda `b4-test-20261105`. Dilakukan dari laptop BE; konektivitas laptop FE belum diverifikasi.
- **29/29 pemeriksaan API langsung lokal** dan **29/29 melalui HTTPS Tailscale** berhasil: login Admin/Siswa terverifikasi, profil sendiri, penolakan data siswa lain dan katalog admin, detail, tambah/edit, validasi salah, arsip/aktifkan, dashboard UKT, preview, chat tanpa LLM, serta penolakan generate tanpa key (503). Token pemeriksaan sudah logout.
- Suite backend: **124 tes, 870 assertions, 0 gagal, 1 skipped**. Tes yang dilewati adalah impor XLSX lama karena `is_readable` pada lingkungan Windows ini; tidak dihitung sebagai pengujian antivirus end-to-end.
- Koleksi Postman: 24 request; password/token environment yang dibagikan kosong. Contoh respons berasal dari data sintetis yang benar-benar diuji.
- **Belum diselesaikan oleh paket ini:** koneksi dari laptop FE, pemasangan seluruh aksi tulis di BFF FE, uji Gemini dengan key pada environment uji, impor dengan scanner aktif, email OTP nyata, dan kepastian laptop tetap menyala/internet aktif saat jadwal uji.
- FE utama tetap memakai konfigurasi awal; beralih ke database uji dilakukan dengan base URL pada panduan. Tampilan FE tidak diubah.
- Ruang C: sekitar 100–120 MB saat akhir pemeriksaan. Cache build lama `C:\eightracer\frontend\.next` sekitar941 MB sudah tidak dipakai oleh config FE saat ini, tetapi penghapusannya melalui alat ditolak persetujuan otomatis. Pemilik dapat menghapus cache lama tersebut lewat Explorer. Cache aktif bernama `.next-local`; cek config sebelum pembersihan lanjutan.

Bukti rinci lokal (pribadi, diabaikan Git): `.local/b4-test/verification.json`, `verification-remote.json`, `database-verification.json`, `remote-status.json`, `migrations.log`, serta `.local/b4-contract-tests.log`.
