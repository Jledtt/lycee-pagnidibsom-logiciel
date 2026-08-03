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
        .list th, .list td { border: 1px solid #000; padding: 5px; vertical-align: top; }
        .list th { background: #f1f1f1; text-align: left; text-transform: uppercase; font-size: 9px; }
        .center { text-align: center; }
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
                Candidats : {{ $exam->candidates->count() }}
            </td>
        </tr>
    </table>

    <div class="title">{{ $title }}</div>

    <table class="list">
        <thead>
            <tr>
                <th style="width:34px" class="center">No</th>
                <th style="width:90px">Anonymat</th>
                <th style="width:95px">Matricule</th>
                <th>Nom et prénom(s)</th>
                <th style="width:90px">Classe</th>
                <th style="width:80px">Salle</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($exam->candidates->sortBy('anonymous_code') as $candidate)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td><strong>{{ $candidate->anonymous_code ?: '-' }}</strong></td>
                    <td>{{ $candidate->student?->matricule }}</td>
                    <td>{{ $candidate->student?->full_name }}</td>
                    <td>{{ $candidate->schoolClass?->name }}</td>
                    <td>{{ $candidate->room_name ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="center">Aucun candidat.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
