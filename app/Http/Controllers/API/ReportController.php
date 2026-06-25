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
use Illuminate\Support\Facades\DB;

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
                if ($request->has('start_date') && $request->has('end_date')) {
                    $start = Carbon::parse($request->start_date)->startOfMonth();
                    $end = Carbon::parse($request->end_date)->endOfMonth();
                } else {
                    $start = Carbon::now()->startOfMonth();
                    $end = Carbon::now()->endOfMonth();
                }
                break;
            case 'quarterly':
                $start = Carbon::now()->startOfQuarter();
                $end = Carbon::now()->endOfQuarter();
                break;
            case 'yearly':
                if ($request->has('start_date') && $request->has('end_date')) {
                    $start = Carbon::parse($request->start_date)->startOfYear();
                    $end = Carbon::parse($request->end_date)->endOfYear();
                } else {
                    $start = Carbon::now()->startOfYear();
                    $end = Carbon::now()->endOfYear();
                }
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
     * Mengecek apakah pasien adalah pasien baru (transaksi pertama kalinya sukses di dalam sistem)
     * Kriteria sukses: completed, confirmed, atau force_completed
     */
    private function isNewPatient($booking)
    {
        $patientId = $booking->patient_id;
        $bookingDate = Carbon::parse($booking->booking_date)->format('Y-m-d');
        $bookingTime = $booking->booking_time;
        $bookingId = $booking->id;

        $hasPrevious = Booking::where('patient_id', $patientId)
            ->whereIn('status', ['completed', 'force_completed'])
            ->where(function ($q) use ($bookingDate, $bookingTime, $bookingId) {
                $q->where('booking_date', '<', $bookingDate)
                  ->orWhere(function ($q2) use ($bookingDate, $bookingTime, $bookingId) {
                      $q2->where('booking_date', '=', $bookingDate)
                         ->where(function ($q3) use ($bookingTime, $bookingId) {
                             $q3->where('booking_time', '<', $bookingTime)
                                ->orWhere(function ($q4) use ($bookingTime, $bookingId) {
                                    $q4->where('booking_time', '=', $bookingTime)
                                       ->where('id', '<', $bookingId);
                                });
                         });
                  });
            })
            ->exists();
            
        return !$hasPrevious;
    }

    // =========================================================================
    // 1. LAPORAN KEUANGAN (Admin & Super Admin)
    // =========================================================================
    public function getFinancial(Request $request)
    {
        [$startDate, $endDate] = $this->parsePeriodFilter($request);
        
        // Base query for successful or refunded transactions of completed/canceled bookings
        $query = Transaction::with(['booking.patient', 'booking.therapist', 'booking.service'])
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('status', 'paid')
                        ->whereHas('booking', function ($bQ) {
                            $bQ->whereIn('status', ['completed', 'force_completed', 'canceled']);
                        });
                })->orWhere(function ($sub) {
                    $sub->where('status', 'refund')
                        ->whereHas('booking', function ($bQ) {
                            $bQ->where('status', 'canceled');
                        });
                });
            })
            ->whereHas('booking', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
            });

        // Fetch all transactions in the period to accurately calculate net metrics in one pass
        $allTransactions = Transaction::with(['booking.service'])
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('status', 'paid')
                        ->whereHas('booking', function ($bQ) {
                            $bQ->whereIn('status', ['completed', 'force_completed', 'canceled']);
                        });
                })->orWhere(function ($sub) {
                    $sub->where('status', 'refund')
                        ->whereHas('booking', function ($bQ) {
                            $bQ->where('status', 'canceled');
                        });
                });
            })
            ->whereHas('booking', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
            })
            ->get();

        // Query for completed bookings that DO NOT have any transaction with status = 'paid'
        $completedBookingsNoPaidTx = Booking::with(['patient', 'therapist', 'service', 'transaction'])
            ->whereIn('status', ['completed', 'force_completed'])
            ->whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->whereDoesntHave('transaction', function($tQ) {
                $tQ->where('status', 'paid');
            })
            ->get();

        // Hanya transaksi paid yang dihitung sebagai pendapatan
        $combinedAll = collect($allTransactions);

        $totalRevenue = 0;
        $totalSuccess = 0;
        $totalAdminFee = 0;

        $services = Service::withTrashed()->get();
        $serviceRevenueMap = [];
        foreach ($services as $srv) {
            $serviceRevenueMap[$srv->id] = 0;
        }

        foreach ($combinedAll as $tx) {
            $isRefund = ($tx->status === 'refund' || $tx->status === 'refunded' || $tx->status === 'cancelled');
            $amount = (int) $tx->amount;
            
            // Calculate admin fee for this transaction
            $adminFee = 0;
            if ($tx->booking && $tx->booking->service) {
                // Formula: amount - service price
                $adminFee = max(0, $amount - (int) $tx->booking->service->price);
            }

            if ($isRefund) {
                $totalRevenue -= $amount;
                $totalAdminFee -= $adminFee;
                if ($tx->booking && $tx->booking->service_id) {
                    $sid = $tx->booking->service_id;
                    $serviceRevenueMap[$sid] = ($serviceRevenueMap[$sid] ?? 0) - $amount;
                }
            } else {
                $totalRevenue += $amount;
                $totalSuccess++;
                $totalAdminFee += $adminFee;
                if ($tx->booking && $tx->booking->service_id) {
                    $sid = $tx->booking->service_id;
                    $serviceRevenueMap[$sid] = ($serviceRevenueMap[$sid] ?? 0) + $amount;
                }
            }
        }

        $revenueByService = [];
        foreach ($services as $srv) {
            $revenue = (int) ($serviceRevenueMap[$srv->id] ?? 0);
            if ($srv->deleted_at && $revenue === 0) {
                continue; // Skip soft-deleted services with zero revenue
            }
            $revenueByService[] = [
                'service_name' => $srv->deleted_at ? $srv->name . ' (Terhapus)' : $srv->name,
                'revenue' => $revenue
            ];
        }

        $totalCanceled = Booking::whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('status', 'canceled')->count();

        // Build combined transaction list for pagination / display
        $combinedList = collect();
        foreach ($query->get() as $tx) {
            $combinedList->push($tx);
        }
        // Booking completed tanpa transaksi paid tetap ditampilkan sebagai audit trail (status: unpaid)
        foreach ($completedBookingsNoPaidTx as $b) {
            $virtualTx = new Transaction([
                'booking_id' => $b->id,
                'payment_method' => $b->transaction->payment_method ?? 'cash',
                'status' => 'unpaid',
                'amount' => $b->total_price,
                'verified_at' => null,
            ]);
            $virtualTx->id = -$b->id; // Negative to prevent ID clashes
            $virtualTx->setRelation('booking', $b);
            $virtualTx->created_at = $b->created_at;
            
            $combinedList->push($virtualTx);
        }

        // Sort by booking date and time desc, or created_at desc
        $combinedList = $combinedList->sortByDesc(function ($item) {
            $b = $item->booking;
            if ($b) {
                $dateStr = is_string($b->booking_date) ? $b->booking_date : $b->booking_date->format('Y-m-d');
                return $dateStr . ' ' . $b->booking_time;
            }
            return $item->created_at;
        })->values();

        $totalTransactionsCount = $combinedList->count();
        $isExport = $request->query('export') === 'true';
        
        if ($isExport) {
            $paginatedItems = $combinedList;
        } else {
            $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
            $perPage = 15;
            $currentPageItems = $combinedList->slice(($currentPage - 1) * $perPage, $perPage)->values();
            $paginatedItems = new \Illuminate\Pagination\LengthAwarePaginator(
                $currentPageItems,
                $totalTransactionsCount,
                $perPage,
                $currentPage,
                ['path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath()]
            );
        }

        $dataList = ($isExport ? $paginatedItems : $paginatedItems->getCollection())->map(function ($tx) {
            $b = $tx->booking;
            $isRefund = ($tx->status === 'refund' || $tx->status === 'refunded' || $tx->status === 'cancelled');
            $amount = (int) $tx->amount;
            
            return [
                'id' => $tx->id,
                'booking_date' => $b ? (is_string($b->booking_date) ? $b->booking_date : $b->booking_date->format('Y-m-d')) : null,
                'booking_no' => $b ? 'BKG-'.$b->id : '-',
                'patient_name' => $b && $b->patient ? $b->patient->name : '-',
                'therapist_name' => $b && $b->therapist ? $b->therapist->name : '-',
                'service_name' => $b && $b->service ? $b->service->name : '-',
                'payment_method' => $tx->payment_method,
                'status' => $tx->status,
                'total_amount' => $isRefund ? -$amount : $amount,
                'is_refund' => $isRefund,
                'verified_at' => $tx->verified_at ? Carbon::parse($tx->verified_at)->format('Y-m-d H:i:s') : null,
                'refunded_at' => $tx->refunded_at ? Carbon::parse($tx->refunded_at)->format('Y-m-d H:i:s') : null,
            ];
        });

        return ResponseFormatter::success([
            'period' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
            'total_revenue' => (int) $totalRevenue,
            'total_success' => $totalSuccess,
            'total_canceled' => $totalCanceled,
            'total_admin_fee' => (int) $totalAdminFee,
            'revenue_by_service' => $revenueByService,
            'transactions' => $dataList,
            'pagination' => $isExport ? null : [
                'current_page' => $paginatedItems->currentPage(),
                'last_page' => $paginatedItems->lastPage(),
                'per_page' => $paginatedItems->perPage(),
                'total' => $paginatedItems->total(),
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
        
        $dataList = ($isExport ? $bookings : $bookings->getCollection())->map(function ($b, $index) use ($isExport, $bookings) {
            $isNew = $this->isNewPatient($b);
            
            $age = null;
            if ($b->patient && $b->patient->birth_date) {
                $age = Carbon::parse($b->patient->birth_date)->age;
            }

            $serviceName = strtolower($b->service->name ?? '');
            $isKombinasi = str_contains($serviceName, 'kombinasi');
            $isRamuan = str_contains($serviceName, 'ramuan');
            $isKeterampilan = str_contains($serviceName, 'bekam') || str_contains($serviceName, 'akupunktur');
            
            if ($isKombinasi) {
                $isRamuan = false;
                $isKeterampilan = false;
            } else {
                if (!$isRamuan && !$isKeterampilan) {
                    $isKeterampilan = true;
                }
            }

            $complaint = $b->therapyRecord->patient_complaint ?? '-';
            $notes = $b->therapyRecord->therapist_action ?? '-';

            $no = $index + 1;
            if (!$isExport) {
                $no = (($bookings->currentPage() - 1) * $bookings->perPage()) + $index + 1;
            }

            return [
                'no' => $no,
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
                'is_kombinasi' => $isKombinasi,
                'notes' => $notes
            ];
        });

        // Rekapitulasi Global (tanpa pagination)
        $summaryQuery = clone $query;
        $allBookings = $summaryQuery->get();
        
        $totalL = 0;
        $totalP = 0;
        $totalNew = 0;
        $totalOld = 0;
        $totalRamuan = 0;
        $totalKeterampilan = 0;
        $totalKombinasi = 0;

        foreach ($allBookings as $b) {
            // Gender
            if (($b->patient->gender ?? 'L') === 'P') {
                $totalP++;
            } else {
                $totalL++;
            }

            // Kunjungan (Baru / Lama)
            if ($this->isNewPatient($b)) {
                $totalNew++;
            } else {
                $totalOld++;
            }

            // Pelayanan / Tindakan
            $serviceName = strtolower($b->service->name ?? '');
            $isKombinasi = str_contains($serviceName, 'kombinasi');
            $isRamuan = str_contains($serviceName, 'ramuan');
            $isKeterampilan = str_contains($serviceName, 'bekam') || str_contains($serviceName, 'akupunktur');

            if ($isKombinasi) {
                $totalKombinasi++;
            } elseif ($isRamuan) {
                $totalRamuan++;
            } else {
                $totalKeterampilan++;
            }
        }

        $therapistName = $therapistId ? (User::find($therapistId)->name ?? 'Semua Terapis') : 'Semua Terapis';
        $therapistAddress = 'Kampung Tengah Gg. Masjid Ar-Rahman';

        return ResponseFormatter::success([
            'therapist_name' => $therapistName,
            'therapist_address' => $therapistAddress,
            'period' => $startDate->locale('id')->translatedFormat('F Y'),
            'printed_date' => Carbon::now()->locale('id')->translatedFormat('F Y'),
            'summary' => [
                'total_male' => $totalL,
                'total_female' => $totalP,
                'total_new' => $totalNew,
                'total_old' => $totalOld,
                'total_ramuan' => $totalRamuan,
                'total_keterampilan' => $totalKeterampilan,
                'total_kombinasi' => $totalKombinasi,
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
            
            $newPatients = 0;
            $oldPatients = 0;
            foreach ($completedBookings as $b) {
                if ($this->isNewPatient($b)) {
                    $newPatients++;
                } else {
                    $oldPatients++;
                }
            }

            $bekam = 0; $akupunktur = 0; $ramuan = 0;
            foreach ($completedBookings as $b) {
                $sName = strtolower($b->service->name ?? '');
                if (str_contains($sName, 'bekam')) {
                    $bekam++;
                } elseif (str_contains($sName, 'akupunktur')) {
                    $akupunktur++;
                } elseif (str_contains($sName, 'ramuan')) {
                    $ramuan++;
                }
            }

            $revenue = Transaction::whereHas('booking', function($q) use ($t, $startDate, $endDate) {
                $q->where('therapist_id', $t->id)
                  ->whereIn('status', ['completed', 'force_completed'])
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
        $newPatients = 0;
        $oldPatients = 0;
        foreach ($bookings as $b) {
            if ($this->isNewPatient($b)) {
                $newPatients++;
            } else {
                $oldPatients++;
            }
        }

        $totalRevenue = Transaction::whereHas('booking', function($q) use ($startDate, $endDate) {
            $q->whereIn('status', ['completed', 'force_completed'])
              ->whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
        })->where('status', 'paid')->sum('amount');

        // Layanan terpopuler
        $services = Service::withTrashed()->get();
        $serviceBreakdown = [];
        foreach ($services as $srv) {
            $count = $bookings->where('service_id', $srv->id)->count();
            if ($srv->deleted_at && $count === 0) {
                continue; // Skip soft-deleted services with zero sessions in the report
            }
            $rev = Transaction::whereHas('booking', function($q) use ($startDate, $endDate, $srv) {
                $q->whereIn('status', ['completed', 'force_completed'])
                  ->whereBetween('booking_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                  ->where('service_id', $srv->id);
            })->where('status', 'paid')->sum('amount');
            
            $serviceBreakdown[] = [
                'service_name' => $srv->deleted_at ? $srv->name . ' (Terhapus)' : $srv->name,
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
                  ->whereIn('status', ['completed', 'force_completed'])
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
