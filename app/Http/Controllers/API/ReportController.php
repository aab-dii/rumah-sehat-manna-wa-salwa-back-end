<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Service;
use App\Helpers\ResponseFormatter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    /**
     * Parsing filter periode ke [start_date, end_date]
     */
    private function parsePeriodFilter(Request $request)
    {
        $period = $request->query('period', 'monthly');
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        switch ($period) {
            case 'daily':
                $start = Carbon::now()->startOfDay();
                $end = Carbon::now()->endOfDay();
                break;
            case 'weekly':
                $start = Carbon::now()->startOfWeek();
                $end = Carbon::now()->endOfWeek();
                break;
            case 'monthly':
                $start = Carbon::now()->startOfMonth();
                $end = Carbon::now()->endOfMonth();
                break;
            case 'quarterly':
                $start = Carbon::now()->startOfQuarter();
                $end = Carbon::now()->endOfQuarter();
                break;
            case 'yearly':
                $start = Carbon::now()->startOfYear();
                $end = Carbon::now()->endOfYear();
                break;
            case 'custom':
                if ($request->has('start_date') && $request->has('end_date')) {
                    $start = Carbon::parse($request->start_date)->startOfDay();
                    $end = Carbon::parse($request->end_date)->endOfDay();
                }
                break;
        }

        return [$start, $end];
    }

    /**
     * Mengecek apakah pasien adalah pasien baru (belum ada riwayat completed sebelum start_date)
     */
    private function isNewPatient($patientId, $bookingId, $bookingDate)
    {
        $hasPrevious = Booking::where('patient_id', $patientId)
            ->whereIn('status', ['completed', 'force_completed'])
            ->where('booking_date', '<', $bookingDate)
            ->exists();
            
        return !$hasPrevious;
    }

    // =========================================================================
    // 1. LAPORAN KEUANGAN (Admin & Super Admin)
    // =========================================================================
    public function getFinancial(Request $request)
    {
        [$startDate, $endDate] = $this->parsePeriodFilter($request);
        
        $query = Transaction::with(['booking.patient', 'booking.therapist', 'booking.service'])
            ->whereHas('booking', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
            });

        // Rekapitulasi (Hanya yang paid)
        $paidQuery = clone $query;
        $totalRevenue = $paidQuery->where('status', 'paid')->sum('amount');
        
        // Status count
        $allTx = clone $query;
        $statuses = $allTx->pluck('status');
        $totalSuccess = $statuses->filter(fn($s) => $s === 'paid')->count();
        $totalCanceled = Booking::whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('status', 'canceled')->count();

        // Revenue per service
        $services = Service::all();
        $revenueByService = [];
        foreach ($services as $srv) {
            $sum = Transaction::whereHas('booking', function ($q) use ($startDate, $endDate, $srv) {
                $q->whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                  ->where('service_id', $srv->id);
            })->where('status', 'paid')->sum('amount');
            
            $revenueByService[] = [
                'service_name' => $srv->name,
                'revenue' => (int) $sum
            ];
        }

        // Data transaksi
        $query->orderBy('created_at', 'desc');
        
        $isExport = $request->query('export') === 'true';
        $transactions = $isExport ? $query->get() : $query->paginate(15);
        
        $dataList = ($isExport ? $transactions : $transactions->getCollection())->map(function ($tx) {
            $b = $tx->booking;
            return [
                'id' => $tx->id,
                'booking_date' => $b ? Carbon::parse($b->booking_date)->format('Y-m-d') : null,
                'booking_no' => $b ? 'BKG-'.$b->id : '-',
                'patient_name' => $b && $b->patient ? $b->patient->name : '-',
                'therapist_name' => $b && $b->therapist ? $b->therapist->name : '-',
                'service_name' => $b && $b->service ? $b->service->name : '-',
                'payment_method' => $tx->payment_method,
                'status' => $tx->status,
                'total_amount' => (int) $tx->amount,
            ];
        });

        return ResponseFormatter::success([
            'period' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
            'total_revenue' => (int) $totalRevenue,
            'total_success' => $totalSuccess,
            'total_canceled' => $totalCanceled,
            'revenue_by_service' => $revenueByService,
            'transactions' => $dataList,
            'pagination' => $isExport ? null : [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ]
        ], 'Laporan keuangan berhasil diambil');
    }

    // =========================================================================
    // 2. LAPORAN KUNJUNGAN (Terapis, Admin, Super Admin)
    // =========================================================================
    public function getVisits(Request $request)
    {
        [$startDate, $endDate] = $this->parsePeriodFilter($request);
        $user = Auth::user();
        
        $therapistId = $request->query('therapist_id');
        if ($user->role === 'terapis') {
            $therapistId = $user->id; // Terapis hanya bisa lihat miliknya sendiri
        }

        $query = Booking::with(['patient', 'therapyRecord', 'service', 'therapist'])
            ->whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->whereIn('status', ['completed', 'force_completed']);

        if ($therapistId) {
            $query->where('therapist_id', $therapistId);
        }

        $query->orderBy('booking_date', 'asc')->orderBy('booking_time', 'asc');
        
        $isExport = $request->query('export') === 'true';
        $bookings = $isExport ? $query->get() : $query->paginate(15);
        
        $dataList = ($isExport ? $bookings : $bookings->getCollection())->map(function ($b, $index) {
            $isNew = $this->isNewPatient($b->patient_id, $b->id, $b->booking_date);
            
            $age = null;
            if ($b->patient && $b->patient->birth_date) {
                $age = Carbon::parse($b->patient->birth_date)->age;
            }

            $serviceName = strtolower($b->service->name ?? '');
            $isRamuan = str_contains($serviceName, 'ramuan');
            $isKeterampilan = str_contains($serviceName, 'bekam') || str_contains($serviceName, 'akupunktur');
            // Jika bukan keduanya tapi completed, anggap keterampilan
            if (!$isRamuan && !$isKeterampilan) $isKeterampilan = true;

            $complaint = $b->therapyRecord->patient_complaint ?? '-';
            $notes = $b->therapyRecord->therapist_action ?? '-';

            return [
                'no' => $index + 1,
                'id' => $b->id,
                'date' => Carbon::parse($b->booking_date)->format('Y-m-d'),
                'patient_name' => $b->patient->name ?? '-',
                'patient_age' => $age,
                'address' => $b->patient->address ?? '-',
                'gender' => $b->patient->gender ?? 'L',
                'is_new' => $isNew,
                'complaint' => $complaint,
                'is_ramuan' => $isRamuan,
                'is_keterampilan' => $isKeterampilan,
                'is_kombinasi' => false,
                'notes' => $notes
            ];
        });

        // Rekapitulasi Global (tanpa pagination)
        $summaryQuery = clone $query;
        $allBookings = $summaryQuery->get();
        
        $totalL = 0; $totalP = 0;
        $totalNew = 0; $totalOld = 0;
        $totalRamuan = 0; $totalKeterampilan = 0;

        foreach ($allBookings as $b) {
            if ($b->patient && $b->patient->gender === 'P') $totalP++; else $totalL++;
            if ($this->isNewPatient($b->patient_id, $b->id, $b->booking_date)) $totalNew++; else $totalOld++;
            
            $serviceName = strtolower($b->service->name ?? '');
            if (str_contains($serviceName, 'ramuan')) $totalRamuan++;
            else $totalKeterampilan++;
        }

        $therapistName = $therapistId ? (User::find($therapistId)->name ?? 'Semua Terapis') : 'Semua Terapis';
        $therapistAddress = $therapistId ? (User::find($therapistId)->address ?? '-') : '-';

        return ResponseFormatter::success([
            'therapist_name' => $therapistName,
            'therapist_address' => $therapistAddress,
            'period' => $startDate->format('F Y'),
            'summary' => [
                'total_male' => $totalL,
                'total_female' => $totalP,
                'total_new' => $totalNew,
                'total_old' => $totalOld,
                'total_ramuan' => $totalRamuan,
                'total_keterampilan' => $totalKeterampilan,
                'total_kombinasi' => 0,
                'total_visits' => $allBookings->count()
            ],
            'visits' => $dataList,
            'pagination' => $isExport ? null : [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ]
        ], 'Laporan kunjungan berhasil diambil');
    }

    // =========================================================================
    // 3. LAPORAN KINERJA TERAPIS (Admin & Super Admin, Terapis(sendiri))
    // =========================================================================
    public function getPerformance(Request $request)
    {
        [$startDate, $endDate] = $this->parsePeriodFilter($request);
        $user = Auth::user();

        $query = User::where('role', 'terapis');
        if ($user->role === 'terapis') {
            $query->where('id', $user->id);
        }

        $therapists = $query->get();
        $results = [];

        foreach ($therapists as $t) {
            $bookings = Booking::where('therapist_id', $t->id)
                ->whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get();

            $completedBookings = $bookings->filter(fn($b) => in_array($b->status, ['completed', 'force_completed']));
            $canceledBookings = $bookings->filter(fn($b) => $b->status === 'canceled');

            $totalSessions = $completedBookings->count();
            $patientIds = $completedBookings->pluck('patient_id')->unique();
            $totalPatients = $patientIds->count();
            
            $newPatients = 0; $oldPatients = 0;
            $bekam = 0; $akupunktur = 0; $ramuan = 0;
            
            foreach ($completedBookings as $b) {
                if ($this->isNewPatient($b->patient_id, $b->id, $b->booking_date)) $newPatients++;
                else $oldPatients++;
                
                $sName = strtolower($b->service->name ?? '');
                if (str_contains($sName, 'bekam')) $bekam++;
                elseif (str_contains($sName, 'akupunktur')) $akupunktur++;
                elseif (str_contains($sName, 'ramuan')) $ramuan++;
                else $bekam++;
            }

            $revenue = Transaction::whereHas('booking', function($q) use ($t, $startDate, $endDate) {
                $q->where('therapist_id', $t->id)
                  ->whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
            })->where('status', 'paid')->sum('amount');

            $results[] = [
                'therapist_name' => $t->name,
                'total_sessions' => $totalSessions,
                'total_patients' => $totalPatients,
                'new_patients' => $newPatients,
                'old_patients' => $oldPatients,
                'total_bekam' => $bekam,
                'total_akupunktur' => $akupunktur,
                'total_ramuan' => $ramuan,
                'total_revenue' => (int) $revenue,
                'total_canceled' => $canceledBookings->count()
            ];
        }

        return ResponseFormatter::success([
            'period' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
            'therapists' => $results
        ], 'Laporan kinerja berhasil diambil');
    }

    // =========================================================================
    // 4. LAPORAN KEGIATAN KLINIK (Admin & Super Admin)
    // =========================================================================
    public function getActivity(Request $request)
    {
        [$startDate, $endDate] = $this->parsePeriodFilter($request);

        $bookings = Booking::whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->whereIn('status', ['completed', 'force_completed'])
            ->get();

        $totalVisits = $bookings->count();
        $newPatients = 0; $oldPatients = 0;
        foreach ($bookings as $b) {
            if ($this->isNewPatient($b->patient_id, $b->id, $b->booking_date)) $newPatients++;
            else $oldPatients++;
        }

        $totalRevenue = Transaction::whereHas('booking', function($q) use ($startDate, $endDate) {
            $q->whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
        })->where('status', 'paid')->sum('amount');

        // Layanan terpopuler
        $services = Service::all();
        $serviceBreakdown = [];
        foreach ($services as $srv) {
            $count = $bookings->where('service_id', $srv->id)->count();
            $rev = Transaction::whereHas('booking', function($q) use ($startDate, $endDate, $srv) {
                $q->whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                  ->where('service_id', $srv->id);
            })->where('status', 'paid')->sum('amount');
            
            $serviceBreakdown[] = [
                'service_name' => $srv->name,
                'total_sessions' => $count,
                'percentage' => $totalVisits > 0 ? round(($count / $totalVisits) * 100, 1) : 0,
                'revenue' => (int) $rev
            ];
        }

        // Terapis terpopuler
        $therapists = User::where('role', 'terapis')->get();
        $topTherapistName = '-';
        $maxSessions = -1;
        foreach ($therapists as $t) {
            $c = $bookings->where('therapist_id', $t->id)->count();
            if ($c > $maxSessions) {
                $maxSessions = $c;
                $topTherapistName = $t->name;
            }
        }

        return ResponseFormatter::success([
            'period' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
            'summary' => [
                'total_visits' => $totalVisits,
                'new_patients' => $newPatients,
                'old_patients' => $oldPatients,
                'total_revenue' => (int) $totalRevenue,
                'top_service' => collect($serviceBreakdown)->sortByDesc('total_sessions')->first()['service_name'] ?? '-',
                'top_therapist' => $topTherapistName
            ],
            'service_breakdown' => $serviceBreakdown
        ], 'Laporan kegiatan berhasil diambil');
    }

    // =========================================================================
    // 5. LAPORAN KOMPARATIF TERAPIS (Super Admin)
    // =========================================================================
    public function getComparative(Request $request)
    {
        [$startDate, $endDate] = $this->parsePeriodFilter($request);
        
        $therapists = User::where('role', 'terapis')->get();
        $results = [];

        $totalKlinikSessions = Booking::whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->whereIn('status', ['completed', 'force_completed'])->count();

        foreach ($therapists as $t) {
            $sessions = Booking::where('therapist_id', $t->id)
                ->whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->whereIn('status', ['completed', 'force_completed'])
                ->count();
                
            $patients = Booking::where('therapist_id', $t->id)
                ->whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->whereIn('status', ['completed', 'force_completed'])
                ->distinct('patient_id')->count('patient_id');
                
            $revenue = Transaction::whereHas('booking', function($q) use ($t, $startDate, $endDate) {
                $q->where('therapist_id', $t->id)
                  ->whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
            })->where('status', 'paid')->sum('amount');
            
            $percentage = $totalKlinikSessions > 0 ? round(($sessions / $totalKlinikSessions) * 100, 1) : 0;
            
            // Trend (Bulan lalu vs Bulan ini misal periode bulanan, untuk simplifikasi kita skip complex trend dulu)
            $trend = '↑'; // Placeholder

            // Visual bar
            $barCount = round($percentage / 5);
            $bar = str_repeat('█', $barCount);
            if (empty($bar)) $bar = '▏';

            $results[] = [
                'therapist_name' => $t->name,
                'total_sessions' => $sessions,
                'total_patients' => $patients,
                'revenue' => (int) $revenue,
                'percentage' => $percentage,
                'trend' => $trend,
                'visual_bar' => $bar
            ];
        }

        // Sort by sessions desc
        usort($results, fn($a, $b) => $b['total_sessions'] <=> $a['total_sessions']);
        
        // Add ranking
        foreach ($results as $index => &$r) {
            $r['ranking'] = $index + 1;
        }

        return ResponseFormatter::success([
            'period' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
            'comparative' => $results
        ], 'Laporan komparatif berhasil diambil');
    }
}
