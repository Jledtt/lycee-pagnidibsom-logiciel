<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin - {{ $reportCard->student->full_name }}</title>
    <style>
        @page { margin: 18px 22px; }
        body { margin: 0; color: #000; font-family: "DejaVu Sans", sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .logo { width: 58px; height: 58px; object-fit: contain; }
        .school h1 { margin: 0 0 4px; font-size: 17px; text-transform: uppercase; }
        .school p { margin: 0 0 3px; font-weight: bold; }
        .meta { text-align: right; line-height: 1.45; }
        .title { margin: 18px 0 12px; text-align: center; font-size: 19px; font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .summary { margin-bottom: 10px; }
        .summary td { border: 1px solid #000; padding: 6px 8px; font-weight: bold; }
        .list th, .list td { border: 1px solid #000; padding: 6px 5px; vertical-align: top; }
        .list th { background: #f1f1f1; text-align: left; font-size: 9px; text-transform: uppercase; }
        .center { text-align: center; }
        .right { text-align: right; }
        .decision { margin-top: 12px; }
        .decision td { border: 1px solid #000; padding: 8px; height: 48px; vertical-align: top; }
        .footer { margin-top: 18px; font-size: 10px; }
    </style>
</head>
<body>
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')

    <table class="header">
        <tr>
            <td style="width:78px">
                <img class="logo" src="{{ public_path($logoPath) }}" alt="Logo">
            </td>
            <td class="school">
                <h1>{{ $school?->school_name ?? 'Lycee Prive Pagnidibsom' }}</h1>
                <p>{{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }}</p>
                <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
            </td>
            <td class="meta" style="width:220px">
                <strong>Annee scolaire : {{ $reportCard->academicYear?->name ?? '-' }}</strong><br>
                Classe : {{ $reportCard->schoolClass->name }}<br>
                Trimestre : {{ $reportCard->term->name }}
            </td>
        </tr>
    </table>

    <div class="title">Bulletin de notes</div>

    <table class="summary">
        <tr>
            <td>Eleve : {{ $reportCard->student->full_name }}</td>
            <td>Matricule : {{ $reportCard->student->matricule }}</td>
            <td>Classe : {{ $reportCard->schoolClass->name }}</td>
        </tr>
        <tr>
            <td>Moyenne generale : {{ $reportCard->general_average === null ? '-' : number_format($reportCard->general_average, 2, ',', ' ') . ' / 20' }}</td>
            <td>Rang : {{ $reportCard->rank ? $reportCard->rank . ' / ' . $reportCard->class_size : '-' }}</td>
            <td>Statut : {{ ucfirst($reportCard->status) }}</td>
        </tr>
    </table>

    <table class="list">
        <thead>
            <tr>
                <th>Matiere</th>
                <th style="width:70px" class="center">Coef.</th>
                <th style="width:90px" class="center">Moyenne</th>
                <th style="width:90px" class="center">Points</th>
                <th style="width:120px">Appreciation</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($subjectRows as $row)
                <tr>
                    <td><strong>{{ $row['subject']->name }}</strong></td>
                    <td class="center">{{ number_format($row['coefficient'], 2, ',', ' ') }}</td>
                    <td class="center">{{ $row['average'] === null ? '-' : number_format($row['average'], 2, ',', ' ') }}</td>
                    <td class="center">{{ $row['points'] === null ? '-' : number_format($row['points'], 2, ',', ' ') }}</td>
                    <td>{{ $row['appreciation'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="center">Aucune matiere active pour cette classe.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="decision">
        <tr>
            <td style="width:50%">
                <strong>Appreciation generale</strong><br>
                {{ $reportCard->appreciation ?: '-' }}
            </td>
            <td>
                <strong>Visa de l'administration</strong><br>
            </td>
        </tr>
    </table>

    <table class="footer">
        <tr>
            <td>Document genere par le logiciel de gestion scolaire.</td>
            <td style="text-align:right">{{ $school?->principal_title ?? 'Le Proviseur' }}</td>
        </tr>
    </table>
</body>
</html>
