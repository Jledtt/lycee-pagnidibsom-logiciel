<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Absences - {{ $schoolClass->name }}</title>
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
    @php($school = $school ?? $schoolSettings)
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')
    @php($statusLabels = ['absent' => 'Absent', 'late' => 'Retard', 'excused' => 'Justifie'])

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
            <td class="meta" style="width:210px">
                <strong>Année scolaire : {{ $academicYear?->name ?? '-' }}</strong><br>
                Classe : {{ $schoolClass->name }}<br>
                Date : {{ $date->format('d/m/Y') }}
            </td>
        </tr>
    </table>

    <div class="title">Liste des absences et retards</div>

    <table class="summary">
        <tr>
            <td>Classe : {{ $schoolClass->name }}</td>
            <td>Presents : {{ $summary['present'] }}</td>
            <td>Absents : {{ $summary['absent'] }}</td>
            <td>Retards : {{ $summary['late'] }}</td>
            <td>Justifies : {{ $summary['excused'] }}</td>
        </tr>
    </table>

    <table class="list">
        <thead>
            <tr>
                <th style="width:34px" class="center">No</th>
                <th style="width:105px">Matricule</th>
                <th>Élève</th>
                <th style="width:90px">Statut</th>
                <th style="width:80px">Retard</th>
                <th>Motif / observation</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $record)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>{{ $record->student?->matricule }}</td>
                    <td><strong>{{ $record->student?->full_name }}</strong></td>
                    <td>{{ $statusLabels[$record->status] ?? $record->status }}</td>
                    <td>{{ $record->status === 'late' ? (($record->minutes_late ?? 0) . ' min') : '-' }}</td>
                    <td>{{ $record->reason ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="center">Aucune absence ou retard pour cette classe à cette date.</td>
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
