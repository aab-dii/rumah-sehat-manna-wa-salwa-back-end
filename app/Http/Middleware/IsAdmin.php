<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 2.1: Middleware untuk admin DAN super_admin.
 * - Cek role admin atau super_admin
 * - Cek akun aktif (is_active)
 * - Update last_active_at setiap request
 */
class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->isAdminOrSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        if (!$user->isActive()) {
            return response()->json(['message' => 'Akun Anda telah dinonaktifkan. Hubungi Super Admin.'], 403);
        }

        // Track aktivitas terakhir admin
        $user->updateQuietly(['last_active_at' => now()]);

        return $next($request);
    }
}
