<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $exam->name }}</title>
    <style>
        @page { margin: 20px 24px; }
        body { margin: 0; color: #000; font-family: "DejaVu Sans", sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .logo { width: 58px; height: 58px; object-fit: contain; }
        .school h1 { margin: 0 0 4px; font-size: 16px; text-transform: uppercase; }
        .school p { margin: 0 0 3px; font-weight: bold; }
        .meta { text-align: right; line-height: 1.45; }
        .title { margin: 18px 0 12px; text-align: center; font-size: 17px; font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .list th, .list td { border: 1px solid #000; padding: 6px; vertical-align: top; }
        .list th { background: #f1f1f1; text-align: left; text-transform: uppercase; font-size: 9px; }
        .box { border: 1px solid #000; padding: 10px; line-height: 1.6; }
        .center { text-align: center; }
        .sign { height: 56px; }
    </style>
</head>
<body>
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')
    <table class="header">
        <tr>
            <td style="width:78px">@include('pdf.partials.logo-with-motto', ['logoPath' => $logoPath])</td>
            <td class="school">
                <h1>{{ $school?->school_name ?? 'Lycée Privé Pagnidibsom' }}</h1>
                <p>{{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }}</p>
                <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
            </td>
            <td class="meta" style="width:220px">
                <strong>Année scolaire : {{ $exam->academicYear?->name }}</strong><br>
                Session : {{ $exam->name }}<br>
                Type : {{ $exam->exam_type_label }}<br>
                Date : {{ now()->format('d/m/Y') }}
            </td>
        </tr>
    </table>

    <div class="title">{{ $title }}</div>

    <div class="box">
        Le jury de la session <strong>{{ $exam->name }}</strong>, après examen des résultats, arrête les décisions provisoires suivantes :
    </div>

    <table class="list" style="margin-top:12px">
        <tr>
            <th>Total candidats</th>
            <th>Admis</th>
            <th>A deliberer</th>
            <th>Ajournes</th>
        </tr>
        <tr>
            <td class="center">{{ $results->count() }}</td>
            <td class="center">{{ $admitted }}</td>
            <td class="center">{{ $deferred }}</td>
            <td class="center">{{ $rejected }}</td>
        </tr>
    </table>

    <table class="list" style="margin-top:12px">
        <thead>
            <tr>
                <th style="width:34px" class="center">Rang</th>
                <th>Nom et prénom(s)</th>
                <th style="width:86px">Classe</th>
                <th style="width:70px">Moyenne</th>
                <th style="width:96px">Décision</th>
                <th>Observation jury</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($results as $row)
                @php($candidate = $row['candidate'])
                <tr>
                    <td class="center">{{ $row['rank'] ?? '-' }}</td>
                    <td>{{ $candidate->student?->full_name }}</td>
                    <td>{{ $candidate->schoolClass?->name }}</td>
                    <td class="center">{{ $row['average'] === null ? '-' : number_format($row['average'], 2, ',', ' ') }}</td>
                    <td>{{ $candidate->jury_decision ? ($juryDecisionLabels[$candidate->jury_decision] ?? $candidate->jury_decision) : $row['decision'] }}</td>
                    <td>{{ $candidate->jury_observation }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="center">Aucun candidat.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table style="margin-top:20px">
        <tr>
            <td class="list sign">President du jury</td>
            <td style="width:24px"></td>
            <td class="list sign">Membre du jury</td>
            <td style="width:24px"></td>
            <td class="list sign">Visa direction</td>
        </tr>
    </table>
</body>
</html>
