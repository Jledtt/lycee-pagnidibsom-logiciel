<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 20px 24px 30px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 8.5px; }
        h1 { margin: 6px 0 8px; text-align: center; font-size: 17px; text-transform: uppercase; }
        .document-state { margin: -2px 0 9px; text-align: center; color: #555; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .info { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        .info td { border: 1px solid #333; padding: 5px 7px; vertical-align: top; }
        .schedule { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .schedule th, .schedule td { border: 1px solid #222; padding: 5px 2px; text-align: center; vertical-align: middle; overflow-wrap: anywhere; word-wrap: break-word; }
        .schedule th { background: #174534; color: #fff; font-weight: 800; }
        .schedule td:first-child { width: 62px; font-weight: 800; background: #f2f2f2; }
        .schedule tbody tr:not(.break) td { height: 34px; }
        .break td { background: #faedcd; font-weight: 800; color: #7a5300; letter-spacing: 1px; }
        .subject { font-size: 9px; font-weight: 800; }
        .teacher, .room { margin-top: 2px; font-size: 7px; color: #444; }
        .notes { margin-top: 10px; font-size: 9px; }
        .footer { position: fixed; right: 0; bottom: -20px; left: 0; border-top: 1px solid #aaa; padding-top: 4px; color: #666; font-size: 7px; text-align: center; }
        .page-number::after { content: counter(page); }
    </style>
</head>
<body>
    @php($school = $school ?? $schoolSettings ?? null)
    @php($statusLabel = match ($timetable->status) {
        'active' => 'Publié - document officiel',
        'archived' => 'Archivé',
        default => 'Brouillon - à valider',
    })

    @include('pdf.partials.school-header', [
        'school' => $school,
        'logoSize' => 58,
        'schoolNameSize' => 15,
        'schoolInfoSize' => 9,
        'rightWidth' => 175,
        'rightSize' => 9,
        'marginBottom' => 10,
        'rightLines' => [
            'Année scolaire : '.($timetable->academicYear?->name ?? '-'),
            'Classe : '.($timetable->schoolClass?->name ?? '-'),
        ],
    ])

    <h1>{{ $timetable->title }}</h1>
    <div class="document-state">{{ $statusLabel }}</div>

    <table class="info">
        <tr>
            <td style="width:62%"><strong>Professeur principal / équipe pédagogique :</strong> {{ $timetable->principal_teacher ?: 'Non renseigné' }}</td>
            <td><strong>Dernière mise à jour :</strong> {{ $timetable->updated_at?->format('d/m/Y à H:i') ?? '-' }}</td>
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

    <div class="footer">
        {{ $timetable->schoolClass?->name ?? 'Classe' }} · {{ $timetable->academicYear?->name ?? 'Année scolaire' }} · Page <span class="page-number"></span>
    </div>
</body>
</html>
