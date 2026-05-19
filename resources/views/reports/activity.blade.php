<!DOCTYPE html>
<html>
<head>
    <title>Laporan Kegiatan Klinik</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; text-align: center; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .footer { margin-top: 40px; font-style: italic; font-size: 11px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>LAPORAN KEGIATAN KLINIK</h2>
        <h3>Rumah Sehat Manna wa Salwa</h3>
        <p>Periode: {{ $period }}</p>
    </div>

    <h3>Ringkasan Aktivitas</h3>
    <table>
        <tr>
            <td width="30%">Total Kunjungan</td>
            <td width="20%"><strong>{{ $summary['total_visits'] }}</strong></td>
            <td width="30%">Total Pendapatan</td>
            <td width="20%"><strong>Rp {{ number_format($summary['total_revenue'], 0, ',', '.') }}</strong></td>
        </tr>
        <tr>
            <td>Pasien Baru</td>
            <td><strong>{{ $summary['new_patients'] }}</strong></td>
            <td>Layanan Terpopuler</td>
            <td><strong>{{ $summary['top_service'] }}</strong></td>
        </tr>
        <tr>
            <td>Pasien Lama</td>
            <td><strong>{{ $summary['old_patients'] }}</strong></td>
            <td>Terapis Paling Produktif</td>
            <td><strong>{{ $summary['top_therapist'] }}</strong></td>
        </tr>
    </table>

    <h3>Breakdown Layanan</h3>
    <table>
        <thead>
            <tr>
                <th>Layanan</th>
                <th>Total Sesi</th>
                <th>Persentase (%)</th>
                <th>Pendapatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($service_breakdown as $srv)
            <tr>
                <td>{{ $srv['service_name'] }}</td>
                <td class="text-center">{{ $srv['total_sessions'] }}</td>
                <td class="text-center">{{ $srv['percentage'] }}%</td>
                <td class="text-right">Rp {{ number_format($srv['revenue'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Dicetak pada: {{ $printed_at }}<br>
        Dicetak oleh: {{ $printed_by }}
    </div>
</body>
</html>
