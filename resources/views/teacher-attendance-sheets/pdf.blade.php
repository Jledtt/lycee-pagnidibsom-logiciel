<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Fiche d emargement des professeurs</title>
    <style>
        @page { margin: 24px 28px; }
        body {
            margin: 0;
            color: #000;
            font-family: "Times New Roman", "DejaVu Serif", serif;
            font-size: 11px;
        }
        .header { text-align: center; margin-bottom: 12px; line-height: 1.3; }
        .header strong { font-size: 14px; text-transform: uppercase; }
        .meta { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        .meta td { padding: 2px 4px; }
        .sheet { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .sheet th, .sheet td { border: 1px solid #000; padding: 5px 4px; vertical-align: middle; }
        .sheet th { text-align: center; font-weight: bold; }
        .date { width: 74px; text-align: center; }
        .slot { width: 86px; min-height: 28px; }
        .hours { width: 70px; text-align: center; }
        .sign { width: 92px; }
        .section-row td { text-align: center; font-weight: bold; padding: 7px 4px; }
        .footer { margin-top: 10px; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <strong>{{ $school?->school_name ?? 'Lycee Prive Pagnidibsom' }}</strong><br>
        {{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }} -
        Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}
    </div>

    <table class="meta">
        <tr>
            <td><strong>FICHE D'EMARGEMENT DES PROFESSEURS</strong></td>
            <td style="text-align:center">Periode : {{ $start->format('d/m/Y') }} au {{ $end->format('d/m/Y') }}</td>
            <td style="text-align:right">Annee scolaire : {{ $academicYear?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td colspan="3">Professeur : <strong>{{ $teacherName ?: '........................................................' }}</strong></td>
        </tr>
    </table>

    <table class="sheet">
        <thead>
            <tr>
                <th rowspan="2" class="date">Dates</th>
                <th colspan="{{ count($periods) }}">Classes tenues</th>
                <th rowspan="2" class="hours">Cumule des heures</th>
                <th rowspan="2" class="sign">Emargement</th>
            </tr>
            <tr>
                @foreach ($periods as $label)
                    <th class="slot">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr class="section-row">
                <td colspan="{{ count($periods) + 3 }}">DEROULEMENT DES COURS ORDINAIRES SELON L'EMPLOI DU TEMPS</td>
            </tr>
            @foreach ($rows as $row)
                <tr>
                    <td class="date">{{ $row['date']->format('d/m/Y') }}</td>
                    @foreach ($periods as $label)
                        <td class="slot">{{ $row['cells'][$label] ?: '' }}</td>
                    @endforeach
                    <td class="hours">{{ $row['hours'] ?: '' }}</td>
                    <td class="sign"></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Note : a chaque fin de cours, le professeur passe a la vie scolaire pour emarger le nombre d heures effectuees.
    </div>
</body>
</html>
