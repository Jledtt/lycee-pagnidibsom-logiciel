<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Emploi du temps - {{ $schoolClass->name }}</title>
    <style>
        @page { margin: 18px 20px; }
        body { margin: 0; color: #000; font-family: "DejaVu Sans", sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .logo { width: 58px; height: 58px; object-fit: contain; }
        .school h1 { margin: 0 0 4px; font-size: 17px; text-transform: uppercase; }
        .school p { margin: 0 0 3px; font-weight: bold; }
        .meta { text-align: right; line-height: 1.45; }
        .title { margin: 16px 0 10px; text-align: center; font-size: 20px; font-weight: bold; text-transform: uppercase; text-decoration: underline; }
        .schedule th, .schedule td { border: 1px solid #000; padding: 5px 4px; vertical-align: top; }
        .schedule th { background: #f1f1f1; text-align: center; text-transform: uppercase; }
        .time { width: 84px; text-align: center; font-weight: bold; }
        .subject { font-weight: bold; font-size: 10px; }
        .teacher { margin-top: 2px; font-size: 8px; color: #333; }
        .empty { color: #777; text-align: center; }
        .footer { margin-top: 14px; font-size: 9px; }
    </style>
</head>
<body>
    @php($school = $school ?? $schoolSettings)
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
                <strong>Annee scolaire : {{ $academicYear?->name ?? '-' }}</strong><br>
                Classe : {{ $schoolClass->name }}<br>
                Document genere le : {{ now()->format('d/m/Y') }}
            </td>
        </tr>
    </table>

    <div class="title">Emploi du temps</div>

    <table class="schedule">
        <thead>
            <tr>
                <th class="time">Horaire</th>
                @foreach ($dayLabels as $day)
                    <th>{{ $day }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($grid as $row)
                <tr>
                    <td class="time">{{ $row['slot']['label'] }}</td>
                    @foreach ($dayLabels as $dayNumber => $day)
                        @php($entry = $row['days'][$dayNumber] ?? null)
                        <td>
                            @if ($entry)
                                <div class="subject">{{ $entry->subject_label }}</div>
                                @if ($entry->teacher_name)
                                    <div class="teacher">{{ $entry->teacher_name }}</div>
                                @endif
                                @if ($entry->room)
                                    <div class="teacher">Salle : {{ $entry->room }}</div>
                                @endif
                            @else
                                <div class="empty">-</div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty">Aucun creneau saisi pour cette classe.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="footer">
        <tr>
            <td>Document provisoire modifiable depuis le logiciel de gestion scolaire.</td>
            <td style="text-align:right">{{ $school?->principal_title ?? 'Le Proviseur' }}</td>
        </tr>
    </table>
</body>
</html>
