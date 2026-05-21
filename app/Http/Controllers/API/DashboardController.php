<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Unified dashboard endpoint — role-aware scoping.
     * Same concept as BookingController::all():
     *   - admin    → all bookings
     *   - terapis  → only their own (therapist_id = auth id)
     *   - pasien   → only their own (patient_id = auth id)
     */
    public function index(Request $request)
    {
        $timezone  = $request->input('timezone', 'Asia/Jakarta');
        $today     = Carbon::now($timezone)->toDateString();
        $user      = Auth::user();

        // ── Base query scoped by role ──────────────────────────────────────
        $base = Booking::query();

        if ($user->role === 'terapis') {
            $base->where('therapist_id', $user->id);
        } elseif ($user->role === 'pasien') {
            $base->where('patient_id', $user->id);
        }
        // admin → no scope (sees everything)

        // ── Today stats ───────────────────────────────────────────────────
        $todayBase = (clone $base)->whereDate('booking_date', $today);

        $todayStats = [
            'confirmed' => (clone $todayBase)->where('status', 'confirmed')->count(),
            'completed' => (clone $todayBase)->where('status', 'completed')->count(),
            'canceled'  => (clone $todayBase)->where('status', 'canceled')->count(),
            'total'     => (clone $todayBase)->count(),
        ];

        // ── Upcoming agenda (next 3 confirmed from today) ─────────────────
        $upcomingAgenda = (clone $base)
            ->with(['patient:id,name', 'therapist:id,name', 'service:id,name'])
            ->where('status', 'confirmed')
            ->whereDate('booking_date', '>=', $today)
            ->orderBy('booking_date')
            ->orderBy('booking_time')
            ->limit(3)
            ->get()
            ->map(fn($b) => [
                'id'           => $b->id,
                'patient_name' => $b->patient->name  ?? '-',
                'therapist_name' => $b->therapist->name ?? '-',
                'service_name' => $b->service->name  ?? '-',
                'booking_date' => $b->booking_date,
                'booking_time' => $b->booking_time,
                'status'       => $b->status,
            ]);

        // ── Admin-only extras ─────────────────────────────────────────────
        $adminExtras = [];
        if ($user->role === 'admin' || $user->role === 'super_admin') {
            // Statistik Admin Hari Ini (untuk cards)
            $adminExtras['admin_stats'] = [
                'confirmed'            => (clone $todayBase)->where('status', 'confirmed')->count(),
                'pending'              => (clone $todayBase)->where('status', 'pending')->count(),
                'waiting_verification' => (clone $todayBase)->where('status', 'waiting_verification')->count(),
                'canceled'             => (clone $todayBase)->where('status', 'canceled')->count(),
            ];

            // Revenue bulan ini dari transactions.amount (sumber kebenaran harga)
            $startOfMonth = Carbon::now($timezone)->startOfMonth()->toDateString();
            $adminExtras['monthly_revenue'] = Booking::whereDate('booking_date', '>=', $startOfMonth)
                ->whereDate('booking_date', '<=', $today)
                ->whereHas('transaction', fn($q) => $q->where('status', 'paid'))
                ->with('transaction')
                ->get()
                ->sum(fn($b) => $b->transaction->amount ?? 0);
        }

        return response()->json(array_merge([
            'today_stats'     => $todayStats,
            'upcoming_agenda' => $upcomingAgenda,
            'server_today'    => $today,
        ], $adminExtras));
    }
}
