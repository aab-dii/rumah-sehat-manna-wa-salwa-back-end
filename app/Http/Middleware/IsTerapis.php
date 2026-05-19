<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk role terapis.
 * - Hanya role terapis yang diizinkan
 * - Cek akun aktif (is_active)
 */
class IsTerapis
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->role !== 'terapis') {
            return response()->json(['message' => 'Unauthorized. Therapist access only.'], 403);
        }

        if (!$user->isActive()) {
            return response()->json(['message' => 'Akun Anda telah dinonaktifkan.'], 403);
        }

        $user->updateQuietly(['last_active_at' => now()]);

        return $next($request);
    }
}
