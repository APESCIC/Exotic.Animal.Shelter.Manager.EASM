<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Update\ReleaseUpload;
use App\Update\UpdateException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReleaseUploadController extends Controller
{
    public function begin(ReleaseUpload $uploads): JsonResponse
    {
        $started = $uploads->begin();

        return response()->json([
            'upload_id' => $started['upload_id'],
            'max_chunk_bytes' => ReleaseUpload::MAX_CHUNK_BYTES,
        ]);
    }

    public function chunk(Request $request, ReleaseUpload $uploads): JsonResponse
    {
        $validated = $request->validate([
            'upload_id' => ['required', 'string', 'max:64'],
            'kind' => ['required', 'in:package,signature'],
            'index' => ['required', 'integer', 'min:0'],
            'final' => ['sometimes', 'boolean'],
            'chunk' => ['required', 'file', 'max:4096'], // kilobytes ≈ 4 MiB
        ]);

        $file = $request->file('chunk');
        if ($file === null || ! $file->isValid()) {
            return response()->json(['message' => 'Upload chunk is missing or invalid.'], 422);
        }

        try {
            $result = $uploads->appendChunk(
                (string) $validated['upload_id'],
                (string) $validated['kind'],
                (int) $validated['index'],
                (string) file_get_contents($file->getRealPath()),
                (bool) ($validated['final'] ?? false),
            );
        } catch (UpdateException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function finish(Request $request, ReleaseUpload $uploads): JsonResponse
    {
        $validated = $request->validate([
            'upload_id' => ['required', 'string', 'max:64'],
        ]);

        try {
            $paths = $uploads->finish((string) $validated['upload_id']);
        } catch (UpdateException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Upload ready.',
            'package_path' => $paths['package_path'],
            'signature_path' => $paths['signature_path'],
            'upload_id' => $paths['upload_id'],
            'sha256' => hash_file('sha256', $paths['package_path']),
        ]);
    }
}
