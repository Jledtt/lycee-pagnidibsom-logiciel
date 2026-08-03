<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $exam->name }}</title>
    <style>
        @page { margin: 18px 22px; }
        body { margin: 0; color: #000; font-family: "DejaVu Serif", serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        .header td { border: 0; vertical-align: top; }
        .logo { width: 60px; height: 60px; object-fit: contain; }
        .school h1 { margin: 0 0 4px; font-size: 14px; text-transform: uppercase; }
        .school p { margin: 0 0 3px; font-weight: bold; }
        .meta { text-align: right; line-height: 1.45; }
        .title { margin: 18px 0 12px; text-align: center; font-size: 18px; font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .subtitle { margin: 0 0 12px; text-align: center; font-size: 12px; font-weight: bold; }
        .list th, .list td { border: 1px solid #000; padding: 6px; vertical-align: top; }
        .list th { background: #f1f1f1; text-align: left; text-transform: uppercase; font-size: 9px; }
        .center { text-align: center; }
        .right { text-align: right; }
        .sign { margin-top: 24px; text-align: right; line-height: 1.6; }
    </style>
</head>
<body>
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')

    <table class="header">
        <tr>
            <td style="width:74px">@include('pdf.partials.logo-with-motto', ['logoPath' => $logoPath])</td>
            <td class="school">
                <h1>{{ $school?->school_name ?? 'Lycée Privé Pagnidibsom' }}</h1>
                <p>{{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }}</p>
                <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
            </td>
            <td class="meta" style="width:220px">
                <strong>Année scolaire : {{ $exam->academicYear?->name }}</strong><br>
                Session : {{ $exam->name }}<br>
                Epreuve : {{ $subject->subject?->name }}<br>
                Note / {{ number_format($subject->max_score, 0, ',', ' ') }}
            </td>
        </tr>
    </table>

    <div class="title">Rélève de notes</div>
    <div class="subtitle">Epreuve : {{ $subject->subject?->name }} - Correcteur : {{ $subject->correction_teacher_name ?: '................................' }}</div>

    <table class="list">
        <thead>
            <tr>
                <th style="width:9%" class="center">No ordre</th>
                <th style="width:22%">Anonymat</th>
                <th style="width:17%" class="right">Note / {{ number_format($subject->max_score, 0, ',', ' ') }}</th>
                <th>Observation</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($candidates as $candidate)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td><strong>{{ $candidate->anonymous_code ?: '-' }}</strong></td>
                    <td class="right">
                        @if ($candidate->sheet_is_absent)
                            Absent
                        @elseif ($candidate->sheet_score !== null)
                            {{ number_format((float) $candidate->sheet_score, 2, ',', ' ') }}
                        @endif
                    </td>
                    <td>{{ $candidate->sheet_observation }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="center">Aucun candidat.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="sign">
        Ouagadougou, le {{ now()->format('d/m/Y') }}<br>
        Correcteur<br><br><br>
        ................................
    </div>
</body>
</html>
