<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Concerns\PrdFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use App\Services\School\LocalStudentPreview;
use App\Models\BasisDataBeasiswa;

class LocalStudentPreviewTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;

    public function test_local_preview_shows_only_basic_alumni_and_real_catalog_without_publishing(): void
    {
        $this->app->instance('env','local');
        config(['b4.local_student_preview'=>true]);
        $alumni=$this->student(null,['tahun_lulus'=>2026]);
        $admission=$this->admission($alumni);
        $b=BasisDataBeasiswa::create(['nama_beasiswa'=>'Katalog Nyata Uji','jenjang'=>'Kuliah','is_active'=>true]);
        $this->signIn($this->account());
        $this->withServerVariables(['REMOTE_ADDR'=>'127.0.0.1']);
        $this->getJson('/api/jejak-alumni')->assertOk()->assertJsonPath('preview',true)->assertJsonPath('total',1)->assertDontSee('PRIVATE_PHONE')->assertDontSee('nisn');
        $this->getJson('/api/jejak-alumni/'.$admission->id)->assertOk()->assertJsonCount(0,'data.progress')->assertJsonCount(2,'data.timeline')->assertDontSee('PRIVATE_PHONE');
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertNotFound();
        $this->assertDatabaseCount('rekomendasi_beasiswa',0);
        $this->assertFalse((bool)$admission->fresh()->izinkan_tampil_di_buku);
        $this->assertNull($alumni->fresh()->user_id);
    }

    public function test_preview_is_closed_in_production_and_for_remote_addresses(): void
    {
        config(['b4.local_student_preview'=>true]);
        $request=Request::create('/api/jejak-alumni','GET',[],[],[],['REMOTE_ADDR'=>'127.0.0.1']);
        $user=$this->account();$request->setUserResolver(fn()=>$user);
        $this->app->instance('env','production');
        $this->assertFalse(app(LocalStudentPreview::class)->enabled($request));
        $this->app->instance('env','local');
        $request->server->set('REMOTE_ADDR','203.0.113.5');
        $this->assertFalse(app(LocalStudentPreview::class)->enabled($request));
        $request->server->set('REMOTE_ADDR','127.0.0.1');
        $this->assertTrue(app(LocalStudentPreview::class)->enabled($request));
        config(['b4.local_student_preview'=>false]);
        $this->assertFalse(app(LocalStudentPreview::class)->enabled($request));
    }
}
