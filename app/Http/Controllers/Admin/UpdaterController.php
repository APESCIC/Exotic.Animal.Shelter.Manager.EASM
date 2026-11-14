<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Update\UpdateException;
use App\Update\WebUpdater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UpdaterController extends Controller
{
    public function index(WebUpdater $updater): View
    {
        return view('admin.update.index', [
            'state' => $updater->status(),
        ]);
    }

    public function status(WebUpdater $updater): JsonResponse
    {
        return response()->json(['state' => $updater->status()]);
    }

    public function start(Request $request, WebUpdater $updater): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'target_version' => ['required', 'string', 'max:32'],
            'package_url' => ['nullable', 'url', 'max:2048'],
            'signature_url' => ['nullable', 'url', 'max:2048'],
            'sha256' => ['nullable', 'string', 'size:64'],
            'package_path' => ['nullable', 'string', 'max:2048'],
            'signature_path' => ['nullable', 'string', 'max:2048'],
        ]);

        if (! Hash::check($validated['password'], (string) $request->user()->password)) {
            return response()->json([
                'message' => 'Password confirmation failed.',
            ], 422);
        }

        try {
            $state = $updater->start($validated['target_version'], [
                'package_url' => $validated['package_url'] ?? null,
                'signature_url' => $validated['signature_url'] ?? null,
                'sha256' => $validated['sha256'] ?? null,
                'package_path' => $validated['package_path'] ?? null,
                'signature_path' => $validated['signature_path'] ?? null,
            ]);
        } catch (UpdateException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'message' => 'Update started.',
            'state' => $state,
        ]);
    }

    public function step(WebUpdater $updater): JsonResponse
    {
        $result = $updater->runNextStep(owner: 'admin-'.(string) auth()->id());

        $status = isset($result['error']) ? 422 : 200;

        return response()->json($result, $status);
    }
}
