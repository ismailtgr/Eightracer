<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\SchoolAccess;
use App\Services\Auth\SessionIssuer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolStaffController extends Controller
{
    public function save(Request $r, int $user)
    {
        abort_unless($r->user()->role === 'administrator', 403);
        $d = $r->validate(['enabled' => 'required|boolean']);

        return DB::transaction(function () use ($r, $user, $d) {
            $account = User::lockForUpdate()->findOrFail($user);
            abort_unless($account->role === 'guru', 422, 'Pilih akun guru yang disediakan pengelola.');
            if ($d['enabled']) {
                abort_unless($account->email_verified_at && SchoolAccess::schoolEmail($account->email), 422, 'Email sekolah terverifikasi dan domain yang dikonfigurasi diperlukan.');
                DB::table('school_staff_grants')->updateOrInsert(['user_id' => $account->id], ['granted_email' => mb_strtolower($account->email), 'granted_by' => $r->user()->id, 'granted_at' => now(), 'revoked_at' => null]);
            } else {
                DB::table('school_staff_grants')->where('user_id', $account->id)->update(['revoked_at' => now()]);
            }
            app(SessionIssuer::class)->revoke($account);

            return response()->json(['data' => ['user_id' => $account->id, 'school_staff' => (bool) $d['enabled'], 'login_ulang' => true]]);
        });
    }
}
