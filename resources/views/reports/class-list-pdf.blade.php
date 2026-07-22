<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Liste des élèves - {{ $schoolClass->name }}</title>
    <style>
        @page { margin: 18px 22px; }
        body {
            margin: 0;
            color: #000;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
        }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .logo { width: 58px; height: 58px; object-fit: contain; }
        .school h1 { margin: 0 0 4px; font-size: 17px; text-transform: uppercase; }
        .school p { margin: 0 0 3px; font-weight: bold; }
        .meta { text-align: right; line-height: 1.45; }
        .title {
            margin: 18px 0 12px;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
        }
        .summary { margin-bottom: 10px; }
        .summary td {
            width: 25%;
            border: 1px solid #000;
            padding: 6px 8px;
            font-weight: bold;
        }
        .list th,
        .list td {
            border: 1px solid #000;
            padding: 6px 5px;
            vertical-align: top;
        }
        .list th {
            background: #f1f1f1;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
        }
        .center { text-align: center; }
        .footer {
            margin-top: 18px;
            font-size: 10px;
        }
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
                <h1>{{ $school?->school_name ?? 'Lycée Privé Pagnidibsom' }}</h1>
                <p>{{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }}</p>
                <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
            </td>
            <td class="meta" style="width:210px">
                <strong>Année scolaire : {{ $academicYear?->name ?? '-' }}</strong><br>
                Classe : {{ $schoolClass->name }}<br>
                Date : {{ now()->format('d/m/Y') }}
            </td>
        </tr>
    </table>

    <div class="title">Liste des élèves par classe</div>

    <table class="summary">
        <tr>
            <td>Classe : {{ $schoolClass->name }}</td>
            <td>Niveau : {{ $schoolClass->level?->name ?? '-' }}</td>
            <td>Effectif : {{ $summary['total'] }}</td>
            <td>Filles / Garcons : {{ $summary['girls'] }} / {{ $summary['boys'] }}</td>
        </tr>
    </table>

    <table class="list">
        <thead>
            <tr>
                <th style="width:32px" class="center">No</th>
                <th style="width:96px">Matricule</th>
                <th>Nom et prénom(s)</th>
                <th style="width:58px" class="center">Sexe</th>
                <th style="width:80px">Naissance</th>
                <th style="width:170px">Tuteur</th>
                <th style="width:110px">Contact</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($schoolClass->enrollments as $enrollment)
                @php($student = $enrollment->student)
                @php($guardian = $student?->guardians->first())
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>{{ $student?->matricule }}</td>
                    <td><strong>{{ $student?->full_name }}</strong></td>
                    <td class="center">{{ $student?->gender_short_label ?? '-' }}</td>
                    <td>{{ $student?->birth_date?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $guardian?->full_name ?? '-' }}</td>
                    <td>{{ $guardian?->phone_primary ?? $student?->home_phone ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="center">Aucun élève actif inscrit dans cette classe.</td>
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
