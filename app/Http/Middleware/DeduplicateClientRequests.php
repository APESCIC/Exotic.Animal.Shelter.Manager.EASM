<?php

namespace App\Http\Middleware;

use App\Models\ClientRequest;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies a write at most once per client request id (X-Client-Request-Id header or
 * _client_request_id field). Failed writes release the id so the client can retry.
 */
class DeduplicateClientRequests
{
    public const HEADER = 'X-Client-Request-Id';

    public const FIELD = '_client_request_id';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header(self::HEADER) ?? $request->input(self::FIELD);
        $user = $request->user();

        if ($request->isMethodSafe() || $user === null || ! is_string($requestId) || ! preg_match('/^[A-Za-z0-9-]{8,64}$/', $requestId)) {
            return $next($request);
        }

        try {
            $record = ClientRequest::query()->create([
                'user_id' => $user->id,
                'request_id' => $requestId,
                'method' => $request->method(),
                'path' => mb_substr($request->path(), 0, 255),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->alreadyApplied($request);
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 400 || $this->flashedErrors($request)) {
            $record->delete();

            return $response;
        }

        if (random_int(1, 100) === 1) {
            ClientRequest::query()->where('created_at', '<', now()->subDays(30))->delete();
        }

        if ($response->isRedirection() && $request->expectsJson()) {
            return response()->json([
                'saved' => true,
                'message' => $request->hasSession() ? $request->session()->pull('status') : null,
            ]);
        }

        return $response;
    }

    private function alreadyApplied(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['duplicate' => true, 'message' => 'Already saved.']);
        }

        return redirect()->back()->with('status', 'Already saved.');
    }

    private function flashedErrors(Request $request): bool
    {
        return $request->hasSession()
            && in_array('errors', (array) $request->session()->get('_flash.new', []), true);
    }
}
