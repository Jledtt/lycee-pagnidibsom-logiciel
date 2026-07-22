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
            <td style="width:78px"><img class="logo" src="{{ public_path($logoPath) }}" alt="Logo"></td>
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
                Classes : {{ $exam->classes->pluck('name')->join(', ') }}
            </td>
        </tr>
    </table>

    <div class="title">{{ $title }}</div>

    <table class="list">
        <thead>
            <tr>
                <th style="width:28px" class="center">No</th>
                <th style="width:95px">Matricule</th>
                <th>Nom et prénom(s)</th>
                <th style="width:52px">Sexe</th>
                <th style="width:90px">Date naissance</th>
                <th style="width:90px">Classe</th>
                <th style="width:70px">Statut</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($exam->candidates->sortBy([['schoolClass.name', 'asc'], ['student.last_name', 'asc']]) as $candidate)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>{{ $candidate->student?->matricule }}</td>
                    <td><strong>{{ $candidate->student?->full_name }}</strong></td>
                    <td>{{ $candidate->student?->gender_short_label }}</td>
                    <td>{{ $candidate->student?->birth_date?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $candidate->schoolClass?->name }}</td>
                    <td>{{ $candidate->status }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="center">Aucun candidat.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
