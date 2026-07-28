<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rélève de notes</title>
    <style>
        @page { margin: 20px 24px; }
        body { margin: 0; color: #111; font-family: "DejaVu Sans", sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cfd8d1; padding: 6px; vertical-align: top; }
        th { background: #eef4f0; text-align: left; text-transform: uppercase; font-size: 8px; }
        .header td { border: 0; }
        .logo { width: 56px; height: 56px; object-fit: contain; }
        .school h1 { margin: 0 0 4px; font-size: 16px; text-transform: uppercase; }
        .school p { margin: 0 0 3px; font-weight: bold; }
        .meta { text-align: right; line-height: 1.45; }
        .title { margin: 16px 0 10px; text-align: center; font-size: 18px; font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .summary { margin-bottom: 10px; }
        .summary td { font-weight: bold; }
        .section-title { margin: 14px 0 6px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .center { text-align: center; }
        .footer td { border: 0; padding-top: 24px; }
    </style>
</head>
<body>
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')

    <table class="header">
        <tr>
            <td style="width:72px"><img class="logo" src="{{ public_path($logoPath) }}" alt="Logo"></td>
            <td class="school">
                <h1>{{ $school?->school_name ?? 'Lycée Privé Pagnidibsom' }}</h1>
                <p>{{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }}</p>
                <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
            </td>
            <td class="meta" style="width:220px">
                <strong>Année scolaire : {{ $reportCard->academicYear?->name ?? '-' }}</strong><br>
                Classe : {{ $reportCard->schoolClass->name }}<br>
                Trimestre : {{ $reportCard->term->name }}
            </td>
        </tr>
    </table>

    <div class="title">Rélève de notes</div>

    <table class="summary">
        <tr>
            <td>Élève : {{ $reportCard->student->full_name }}</td>
            <td>Matricule : {{ $reportCard->student->matricule }}</td>
            <td>Moyenne generale : {{ $reportCard->general_average === null ? '-' : number_format($reportCard->general_average, 2, ',', ' ') . ' / 20' }}</td>
        </tr>
        <tr>
            <td>Rang : {{ $reportCard->rank ? $reportCard->rank . ' / ' . $reportCard->class_size : '-' }}</td>
            <td>Appreciation : {{ $reportCard->appreciation ?: '-' }}</td>
            <td>Décision : {{ $reportCard->decision ?: '-' }}</td>
        </tr>
    </table>

    <div class="section-title">Synthese par matière</div>
    <table>
        <thead>
            <tr>
                <th>Matière</th>
                <th style="width:12%" class="center">Coef.</th>
                <th style="width:14%" class="center">Moyenne / 20</th>
                <th style="width:14%" class="center">Points</th>
                <th style="width:20%">Appreciation</th>
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
                <tr><td colspan="5">Aucune matière active.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Détails des évaluations</div>
    <table>
        <thead>
            <tr>
                <th>Matière</th>
                <th>Evaluation</th>
                <th style="width:12%" class="center">Note</th>
                <th style="width:12%" class="center">Sur</th>
                <th style="width:12%" class="center">Note / 20</th>
                <th style="width:16%">Appreciation</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($assessmentRows as $row)
                @php($grade = $row['grade'])
                <tr>
                    <td><strong>{{ $row['assessment']->subject?->name }}</strong></td>
                    <td>{{ $row['assessment']->title }}<br>{{ $row['assessment']->assessmentType?->name }}</td>
                    <td class="center">
                        @if ($grade && ! $grade->isCounted())
                            {{ \App\Models\Grade::statusLabels()[$grade->resolvedStatus()] ?? '-' }}
                        @else
                            {{ $grade?->score ?? '-' }}
                        @endif
                    </td>
                    <td class="center">{{ number_format($row['assessment']->max_score, 0, ',', ' ') }}</td>
                    <td class="center">{{ $row['normalized_score'] === null ? '-' : number_format($row['normalized_score'], 2, ',', ' ') }}</td>
                    <td>{{ $row['appreciation'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Aucune evaluation saisie.</td></tr>
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
