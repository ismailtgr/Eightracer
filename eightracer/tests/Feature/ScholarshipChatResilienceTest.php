<?php
namespace Tests\Feature;

use App\Models\BasisDataBeasiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class ScholarshipChatResilienceTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'gemini.api_key' => null, 'gemini.chat_requests_per_minute' => 30,
            'gemini.chat_llm_per_minute' => 5, 'gemini.chat_cache_seconds' => 120]);
        Http::preventStrayRequests();
    }

    private function prepareStudent()
    {
        $user = $this->account();
        $student = $this->student($user, ['nilai_tka' => 90]);
        $this->classroom($student);
        $b = BasisDataBeasiswa::create(['nama_beasiswa' => 'Program Akademik', 'jenjang' => 'SMA', 'is_active' => true]);
        $b->kriteriaKelayakanBeasiswa()->create(['nama_kriteria' => 'TKA', 'tipe_kriteria' => 'nilai_tka', 'operator' => '>=', 'nilai_kriteria' => '85']);
        $this->signIn($user);
        return $student;
    }

    private function provider(): void
    {
        config(['gemini.api_key' => 'synthetic-test-key']);
        $json = json_encode(['fact_ids' => ['profile'], 'action_ids' => ['official']]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['status' => 'completed',
            'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $json]]]]])]);
    }

    public function test_repeated_questions_use_cached_validated_sections_and_allow_more_than_five_messages(): void
    {
        $this->prepareStudent();
        $this->provider();
        foreach (range(1, 8) as $i) {
            $this->postJson('/api/chat-beasiswa', ['message' => 'Apa yang perlu aku persiapkan?'])->assertOk()
                ->assertJsonPath('data.llm_used', true)->assertSee('salinan rapor');
        }
        Http::assertSentCount(1);
    }

    public function test_provider_budget_keeps_answering_from_backend_instead_of_blocking_the_conversation(): void
    {
        $this->prepareStudent();
        $this->provider();
        config(['gemini.chat_llm_per_minute' => 1]);
        $this->postJson('/api/chat-beasiswa', ['message' => 'Apa yang perlu aku persiapkan?'])->assertOk()->assertJsonPath('data.llm_used', true);
        $this->postJson('/api/chat-beasiswa', ['message' => 'Apa saja persyaratannya?'])->assertOk()
            ->assertJsonPath('data.llm_used', false)->assertSee('TKA');
        Http::assertSentCount(1);
    }

    public function test_provider_quota_failure_is_cooled_down_and_does_not_become_a_chat_error(): void
    {
        $this->prepareStudent();
        config(['gemini.api_key' => 'synthetic-test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'PRIVATE_PROVIDER_DETAIL'], 429)]);
        foreach (['Apa yang perlu aku persiapkan?', 'Apa saja persyaratannya?'] as $message) {
            $this->postJson('/api/chat-beasiswa', compact('message'))->assertOk()
                ->assertJsonPath('data.llm_used', false)->assertDontSee('PRIVATE_PROVIDER_DETAIL');
        }
        Http::assertSentCount(1);
    }

    public function test_changed_eligibility_invalidates_cached_sections(): void
    {
        $student = $this->prepareStudent();
        $this->provider();
        $this->postJson('/api/chat-beasiswa', ['message' => 'Aku cocok beasiswa apa'])->assertOk()
            ->assertJsonPath('data.candidates.0.evaluasi.memenuhi', true);
        $student->update(['nilai_tka' => 70]);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/chat-beasiswa', ['message' => 'Aku cocok beasiswa apa'])->assertOk()
            ->assertJsonPath('data.candidates.0.evaluasi.memenuhi', false);
        Http::assertSentCount(2);
    }

    public function test_abuse_limit_still_returns_retry_time_and_is_scoped_to_each_account(): void
    {
        $this->prepareStudent();
        foreach (range(1, 30) as $i) $this->postJson('/api/chat-beasiswa', ['message' => 'Apa yang perlu aku persiapkan?'])->assertOk();
        $this->postJson('/api/chat-beasiswa', ['message' => 'Apa yang perlu aku persiapkan?'])->assertStatus(429)
            ->assertHeader('Retry-After')->assertJsonStructure(['message', 'retry_after']);
        $other = $this->account();
        $student = $this->student($other);
        $this->classroom($student);
        $this->signIn($other);
        $this->postJson('/api/chat-beasiswa', ['message' => 'Apa yang perlu aku persiapkan?'])->assertOk();
    }
}
