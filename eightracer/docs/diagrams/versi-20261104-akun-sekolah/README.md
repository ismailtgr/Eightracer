# Revisi akun sekolah — 4 November 2026

Keputusan terbaru menggantikan pendaftaran publik dalam PRD lama. Akun siswa disiapkan administrator dari master sekolah; siswa login NISN/password dan dapat mengganti password sendiri.

- Draw.io berisi satu use case seluruh sistem dan satu use scenario terpadu (dua halaman), hitam putih, dengan susunan asli dipertahankan.
- UC02 kini menjadi Siapkan akun siswa oleh admin. Hubungan Pengunjung/Google/email ke UC02 dihapus; admin menjadi aktor penyedia akun.
- UC03 menjelaskan NISN/password, aktivasi sekolah dan batas sesi; UC05 mencakup penggantian password sendiri.
- Skenario Markdown/HTML diperbarui, termasuk pemulihan bagi akun yang memiliki email.
- QEA memakai format native dan GUID dari model sebelumnya. Use case/skenario direvisi. Empat diagram lainnya diberi label Arsip 02 Nov; belum diklaim sebagai revisi desain penuh setelah perubahan autentikasi ini.
- Kontrak implementasi: ../../AKUN_SISWA_DARI_ADMIN.md.

Versi 2 November dipertahankan sebagai arsip. Kredensial siswa tidak dimasukkan ke diagram atau dokumen.
