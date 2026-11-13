# Eightracer fullstack lokal

FE: C:\eightracer\frontend, repo Zharussanoy/Eightracer-SMAN-8-MALANG, branch saat pemeriksaan b4.
BE: Git root C:\eightracer, aplikasi Laravel C:\eightracer\eightracer, repo ismailtgr/Eightracer, branch saat pemeriksaan feature/b4/profil-siswa.

Jalankan dari PowerShell:

```powershell
cd C:\eightracer
& .\Start_Fullstack_Lokal.ps1
```

Buka http://127.0.0.1:3000/login. FE memanggil Laravel http://127.0.0.1:8000/api. Laravel memakai Supabase dan Gemini. Internet tetap diperlukan, tetapi Tailscale dan XAMPP tidak diperlukan. Launcher memakai PHP 8.4 yang sudah ada dan Next.js yang terpasang, tidak memasang paket baru atau mengganti desain.

Launcher hanya menyalakan server yang portnya belum aktif, tidak membunuh proses lain, mencatat log di .local/fullstack-local, dan memeriksa FAQ BE. Port aktif tidak berarti login dan seluruh fitur sudah teruji. Boot FE pertama bisa memerlukan kompilasi.

APP_URL untuk proses Laravel diatur ke alamat lokal; konfigurasi URL API untuk proses FE juga lokal. .env tetap dipertahankan, termasuk APP_KEY, kredensial Supabase dan Gemini. Jangan gunakan launcher lama Start_Eightracer.ps1 untuk alur ini karena masih mencantumkan XAMPP/konfigurasi lama.

## Pengingat Git saat bekerja

1. Sebelum mulai: cek status BE dan FE; jalankan Cek_Git_Sebelum_Kerja.ps1. Script hanya status/fetch, tidak auto-pull.
2. Kalau ada perubahan lokal: tinjau dahulu, commit bagian yang siap atau stash secara selektif. Jangan pull/merge di tengah perubahan yang belum diamankan.
3. Saat bersih dan branch yang benar sudah dipastikan: git pull --ff-only. Bila divergen, selesaikan integrasinya secara sadar; jangan reset --hard atau force push.
4. Bekerjalah di branch pribadi untuk menghindari saling push ke branch bersama. Pemilihan/pembuatan branch menunggu pemeriksaan perubahan lokal; launcher tidak membuat branch otomatis.
5. Commit per fitur setelah tes yang sesuai. FE di repo FE, BE di repo BE. Root BE mengabaikan folder frontend sehingga perubahan FE tidak ikut commit BE.
6. Sebelum push: fetch/status lagi, sinkronkan jika remote berubah, lalu push branch pribadi dan buat PR. Kabari teman nama branch dan file/fitur yang sedang ditangani.
7. Setelah teman menggabungkan PR atau sebelum berganti tugas: amankan pekerjaan lokal lalu ambil pembaruan lagi.

Jangan gunakan Commit All pada repo home C:\Users\Ossa Amelia. Jangan commit .env, API key, password, token, .local, node_modules, vendor, atau cache/build. Kedua repo tetap terpisah; tidak dilakukan merge repo atau perubahan desain.

Pengingat bersifat pada langkah kerja di chat ini, bukan notifikasi berkala yang dijadwalkan. Tidak ada jadwal waktu khusus yang diminta.
