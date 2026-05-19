<!DOCTYPE html>
<html>
<head>
    <title>Laporan Komparatif Terapis</title>
    <style>
        body { font-family: monospace; font-size: 13px; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; font-family: Arial, sans-serif; }
        .header h2 { margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-family: Arial, sans-serif; font-size: 11px;}
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; text-align: center; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bar-chart { margin-top: 20px; }
        .bar-row { margin-bottom: 5px; }
        .footer { margin-top: 40px; font-style: italic; font-size: 11px; font-family: Arial, sans-serif; }
    </style>
</head>
<body>
    <div class="header">
        <h2>LAPORAN KOMPARATIF TERAPIS</h2>
        <h3>Rumah Sehat Manna wa Salwa</h3>
        <p>Periode: {{ $period }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Ranking</th>
                <th>Nama Terapis</th>
                <th>Total Sesi</th>
                <th>Total Pasien</th>
                <th>Pendapatan</th>
                <th>% Kontribusi (Sesi)</th>
                <th>Trend</th>
            </tr>
        </thead>
        <tbody>
            @foreach($comparative as $c)
            <tr>
                <td class="text-center"><strong>#{{ $c['ranking'] }}</strong></td>
                <td>{{ $c['therapist_name'] }}</td>
                <td class="text-center">{{ $c['total_sessions'] }}</td>
                <td class="text-center">{{ $c['total_patients'] }}</td>
                <td class="text-right">Rp {{ number_format($c['revenue'], 0, ',', '.') }}</td>
                <td class="text-center">{{ $c['percentage'] }}%</td>
                <td class="text-center">{{ $c['trend'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="bar-chart">
        <h3 style="font-family: Arial, sans-serif; margin-bottom: 10px;">Visualisasi Komparasi (Sesi)</h3>
        @foreach($comparative as $c)
        <div class="bar-row">
            {{ str_pad(substr($c['therapist_name'], 0, 15), 15, ' ') }} | 
            <span style="color: #2c3e50;">{{ $c['visual_bar'] }}</span> 
            ({{ $c['total_sessions'] }} sesi)
        </div>
        @endforeach
    </div>

    <div class="footer">
        Dicetak pada: {{ $printed_at }}<br>
        Dicetak oleh: {{ $printed_by }}
    </div>
</body>
</html>
