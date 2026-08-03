<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>PV conseil de classe</title>
    <style>
        @page { margin: 24px 28px; }
        body { margin: 0; color: #111; font-family: "DejaVu Sans", sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #cfd8d1; padding: 5px; vertical-align: top; overflow-wrap: anywhere; word-wrap: break-word; }
        th { background: #eef4f0; text-align: left; text-transform: uppercase; font-size: 8px; }
        .header td { border: 0; }
        .logo { width: 56px; height: 56px; object-fit: contain; }
        .school h1 { margin: 0 0 4px; font-size: 16px; text-transform: uppercase; }
        .school p { margin: 0 0 3px; font-weight: bold; }
        .meta { text-align: right; line-height: 1.45; }
        .title { margin: 16px 0 10px; text-align: center; font-size: 18px; font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .summary td { text-align: center; font-weight: bold; }
        .summary strong { display: block; font-size: 16px; margin-top: 3px; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 10px; background: #edf4ef; color: #134c35; font-weight: bold; }
        .warning { background: #f6ebd8; color: #84560f; }
        .footer td { border: 0; padding-top: 28px; height: 64px; }
    </style>
</head>
<body>
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')

    <table class="header">
        <tr>
            <td style="width:72px">@include('pdf.partials.logo-with-motto', ['logoPath' => $logoPath])</td>
            <td class="school">
                <h1>{{ $school?->school_name ?? 'Lycée Privé Pagnidibsom' }}</h1>
                <p>{{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }}</p>
                <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
            </td>
            <td class="meta" style="width:155px">
                <strong>Année scolaire : {{ $academicYear?->name ?? '-' }}</strong><br>
                Classe : {{ $schoolClass->name }}<br>
                Trimestre : {{ $term->name }}<br>
                Verrouillage : {{ $lockSummary['locked'] }} / {{ $lockSummary['total'] }} evaluation(s)
            </td>
        </tr>
    </table>

    <div class="title">Procès-verbal de conseil de classe</div>

    <table class="summary">
        <tr>
            <td>Élèves<strong>{{ $summary['students'] }}</strong></td>
            <td>Moyenne classe<strong>{{ $summary['class_average'] === null ? '-' : number_format($summary['class_average'], 2, ',', ' ') }}</strong></td>
            <td>Meilleure moyenne<strong>{{ $summary['best']?->general_average === null ? '-' : number_format($summary['best']->general_average, 2, ',', ' ') }}</strong></td>
            <td>Plus faible moyenne<strong>{{ $summary['weakest']?->general_average === null ? '-' : number_format($summary['weakest']->general_average, 2, ',', ' ') }}</strong></td>
            <td>Admis<strong>{{ $summary['admitted'] }}</strong></td>
            <td>A deliberer<strong>{{ $summary['deliberation'] }}</strong></td>
        </tr>
    </table>

    <p>
        Premier de la classe :
        <strong>{{ $summary['best']?->student?->full_name ?? '-' }}</strong>
        @if ($summary['best'])
            ({{ number_format($summary['best']->general_average, 2, ',', ' ') }} / 20)
        @endif
    </p>

    <table>
        <thead>
            <tr>
                <th style="width:7%">Rang</th>
                <th style="width:18%">Matricule</th>
                <th>Élève</th>
                <th style="width:12%">Moyenne</th>
                <th style="width:17%">Appreciation</th>
                <th style="width:18%">Décision</th>
                <th style="width:12%">Statut</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reportCards as $reportCard)
                <tr>
                    <td><strong>{{ $reportCard->rank ?: '-' }}</strong></td>
                    <td>{{ $reportCard->student?->matricule }}</td>
                    <td><strong>{{ $reportCard->student?->full_name }}</strong></td>
                    <td>{{ $reportCard->general_average === null ? '-' : number_format($reportCard->general_average, 2, ',', ' ') }}</td>
                    <td>{{ $reportCard->appreciation ?: '-' }}</td>
                    <td>{{ $reportCard->decision ?: '-' }}</td>
                    <td>
                        <span class="badge {{ $reportCard->status === 'draft' ? 'warning' : '' }}">
                            {{ ['draft' => 'Brouillon', 'validated' => 'Valide', 'published' => 'Publie'][$reportCard->status] ?? $reportCard->status }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Aucun bulletin généré.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="footer">
        <tr>
            <td>Le professeur principal</td>
            <td style="text-align:center">Le conseil de classe</td>
            <td style="text-align:right">{{ $school?->principal_title ?? 'Le Proviseur' }}</td>
        </tr>
    </table>
</body>
</html>
