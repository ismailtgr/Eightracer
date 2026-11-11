<?php
namespace App\Services\School;

use App\Models\Siswa;
use Illuminate\Database\Eloquent\Builder;

class LocalExampleRecords
{
    public static function isAlumniExample(Siswa $student): bool
    {
        $notes = json_decode($student->catatan ?? '{}', true);
        return ($notes['fixture_key'] ?? null) === 'b4-local-alumni-20261111' && ($notes['synthetic'] ?? false) === true;
    }

    public static function exclude(Builder $query): Builder
    {
        $column = $query->getModel()->qualifyColumn('catatan');
        return $query->where(fn ($q) => $q->whereNull($column)->orWhere($column, 'not like', '%"fixture_key":"b4-local-%'));
    }
}
