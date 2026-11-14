<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Update\UpdateException;
use App\Update\UpdateStatePresenter;
use App\Update\WebUpdater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UpdaterController extends Controller
{
    public function __construct(
        private UpdateStatePresenter $presenter = new UpdateStatePresenter,
    ) {}

    public function index(WebUpdater $updater): View
    {
        $state = $this->presenter->present($updater->status());

        return view('admin.update.index', [
            'state' => $state,
            'bypassPath' => $this->bypassPath($updater->status()),
            'recoveryToken' => $this->recoveryToken($updater->status()),
        ]);
    }

    public function status(WebUpdater $updater): JsonResponse
    {
        $raw = $updater->status();

        return response()->json([
            'state' => $this->presenter->present($raw),
            'maintenance_bypass_path' => $this->bypassPath($raw),
        ]);
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
            'state' => $this->presenter->present($state),
            'maintenance_bypass_path' => $this->bypassPath($state),
            'recovery_token' => $this->recoveryToken($state),
        ]);
    }

    public function step(WebUpdater $updater): JsonResponse
    {
        $result = $updater->runNextStep(owner: 'admin-'.(string) auth()->id());
        $rawState = is_array($result['state'] ?? null) ? $result['state'] : $updater->status();
        $result['state'] = $this->presenter->present($rawState);
        $result['maintenance_bypass_path'] = $this->bypassPath($rawState);

        $status = isset($result['error']) ? 422 : 200;

        return response()->json($result, $status);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function bypassPath(array $state): ?string
    {
        $path = $state['context']['maintenance_bypass_path'] ?? null;
        if (is_string($path) && $path !== '') {
            return $path;
        }

        $secret = $state['context']['maintenance_secret'] ?? null;
        if (is_string($secret) && $secret !== '' && ! empty($state['context']['maintenance'])) {
            return '/'.$secret;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function recoveryToken(array $state): ?string
    {
        $token = $state['context']['recovery_token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }
}
