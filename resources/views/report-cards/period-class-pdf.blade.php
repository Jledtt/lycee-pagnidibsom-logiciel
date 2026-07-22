<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rélèves - {{ $period->name }}</title>
    <style>
        @page { margin: 16px 22px; }
        body { margin: 0; color: #000; font-family: "DejaVu Sans", sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .header td { vertical-align: top; }
        .logo { width: 58px; height: 58px; object-fit: contain; }
        .school h1 { margin: 0 0 4px; font-size: 16px; text-transform: uppercase; }
        .school p { margin: 0 0 3px; font-weight: bold; }
        .meta { text-align: right; line-height: 1.45; }
        .title { margin: 16px 0 10px; text-align: center; font-size: 16px; font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .identity td { border: 1px solid #000; padding: 5px 7px; font-weight: bold; }
        .list th, .list td { border: 1px solid #000; padding: 5px; vertical-align: top; }
        .list th { background: #f1f1f1; text-align: left; font-size: 9px; text-transform: uppercase; }
        .center { text-align: center; }
        .summary td { border: 1px solid #000; padding: 7px; font-weight: bold; }
        .footer { margin-top: 16px; }
    </style>
</head>
<body>
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')

    @foreach ($items as $item)
        <div class="page">
            <table class="header">
                <tr>
                    <td style="width:78px"><img class="logo" src="{{ public_path($logoPath) }}" alt="Logo"></td>
                    <td class="school">
                        <h1>{{ $school?->school_name ?? 'Lycée Privé Pagnidibsom' }}</h1>
                        <p>{{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }}</p>
                        <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                        <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
                    </td>
                    <td class="meta" style="width:220px">
                        <strong>Année scolaire : {{ $term->academicYear?->name ?? '-' }}</strong><br>
                        Classe : {{ $schoolClass->name }}<br>
                        Trimestre : {{ $term->name }}<br>
                        Période : {{ $period->name }}
                    </td>
                </tr>
            </table>

            <div class="title">Rélève de notes - {{ $period->name }}</div>

            <table class="identity">
                <tr>
                    <td>Matricule : {{ $item['student']->matricule }}</td>
                    <td>Élève : {{ $item['student']->full_name }}</td>
                    <td>Classe : {{ $schoolClass->name }}</td>
                </tr>
            </table>

            <table class="list" style="margin-top:10px">
                <thead>
                    <tr>
                        <th>Matière</th>
                        <th style="width:70px" class="center">Coeff.</th>
                        <th style="width:80px" class="center">Moy. /20</th>
                        <th style="width:80px" class="center">Points</th>
                        <th style="width:120px">Appreciation</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($item['subjectRows'] as $row)
                        <tr>
                            <td><strong>{{ $row['subject']->name }}</strong></td>
                            <td class="center">{{ number_format($row['coefficient'], 2, ',', ' ') }}</td>
                            <td class="center">{{ $row['average'] === null ? '-' : number_format($row['average'], 2, ',', ' ') }}</td>
                            <td class="center">{{ $row['points'] === null ? '-' : number_format($row['points'], 2, ',', ' ') }}</td>
                            <td>{{ $row['appreciation'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="summary" style="margin-top:10px">
                <tr>
                    <td>Moyenne {{ $period->name }} : {{ $item['average'] === null ? '-' : number_format($item['average'], 2, ',', ' ') . ' / 20' }}</td>
                    <td>Rang période : {{ $item['rank'] ? $item['rank'].' / '.$item['classSize'] : '-' }}</td>
                    <td>Appreciation : {{ $item['average'] === null ? 'Non note' : ($item['average'] >= 10 ? 'Travail acceptable' : 'Doit fournir plus d efforts') }}</td>
                </tr>
            </table>

            <table class="footer">
                <tr>
                    <td>Ce relevé concerne uniquement {{ $period->name }}. Il est inclus dans le calcul du {{ $term->name }}.</td>
                    <td style="text-align:right">{{ $school?->principal_title ?? 'Le Proviseur' }}</td>
                </tr>
            </table>
        </div>
    @endforeach
</body>
</html>
