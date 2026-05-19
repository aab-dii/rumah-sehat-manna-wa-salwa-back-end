<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class ReportPdfController extends Controller
{
    protected $reportController;

    public function __construct(ReportController $reportController)
    {
        $this->reportController = $reportController;
    }

    /**
     * Download Laporan Kunjungan (Terapis & Admin)
     */
    public function exportVisits(Request $request)
    {
        // Set export = true untuk bypass pagination
        $request->merge(['export' => 'true']);
        $response = $this->reportController->getVisits($request);
        $data = json_decode($response->getContent(), true)['data'];
        
        // Cek 403 / 404 kalau diperlukan, tapi ReportController sudah handle (terapis hanya bisa akses miliknya)
        
        $pdf = Pdf::loadView('reports.visits', $data)->setPaper('a4', 'landscape');
        
        $fileName = 'LaporanKunjungan_' . str_replace(' ', '', $data['period']) . '_' . time() . '.pdf';
        return $pdf->download($fileName);
    }

    /**
     * Download Laporan Keuangan (Admin)
     */
    public function exportFinancial(Request $request)
    {
        $request->merge(['export' => 'true']);
        $response = $this->reportController->getFinancial($request);
        $data = json_decode($response->getContent(), true)['data'];
        
        $data['printed_by'] = Auth::user()->name;
        $data['printed_at'] = now()->format('d M Y H:i:s');
        
        $pdf = Pdf::loadView('reports.financial', $data)->setPaper('a4', 'landscape');
        
        $fileName = 'LaporanKeuangan_' . str_replace([' ', '/', '-'], '', $data['period']) . '_' . time() . '.pdf';
        return $pdf->download($fileName);
    }

    /**
     * Download Laporan Kinerja Terapis (Admin & Super Admin)
     */
    public function exportPerformance(Request $request)
    {
        $request->merge(['export' => 'true']);
        $response = $this->reportController->getPerformance($request);
        $data = json_decode($response->getContent(), true)['data'];
        
        $data['printed_by'] = Auth::user()->name;
        $data['printed_at'] = now()->format('d M Y H:i:s');
        
        $pdf = Pdf::loadView('reports.performance', $data)->setPaper('a4', 'landscape');
        
        $fileName = 'LaporanKinerjaTerapis_' . str_replace([' ', '/', '-'], '', $data['period']) . '_' . time() . '.pdf';
        return $pdf->download($fileName);
    }

    /**
     * Download Laporan Kegiatan Klinik (Admin & Super Admin)
     */
    public function exportActivity(Request $request)
    {
        $request->merge(['export' => 'true']);
        $response = $this->reportController->getActivity($request);
        $data = json_decode($response->getContent(), true)['data'];
        
        $data['printed_by'] = Auth::user()->name;
        $data['printed_at'] = now()->format('d M Y H:i:s');
        
        $pdf = Pdf::loadView('reports.activity', $data)->setPaper('a4', 'landscape');
        
        $fileName = 'LaporanKegiatanKlinik_' . str_replace([' ', '/', '-'], '', $data['period']) . '_' . time() . '.pdf';
        return $pdf->download($fileName);
    }

    /**
     * Download Laporan Komparatif (Super Admin)
     */
    public function exportComparative(Request $request)
    {
        $request->merge(['export' => 'true']);
        $response = $this->reportController->getComparative($request);
        $data = json_decode($response->getContent(), true)['data'];
        
        $data['printed_by'] = Auth::user()->name;
        $data['printed_at'] = now()->format('d M Y H:i:s');
        
        $pdf = Pdf::loadView('reports.comparative', $data)->setPaper('a4', 'landscape');
        
        $fileName = 'LaporanKomparatif_' . str_replace([' ', '/', '-'], '', $data['period']) . '_' . time() . '.pdf';
        return $pdf->download($fileName);
    }
}
