<!DOCTYPE html>
<html>
<head>
    <title>Laporan Komparatif Terapis</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; color: #334155; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h2 { margin: 0; font-size: 18px; color: #1e293b; }
        .header h3 { margin: 5px 0 0 0; font-size: 14px; color: #64748b; font-weight: normal; }
        .header p { margin: 5px 0 0 0; font-size: 12px; color: #64748b; }
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 11px; }
        table.data-table th, table.data-table td { border: 1px solid #cbd5e1; padding: 10px 8px; text-align: left; }
        table.data-table th { background-color: #f8fafc; text-align: center; color: #475569; font-weight: bold; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bar-chart { margin-top: 30px; }
        .footer { margin-top: 40px; font-style: italic; font-size: 11px; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <h2>LAPORAN KOMPARATIF TERAPIS</h2>
        <h3>Rumah Sehat Manna wa Salwa</h3>
        <p>Periode: {{ $period }}</p>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="10%">Ranking</th>
                <th width="25%">Nama Terapis</th>
                <th width="15%">Total Sesi</th>
                <th width="15%">Total Pasien</th>
                <th width="20%">Pendapatan</th>
                <th width="15%">% Kontribusi (Sesi)</th>
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
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="bar-chart">
        <h3 style="margin-bottom: 15px; font-size: 13px; color: #1e293b; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;">Visualisasi Komparasi (Sesi)</h3>
        <table style="width: 100%; border-collapse: collapse;">
            @foreach($comparative as $c)
            <tr>
                <td style="width: 150px; border: none; padding: 6px 0; font-size: 11px; color: #334155; font-weight: bold; vertical-align: middle;">
                    {{ $c['therapist_name'] }}
                </td>
                <td style="width: 15px; border: none; padding: 6px 0; text-align: center; font-size: 11px; color: #cbd5e1; vertical-align: middle;">|</td>
                <td style="border: none; padding: 6px 0; vertical-align: middle;">
                    <div style="background-color: #f1f5f9; height: 14px; border-radius: 6px; width: 100%; max-width: 400px; display: block;">
                        <div style="background-color: #0f766e; height: 14px; border-radius: 6px; width: {{ max(1, $c['percentage']) }}%;"></div>
                    </div>
                </td>
                <td style="width: 150px; border: none; padding: 6px 0; font-size: 11px; padding-left: 10px; color: #64748b; vertical-align: middle;">
                    {{ $c['total_sessions'] }} sesi ({{ $c['percentage'] }}%)
                </td>
            </tr>
            @endforeach
        </table>
    </div>

    <div class="footer">
        Dicetak pada: {{ $printed_at }}<br>
        Dicetak oleh: {{ $printed_by }}
    </div>
</body>
</html>
