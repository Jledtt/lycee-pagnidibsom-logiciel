<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Assiduite - {{ $student->full_name }}</title>
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
        .footer { margin-top: 18px; font-size: 10px; }
    </style>
</head>
<body>
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')
    @php($statusLabels = ['absent' => 'Absent', 'late' => 'Retard', 'excused' => 'Justifie'])

    <table class="header">
        <tr>
            <td style="width:78px">
                <img class="logo" src="{{ public_path($logoPath) }}" alt="Logo">
            </td>
            <td class="school">
                <h1>{{ $school?->school_name ?? 'Lycée Privé Pagnidibsom' }}</h1>
                <p>{{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }}</p>
                <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
            </td>
            <td class="meta" style="width:220px">
                <strong>Année scolaire : {{ $academicYear?->name ?? '-' }}</strong><br>
                Élève : {{ $student->full_name }}<br>
                Matricule : {{ $student->matricule }}<br>
                Mois : {{ $month }}
            </td>
        </tr>
    </table>

    <div class="title">Historique d’assiduite</div>

    <table class="summary">
        <tr>
            <td>Absences : {{ $summary['absent'] }}</td>
            <td>Retards : {{ $summary['late'] }}</td>
            <td>Justifies : {{ $summary['excused'] }}</td>
        </tr>
    </table>

    <table class="list">
        <thead>
            <tr>
                <th style="width:34px" class="center">No</th>
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
