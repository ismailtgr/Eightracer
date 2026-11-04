<?php

namespace App\Services\Auth;

use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SchoolAccountProvisioner
{
    public function provision(array $ids, bool $preview, User $admin): array
    {
        return DB::transaction(function () use ($ids, $preview, $admin) {
            $students = Siswa::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $rows = [];
            foreach ($ids as $id) {
                $student = $students->get($id);
                abort_unless($student, 409);
                $nisn = (string) $student->nisn;
                $reason = null;
                $email = filled($student->registration_email) ? mb_strtolower(trim($student->registration_email)) : null;
                if ($student->user_id) {
                    $reason = 'akun_sudah_ada';
                } elseif (! preg_match('/^[0-9]{10}$/D', $nisn) || Siswa::where('nisn', $nisn)->count() !== 1) {
                    $reason = 'nisn_belum_valid_atau_tidak_unik';
                } elseif (User::where('username', $nisn)->exists()) {
                    $reason = 'username_sudah_dipakai';
                } elseif ($email && (! filter_var($email, FILTER_VALIDATE_EMAIL) || User::where('email', $email)->exists())) {
                    $reason = 'email_perlu_diperiksa';
                } elseif (! filled($student->nama_lengkap)) {
                    $reason = 'nama_belum_lengkap';
                }
                if ($reason) {
                    $rows[] = ['siswa_id' => $id, 'status' => 'ditahan', 'alasan' => $reason];
                    continue;
                }
                if ($preview) {
                    $rows[] = ['siswa_id' => $id, 'username' => $nisn, 'status' => 'siap'];
                    continue;
                }
                $password = Str::password(20);
                $user = User::forceCreate([
                    'username' => $nisn, 'email' => $email, 'full_name' => $student->nama_lengkap,
                    'password' => Hash::make($password), 'role' => 'siswa',
                    // Activation by school is distinct from ownership verification of an email.
                    'school_provisioned_at' => now(), 'school_provisioned_by' => $admin->id,
                ]);
                $student->user_id = $user->id;
                $student->save();
                $rows[] = ['siswa_id' => $id, 'user_id' => $user->id, 'username' => $nisn,
                    'status' => 'dibuat', 'initial_password' => $password];
            }

            return ['dry_run' => $preview, 'created_count' => collect($rows)->where('status', 'dibuat')->count(),
                'ready_count' => collect($rows)->where('status', 'siap')->count(),
                'held_count' => collect($rows)->where('status', 'ditahan')->count(), 'data' => $rows];
        });
    }
}
