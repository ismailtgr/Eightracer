<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class CrudCoverageTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;

    public function test_all_reference_types_complete_create_read_update_delete(): void
    {
        $this->signIn($this->account('administrator'));
        $cases = [
            'kelas' => ['nama_kelas', ['nama_kelas'=>'Kelas Uji','tingkat'=>12,'tahun_ajaran'=>'2026/2027']],
            'mata-pelajaran' => ['nama_mapel', ['nama_mapel'=>'Mapel Uji','jenis_mapel'=>'A']],
            'jenis-bantuan' => ['nama_bantuan', ['nama_bantuan'=>'Bantuan Uji']],
            'hobi' => ['nama_hobi', ['nama_hobi'=>'Hobi Uji']],
            'cita-cita' => ['nama_cita_cita', ['nama_cita_cita'=>'Cita Uji']],
            'perguruan-tinggi' => ['nama_pt', ['nama_pt'=>'Kampus Uji','jenis_pt'=>'Negeri']],
        ];
        foreach ($cases as $type => [$field,$body]) {
            $path='/api/referensi/'.$type;
            $id=$this->postJson($path,$body)->assertCreated()->json('data.id');
            $this->getJson($path)->assertOk()->assertJsonPath('data.0.id',$id);
            $this->putJson($path.'/'.$id,[$field=>'Nama Revisi']+$body)->assertOk()->assertJsonPath('data.'.$field,'Nama Revisi');
            $this->deleteJson($path.'/'.$id)->assertOk();
            $this->getJson($path)->assertOk()->assertJsonCount(0,'data');
            $this->putJson($path.'/'.$id,$body)->assertNotFound();
        }
    }

    public function test_student_and_all_support_records_crud_preserve_ownership(): void
    {
        $this->signIn($this->account('administrator'));
        $id=$this->postJson('/api/siswa',['nisn'=>'1234567890','nama_lengkap'=>'Profil Uji','jenis_kelamin'=>'P'])->assertCreated()->json('data.id');
        $this->getJson('/api/siswa/'.$id)->assertOk();
        $this->putJson('/api/siswa/'.$id,['nama_lengkap'=>'Profil Revisi'])->assertOk()->assertJsonPath('data.nama_lengkap','Profil Revisi');
        $other=$this->student();
        $cases=[
            'karakter'=>['semester'=>1,'jenis_catatan'=>'Disiplin','poin_disiplin'=>1,'tanggal_pencatatan'=>today()->toDateString()],
            'organisasi'=>['jenis_organisasi'=>'Sekolah','nama_organisasi'=>'OSIS'],
            'saudara'=>['nama'=>'Saudara','jenis_kelamin'=>'L','hubungan'=>'Adik'],
            'prestasi'=>['jenis_prestasi'=>'Akademik','nama_kompetisi'=>'Lomba','tingkat'=>'Nasional','status_verifikasi'=>'pending'],
        ];
        foreach($cases as $type=>$body) {
            $path='/api/siswa/'.$id.'/pendukung/'.$type;
            $record=$this->postJson($path,$body)->assertCreated()->json('data.id');
            $this->getJson('/api/siswa/'.$id.'/data-pendukung')->assertOk();
            $this->putJson($path.'/'.$record,$body)->assertOk();
            $wrong='/api/siswa/'.$other->id.'/pendukung/'.$type.'/'.$record;
            $this->putJson($wrong,$body)->assertNotFound();
            $this->deleteJson($wrong)->assertNotFound();
            $this->deleteJson($path.'/'.$record)->assertOk();
            $this->deleteJson($path.'/'.$record)->assertNotFound();
        }
        $this->deleteJson('/api/siswa/'.$id)->assertOk();
        $this->getJson('/api/siswa/'.$id)->assertNotFound();
    }

    public function test_social_and_aid_crud_and_student_write_restrictions(): void
    {
        $s=$this->student();$other=$this->student();
        $this->signIn($this->account('administrator'));
        $aid=$this->postJson('/api/referensi/jenis-bantuan',['nama_bantuan'=>'PIP Uji'])->assertCreated()->json('data.id');
        foreach([
            ['media-sosial',['platform'=>'Instagram','username'=>'akun.uji']],
            ['bantuan',['jenis_bantuan_id'=>$aid,'aktif'=>true]],
        ] as [$type,$body]){
            $base='/api/siswa/'.$s->id;
            $id=$this->postJson($base.'/'.$type,$body)->assertCreated()->json('data.id');
            $this->getJson($base.'/'.$type)->assertOk()->assertJsonCount(1,'data');
            $path=$base.'/keluarga/'.$type.'/'.$id;
            $updated=$type === 'media-sosial' ? ['platform'=>'TikTok','username'=>'akun.revisi'] : [...$body,'aktif'=>false];
            $response=$this->putJson($path,$updated)->assertOk();
            if ($type === 'media-sosial') {
                $response->assertJsonPath('data.platform','TikTok');
                $this->assertDatabaseHas('media_sosial',['id'=>$id,'jenis_media'=>'TikTok','username'=>'akun.revisi']);
                $this->getJson($base.'/'.$type)->assertOk()->assertJsonPath('data.0.platform','TikTok');
            } else {
                $this->assertDatabaseHas('bantuan_siswa',['id'=>$id,'aktif'=>false]);
            }
            $this->deleteJson('/api/siswa/'.$other->id.'/keluarga/'.$type.'/'.$id)->assertNotFound();
            $this->deleteJson($path)->assertOk();
            $this->getJson($base.'/'.$type)->assertOk()->assertJsonCount(0,'data');
        }
        $this->signIn($this->account());
        $this->postJson('/api/siswa',['nisn'=>'1234567891','nama_lengkap'=>'Tidak Boleh','jenis_kelamin'=>'L'])->assertForbidden();
        $this->putJson('/api/siswa/'.$s->id,['nama_lengkap'=>'Tidak Boleh'])->assertForbidden();
        $this->deleteJson('/api/siswa/'.$s->id)->assertForbidden();
        $this->postJson('/api/referensi/hobi',['nama_hobi'=>'Tidak Boleh'])->assertForbidden();
    }
}
