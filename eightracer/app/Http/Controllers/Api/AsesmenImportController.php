<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\AsesmenImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AsesmenImportController extends Controller
{
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:20480']);

        $import = new AsesmenImport();
        Excel::import($import, $request->file('file'));

        $gagal = count($import->errors);

        return response()->json([
            'message'      => 'Import asesmen (RIASEC & Multiple Intelligence) selesai',
            'berhasil'     => $import->successCount,
            'dilewati'     => $import->skippedCount,
            'peringatan'   => $import->peringatan,
            'gagal'        => $gagal,
            'error_detail' => $import->errors,
        ], $gagal === 0 ? 200 : 207);
    }
}
