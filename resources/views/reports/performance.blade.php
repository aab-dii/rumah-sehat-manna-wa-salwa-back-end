<!DOCTYPE html>
<html>
<head>
    <title>Laporan Kinerja Terapis</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; }
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.data-table th, table.data-table td { border: 1px solid #000; padding: 5px; text-align: left; }
        table.data-table th { background-color: #f2f2f2; text-align: center; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .footer { margin-top: 40px; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h2>LAPORAN KINERJA TERAPIS</h2>
        <h3>Rumah Sehat Manna wa Salwa</h3>
        <p>Periode: {{ $period }}</p>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Terapis</th>
                <th>Total Sesi</th>
                <th>Total Pasien</th>
                <th>Pasien Baru</th>
                <th>Pasien Lama</th>
                <th>Bekam</th>
                <th>Akupunktur</th>
                <th>Ramuan</th>
                <th>Dibatalkan</th>
                <th>Pendapatan Dihasilkan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($therapists as $index => $t)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $t['therapist_name'] }}</td>
                <td class="text-center">{{ $t['total_sessions'] }}</td>
                <td class="text-center">{{ $t['total_patients'] }}</td>
                <td class="text-center">{{ $t['new_patients'] }}</td>
                <td class="text-center">{{ $t['old_patients'] }}</td>
                <td class="text-center">{{ $t['total_bekam'] }}</td>
                <td class="text-center">{{ $t['total_akupunktur'] }}</td>
                <td class="text-center">{{ $t['total_ramuan'] }}</td>
                <td class="text-center">{{ $t['total_canceled'] }}</td>
                <td class="text-right">Rp {{ number_format($t['total_revenue'], 0, ',', '.') }}</td>
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
