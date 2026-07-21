<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $exam->name }}</title>
    <style>
        @page { margin: 18px 22px; }
        body { margin: 0; color: #000; font-family: "DejaVu Sans", sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .logo { width: 58px; height: 58px; object-fit: contain; }
        .school h1 { margin: 0 0 4px; font-size: 16px; text-transform: uppercase; }
        .school p { margin: 0 0 3px; font-weight: bold; }
        .meta { text-align: right; line-height: 1.45; }
        .title { margin: 18px 0 12px; text-align: center; font-size: 17px; font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .room { margin: 14px 0 6px; font-size: 13px; font-weight: bold; }
        .box, .list th, .list td { border: 1px solid #000; padding: 6px; vertical-align: top; }
        .list th { background: #f1f1f1; text-align: left; text-transform: uppercase; font-size: 9px; }
        .center { text-align: center; }
        .sign { height: 42px; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')
    <table class="header">
        <tr>
            <td style="width:78px"><img class="logo" src="{{ public_path($logoPath) }}" alt="Logo"></td>
            <td class="school">
                <h1>{{ $school?->school_name ?? 'Lycee Prive Pagnidibsom' }}</h1>
                <p>{{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }}</p>
                <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
            </td>
            <td class="meta" style="width:220px">
                <strong>Annee scolaire : {{ $exam->academicYear?->name }}</strong><br>
                Session : {{ $exam->name }}<br>
                Type : {{ $exam->exam_type_label }}<br>
                Date : {{ now()->format('d/m/Y') }}
            </td>
        </tr>
    </table>

    <div class="title">{{ $title }}</div>

    <table class="list" style="margin-bottom:12px">
        <thead>
            <tr>
                <th>Matiere</th>
                <th>Date</th>
                <th>Heure</th>
                <th>Surveillant 1</th>
                <th>Surveillant 2</th>
                <th>Absents</th>
                <th>Copies</th>
                <th>Incidents</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($exam->subjects->sortBy('position') as $subject)
                <tr>
                    <td><strong>{{ $subject->subject?->name }}</strong></td>
                    <td>{{ $subject->exam_date?->format('d/m/Y') ?: '-' }}</td>
                    <td>{{ $subject->starts_at ?: '-' }} - {{ $subject->ends_at ?: '-' }}</td>
                    <td>{{ $subject->supervisor_one ?: '-' }}</td>
                    <td>{{ $subject->supervisor_two ?: '-' }}</td>
                    <td class="center">{{ $subject->absent_count ?? '-' }}</td>
                    <td class="center">{{ $subject->received_copies ?? '-' }} / {{ $subject->expected_copies ?? $exam->candidates->count() }}</td>
                    <td>{{ $subject->incident_notes ?: '' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="center">Aucune matiere.</td></tr>
            @endforelse
        </tbody>
    </table>

    @forelse ($exam->candidates->groupBy(fn ($candidate) => $candidate->room_name ?: 'Salle non affectee') as $room => $candidates)
        <div class="room">{{ $room }} - {{ $candidates->count() }} candidat(s)</div>
        <table class="list">
            <tr>
                <th>Inscrits</th>
                <th>Present(s)</th>
                <th>Absent(s)</th>
                <th>Copies remises</th>
                <th>Incidents / observations</th>
            </tr>
            <tr>
                <td class="center">{{ $candidates->count() }}</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="sign"></td>
            </tr>
        </table>

        <table class="list" style="margin-top:8px">
            <thead>
                <tr>
                    <th style="width:28px" class="center">No</th>
                    <th style="width:90px">Anonymat</th>
                    <th>Nom et prenom(s)</th>
                    <th style="width:80px">Classe</th>
                    <th style="width:80px">Present</th>
                    <th style="width:80px">Absent</th>
                    <th style="width:110px">Emargement</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($candidates->sortBy([['schoolClass.name', 'asc'], ['student.last_name', 'asc']]) as $candidate)
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td>{{ $candidate->anonymous_code ?: '-' }}</td>
                        <td><strong>{{ $candidate->student?->full_name }}</strong></td>
                        <td>{{ $candidate->schoolClass?->name }}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table style="margin-top:16px">
            <tr>
                <td class="box sign">Signature surveillant 1</td>
                <td style="width:20px"></td>
                <td class="box sign">Signature surveillant 2</td>
                <td style="width:20px"></td>
                <td class="box sign">Chef de centre</td>
            </tr>
        </table>
        @if (! $loop->last)
            <div class="page-break"></div>
        @endif
    @empty
        <p>Aucun candidat.</p>
    @endforelse
</body>
</html>
