<?php

namespace Tests\Feature;

use App\Services\Auth\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class PrdAuthenticationTest extends TestCase
{
    use PrdFixtures,RefreshDatabase;

    public function test_roster_registration_requires_email_ownership_then_links_existing_student(): void
    {
        $this->mailReady();
        $s = $this->student(null, ['registration_email' => 'student@example.test']);
        $body = ['full_name' => 'Nama Akun', 'email' => 'student@example.test', 'nisn' => $s->nisn, 'password' => 'Strong-Test123!', 'password_confirmation' => 'Strong-Test123!'];
        $this->postJson('/api/register', [...$body, 'email' => 'attacker@example.test'])->assertUnprocessable();
        $id = $this->postJson('/api/register', $body)->assertAccepted()->assertJsonMissingPath('otp')->json('challenge_id');
        $this->assertDatabaseCount('users', 0);
        $this->assertNull($s->fresh()->user_id);
        $row = DB::table('auth_challenges')->where('id', $id)->first();
        $this->assertStringNotContainsString('Nama Akun', $row->payload);
        $this->assertStringNotContainsString('Strong-Test123!', $row->payload);
        $code = $this->code($body['email'], 'register');
        $u = $this->postJson('/api/register/verify', ['challenge_id' => $id, 'otp' => $code])->assertCreated()->assertJsonPath('user.role', 'siswa')->json('user.id');
        $this->assertSame($u, $s->fresh()->user_id);
        $this->assertSame('Murid Uji', $s->fresh()->nama_lengkap);
        $this->postJson('/api/register/verify', ['challenge_id' => $id, 'otp' => $code])->assertUnprocessable();
        $this->postJson('/api/login', ['email' => $body['email'], 'password' => $body['password']])->assertOk();
    }

    public function test_otp_expires_and_is_locked_after_five_wrong_codes(): void
    {
        $this->mailReady();
        $u = $this->account();
        $otp = app(OtpService::class);
        $id = $otp->issue('password_reset', $u->email, [], $u->id);
        $right = $this->code($u->email, 'password_reset');
        $wrong = $right === '000000' ? '000001' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/forgot-password/verify', ['challenge_id' => $id, 'otp' => $wrong])->assertUnprocessable();
        }
        $this->postJson('/api/forgot-password/verify', ['challenge_id' => $id, 'otp' => $right])->assertUnprocessable();
        $this->assertDatabaseHas('auth_challenges', ['id' => $id, 'attempts' => 5]);
        $id = $otp->issue('password_reset', $u->email, [], $u->id);
        $code = $this->code($u->email, 'password_reset');
        $this->travel(11)->minutes();
        $this->postJson('/api/forgot-password/verify', ['challenge_id' => $id, 'otp' => $code])->assertUnprocessable();
        $this->travelBack();
    }

    public function test_new_otp_invalidates_previous_challenge_and_requests_are_limited(): void
    {
        $this->mailReady();
        $u = $this->account();
        $id = $this->postJson('/api/forgot-password', ['email' => $u->email])->assertOk()->json('challenge_id');
        $code = $this->code($u->email, 'password_reset');
        $this->postJson('/api/forgot-password', ['email' => $u->email])->assertOk();
        $this->postJson('/api/forgot-password/verify', ['challenge_id' => $id, 'otp' => $code])->assertUnprocessable();
        $this->postJson('/api/forgot-password', ['email' => $u->email])->assertOk();
        $this->postJson('/api/forgot-password', ['email' => $u->email])->assertTooManyRequests();
    }

    public function test_own_profile_does_not_change_school_biodata_or_role(): void
    {
        $u = $this->account();
        $s = $this->student($u);
        $this->signIn($u);
        $this->patchJson('/api/profile', ['full_name' => 'Nama Tampilan'])->assertOk()->assertJsonPath('data.full_name', 'Nama Tampilan');
        $this->assertSame('Murid Uji', $s->fresh()->nama_lengkap);
        $this->patchJson('/api/profile', ['full_name' => 'Admin', 'role' => 'administrator'])->assertUnprocessable();
        $this->getJson('/api/profile?user_id=99999')->assertOk()->assertJsonPath('data.id', $u->id)->assertJsonMissingPath('data.password');
        $this->putJson('/api/profile/password', ['current_password' => 'wrong', 'password' => 'New-Strong123!', 'password_confirmation' => 'New-Strong123!'])->assertUnprocessable();
        $this->putJson('/api/profile/password', ['current_password' => 'Strong-Test123!', 'password' => 'New-Strong123!', 'password_confirmation' => 'New-Strong123!'])->assertOk();
        $this->assertSame(0, $u->tokens()->count());
        $this->assertTrue(Hash::check('New-Strong123!', $u->fresh()->password));
    }

    public function test_email_change_requires_otp_bound_to_account_and_revokes_sessions(): void
    {
        $this->mailReady();
        $u = $this->account();
        $other = $this->account();
        $this->signIn($u);
        $id = $this->postJson('/api/profile/email/otp', ['email' => 'new@example.test', 'current_password' => 'Strong-Test123!'])->assertOk()->json('challenge_id');
        $code = $this->code('new@example.test', 'email_change');
        $this->signIn($other);
        $this->postJson('/api/profile/email/verify', ['challenge_id' => $id, 'otp' => $code])->assertUnprocessable();
        $this->signIn($u);
        $this->postJson('/api/profile/email/verify', ['challenge_id' => $id, 'otp' => $code])->assertOk();
        $this->assertSame('new@example.test', $u->fresh()->email);
        $this->assertSame(0, $u->tokens()->count());
        $this->assertNotSame('new@example.test', $other->fresh()->email);
    }

    public function test_unverified_accounts_and_unconfigured_providers_are_closed(): void
    {
        $u = $this->account('siswa', ['email_verified_at' => null]);
        $this->postJson('/api/login', ['email' => $u->email, 'password' => 'Strong-Test123!'])->assertForbidden();
        $this->signIn($u);
        $this->getJson('/api/profile')->assertForbidden();
        config(['prd.email_otp_enabled' => false, 'prd.google_client_id' => null]);
        $this->postJson('/api/forgot-password', ['email' => $u->email])->assertStatus(503);
        $this->postJson('/api/auth/google/challenge')->assertStatus(503);
    }

    public function test_google_only_account_can_set_password_only_after_own_email_otp(): void
    {
        $this->mailReady();
        $u = $this->account('siswa', ['password' => null, 'google_id' => 'synthetic-google']);
        $this->signIn($u);
        $id = $this->postJson('/api/profile/password/otp')->assertOk()->json('challenge_id');
        $code = $this->code($u->email, 'set_password');
        $this->putJson('/api/profile/password', ['challenge_id' => $id, 'otp' => $code, 'password' => 'New-Strong123!', 'password_confirmation' => 'New-Strong123!'])->assertOk();
        $this->assertTrue(Hash::check('New-Strong123!',$u->fresh()->password));
        $this->assertSame(0,$u->tokens()->count());
    }
}
