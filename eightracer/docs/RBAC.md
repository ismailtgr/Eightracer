# RBAC & Data Ownership — Ringkasan Rute

Middleware: `role:<daftar role>` ([CheckRole](../app/Http/Middleware/CheckRole.php)).
Alias role → nilai `users.role` di DB:

| Alias di route | users.role      |
|----------------|-----------------|
| `admin`        | `administrator` |
| `bk`           | `guru`          |
| `wali_kelas`   | `guru`          |
| `siswa`        | `siswa`         |

Respons: tanpa/invalid token → `401`; role tidak cocok → `403`
`{ "success": false, "message": "Akses ditolak. Peran Anda (...) tidak memiliki izin ..." }`

## Publik
- `POST /api/register`, `POST /api/login`, `POST /api/v1/auth/login` (alias)
- `GET /api/perguruan-tinggi`, `GET /api/alumni/statistik`

## Semua user terautentikasi
- `POST /api/logout`, `POST /api/v1/auth/logout`
- `GET /api/me`, `GET /api/v1/auth/me`
- `/api/alumni-form/*`, `GET /api/alumni`, `/alumni/filter-options`, `/alumni/{id}`

## `role:admin`
- `POST /api/alumni/import`
- `POST /api/v1/admin/import/excel`
- `apiResource /api/v1/admin/users`
- `PUT /api/v1/admin/bobot`
- `POST /api/v1/admin/engine/kalkulasi-eligibilitas`

## `role:admin,bk`
- `apiResource /api/v1/admin/siswa`, `/admin/alumni`, `/admin/prodi`
- `GET /api/v1/admin/bobot`
- `GET /api/v1/ranking`
- `GET /api/v1/admin/ranking/{siswaId}/detail`
- `GET /api/v1/admin/ranking/export/pdf|excel`

## `role:siswa` (data selalu milik user yang login)
- `GET|PUT /api/v1/siswa/profile`, `PUT /api/v1/siswa/password`
- `GET|POST /api/v1/siswa/asesmen`
- `GET /api/v1/siswa/akademik`, `POST /api/v1/siswa/akademik/{rapor|prestasi|presensi-karakter}`
- `GET /api/v1/siswa/benchmark-alumni`
- `GET /api/v1/siswa/ranking/detail`, `GET /api/v1/siswa/eligibilitas` (alias)

## Data Ownership
Parameter `siswa_id` dari client **diabaikan/dihapus** di `AkademikController`,
`AsesmenController`, dan `BenchmarkAlumniController`. Siswa selalu di-resolve dari
akun login (`Siswa::where('user_id', $request->user()->id)`).

## Format login / me
`{ success, message, data: { token, user: { id, username, role, siswa: {...} } } }`.
Key lama (`user`, `token` di root; atribut user di root untuk `/me`) tetap ada demi kompatibilitas frontend.
