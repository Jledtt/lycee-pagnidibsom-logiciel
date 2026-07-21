<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $exam->name }}</title>
    <style>
        @page { margin: 16px 18px; }
        body { margin: 0; color: #000; font-family: "DejaVu Sans", sans-serif; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .logo { width: 54px; height: 54px; object-fit: contain; }
        .school h1 { margin: 0 0 4px; font-size: 15px; text-transform: uppercase; }
        .school p { margin: 0 0 3px; font-weight: bold; }
        .meta { text-align: right; line-height: 1.45; }
        .title { margin: 16px 0 10px; text-align: center; font-size: 16px; font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .list th, .list td { border: 1px solid #000; padding: 4px; vertical-align: top; }
        .list th { background: #f1f1f1; text-align: left; text-transform: uppercase; font-size: 8px; }
        .center { text-align: center; }
        .right { text-align: right; }
        .stamp { margin-top: 18px; text-align: right; line-height: 1.6; }
    </style>
</head>
<body>
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')
    @php($format = fn ($value) => $value === null ? '-' : number_format((float) $value, 2, ',', ' '))

    <table class="header">
        <tr>
            <td style="width:72px"><img class="logo" src="{{ public_path($logoPath) }}" alt="Logo"></td>
            <td class="school">
                <h1>{{ $school?->school_name ?? 'Lycee Prive Pagnidibsom' }}</h1>
                <p>{{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }}</p>
                <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
            </td>
            <td class="meta" style="width:210px">
                <strong>Annee scolaire : {{ $exam->academicYear?->name }}</strong><br>
                Session : {{ $exam->name }}<br>
                Type : {{ $exam->exam_type_label }}<br>
                Statut : {{ ucfirst($status) }}
            </td>
        </tr>
    </table>

    <div class="title">{{ $title }}</div>

    <table class="list">
        <thead>
            <tr>
                <th style="width:28px" class="center">Rang</th>
                <th style="width:85px">Anonymat</th>
                <th style="width:90px">Matricule</th>
                <th>Nom et prenom(s)</th>
                <th style="width:80px">Classe</th>
                <th style="width:58px" class="right">Moy./20</th>
                <th style="width:72px">Decision</th>
                <th style="width:62px" class="center">Manquants</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($results as $row)
                @php($candidate = $row['candidate'])
                <tr>
                    <td class="center">{{ $row['rank'] ?? '-' }}</td>
                    <td>{{ $candidate->anonymous_code ?: '-' }}</td>
                    <td>{{ $candidate->student?->matricule }}</td>
                    <td><strong>{{ $candidate->student?->full_name }}</strong></td>
                    <td>{{ $candidate->schoolClass?->name }}</td>
                    <td class="right"><strong>{{ $format($row['average']) }}</strong></td>
                    <td>{{ $row['decision'] }}</td>
                    <td class="center">{{ $row['missing'] }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="center">Aucun resultat.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="stamp">
        Ouagadougou, le {{ now()->format('d/m/Y') }}<br>
        {{ $school?->principal_title ?? 'Le Proviseur' }}<br><br><br>
        <strong>{{ $school?->principal_name ?? 'Yamdaogo TINTILA' }}</strong>
    </div>
</body>
</html>
