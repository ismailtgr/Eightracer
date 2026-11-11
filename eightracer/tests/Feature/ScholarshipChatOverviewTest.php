<?php
namespace Tests\Feature;

use App\Models\BasisDataBeasiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class ScholarshipChatOverviewTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gemini.api_key' => null, 'cache.default' => 'array']);
        Http::preventStrayRequests();
    }

    private function program(string $name, int $minimum, array $extra = []): BasisDataBeasiswa
    {
        $b = BasisDataBeasiswa::create([...['nama_beasiswa' => $name, 'jenjang' => 'SMA', 'is_active' => true], ...$extra]);
        $b->kriteriaKelayakanBeasiswa()->create(['nama_kriteria' => 'TKA minimum', 'tipe_kriteria' => 'nilai_tka', 'operator' => '>=', 'nilai_kriteria' => (string) $minimum]);
        return $b;
    }

    public function test_general_question_compares_own_profile_without_selecting_a_catalog_or_publishing_results(): void
    {
        $user = $this->account();
        $student = $this->student($user, ['nilai_tka' => 90]);
        $this->classroom($student);
        $other = $this->student($this->account(), ['nilai_tka' => 100]);
        $this->classroom($other);
        $accepted = $this->program('Program Rapor', 85);
        $rejected = $this->program('Program Prestasi', 95);
        $this->program('Program Nonaktif', 80, ['is_active' => false]);
        $this->program('Program Kedaluwarsa', 80, ['tanggal_tutup' => today()->subDay()]);
        $this->program('Program Kuliah', 80, ['jenjang' => 'Kuliah']);
        $this->signIn($user);
        foreach (['Aku cocok beasiswa apa', 'Beasiswa apa yang cocok buat aku?', 'Rekomendasi beasiswa untuk saya'] as $question) {
            $response = $this->postJson('/api/chat-beasiswa', ['message' => $question])->assertOk()
                ->assertJsonPath('data.status', 'recommendation_overview')->assertJsonPath('data.suggestions', [])
                ->assertJsonCount(2, 'data.candidates')->assertDontSee('Pilih satu program');
            $rows = collect($response->json('data.candidates'))->keyBy('beasiswa_id');
            $this->assertTrue($rows[$accepted->id]['evaluasi']['memenuhi']);
            $this->assertFalse($rows[$rejected->id]['evaluasi']['memenuhi']);
            $response->assertDontSee('Program Nonaktif')->assertDontSee('Program Kedaluwarsa')->assertDontSee('Program Kuliah');
        }
        $this->assertDatabaseCount('rekomendasi_beasiswa', 0);
        Http::assertNothingSent();
    }

    public function test_unknown_profile_values_are_not_claimed_to_meet_requirements(): void
    {
        $user = $this->account();
        $student = $this->student($user);
        $this->classroom($student);
        $this->program('Program Akademik', 85);
        $this->signIn($user);
        $this->postJson('/api/chat-beasiswa', ['message' => 'Aku cocok beasiswa apa'])->assertOk()
            ->assertJsonPath('data.candidates.0.evaluasi.lengkap', false)
            ->assertJsonPath('data.candidates.0.evaluasi.memenuhi', false)
            ->assertSee('Data belum cukup');
    }

    public function test_overview_gemini_cannot_invent_awards_or_receive_student_identity(): void
    {
        $user = $this->account();
        $student = $this->student($user, ['nama_lengkap' => 'PRIVATE_NAME', 'nilai_tka' => 90]);
        $this->classroom($student);
        $this->program('Program Akademik', 85);
        config(['gemini.api_key' => 'synthetic-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => function ($request) use ($student, $user) {
            foreach ([$student->nisn, 'PRIVATE_NAME', $user->email] as $secret) $this->assertStringNotContainsString($secret, $request['input']);
            $json = json_encode(['fact_ids' => ['profile'], 'action_ids' => ['bk'], 'answer' => 'Pasti lolos Beasiswa Fiktif 99 persen']);
            return Http::response(['status' => 'completed', 'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $json]]]]]);
        }]);
        $this->signIn($user);
        $this->postJson('/api/chat-beasiswa', ['message' => 'PRIVATE_NAME '.$student->nisn.' cocok beasiswa apa'])->assertOk()
            ->assertJsonPath('data.llm_used', true)->assertDontSee('Beasiswa Fiktif')->assertDontSee('PRIVATE_NAME');
    }

    public function test_preparation_requirements_and_schedule_questions_work_without_program_selection(): void
    {
        $user = $this->account();
        $student = $this->student($user, ['nilai_tka' => 90]);
        $this->classroom($student);
        $this->program('Program Akademik', 85);
        $this->signIn($user);
        foreach ([
            ['Apa yang perlu aku persiapkan?', 'preparation_guidance', 'salinan rapor'],
            ['Dokumen apa yang harus disiapkan?', 'preparation_guidance', 'identitas'],
            ['Apa saja persyaratannya?', 'requirements_overview', 'TKA'],
            ['Kapan pendaftarannya dibuka?', 'schedule_overview', 'belum tercatat'],
            ['Kenapa beasiswa ini direkomendasikan?', 'recommendation_overview', 'Program Akademik'],
        ] as [$message, $status, $text]) {
            $this->postJson('/api/chat-beasiswa', ['message' => $message])->assertOk()
                ->assertJsonPath('data.status', $status)->assertJsonPath('data.suggestions', [])
                ->assertSee($text)->assertDontSee('Pilih satu program');
        }
        $this->assertDatabaseCount('rekomendasi_beasiswa', 0);
        Http::assertNothingSent();
    }

    public function test_general_preparation_is_available_when_catalog_and_class_profile_are_missing(): void
    {
        $user = $this->account();
        $this->student($user);
        $this->signIn($user);
        $this->postJson('/api/chat-beasiswa', ['message' => 'Apa yang perlu aku persiapkan?'])->assertOk()
            ->assertJsonPath('data.status', 'preparation_guidance')->assertJsonCount(0, 'data.candidates')
            ->assertSee('salinan rapor')->assertSee('Jenjang sekolahmu belum tercatat')->assertDontSee('Pilih satu program');
        Http::assertNothingSent();
    }

    public function test_followup_keeps_the_program_but_an_explicit_new_name_switches_context(): void
    {
        $user = $this->account();
        $student = $this->student($user, ['nilai_tka' => 90]);
        $this->classroom($student);
        $first = $this->program('Beasiswa Tanoto', 85);
        $second = $this->program('Beasiswa Lotte', 95);
        $this->signIn($user);
        $this->postJson('/api/chat-beasiswa', ['message' => 'Apa yang perlu aku persiapkan?', 'beasiswa_id' => $first->id])->assertOk()
            ->assertJsonPath('data.beasiswa_id', $first->id)->assertSee('salinan rapor')->assertDontSee('Pilih satu program');
        $this->postJson('/api/chat-beasiswa', ['message' => 'Kalau Beasiswa Lotte bagaimana?', 'beasiswa_id' => $first->id])->assertOk()
            ->assertJsonPath('data.beasiswa_id', $second->id)->assertJsonPath('data.status', 'belum_memenuhi');
    }
}
