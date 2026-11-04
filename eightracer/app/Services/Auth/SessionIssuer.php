<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class SessionIssuer
{
    public function issue(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless(SchoolAccess::canAuthenticate($user), 403, 'Akun belum aktif. Hubungi admin sekolah.');
            if (SchoolAccess::isStaff($user)) {
                $this->revoke($user);
            }
            $expires = now()->addHours(SchoolAccess::isStaff($user) ? 72 : 168);
            $token = $user->createToken('auth-token', ['*'], $expires)->plainTextToken;

            return ['message' => 'Login berhasil', 'user' => $this->profile($user), 'token' => $token, 'expires_at' => $expires->toIso8601String()];
        });
    }

    public function profile(User $user): array
    {
        return ['id' => $user->id, 'full_name' => $user->full_name, 'email' => $user->email, 'role' => $user->role,
            'school_staff' => SchoolAccess::isStaff($user), 'school_role' => SchoolAccess::isStaff($user) && $user->role === 'guru' ? 'guru_bk' : $user->role,
            'email_verified' => (bool) $user->email_verified_at,
            'account_active' => SchoolAccess::canAuthenticate($user),
            'school_provisioned' => $user->school_provisioned_at !== null,
            'password_change_recommended' => $user->school_provisioned_at !== null && $user->school_password_changed_at === null,
            'has_password' => $user->password !== null,
            'google_connected' => $user->google_id !== null, 'photo_url' => $user->photo_path ? url('/api/profile/photo') : null];
    }

    public function revoke(User $user): void
    {
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
