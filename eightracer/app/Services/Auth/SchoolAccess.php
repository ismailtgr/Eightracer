<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SchoolAccess
{
    public static function canAuthenticate(?User $user): bool
    {
        return $user !== null && ($user->email_verified_at !== null
            || ($user->role === 'siswa' && $user->school_provisioned_at !== null && $user->siswa()->exists()));
    }

    public static function isStaff(?User $user): bool
    {
        if ($user?->role === 'administrator') {
            return true;
        }
        if (! $user || $user->role !== 'guru' || ! $user->email_verified_at || ! self::schoolEmail($user->email) || ! Schema::hasTable('school_staff_grants')) {
            return false;
        }

        return DB::table('school_staff_grants')->where('user_id', $user->id)->where('granted_email', mb_strtolower($user->email))->whereNull('revoked_at')->exists();
    }

    public static function schoolEmail(string $email): bool
    {
        $domain = mb_strtolower((string) substr(strrchr($email, '@') ?: '', 1));

        return in_array(mb_strtolower($email), config('b4.school_staff_emails', []), true) || ($domain !== '' && in_array($domain, config('b4.school_email_domains', []), true));
    }
}
