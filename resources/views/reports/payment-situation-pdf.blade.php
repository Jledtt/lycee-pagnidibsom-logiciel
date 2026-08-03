<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Situation des paiements - {{ $schoolClass->name }}</title>
    <style>
        @page { margin: 18px 22px; }
        body {
            margin: 0;
            color: #000;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9px;
        }
        table { width: 100%; border-collapse: collapse; }
        .list { table-layout: fixed; }
        .header td { vertical-align: top; }
        .logo { width: 58px; height: 58px; object-fit: contain; }
        .school h1 { margin: 0 0 4px; font-size: 17px; text-transform: uppercase; }
        .school p { margin: 0 0 3px; font-weight: bold; }
        .meta { text-align: right; line-height: 1.45; }
        .title {
            margin: 18px 0 12px;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
        }
        .summary { margin-bottom: 10px; }
        .summary td {
            width: 20%;
            border: 1px solid #000;
            padding: 6px 8px;
            font-weight: bold;
        }
        .list th,
        .list td {
            border: 1px solid #000;
            padding: 5px 4px;
            vertical-align: top;
            overflow-wrap: anywhere;
            word-wrap: break-word;
        }
        .list th {
            background: #f1f1f1;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
        }
        .right { text-align: right; }
        .center { text-align: center; }
        .footer {
            margin-top: 18px;
            font-size: 10px;
        }
    </style>
</head>
<body>
    @php($school = $school ?? $schoolSettings)
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')
    @php($currency = $school?->currency ?? 'FCFA')

    <table class="header">
        <tr>
            <td style="width:78px">
                @include('pdf.partials.logo-with-motto', ['logoPath' => $logoPath])
            </td>
            <td class="school">
                <h1>{{ $school?->school_name ?? 'Lycée Privé Pagnidibsom' }}</h1>
                <p>{{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }}</p>
                <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
            </td>
            <td class="meta" style="width:150px">
                <strong>Année scolaire : {{ $academicYear?->name ?? '-' }}</strong><br>
                Classe : {{ $schoolClass->name }}<br>
                Date : {{ now()->format('d/m/Y') }}
            </td>
        </tr>
    </table>

    <div class="title">Situation des paiements par classe</div>

    <table class="summary">
        <tr>
            <td>Élèves : {{ $rows->count() }}</td>
            <td>Attendu : {{ is_null($summary['expected']) ? 'À configurer' : number_format($summary['expected'], 0, ',', ' ') . ' ' . $currency }}</td>
            <td>Paye : {{ number_format($summary['paid'], 0, ',', ' ') }} {{ $currency }}</td>
            <td>Reste : {{ is_null($summary['balance']) ? 'À configurer' : number_format($summary['balance'], 0, ',', ' ') . ' ' . $currency }}</td>
            <td>Statuts : {{ $summary['up_to_date'] }} / {{ $summary['partial'] }} / {{ $summary['unpaid'] }}</td>
        </tr>
    </table>

    <table class="list">
        <thead>
            <tr>
                <th style="width:32px" class="center">No</th>
                <th style="width:76px">Matricule</th>
                <th>Nom et prénom(s)</th>
                <th style="width:78px" class="right">Attendu</th>
                <th style="width:78px" class="right">Payé</th>
                <th style="width:78px" class="right">Reste</th>
                <th style="width:64px">Statut</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>{{ $row['student']?->matricule }}</td>
                    <td><strong>{{ $row['student']?->full_name }}</strong></td>
                    <td class="right">{{ is_null($row['expected']) ? '-' : number_format($row['expected'], 0, ',', ' ') . ' ' . $currency }}</td>
                    <td class="right">{{ number_format($row['paid'], 0, ',', ' ') }} {{ $currency }}</td>
                    <td class="right">{{ is_null($row['balance']) ? '-' : number_format($row['balance'], 0, ',', ' ') . ' ' . $currency }}</td>
                    <td>{{ $row['status']['label'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="center">Aucun élève actif inscrit dans cette classe.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="footer">
        <tr>
            <td>Document généré par le logiciel de gestion scolaire.</td>
            <td style="text-align:right">{{ $school?->principal_title ?? 'Le Proviseur' }}</td>
        </tr>
    </table>
</body>
</html>
