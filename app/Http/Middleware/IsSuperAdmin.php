<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 2.1: Middleware khusus Super Admin.
 * - Hanya role super_admin yang diizinkan
 * - Cek akun aktif (is_active)
 */
class IsSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized. Super Admin access only.'], 403);
        }

        if (!$user->isActive()) {
            return response()->json(['message' => 'Akun Anda telah dinonaktifkan.'], 403);
        }

        // Track aktivitas terakhir
        $user->updateQuietly(['last_active_at' => now()]);

        return $next($request);
    }
}
