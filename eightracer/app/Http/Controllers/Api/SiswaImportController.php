<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\SiswaImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class SiswaImportController extends Controller
{
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:20480']);

        $import = new SiswaImport();
        Excel::import($import, $request->file('file'));

        $gagal = count($import->errors);

        return response()->json([
            'message'         => 'Import biodata siswa selesai',
            'berhasil_baru'   => $import->successCount,
            'diperbarui'      => $import->updatedCount,
            'dilewati'        => $import->skippedCount,
            'dilewati_detail' => $import->skippedDetail,
            'peringatan'      => $import->peringatan,
            'gagal'           => $gagal,
            'error_detail'    => $import->errors,
        ], $gagal === 0 ? 200 : 207);
    }
}
