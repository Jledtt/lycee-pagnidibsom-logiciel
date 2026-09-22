<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 20px 24px 30px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 8.5px; }
        h1 { margin: 6px 0 8px; text-align: center; font-size: 17px; text-transform: uppercase; }
        .document-state { margin: -2px 0 9px; text-align: center; color: #555; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .schedule { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .schedule th, .schedule td { border: 1px solid #222; padding: 5px 2px; text-align: center; vertical-align: middle; overflow-wrap: anywhere; word-wrap: break-word; }
        .schedule th { background: #174534; color: #fff; font-weight: 800; }
        .schedule td:first-child { width: 62px; font-weight: 800; background: #f2f2f2; }
        .schedule tbody tr:not(.break) td { height: 34px; }
        .break td { background: #faedcd; font-weight: 800; color: #7a5300; letter-spacing: 1px; }
        .subject { font-size: 9px; font-weight: 800; }
        .room { margin-top: 2px; font-size: 7px; color: #444; }
        .staff { width: 72%; margin: 10px auto 0; border-collapse: collapse; table-layout: fixed; page-break-inside: auto; }
        .staff th, .staff td { border: 1px solid #333; padding: 3px 6px; vertical-align: middle; }
        .staff th { background: #efefef; font-size: 8px; font-weight: 800; text-align: center; text-transform: uppercase; }
        .staff .staff-title { background: #fff; font-size: 9px; }
        .staff td:first-child { width: 35%; font-weight: 700; text-align: center; }
        .staff td:last-child { width: 65%; }
        .staff tr { page-break-inside: avoid; }
        .staff-empty { color: #666; font-style: italic; text-align: center; }
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

    <table class="staff">
        <thead>
            <tr>
                <th class="staff-title" colspan="2">Corps professoral</th>
            </tr>
            <tr>
                <th>Discipline</th>
                <th>Nom et prénoms</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($teachingStaff as $staffMember)
                <tr>
                    <td>{{ $staffMember['subject'] }}</td>
                    <td>{{ $staffMember['teachers'] }}</td>
                </tr>
            @empty
                <tr>
                    <td class="staff-empty" colspan="2">Aucune matière renseignée</td>
                </tr>
            @endforelse
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
