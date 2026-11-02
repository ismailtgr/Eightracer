<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrivateAttachment;
use App\Models\Siswa;
use App\Services\Security\ClamAvScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function store(Request $r, Siswa $siswa, ClamAvScanner $scanner)
    {
        $r->validate(['file' => 'required|file|extensions:pdf,jpg,jpeg,png|mimes:pdf,jpg,jpeg,png|max:5120']);
        $f = $r->file('file');
        $scanner->assertClean($f->getRealPath());
        $extension = match ($f->getMimeType()) {
            'application/pdf' => 'pdf','image/jpeg' => 'jpg','image/png' => 'png',default => null
        };
        abort_unless($extension, 422);
        $path = 'attachments/'.bin2hex(random_bytes(24)).'.'.$extension;
        abort_unless(Storage::disk('local')->put($path, file_get_contents($f->getRealPath())), 503);
        $row = PrivateAttachment::create(['siswa_id' => $siswa->id, 'path' => $path, 'mime' => $f->getMimeType(), 'size' => $f->getSize(), 'original_name' => mb_substr(basename(str_replace('\\', '/', $f->getClientOriginalName())), 0, 255), 'uploaded_by' => $r->user()->id]);

        return response()->json(['data' => $row->only(['id', 'siswa_id', 'mime', 'size'])], 201);
    }

    public function download(Request $r, int $id)
    {
        $row = PrivateAttachment::findOrFail($id);
        abort_unless($r->user()->role === 'administrator' || ($r->user()->role === 'siswa' && $r->user()->siswa?->id === $row->siswa_id), 403);

        return Storage::disk('local')->download($row->path, 'dokumen-'.$row->id.'.'.pathinfo($row->path, PATHINFO_EXTENSION), ['Content-Type' => $row->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private']);
    }
}
