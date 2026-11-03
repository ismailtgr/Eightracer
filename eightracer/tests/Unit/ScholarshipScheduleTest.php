<?php

namespace Tests\Unit;

use App\Models\BasisDataBeasiswa;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScholarshipScheduleTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_active_catalog_program_can_have_closed_registration_without_being_archived(): void
    {
        Carbon::setTestNow('2026-11-04 03:00:00 UTC');
        $row = new BasisDataBeasiswa(['tanggal_buka' => '2026-09-01', 'tanggal_tutup' => '2026-10-31']);
        $before = $row->getAttributes();
        $this->assertSame('aktif', $row->status_program);
        $this->assertSame('ditutup', $row->status_pendaftaran);
        $this->assertTrue($row->is_active);
        $this->assertSame($before, $row->getAttributes());
    }

    public function test_registration_boundaries_use_wib_and_include_the_whole_closing_day(): void
    {
        $row = new BasisDataBeasiswa(['tanggal_buka' => '2026-09-01', 'tanggal_tutup' => '2026-10-31']);
        foreach ([
            '2026-08-31 16:59:59 UTC' => 'belum_dibuka',
            '2026-08-31 17:00:00 UTC' => 'dibuka',
            '2026-10-31 16:59:59 UTC' => 'dibuka',
            '2026-10-31 17:00:00 UTC' => 'ditutup',
        ] as $instant => $status) {
            Carbon::setTestNow($instant);
            $this->assertSame($status, $row->status_pendaftaran, $instant);
        }
    }

    public function test_month_text_and_partial_dates_do_not_assert_that_registration_is_open(): void
    {
        Carbon::setTestNow('2026-11-04 03:00:00 UTC');
        foreach ([['periode_apply' => 'September–Oktober'], ['tanggal_buka' => '2026-09-01'], ['tanggal_tutup' => '2026-12-31'],
            ['tanggal_buka' => '2026-11-01', 'tanggal_tutup' => '2026-10-01']] as $data) {
            $this->assertSame('belum_ditentukan', (new BasisDataBeasiswa($data))->status_pendaftaran);
        }
        $this->assertSame('belum_dibuka', (new BasisDataBeasiswa(['tanggal_buka' => '2026-12-01']))->status_pendaftaran);
        $this->assertSame('ditutup', (new BasisDataBeasiswa(['tanggal_tutup' => '2026-10-31']))->status_pendaftaran);
    }

    public function test_archived_program_is_unavailable_even_inside_the_date_window(): void
    {
        Carbon::setTestNow('2026-10-15 03:00:00 UTC');
        $row = new BasisDataBeasiswa(['is_active' => false, 'tanggal_buka' => '2026-09-01', 'tanggal_tutup' => '2026-10-31']);
        $this->assertSame('diarsipkan', $row->status_program);
        $this->assertSame('tidak_tersedia', $row->status_pendaftaran);
    }
}
