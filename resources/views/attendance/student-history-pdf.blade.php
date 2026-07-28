<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Assiduité - {{ $student->full_name }}</title>
    <style>
        @page { margin: 18px 22px; }
        body { margin: 0; color: #000; font-family: "DejaVu Sans", sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .title { margin: 18px 0 12px; text-align: center; font-size: 19px; font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .summary { margin-bottom: 10px; }
        .summary td { border: 1px solid #000; padding: 6px 8px; font-weight: bold; }
        .list th, .list td { border: 1px solid #000; padding: 6px 5px; vertical-align: top; }
        .list th { background: #f1f1f1; text-align: left; font-size: 9px; text-transform: uppercase; }
        .center { text-align: center; }
        .footer { margin-top: 18px; font-size: 10px; }
    </style>
</head>
<body>
    @php($school = $school ?? $schoolSettings ?? null)
    @php($statusLabels = ['absent' => 'Absent', 'late' => 'Retard', 'excused' => 'Justifié'])

    @include('pdf.partials.school-header', [
        'school' => $school,
        'logoSize' => 58,
        'schoolNameSize' => 17,
        'schoolInfoSize' => 10,
        'rightWidth' => 220,
        'rightSize' => 10,
        'marginBottom' => 14,
        'rightLines' => [
            'Année scolaire : '.($academicYear?->name ?? '-'),
            'Élève : '.$student->full_name,
            'Matricule : '.$student->matricule,
            'Mois : '.$month,
        ],
    ])

    <div class="title">Historique d’assiduité</div>

    <table class="summary">
        <tr>
            <td>Absences : {{ $summary['absent'] }}</td>
            <td>Retards : {{ $summary['late'] }}</td>
            <td>Justifiés : {{ $summary['excused'] }}</td>
        </tr>
    </table>

    <table class="list">
        <thead>
            <tr>
                <th style="width:34px" class="center">N°</th>
                <th style="width:78px">Date</th>
                <th style="width:80px">Classe</th>
                <th style="width:80px">Statut</th>
                <th style="width:70px">Retard</th>
                <th>Motif / observation</th>
                <th style="width:120px">Justification</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $record)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>{{ $record->session?->session_date?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $record->session?->schoolClass?->name ?? '-' }}</td>
                    <td>{{ $statusLabels[$record->status] ?? $record->status }}</td>
                    <td>{{ $record->status === 'late' ? (($record->minutes_late ?? 0) . ' min') : '-' }}</td>
                    <td>{{ $record->reason ?: '-' }}</td>
                    <td>{{ $record->justified_at ? $record->justified_at->format('d/m/Y') : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="center">Aucune absence ou retard pour cette période.</td>
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
