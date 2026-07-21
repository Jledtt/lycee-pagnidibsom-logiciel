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
        .list th, .list td { border: 1px solid #000; padding: 6px; vertical-align: top; }
        .list th { background: #f1f1f1; text-align: left; text-transform: uppercase; font-size: 9px; }
        .center { text-align: center; }
        .right { text-align: right; }
        .sign { height: 50px; }
        .muted { color: #555; font-size: 9px; }
    </style>
</head>
<body>
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')

    <table class="header">
        <tr>
            <td style="width:78px"><img class="logo" src="{{ public_path($logoPath) }}" alt="Logo"></td>
            <td class="school">
                <h1>{{ $school?->school_name ?? 'Lycee Prive Pagnidibsom' }}</h1>
                <p>{{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }}</p>
                <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
            </td>
            <td class="meta" style="width:220px">
                <strong>Annee scolaire : {{ $exam->academicYear?->name }}</strong><br>
                Session : {{ $exam->name }}<br>
                Type : {{ $exam->exam_type_label }}<br>
                Candidats : {{ $exam->candidates->count() }}
            </td>
        </tr>
    </table>

    <div class="title">{{ $title }}</div>

    <p class="muted">
        Ce bordereau permet de preparer le paiement des professeurs correcteurs ou intervenants.
        Les taux et montants peuvent etre completes par la comptabilite avant validation.
    </p>

    <table class="list">
        <thead>
            <tr>
                <th style="width:28px" class="center">No</th>
                <th>Matiere</th>
                <th style="width:64px">Partie</th>
                <th style="width:60px" class="center">Copies</th>
                <th style="width:130px">Professeur</th>
                <th style="width:70px" class="right">Taux</th>
                <th style="width:80px" class="right">Montant</th>
                <th style="width:90px">Signature</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($exam->subjects->sortBy('position') as $subject)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td><strong>{{ $subject->subject?->name }}</strong></td>
                    <td>{{ $subject->exam_part_label }}</td>
                    <td class="center">{{ $subject->scores->where('is_absent', false)->whereNotNull('score')->count() ?: $exam->candidates->count() }}</td>
                    <td></td>
                    <td class="right"></td>
                    <td class="right"></td>
                    <td></td>
                </tr>
            @empty
                <tr><td colspan="8" class="center">Aucune matiere.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="6" class="right">Total a payer</th>
                <th></th>
                <th></th>
            </tr>
        </tfoot>
    </table>

    <table style="margin-top:20px">
        <tr>
            <td class="list sign">Prepare par</td>
            <td style="width:24px"></td>
            <td class="list sign">Controle comptabilite</td>
            <td style="width:24px"></td>
            <td class="list sign">Visa direction</td>
        </tr>
    </table>
</body>
</html>
