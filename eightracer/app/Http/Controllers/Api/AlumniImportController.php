<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\AlumniImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AlumniImportController extends Controller
{
   public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $import = new AlumniImport();
        $import->setFilePath($request->file('file')->getRealPath());

        Excel::import($import, $request->file('file'));

        return response()->json([
            'message'      => 'Import selesai',
            'berhasil'     => $import->successCount,
            'gagal'        => count($import->errors),
            'error_detail' => $import->errors,
        ]);
    }
}