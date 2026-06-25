<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Http\Controllers\API\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckReportConsistency extends Command
{
    protected $signature = 'reports:check-consistency {--month=2026-06}';
    protected $description = 'Cross-check konsistensi data antar semua laporan';

    public function handle()
    {
        $month = $this->option('month');
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $superAdmin = User::where('role', 'super_admin')->first();
        if (!$superAdmin) {
            $this->error('Super Admin tidak ditemukan!');
            return 1;
        }
        Auth::login($superAdmin);

        $controller = new ReportController();

        $baseParams = [
            'period' => 'custom',
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        // Helper
        $makeReq = function($extra = []) use ($baseParams) {
            return new Request(array_merge($baseParams, $extra));
        };

        // 1. Kegiatan
        $actData = json_decode($controller->getActivity($makeReq())->getContent(), true)['data'];

        // 2. Kunjungan
        $visData = json_decode($controller->getVisits($makeReq(['export' => 'true']))->getContent(), true)['data'];

        // 3. Kinerja
        $perfData = json_decode($controller->getPerformance($makeReq())->getContent(), true)['data'];

        // 4. Keuangan
        $finData = json_decode($controller->getFinancial($makeReq(['export' => 'true']))->getContent(), true)['data'];

        // 5. Komparatif
        $compData = json_decode($controller->getComparative($makeReq())->getContent(), true)['data'];

        $this->newLine();
        $this->info("============================================================");
        $this->info("   CROSS-CHECK LAPORAN {$month} (DATA REAL)");
        $this->info("============================================================");

        // --- Total Sesi ---
        $actSesi = $actData['summary']['total_visits'];
        $visSesi = $visData['summary']['total_visits'];
        $perfSesi = collect($perfData['therapists'])->sum('total_sessions');
        $compSesi = collect($compData['comparative'])->sum('total_sessions');
        $finTxCount = count($finData['transactions']);

        $this->newLine();
        $this->info("=== TOTAL SESI ===");
        $this->line("Kegiatan Klinik  : {$actSesi}");
        $this->line("Kunjungan        : {$visSesi}");
        $this->line("Kinerja (SUM)    : {$perfSesi}");
        $this->line("Komparatif (SUM) : {$compSesi}");
        $this->line("Keuangan (baris) : {$finTxCount}");

        $sesiSama = ($actSesi === $visSesi && $visSesi === $perfSesi && $perfSesi === $compSesi);
        if ($sesiSama) {
            $this->info("STATUS: ✅ SEMUA SAMA ({$actSesi} sesi)");
        } else {
            $this->error("STATUS: ❌ ADA PERBEDAAN");
        }

        // --- Total Pendapatan ---
        $actRev = $actData['summary']['total_revenue'];
        $finRev = $finData['total_revenue'];
        $perfRev = (int) collect($perfData['therapists'])->sum('total_revenue');
        $compRev = (int) collect($compData['comparative'])->sum('revenue');

        $this->newLine();
        $this->info("=== TOTAL PENDAPATAN ===");
        $this->line("Kegiatan Klinik  : Rp " . number_format($actRev, 0, ',', '.'));
        $this->line("Keuangan         : Rp " . number_format($finRev, 0, ',', '.'));
        $this->line("Kinerja (SUM)    : Rp " . number_format($perfRev, 0, ',', '.'));
        $this->line("Komparatif (SUM) : Rp " . number_format($compRev, 0, ',', '.'));

        $revSama = ($actRev === $finRev && $finRev === $perfRev && $perfRev === $compRev);
        if ($revSama) {
            $this->info("STATUS: ✅ SEMUA SAMA (Rp " . number_format($actRev, 0, ',', '.') . ")");
        } else {
            $this->error("STATUS: ❌ ADA PERBEDAAN");
        }

        // --- Pasien Baru / Lama ---
        $actNew = $actData['summary']['new_patients'];
        $actOld = $actData['summary']['old_patients'];
        $visNew = $visData['summary']['total_new'];
        $visOld = $visData['summary']['total_old'];
        $perfNew = collect($perfData['therapists'])->sum('new_patients');
        $perfOld = collect($perfData['therapists'])->sum('old_patients');

        $this->newLine();
        $this->info("=== PASIEN BARU / LAMA ===");
        $this->line("Kegiatan  : Baru={$actNew}, Lama={$actOld}");
        $this->line("Kunjungan : Baru={$visNew}, Lama={$visOld}");
        $this->line("Kinerja   : Baru={$perfNew}, Lama={$perfOld}");

        $patSama = ($actNew == $visNew && $visNew == $perfNew && $actOld == $visOld && $visOld == $perfOld);
        if ($patSama) {
            $this->info("STATUS: ✅ SEMUA SAMA (Baru={$actNew}, Lama={$actOld})");
        } else {
            $this->error("STATUS: ❌ ADA PERBEDAAN");
        }

        // --- Detail Per Terapis ---
        $this->newLine();
        $this->info("=== DETAIL PER TERAPIS ===");
        $headers = ['Terapis', 'Sesi (Kinerja)', 'Sesi (Komparatif)', 'Revenue (Kinerja)', 'Revenue (Komparatif)', 'Status'];
        $rows = [];
        $compByName = collect($compData['comparative'])->keyBy('therapist_name');

        foreach ($perfData['therapists'] as $t) {
            $c = $compByName[$t['therapist_name']] ?? null;
            $cSesi = $c ? $c['total_sessions'] : 'N/A';
            $cRev = $c ? $c['revenue'] : 'N/A';
            $ok = $c && $t['total_sessions'] == $c['total_sessions'] && $t['total_revenue'] == $c['revenue'];
            $rows[] = [
                $t['therapist_name'],
                $t['total_sessions'],
                $cSesi,
                'Rp ' . number_format($t['total_revenue'], 0, ',', '.'),
                $c ? 'Rp ' . number_format($cRev, 0, ',', '.') : 'N/A',
                $ok ? '✅' : '❌',
            ];
        }
        $this->table($headers, $rows);

        // --- Detail Per Layanan ---
        $this->newLine();
        $this->info("=== DETAIL PER LAYANAN ===");
        $lHeaders = ['Layanan', 'Sesi (Kegiatan)', 'Revenue (Kegiatan)', 'Revenue (Keuangan)', 'Status'];
        $lRows = [];
        $finByService = collect($finData['revenue_by_service'])->keyBy('service_name');

        foreach ($actData['service_breakdown'] as $s) {
            $f = $finByService[$s['service_name']] ?? null;
            $fRev = $f ? $f['revenue'] : 0;
            $ok = $s['revenue'] == $fRev;
            $lRows[] = [
                $s['service_name'],
                $s['total_sessions'],
                'Rp ' . number_format($s['revenue'], 0, ',', '.'),
                'Rp ' . number_format($fRev, 0, ',', '.'),
                $ok ? '✅' : '❌',
            ];
        }
        $this->table($lHeaders, $lRows);

        // --- Hasil Akhir ---
        $this->newLine();
        $this->info("============================================================");
        $this->info("   HASIL AKHIR");
        $this->info("============================================================");
        $allOk = $sesiSama && $revSama && $patSama;
        $this->line("Total Sesi       : " . ($sesiSama ? "✅ KONSISTEN" : "❌ TIDAK KONSISTEN"));
        $this->line("Total Pendapatan : " . ($revSama ? "✅ KONSISTEN" : "❌ TIDAK KONSISTEN"));
        $this->line("Pasien Baru/Lama : " . ($patSama ? "✅ KONSISTEN" : "❌ TIDAK KONSISTEN"));
        $this->line("============================================================");

        if ($allOk) {
            $this->info("🎉 SEMUA DATA LAPORAN KONSISTEN!");
        } else {
            $this->error("⚠️ ADA KETIDAKKONSISTENAN PADA DATA LAPORAN!");
        }

        return $allOk ? 0 : 1;
    }
}
