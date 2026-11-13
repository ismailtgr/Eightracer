<?php

namespace Tests\Feature;

use App\Models\BasisDataBeasiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class CatalogOrderingTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;

    public function test_catalog_sorts_active_first_then_name_ignoring_case_with_stable_ties(): void
    {
        $names = [['Zeta', true], ['Arsip A', false], ['beta', true], [' Alpha ', true], ['alpha', true], ['Arsip Z', false]];
        $records = [];
        foreach ($names as [$name, $active]) {
            $records[] = BasisDataBeasiswa::create(['nama_beasiswa' => $name, 'jenjang' => 'Kuliah', 'is_active' => $active]);
        }
        $this->signIn($this->account('administrator'));
        $r = $this->getJson('/api/beasiswa')->assertOk();
        $this->assertSame([$records[3]->id, $records[4]->id, $records[2]->id, $records[0]->id, $records[1]->id, $records[5]->id], array_column($r->json('data'), 'id'));
        // Archive changes position on the next GET; no data is deleted.
        $this->deleteJson('/api/beasiswa/'.$records[3]->id)->assertOk();
        $r = $this->getJson('/api/beasiswa')->assertOk();
        $this->assertSame([$records[4]->id, $records[2]->id, $records[0]->id, $records[3]->id, $records[1]->id, $records[5]->id], array_column($r->json('data'), 'id'));
        $this->assertDatabaseCount('basis_data_beasiswa', 6);
    }

    public function test_order_is_global_before_pagination_and_filters_keep_the_same_order(): void
    {
        $archive = BasisDataBeasiswa::create(['nama_beasiswa' => 'AAA', 'jenjang' => 'Kuliah', 'is_active' => false]);
        $ids = [];
        for ($i = 21; $i >= 0; $i--) {
            $ids[$i] = BasisDataBeasiswa::create(['nama_beasiswa' => sprintf('Program %02d', $i), 'jenjang' => 'Kuliah', 'is_active' => true])->id;
        }
        ksort($ids);
        $this->signIn($this->account('administrator'));
        $first = $this->getJson('/api/beasiswa')->assertOk()->assertJsonPath('total', 23)->assertJsonCount(20, 'data');
        $second = $this->getJson('/api/beasiswa?page=2')->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame(array_values($ids), [...array_column($first->json('data'), 'id'), ...array_slice(array_column($second->json('data'), 'id'), 0, 2)]);
        $this->assertSame($archive->id, $second->json('data.2.id'));
        $filtered = $this->getJson('/api/beasiswa?search=Program%200&jenjang=Kuliah')->assertOk();
        $this->assertSame(array_slice(array_values($ids), 0, 10), array_column($filtered->json('data'), 'id'));
    }
}
