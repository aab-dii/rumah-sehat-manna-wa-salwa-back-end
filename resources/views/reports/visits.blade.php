<!DOCTYPE html>
<html>
<head>
    <title>Laporan Kunjungan</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-table td {
            vertical-align: top;
            padding: 2px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }
        table.data-table th {
            background-color: #f2f2f2;
            text-align: center;
        }
        .text-center { text-align: center; }
        .footer {
            margin-top: 40px;
            float: right;
            width: 300px;
            text-align: center;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

    <div class="header">
        PENYEHAT TRADISIONAL/PANTI SEHAT PRAKTEK MANDIRI KESTRAD<br>
        {{ strtoupper($therapist_name) }}
    </div>

    <table class="info-table">
        <tr>
            <td width="15%">Alamat</td>
            <td width="35%">: {{ $therapist_address }}</td>
            <td width="15%">Bulan</td>
            <td width="35%">: {{ $period }}</td>
        </tr>
        <tr>
            <td>Kecamatan</td>
            <td>: -</td>
            <td>Pelayanan Terapi</td>
            <td>: RAMUAN TRADISIONAL DAN KETERAMPILAN KOP</td>
        </tr>
        <tr>
            <td>Kabupaten</td>
            <td>: -</td>
            <td>Nomor STPT</td>
            <td>: T.500.16.7.2/6560/DPMPTSP.03</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2">No</th>
                <th rowspan="2">Tanggal</th>
                <th rowspan="2">Nama/Usia</th>
                <th rowspan="2">Alamat</th>
                <th colspan="2">L/P</th>
                <th colspan="2">Baru/Lama</th>
                <th rowspan="2">Keluhan</th>
                <th colspan="3">Jenis Pelayanan</th>
                <th rowspan="2">Keterangan</th>
            </tr>
            <tr>
                <th>L</th>
                <th>P</th>
                <th>B</th>
                <th>L</th>
                <th>Ramuan</th>
                <th>Keterampilan</th>
                <th>Kombinasi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($visits as $v)
            <tr>
                <td class="text-center">{{ $v['no'] }}</td>
                <td>{{ date('d-m-Y', strtotime($v['date'])) }}</td>
                <td>{{ $v['patient_name'] }} / {{ $v['patient_age'] ?? '-' }}</td>
                <td>{{ $v['address'] }}</td>
                <td class="text-center">{{ $v['gender'] == 'L' ? '✓' : '' }}</td>
                <td class="text-center">{{ $v['gender'] == 'P' ? '✓' : '' }}</td>
                <td class="text-center">{{ $v['is_new'] ? '✓' : '' }}</td>
                <td class="text-center">{{ !$v['is_new'] ? '✓' : '' }}</td>
                <td>{{ $v['complaint'] }}</td>
                <td class="text-center">{{ $v['is_ramuan'] ? '✓' : '' }}</td>
                <td class="text-center">{{ $v['is_keterampilan'] ? '✓' : '' }}</td>
                <td class="text-center">{{ $v['is_kombinasi'] ? '✓' : '' }}</td>
                <td>{{ $v['notes'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="13" class="text-center">Tidak ada kunjungan di periode ini</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <h3>REKAPITULASI</h3>
    <table class="data-table" style="width: 50%;">
        <tr>
            <td>Total Laki-laki</td>
            <td class="text-center">{{ $summary['total_male'] }}</td>
        </tr>
        <tr>
            <td>Total Perempuan</td>
            <td class="text-center">{{ $summary['total_female'] }}</td>
        </tr>
        <tr>
            <td>Total Pasien Baru</td>
            <td class="text-center">{{ $summary['total_new'] }}</td>
        </tr>
        <tr>
            <td>Total Pasien Lama</td>
            <td class="text-center">{{ $summary['total_old'] }}</td>
        </tr>
        <tr>
            <td>Total Layanan Ramuan</td>
            <td class="text-center">{{ $summary['total_ramuan'] }}</td>
        </tr>
        <tr>
            <td>Total Layanan Keterampilan</td>
            <td class="text-center">{{ $summary['total_keterampilan'] }}</td>
        </tr>
        <tr>
            <td>Total Layanan Kombinasi</td>
            <td class="text-center">{{ $summary['total_kombinasi'] }}</td>
        </tr>
        <tr>
            <th>Total Kunjungan Pasien</th>
            <th class="text-center">{{ $summary['total_visits'] }} orang</th>
        </tr>
    </table>

    <div class="footer">
        ............., {{ date('F Y') }}<br>
        Penyehat Tradisional,<br>
        <br><br><br><br>
        <strong>{{ $therapist_name }}</strong>
    </div>

</body>
</html>
