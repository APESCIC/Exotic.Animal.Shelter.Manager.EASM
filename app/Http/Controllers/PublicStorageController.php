<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves files from the public disk when public/storage cannot be a symlink.
 */
class PublicStorageController extends Controller
{
    public function __invoke(Request $request, string $path): StreamedResponse
    {
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');

        abort_if($path === '' || str_contains($path, '..') || str_contains($path, "\0"), 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path);
    }
}
