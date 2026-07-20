<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Notes - {{ $assessment->title }}</title>
    <style>
        @page { margin: 18px 22px; }
        body { margin: 0; color: #000; font-family: "DejaVu Sans", sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .logo { width: 58px; height: 58px; object-fit: contain; }
        .school h1 { margin: 0 0 4px; font-size: 17px; text-transform: uppercase; }
        .school p { margin: 0 0 3px; font-weight: bold; }
        .meta { text-align: right; line-height: 1.45; }
        .title { margin: 18px 0 12px; text-align: center; font-size: 18px; font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .summary { margin-bottom: 10px; }
        .summary td { border: 1px solid #000; padding: 6px 8px; font-weight: bold; }
        .list th, .list td { border: 1px solid #000; padding: 6px 5px; vertical-align: top; }
        .list th { background: #f1f1f1; text-align: left; font-size: 9px; text-transform: uppercase; }
        .center { text-align: center; }
        .footer { margin-top: 18px; font-size: 10px; }
    </style>
</head>
<body>
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')

    <table class="header">
        <tr>
            <td style="width:78px">
                <img class="logo" src="{{ public_path($logoPath) }}" alt="Logo">
            </td>
            <td class="school">
                <h1>{{ $school?->school_name ?? 'Lycee Prive Pagnidibsom' }}</h1>
                <p>{{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }}</p>
                <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
            </td>
            <td class="meta" style="width:220px">
                <strong>Annee scolaire : {{ $assessment->academicYear?->name ?? '-' }}</strong><br>
                Classe : {{ $assessment->schoolClass->name }}<br>
                Trimestre : {{ $assessment->term->name }}<br>
                Periode : {{ $assessment->termPeriod?->name ?? '-' }}<br>
                Date : {{ $assessment->assessment_date?->format('d/m/Y') ?? '-' }}
            </td>
        </tr>
    </table>

    <div class="title">Feuille de notes</div>

    <table class="summary">
        <tr>
            <td>Matiere : {{ $assessment->subject->name }}</td>
            <td>Evaluation : {{ $assessment->title }}</td>
            <td>Type : {{ $assessment->assessmentType->name }}</td>
            <td>Note sur : {{ number_format($assessment->max_score, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>Eleves : {{ $students->count() }}</td>
            <td>Notes saisies : {{ $enteredCount }}</td>
            <td>Absents : {{ $absentCount }}</td>
            <td>Moyenne /20 : {{ $average === null ? '-' : number_format($average, 2, ',', ' ') }}</td>
        </tr>
    </table>

    <table class="list">
        <thead>
            <tr>
                <th style="width:34px" class="center">No</th>
                <th style="width:105px">Matricule</th>
                <th>Eleve</th>
                <th style="width:90px" class="center">Note</th>
                <th style="width:70px" class="center">Absent</th>
                <th>Commentaire</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($students as $student)
                @php($grade = $gradesByStudent->get($student->id))
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>{{ $student->matricule }}</td>
                    <td><strong>{{ $student->full_name }}</strong></td>
                    <td class="center">
                        @if ($grade?->is_absent)
                            -
                        @elseif ($grade?->score !== null)
                            {{ number_format($grade->score, 2, ',', ' ') }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="center">{{ $grade?->is_absent ? 'Oui' : 'Non' }}</td>
                    <td>{{ $grade?->comment ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="center">Aucun eleve actif dans cette classe.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="footer">
        <tr>
            <td>Document genere par le logiciel de gestion scolaire.</td>
            <td style="text-align:right">{{ $school?->principal_title ?? 'Le Proviseur' }}</td>
        </tr>
    </table>
</body>
</html>
