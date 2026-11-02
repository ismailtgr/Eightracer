<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BackendSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'siswa'): User
    {
        return User::create(['username' => uniqid('security-'), 'password' => 'Strong-Test123!', 'role' => $role]);
    }
    private function student(User $user): Siswa
    {
        return Siswa::create(['user_id' => $user->id, 'nisn' => uniqid(), 'nama_lengkap' => 'Synthetic student', 'jenis_kelamin' => 'P']);
    }
    private function signIn(User $user, ?\DateTimeInterface $expiry = null): string
    {
        $token = $user->createToken('security-test', ['*'], $expiry ?? now()->addHour())->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token);
        return $token;
    }
    private function login(User $user): string
    {
        $this->app['auth']->forgetGuards();
        return $this->postJson('/api/login', ['username' => $user->username, 'password' => 'Strong-Test123!'])->assertOk()->json('token');
    }
    public function test_private_student_family_and_alumni_endpoints_require_admin_for_every_verb(): void
    {
        $student = $this->student($this->account());
        foreach (['siswa', 'guru'] as $role) {
            $this->signIn($this->account($role));
            foreach ([
                ['GET', '/api/siswa'], ['POST', '/api/siswa'], ['GET', "/api/siswa/{$student->id}"],
                ['PUT', "/api/siswa/{$student->id}"], ['DELETE', "/api/siswa/{$student->id}"],
                ['POST', '/api/alumni/import'], ['GET', '/api/alumni/progress'],
                ['GET', "/api/alumni/{$student->id}/progress"], ['POST', "/api/alumni/{$student->id}/progress"],
                ['GET', "/api/alumni/{$student->id}/timeline"],
            ] as [$method, $url]) { $this->json($method, $url)->assertForbidden(); }
            foreach (['orang-tua', 'wali', 'media-sosial', 'bantuan'] as $path) {
                $this->getJson("/api/siswa/{$student->id}/{$path}")->assertForbidden();
                $this->postJson("/api/siswa/{$student->id}/{$path}")->assertForbidden();
            }
        }
        $this->assertDatabaseHas('siswa', ['id' => $student->id]);
    }
    public function test_admin_still_reads_master_data_and_search_is_bound_and_bounded(): void
    {
        $this->student($this->account());
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/siswa')->assertOk()->assertJsonCount(1, 'data.data');
        $this->getJson('/api/siswa?'.http_build_query(['search' => "' OR 1=1 --"]))->assertOk()->assertJsonCount(0, 'data.data');
        $this->getJson('/api/siswa?per_page=100000')->assertUnprocessable();
    }
    public function test_public_registration_cannot_claim_a_school_account_or_role(): void
    {
        $this->postJson('/api/register', ['username' => 'attacker', 'password' => 'Strong-Test123!', 'role' => 'administrator'])->assertForbidden();
        $this->assertDatabaseCount('users', 0);
    }
    public function test_password_is_hashed_and_never_returned_by_login(): void
    {
        $user = $this->account();
        $this->assertNotSame('Strong-Test123!', $user->password);
        $this->assertTrue(Hash::check('Strong-Test123!', $user->password));
        $token = $this->login($user);
        $this->assertNotNull($user->tokens()->first()->expires_at);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonMissingPath('password')->assertJsonMissingPath('email');
    }
    public function test_admin_new_login_revokes_old_device_but_student_can_use_two_devices(): void
    {
        $admin = $this->account('administrator');
        $old = $this->login($admin);
        $this->login($admin);
        $this->assertSame(1, $admin->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($old)->getJson('/api/me')->assertUnauthorized();
        $student = $this->account();
        $this->login($student);
        $this->login($student);
        $this->assertSame(2, $student->tokens()->count());
    }
    public function test_expired_and_legacy_unbounded_tokens_are_rejected(): void
    {
        $user = $this->account();
        $this->signIn($user, now()->subMinute());
        $this->getJson('/api/me')->assertUnauthorized();
        $legacy = $user->createToken('legacy')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($legacy)->getJson('/api/me')->assertUnauthorized();
    }
    public function test_login_is_rate_limited_and_password_is_not_trimmed_or_sanitized(): void
    {
        $user = $this->account();
        $user->forceFill(['password' => '  <Strong-Test123!>  '])->save();
        $this->postJson('/api/login', ['username' => $user->username, 'password' => '  <Strong-Test123!>  '])->assertOk();
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/login', ['username' => $user->username, 'password' => 'wrong']);
        }
        $response->assertTooManyRequests()->assertHeader('Retry-After');
    }
    public function test_plain_text_fields_reject_stored_script_and_encoded_html(): void
    {
        $this->signIn($this->account('administrator'));
        foreach (['<script>alert(1)</script>', '&lt;img src=x onerror=alert(1)&gt;', "name\x00"] as $payload) {
            $this->postJson('/api/siswa', ['nama_lengkap' => $payload])->assertUnprocessable();
        }
        $this->assertDatabaseCount('siswa', 0);
    }
    public function test_invalid_owner_link_and_negative_financial_input_are_rejected(): void
    {
        $admin = $this->account('administrator');
        $student = $this->student($this->account());
        $this->signIn($admin);
        $this->postJson('/api/siswa', ['user_id' => $admin->id, 'nisn' => '1234567890', 'nama_lengkap' => 'Synthetic', 'jenis_kelamin' => 'P'])->assertUnprocessable();
        $this->postJson("/api/siswa/{$student->id}/wali", ['nama' => 'Synthetic guardian', 'penghasilan' => -1])->assertUnprocessable();
    }
    public function test_exceptions_never_expose_sql_stack_path_or_secret_even_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        Route::middleware('api')->get('/api/security-test-error', fn () => throw new \RuntimeException('SQLSTATE /server/private .env SECRET-SYNTHETIC'));
        $this->getJson('/api/security-test-error')->assertStatus(500)
            ->assertExactJson(['message' => 'Permintaan tidak dapat diproses.'])->assertDontSee('SECRET-SYNTHETIC');
    }
    private function recovery(User $user, bool $verified = true): void
    {
        config(['security.password_reset_enabled' => true, 'security.password_reset_url' => 'https://school.example/reset-password', 'mail.default' => 'smtp']);
        $user->forceFill(['email' => $user->username.'@example.test', 'email_verified_at' => $verified ? now() : null])->save();
        Notification::fake();
    }
    public function test_recovery_responses_hide_account_existence_and_unverified_email_is_blocked(): void
    {
        $user = $this->account();
        $this->recovery($user, false);
        $first = $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk()->json();
        $second = $this->postJson('/api/forgot-password', ['email' => 'missing@example.test'])->assertOk()->json();
        $this->assertSame($first, $second);
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }
    public function test_reset_token_is_hashed_one_use_and_revokes_all_auth_tokens(): void
    {
        $user = $this->account();
        $this->recovery($user);
        $this->signIn($user);
        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
        $token = null;
        Notification::assertSentTo($user, \Illuminate\Auth\Notifications\ResetPassword::class, function ($notice) use (&$token) { $token = $notice->token; return true; });
        $this->assertNotSame($token, \Illuminate\Support\Facades\DB::table('password_reset_tokens')->value('token'));
        $body = ['email' => $user->email, 'token' => $token, 'password' => 'New-Strong123!', 'password_confirmation' => 'New-Strong123!'];
        $this->postJson('/api/reset-password', $body)->assertOk();
        $this->assertSame(0, $user->tokens()->count());
        $this->assertTrue(Hash::check('New-Strong123!', $user->fresh()->password));
        $this->postJson('/api/reset-password', $body)->assertUnprocessable();
    }
    public function test_expired_reset_token_cannot_change_password(): void
    {
        $user = $this->account();
        $this->recovery($user);
        $token = Password::createToken($user);
        $this->travel(31)->minutes();
        $this->postJson('/api/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'New-Strong123!', 'password_confirmation' => 'New-Strong123!'])->assertUnprocessable();
        $this->assertTrue(Hash::check('Strong-Test123!', $user->fresh()->password));
        $this->travelBack();
    }
    public function test_recovery_stays_closed_until_server_and_email_are_configured(): void
    {
        config(['security.password_reset_enabled' => false]);
        $this->postJson('/api/forgot-password', ['email' => 'synthetic@example.test'])->assertStatus(503);
    }
    public function test_production_forces_debug_off_and_rejects_plain_http(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => true, 'session.secure' => false]);
        (new \App\Providers\AppServiceProvider($this->app))->boot();
        $this->assertFalse(config('app.debug'));
        $this->assertTrue(config('session.secure'));
        $this->getJson('/api/me')->assertForbidden()->assertJsonMissingPath('trace');
        $this->app['env'] = 'testing';
    }
    public function test_xlsx_import_scans_file_and_does_not_publish_alumni_without_consent(): void
    {
        if (! is_readable(base_path('composer.json'))) {
            $this->markTestSkipped('Native PHP is_readable returns false in this managed Windows environment; rerun the full Excel import on the target server.');
        }
        $student = $this->student($this->account());
        $this->signIn($this->account('administrator'));
        config(['alumni_import.sheets' => ['SNBP' => ['nama' => 'NAMA', 'pt' => 'PERGURUAN TINGGI', 'prodi' => 'PROGRAM STUDI', 'strata' => null, 'jalur_masuk' => 'SNBP']]]);
        $scanned = false;
        $scanner = \Mockery::mock(\App\Services\Security\ClamAvScanner::class);
        $scanner->shouldReceive('assertClean')->once()->andReturnUsing(function ($path) use (&$scanned) { $scanned = is_file($path); });
        $this->app->instance(\App\Services\Security\ClamAvScanner::class, $scanner);
        $sheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet->getActiveSheet()->setTitle('SNBP')->fromArray([
            ['NAMA', 'PERGURUAN TINGGI', 'PROGRAM STUDI'],
            [$student->nama_lengkap, 'Universitas Contoh', 'Informatika'],
        ]);
        $dir = storage_path('framework/testing');
        if (! is_dir($dir)) { mkdir($dir, 0700, true); }
        $path = $dir.'/'.bin2hex(random_bytes(16)).'.xlsx';
        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sheet))->save($path);
            $file = new \Illuminate\Http\UploadedFile($path, 'school.xlsx', null, null, true);
            $this->postJson('/api/alumni/import', ['file' => $file])->assertOk()->assertJsonPath('berhasil', 1)->assertJsonPath('gagal', 0);
            $this->assertTrue($scanned);
            $this->assertDatabaseHas('study_tracer', ['siswa_id' => $student->id, 'izinkan_tampil_di_buku' => false]);
        } finally {
            $sheet->disconnectWorksheets();
            @unlink($path);
        }
    }
    public function test_import_row_validation_and_explicit_consent_default(): void
    {
        $student = $this->student($this->account());
        $parent = new \App\Imports\AlumniImport;
        $mapping = ['nama' => 'NAMA', 'pt' => 'PERGURUAN TINGGI', 'prodi' => 'PROGRAM STUDI', 'strata' => null, 'jalur_masuk' => 'SNBP'];
        $import = new \App\Imports\AlumniSheetImport($parent, $mapping, 'SNBP');
        $import->collection(collect([collect(['nama' => $student->nama_lengkap, 'perguruan_tinggi' => 'Universitas Contoh', 'program_studi' => 'Informatika'])]));
        $this->assertSame(1, $parent->successCount);
        $this->assertSame([], $parent->errors);
        $this->assertDatabaseHas('study_tracer', ['siswa_id' => $student->id, 'izinkan_tampil_di_buku' => false]);
        $import->collection(collect([collect(['nama' => '<script>unsafe</script>', 'perguruan_tinggi' => 'Universitas Contoh', 'program_studi' => 'Informatika'])]));
        $this->assertSame(1, $parent->successCount);
        $this->assertSame('Baris tidak sesuai template atau data siswa tidak cocok.', $parent->errors[0]['pesan']);
    }
}
