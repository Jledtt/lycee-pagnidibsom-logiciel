<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Pièces manquantes</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111b15; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #cfd8d1; padding: 6px; vertical-align: top; overflow-wrap: anywhere; word-wrap: break-word; }
        th { background: #eef4f0; text-align: left; text-transform: uppercase; font-size: 9px; }
        h1 { text-align: center; font-size: 20px; margin: 14px 0 8px; text-transform: uppercase; }
        .header td { border: 0; }
        .school { font-weight: bold; font-size: 14px; }
        .muted { color: #66736c; }
        .summary td { border: 1px solid #cfd8d1; text-align: center; }
        .summary strong { display: block; font-size: 16px; margin-top: 4px; }
        .badge { display: inline-block; padding: 3px 7px; border-radius: 12px; background: #edf4ef; color: #134c35; font-weight: bold; }
        .warning { background: #f6ebd8; color: #84560f; }
        .footer td { border: 0; padding-top: 22px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <div class="school">{{ $school?->name ?? 'LYCÉE PRIVÉ PAGNIDIBSOM' }}</div>
                <div>{{ $school?->address ?? '04 OUAGADOUGOU 04 BP 8825' }}</div>
                <div>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</div>
                <div>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</div>
            </td>
            <td style="text-align:right">
                <strong>Année scolaire : {{ $academicYear?->name ?? '-' }}</strong><br>
                <span>Classe : {{ $schoolClass?->name ?? 'Toutes les classes' }}</span><br>
                <span>Edite le {{ now()->format('d/m/Y H:i') }}</span>
            </td>
        </tr>
    </table>

    <h1>Rapport des pièces manquantes</h1>

    <table class="summary">
        <tr>
            <td>Élèves suivis<strong>{{ $summary['students'] }}</strong></td>
            <td>Dossiers complets<strong>{{ $summary['complete'] }}</strong></td>
            <td>Dossiers incomplets<strong>{{ $summary['incomplete'] }}</strong></td>
            <td>Pièces manquantes<strong>{{ $summary['missing_documents'] }}</strong></td>
        </tr>
    </table>

    <p class="muted">
        Base contrôlee : {{ implode(', ', $requiredDocuments) }}.
    </p>

    <table>
        <thead>
            <tr>
                <th style="width:18%">Matricule</th>
                <th style="width:24%">Élève</th>
                <th style="width:12%">Classe</th>
                <th style="width:14%">Statut</th>
                <th>Pièces manquantes</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['student']?->matricule }}</td>
                    <td><strong>{{ $row['student']?->full_name }}</strong></td>
                    <td>{{ $row['class']?->name ?? '-' }}</td>
                    <td>
                        <span class="badge {{ $row['is_complete'] ? '' : 'warning' }}">
                            {{ $row['is_complete'] ? 'Complet' : 'Incomplet' }}
                        </span>
                    </td>
                    <td>
                        {{ $row['is_complete'] ? 'Aucune' : collect($row['missing_documents'])->pluck('label')->implode(', ') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Aucun élève ne correspond à cette sélection.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="footer">
        <tr>
            <td>Service secretariat</td>
            <td style="text-align:right">Signature</td>
        </tr>
    </table>
</body>
</html>
