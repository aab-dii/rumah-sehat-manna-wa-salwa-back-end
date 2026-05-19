<!DOCTYPE html>
<html>
<head>
    <title>Laporan Keuangan</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; }
        .summary-box { border: 1px solid #000; padding: 10px; margin-bottom: 20px; width: 40%; }
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
        <h2>LAPORAN KEUANGAN</h2>
        <h3>Rumah Sehat Manna wa Salwa</h3>
        <p>Periode: {{ $period }}</p>
    </div>

    <div class="summary-box">
        <strong>Ringkasan:</strong><br>
        Total Pendapatan: Rp {{ number_format($total_revenue, 0, ',', '.') }}<br>
        Total Transaksi Berhasil: {{ $total_success }}<br>
        Total Transaksi Dibatalkan: {{ $total_canceled }}<br>
        <br>
        <strong>Pendapatan per Layanan:</strong><br>
        @foreach($revenue_by_service as $srv)
            - {{ $srv['service_name'] }}: Rp {{ number_format($srv['revenue'], 0, ',', '.') }}<br>
        @endforeach
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>No. Booking</th>
                <th>Nama Pasien</th>
                <th>Terapis</th>
                <th>Layanan</th>
                <th>Metode Bayar</th>
                <th>Status</th>
                <th>Total Bayar</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $index => $tx)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $tx['booking_date'] }}</td>
                <td>{{ $tx['booking_no'] }}</td>
                <td>{{ $tx['patient_name'] }}</td>
                <td>{{ $tx['therapist_name'] }}</td>
                <td>{{ $tx['service_name'] }}</td>
                <td>{{ strtoupper($tx['payment_method']) }}</td>
                <td class="text-center">{{ ucfirst($tx['status']) }}</td>
                <td class="text-right">Rp {{ number_format($tx['total_amount'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Total Pendapatan Periode ini: <strong>Rp {{ number_format($total_revenue, 0, ',', '.') }}</strong><br>
        Dicetak pada: {{ $printed_at }}<br>
        Dicetak oleh: {{ $printed_by }}
    </div>
</body>
</html>
