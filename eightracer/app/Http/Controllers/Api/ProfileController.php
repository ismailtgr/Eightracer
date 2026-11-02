<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Auth\SessionIssuer;
use App\Services\Security\ClamAvScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $r, SessionIssuer $sessions)
    {
        return response()->json(['data' => $sessions->profile($r->user())]);
    }

    public function update(Request $r, SessionIssuer $sessions)
    {
        $d = $r->validate(['full_name' => 'required|string|max:255', 'role' => 'prohibited', 'email' => 'prohibited', 'user_id' => 'prohibited']);
        $r->user()->forceFill(['full_name' => $d['full_name']])->save();

        return response()->json(['data' => $sessions->profile($r->user())]);
    }

    public function passwordOtp(Request $r, OtpService $otp)
    {
        abort_unless(! $r->user()->password && $r->user()->email_verified_at, 422);
        $id = $otp->issue('set_password', $r->user()->email, [], $r->user()->id);

        return response()->json(['challenge_id' => $id, 'expires_in' => 600]);
    }

    public function password(Request $r, OtpService $otp, SessionIssuer $sessions)
    {
        $d = $r->validate(['password' => SchoolAuthController::passwordRules(), 'current_password' => 'nullable|string|max:1024', 'challenge_id' => 'nullable|uuid', 'otp' => 'nullable|string|regex:/^[0-9]{6}$/']);
        $user = $r->user();
        $change = function () use ($user, $d, $sessions) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->password) {
                abort_unless(Hash::check($d['current_password'] ?? '', $user->password), 422);
            }$user->forceFill(['password' => Hash::make($d['password'])])->save();
            $sessions->revoke($user);
        };
        if ($user->password) {
            abort_unless(Hash::check($d['current_password'] ?? '', $user->password), 422);
            DB::transaction($change);
        } else {
            $otp->consume($d['challenge_id'] ?? '', 'set_password', $d['otp'] ?? '', fn () => $change(), $user->id);
        }

        return response()->json(['message' => 'Kata sandi disimpan. Silakan login kembali.']);
    }

    public function emailOtp(Request $r, OtpService $otp)
    {
        $d = $r->validate(['email' => 'required|email:rfc|max:255', 'current_password' => 'required|string|max:1024']);
        $u = $r->user();
        abort_unless($u->password && Hash::check($d['current_password'], $u->password), 422);
        $email = mb_strtolower(trim($d['email']));
        abort_if(User::where('email', $email)->exists(), 422);
        $id = $otp->issue('email_change', $email, [], $u->id);

        return response()->json(['challenge_id' => $id, 'expires_in' => 600]);
    }

    public function emailVerify(Request $r, OtpService $otp, SessionIssuer $sessions)
    {
        $d = $r->validate(['challenge_id' => 'required|uuid', 'otp' => 'required|string|regex:/^[0-9]{6}$/']);
        $otp->consume($d['challenge_id'], 'email_change', $d['otp'], function ($row) use ($r, $sessions) {
            $u = User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            abort_if(User::where('email', $row->email)->whereKeyNot($u->id)->exists(), 409);
            $u->forceFill(['email' => $row->email, 'email_verified_at' => now(), 'google_id' => null])->save();
            $sessions->revoke($u);
            DB::table('auth_challenges')->where('user_id', $u->id)->where('purpose', 'reset_grant')->update(['consumed_at' => now()]);
        }, $r->user()->id);

        return response()->json(['message' => 'Email diperbarui. Silakan login kembali.']);
    }

    public function photo(Request $r, ClamAvScanner $scanner)
    {
        $r->validate(['file' => 'required|file|extensions:jpg,jpeg,png,webp|mimes:jpg,jpeg,png,webp|max:2048']);
        $file = $r->file('file');
        $size = @getimagesize($file->getRealPath());
        abort_unless($size && $size[0] * $size[1] <= 4194304, 422);
        $scanner->assertClean($file->getRealPath());
        $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        abort_unless($image, 422);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $path = 'avatars/'.bin2hex(random_bytes(24)).'.png';
        abort_unless(Storage::disk('local')->put($path, $bytes), 503);
        $old = $r->user()->photo_path;
        $r->user()->forceFill(['photo_path' => $path])->save();
        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return response()->json(['message' => 'Foto profil diperbarui.']);
    }

    public function photoRead(Request $r)
    {
        abort_unless($r->user()->photo_path, 404);

        return Storage::disk('local')->response($r->user()->photo_path,null,['Content-Type' => 'image/png', 'Cache-Control' => 'no-store, private']);
    }
}
