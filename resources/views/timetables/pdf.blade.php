<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 22px 26px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 10px; }
        .header { text-align: center; margin-bottom: 10px; }
        .school { font-size: 16px; font-weight: 800; text-transform: uppercase; }
        .meta { margin-top: 4px; font-size: 11px; }
        h1 { margin: 8px 0 10px; text-align: center; font-size: 18px; text-transform: uppercase; }
        .info { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        .info td { border: 1px solid #333; padding: 5px 7px; vertical-align: top; }
        .schedule { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .schedule th, .schedule td { border: 1px solid #222; padding: 6px 4px; text-align: center; vertical-align: middle; }
        .schedule th { background: #174534; color: #fff; font-weight: 800; }
        .schedule td:first-child { width: 90px; font-weight: 800; background: #f2f2f2; }
        .break td { background: #faedcd; font-weight: 800; color: #7a5300; letter-spacing: 1px; }
        .subject { font-size: 11px; font-weight: 800; }
        .teacher, .room { margin-top: 3px; font-size: 8px; color: #444; }
        .notes { margin-top: 10px; font-size: 9px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="school">Lycee Prive Pagnidibsom</div>
        <div class="meta">Annee scolaire : {{ $timetable->academicYear?->name }} | Classe : {{ $timetable->schoolClass?->name }}</div>
    </div>

    <h1>{{ $timetable->title }}</h1>

    <table class="info">
        <tr>
            <td><strong>Statut :</strong> {{ $timetable->status }}</td>
            <td><strong>Professeur principal / equipe :</strong> {{ $timetable->principal_teacher ?: '-' }}</td>
        </tr>
    </table>

    <table class="schedule">
        <thead>
            <tr>
                <th>Horaire</th>
                @foreach ($days as $dayLabel)
                    <th>{{ $dayLabel }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($grid as $row)
                @if ($row['is_break'])
                    <tr class="break">
                        <td>{{ $row['period_label'] }}</td>
                        <td colspan="{{ count($days) }}">{{ $row['period_label'] }}</td>
                    </tr>
                @else
                    <tr>
                        <td>{{ $row['period_label'] }}</td>
                        @foreach (array_keys($days) as $dayKey)
                            @php($entry = $row['days'][$dayKey] ?? null)
                            <td>
                                <div class="subject">{{ $entry?->subject_name ?: '-' }}</div>
                                @if ($entry?->teacher_name)
                                    <div class="teacher">{{ $entry->teacher_name }}</div>
                                @endif
                                @if ($entry?->room)
                                    <div class="room">Salle : {{ $entry->room }}</div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    @if ($timetable->notes)
        <div class="notes"><strong>Notes :</strong> {{ $timetable->notes }}</div>
    @endif
</body>
</html>
