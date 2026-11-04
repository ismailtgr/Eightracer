<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\SchoolAccountProvisioner;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class SchoolAccountController extends Controller
{
    public function provision(Request $request, SchoolAccountProvisioner $provisioner)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $data = $request->validate([
            'siswa_ids' => 'required|array|min:1|max:100',
            'siswa_ids.*' => 'required|integer|distinct|exists:siswa,id',
            'dry_run' => 'required|boolean',
            'role' => 'prohibited', 'password' => 'prohibited', 'email_verified_at' => 'prohibited',
        ]);
        try {
            $result = $provisioner->provision($data['siswa_ids'], $request->boolean('dry_run'), $request->user());
        } catch (QueryException $error) {
            if (in_array((string) $error->getCode(), ['23000', '23505'], true)) {
                abort(409);
            }
            throw $error;
        }

        return response()->json($result, $result['created_count'] > 0 ? 201 : 200)
            ->header('Cache-Control', 'no-store, private');
    }
}
