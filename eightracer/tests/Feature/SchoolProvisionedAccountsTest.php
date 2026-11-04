<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class SchoolProvisionedAccountsTest extends TestCase
{
    use PrdFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_public_registration_and_verification_are_closed_without_creating_accounts(): void
    {
        $this->postJson('/api/register', ['role' => 'administrator'])->assertStatus(410);
        $this->postJson('/api/register/verify', [])->assertStatus(410);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('auth_challenges', 0);
    }

    public function test_admin_previews_then_provisions_unique_passwords_without_fabricating_email_or_resetting_existing_accounts(): void
    {
        $admin = $this->account('administrator');
        $a = $this->student();
        $b = $this->student();
        $this->signIn($admin);
        $body = ['siswa_ids' => [$a->id, $b->id], 'dry_run' => true];
        $this->postJson('/api/akun-siswa/provision', $body)->assertOk()
            ->assertJsonPath('ready_count', 2)->assertJsonPath('created_count', 0)->assertJsonMissingPath('data.0.initial_password');
        $this->assertDatabaseCount('users', 1);
        $rows = $this->postJson('/api/akun-siswa/provision', [...$body, 'dry_run' => false])
            ->assertCreated()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('created_count', 2)->json('data');
        $this->assertNotSame($rows[0]['initial_password'], $rows[1]['initial_password']);
        $user = User::findOrFail($a->fresh()->user_id);
        $this->assertSame('siswa', $user->role);
        $this->assertSame($a->nisn, $user->username);
        $this->assertNull($user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check($rows[0]['initial_password'], $user->password));
        $hash = $user->password;
        $this->postJson('/api/akun-siswa/provision', [...$body, 'dry_run' => false])
            ->assertOk()->assertJsonPath('held_count', 2)->assertJsonMissingPath('data.0.initial_password');
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_missing_nisn_and_conflicting_identity_are_held_without_creating_fake_data(): void
    {
        $admin = $this->account('administrator');
        $a = $this->student(null, ['nisn' => null]);
        $b = $this->student(null, ['registration_email' => $admin->email]);
        $this->signIn($admin);
        $this->postJson('/api/akun-siswa/provision', ['siswa_ids' => [$a->id, $b->id], 'dry_run' => false])
            ->assertOk()->assertJsonPath('created_count', 0)->assertJsonPath('held_count', 2);
        $this->assertDatabaseCount('users', 1);
        $this->assertNull($a->fresh()->user_id);
        $this->assertNull($b->fresh()->user_id);
    }

    public function test_provisioning_is_admin_only_and_rejects_duplicate_ids_unknown_students_and_role_injection(): void
    {
        $s = $this->student();
        $body = ['siswa_ids' => [$s->id], 'dry_run' => false];
        $this->postJson('/api/akun-siswa/provision', $body)->assertUnauthorized();
        $this->signIn($this->account());
        $this->postJson('/api/akun-siswa/provision', $body)->assertForbidden();
        $this->signIn($this->account('administrator'));
        $this->postJson('/api/akun-siswa/provision', [...$body, 'role' => 'administrator'])->assertUnprocessable();
        $this->postJson('/api/akun-siswa/provision', [...$body, 'siswa_ids' => [$s->id, $s->id]])->assertUnprocessable();
        $this->postJson('/api/akun-siswa/provision', [...$body, 'siswa_ids' => [999999]])->assertUnprocessable();
        $this->assertNull($s->fresh()->user_id);
    }

    public function test_school_student_can_login_with_nisn_and_change_password_without_email_otp(): void
    {
        $admin = $this->account('administrator');
        $student = $this->student();
        $this->signIn($admin);
        $row = $this->postJson('/api/akun-siswa/provision', ['siswa_ids' => [$student->id], 'dry_run' => false])
            ->assertCreated()->json('data.0');
        $this->app['auth']->forgetGuards();
        $token = $this->postJson('/api/login', ['nisn' => $student->nisn, 'password' => $row['initial_password']])
            ->assertOk()->assertJsonPath('user.account_active', true)->assertJsonPath('user.email_verified', false)
            ->assertJsonPath('user.email', null)->assertJsonPath('user.password_change_recommended', true)->json('token');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonMissingPath('password');
        $body = ['current_password' => 'wrong', 'password' => 'New-Private123!', 'password_confirmation' => 'New-Private123!'];
        $this->putJson('/api/profile/password', $body)->assertUnprocessable();
        $this->putJson('/api/profile/password', [...$body, 'current_password' => $row['initial_password']])->assertOk();
        $user = User::findOrFail($row['user_id']);
        $this->assertTrue(Hash::check($body['password'], $user->password));
        $this->assertNotNull($user->school_password_changed_at);
        $this->assertSame(0, $user->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['nisn' => $student->nisn, 'password' => $body['password']])
            ->assertOk()->assertJsonPath('user.password_change_recommended', false);
    }

    public function test_admin_token_survives_two_days_and_expires_at_three_days(): void
    {
        $user = $this->account('administrator');
        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Strong-Test123!'])->assertOk()->json('token');
        $this->travel(48)->hours();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertOk();
        $this->travel(24)->hours();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
        $this->travelBack();
    }
}
