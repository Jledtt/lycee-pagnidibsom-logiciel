<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $exam->name }}</title>
    <style>
        @page { margin: 20px 24px; }
    </style>
    @include('pdf.partials.standard-styles')
    <style>
        body { font-size: 10px; }
        .document-title { margin: 16px 0 10px; font-size: 17px; text-decoration: underline; }
        .intro { margin: 0 0 16px; font-size: 10px; line-height: 1.5; }
        .results { table-layout: fixed; }
        .results th, .results td { padding: 5px 6px; overflow-wrap: anywhere; }
        .closing { margin-top: 16px; font-weight: bold; }
        .signature { margin-top: 18px; text-align: right; line-height: 1.5; }
        .signature-name { display: block; margin-top: 44px; font-weight: bold; }
    </style>
</head>
<body>
    @include('pdf.partials.school-header', [
        'school' => $school,
        'logoSize' => 58,
        'marginBottom' => 4,
        'rightLines' => [
            'Année scolaire '.$exam->academicYear?->name,
            $exam->name,
            $exam->exam_type_label,
        ],
        'rightWidth' => 200,
        'rightSize' => 9,
        'schoolNameSize' => 15,
        'schoolInfoSize' => 8,
    ])

    <div class="document-title">{{ $title }}</div>

    <p class="intro">
        Après délibération, les candidats ci-dessous sont déclarés <strong>{{ $description }}</strong>.<br>
        Session : <strong>{{ $exam->name }}</strong>.
    </p>

    <table class="data-grid results">
        <thead>
            <tr>
                <th style="width:38px" class="center">N°</th>
                <th style="width:56px" class="center">PV</th>
                <th>Nom et prénom(s)</th>
                <th style="width:90px">Classe</th>
                <th style="width:76px" class="center">Moyenne / 20</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($results as $row)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td class="center">{{ $row['pv_number'] }}</td>
                    <td><strong>{{ $row['candidate']->student?->full_name }}</strong></td>
                    <td>{{ $row['candidate']->schoolClass?->name }}</td>
                    <td class="center">{{ $row['average'] === null ? '-' : number_format($row['average'], 2, ',', ' ') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="center">Aucun candidat dans cette catégorie.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="closing">
        Arrêtée la présente liste à {{ $results->count() }} candidat(s).
    </div>

    <div class="signature">
        {{ $school?->city ?: 'Ouagadougou' }}, le {{ now()->format('d/m/Y') }}<br>
        Le Président du jury
        <span class="signature-name">{{ $school?->principal_name ?: 'Yamdaogo TINTILA' }}</span>
    </div>
</body>
</html>
