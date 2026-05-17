<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 2.1: Middleware global untuk cek akun aktif.
 * - Berlaku untuk semua role (pasien, terapis, admin, super_admin)
 * - Jika is_active = false → hapus token & tolak akses
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->isActive()) {
            // Hapus token agar user dipaksa login ulang
            $accessToken = $user->currentAccessToken();
            if ($accessToken) {
                $accessToken->delete();
            }

            return response()->json([
                'message' => 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator.'
            ], 401);
        }

        return $next($request);
    }
}
