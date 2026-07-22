<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Liste des élèves rachetes</title>
    <style>
        @page { margin: 22px 26px; }
        body { margin: 0; color: #000; font-family: "DejaVu Serif", serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .header td { border: 0; vertical-align: top; }
        .logo { width: 58px; height: 58px; object-fit: contain; }
        .school h1 { margin: 0 0 4px; font-size: 15px; text-transform: uppercase; }
        .school p { margin: 0 0 3px; font-weight: bold; }
        .meta { text-align: right; line-height: 1.45; }
        .title { margin: 18px 0 12px; text-align: center; font-size: 18px; font-weight: bold; text-decoration: underline; }
        .list th, .list td { border: 1px solid #000; padding: 5px; vertical-align: top; }
        .list th { background: #f1f1f1; text-transform: uppercase; font-size: 8px; }
        .center { text-align: center; }
        .right { text-align: right; }
        .stamp { margin-top: 20px; text-align: right; line-height: 1.6; }
    </style>
</head>
<body>
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')

    <table class="header">
        <tr>
            <td style="width:72px"><img class="logo" src="{{ public_path($logoPath) }}" alt="Logo"></td>
            <td class="school">
                <h1>{{ $school?->school_name ?? 'Lycée Privé Pagnidibsom' }}</h1>
                <p>{{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }}</p>
                <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
            </td>
            <td class="meta" style="width:220px">
                <strong>Année scolaire : {{ $academicYear->name }}</strong><br>
                Classe : {{ $schoolClass->name }}<br>
                Seuil : {{ number_format($threshold, 2, ',', ' ') }} / 20
            </td>
        </tr>
    </table>

    <div class="title">Liste des élèves rachetes</div>

    <table class="list">
        <thead>
            <tr>
                <th class="center" style="width:8%">Rang</th>
                <th style="width:18%">Matricule</th>
                <th>Nom et prénom(s)</th>
                @foreach ($terms as $term)
                    <th class="right">{{ $term->name }}</th>
                @endforeach
                <th class="right">Moyenne</th>
                <th class="right">Moy. rachetee</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($eligibleRows as $row)
                <tr>
                    <td class="center">{{ $row['rank'] ?: '-' }}</td>
                    <td>{{ $row['student']->matricule }}</td>
                    <td><strong>{{ $row['student']->full_name }}</strong></td>
                    @foreach ($terms as $term)
                        @php($average = $row['term_averages']->get($term->id))
                        <td class="right">{{ $average === null ? '-' : number_format($average, 2, ',', ' ') }}</td>
                    @endforeach
                    <td class="right"><strong>{{ number_format($row['annual_average'], 2, ',', ' ') }}</strong></td>
                    <td class="right"><strong>{{ number_format($row['redeemed_average'], 2, ',', ' ') }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="{{ 5 + $terms->count() }}" class="center">Aucun élève eligible.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p>Arrete la presente liste a {{ $eligibleRows->count() }} élève(s) rachete(s).</p>

    <div class="stamp">
        Ouagadougou, le {{ now()->format('d/m/Y') }}<br>
        {{ $school?->principal_title ?? 'Le Proviseur' }}<br><br><br>
        <strong>{{ $school?->principal_name ?? 'Yamdaogo TINTILA' }}</strong>
    </div>
</body>
</html>
