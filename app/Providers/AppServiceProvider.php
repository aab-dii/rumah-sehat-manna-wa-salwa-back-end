<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // RATE LIMITER: booking_limiter
        // ═══════════════════════════════════════════════════════════════════
        // Membatasi pembuatan booking maks 5 request per menit per user.
        // Menggunakan User ID (bukan IP) karena rute ini di-protect auth:sanctum.
        // Tujuan: Mencegah spam booking & mengurangi risiko race condition.
        RateLimiter::for('booking_limiter', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'meta' => [
                            'code'    => 429,
                            'status'  => 'error',
                            'message' => 'Terlalu banyak permintaan. Anda hanya dapat membuat maksimal 5 booking per menit. Silakan coba lagi nanti.',
                        ],
                        'data' => null,
                    ], 429, $headers);
                });
        });

        // ═══════════════════════════════════════════════════════════════════
        // RATE LIMITER: api (Global untuk semua rute API)
        // ═══════════════════════════════════════════════════════════════════
        // Membatasi semua rute API maks 60 request per menit per user/IP.
        // Tujuan: Mencegah DDoS dan abuse pada seluruh endpoint API.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'meta' => [
                            'code'    => 429,
                            'status'  => 'error',
                            'message' => 'Terlalu banyak permintaan. Maksimal 60 request per menit. Silakan coba lagi nanti.',
                        ],
                        'data' => null,
                    ], 429, $headers);
                });
        });
    }
}
