@php
    $student = $reportCard->student;
    $schoolClass = $reportCard->schoolClass;
    $term = $reportCard->term;
    $academicYear = $reportCard->academicYear;
    $rows = collect($subjectRows);
    $format = fn ($value) => $value === null ? '-' : number_format((float) $value, 2, ',', ' ');
    $totalCoefficient = $rows->filter(fn ($row) => $row['average'] !== null)->sum('coefficient');
    $totalPoints = $rows->sum(fn ($row) => $row['points'] ?? 0);
    $average = $reportCard->general_average === null ? null : (float) $reportCard->general_average;
    $principalTitle = $school?->principal_title ?? 'Le Proviseur';
    $principalName = $school?->principal_name ?? 'Yamdaogo TINTILA';
    $date = now()->format('d/m/Y');
    $logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png';
    $groups = [
        'Bilan Matières Litteraires' => ['FR', 'ANG', 'ALL', 'HG', 'PHILO'],
        'Bilan Matières Scientifiques' => ['MATH', 'SVT', 'PC'],
        'Bilan Matières Complementaires' => ['EPS', 'ECM', 'TIC', 'ART', 'TECH'],
    ];
    $groupAverage = function ($groupRows) {
        $rated = collect($groupRows)->filter(fn ($row) => $row['average'] !== null);
        $coefficient = $rated->sum('coefficient');

        if ($coefficient <= 0) {
            return null;
        }

        return round($rated->sum(fn ($row) => ((float) $row['average']) * ((float) $row['coefficient'])) / $coefficient, 2);
    };
    $sanctions = [
        'T.H + Felicitations' => $average !== null && $average >= 16,
        'T.H + Encouragements' => $average !== null && $average >= 14 && $average < 16,
        'Tableau d honneur' => $average !== null && $average >= 12 && $average < 14,
        'Avertissement - Conduite' => false,
        'Avertissement - Travail' => $average !== null && $average < 10,
        'Blame - Conduite' => false,
        'Blame - Travail' => $average !== null && $average < 8,
    ];
@endphp

<table class="bulletin-header">
    <tr>
        <td class="logo-cell">
            <img class="logo" src="{{ public_path($logoPath) }}" alt="Logo">
        </td>
        <td class="school-box">
            <h1>{{ $school?->school_name ?? 'Lycée Privé Pagnidibsom' }}</h1>
            <div>{{ $school?->address ?? '04 BP 8825 OUAGA 04' }}</div>
            <div>{{ $school?->authorization ?? 'Enseignement technique et general' }}</div>
            <div>Tel. {{ $school?->phone ?? '00226 72 81 61 59 / 78 42 62 06' }}</div>
        </td>
        <td class="year-cell">
            <strong>Année Scolaire</strong><br>
            {{ $academicYear?->name ?? '-' }}
        </td>
    </tr>
</table>

<table class="identity">
    <tr>
        <td><strong>Classe:</strong> {{ $schoolClass->name }}</td>
        <td><strong>Effectif:</strong> {{ $reportCard->class_size ?: '-' }}</td>
        <td><strong>Matricule:</strong> {{ $student->matricule ?: '-' }}</td>
    </tr>
    <tr>
        <td colspan="2"><strong>Nom et prénom(s):</strong> {{ $student->last_name }} {{ $student->first_name }}</td>
        <td><strong>Statut:</strong> {{ $student->status === 'active' ? 'Actif' : ucfirst($student->status) }}</td>
    </tr>
    <tr>
        <td><strong>Ne(e) le:</strong> {{ $student->birth_date?->format('d/m/Y') ?? '-' }}</td>
        <td><strong>A:</strong> {{ $student->birth_place ?: '-' }}</td>
        <td><strong>Classes redoublees:</strong> {{ $student->repeated_class ?: '-' }}</td>
    </tr>
</table>

<div class="bulletin-title">Bulletin {{ $term?->name ? 'du ' . $term->name : 'de notes' }}</div>

<table class="marks">
    <thead>
        <tr>
            <th class="discipline">Disciplines</th>
            <th>Moy.<br>Devoir</th>
            <th>Moy.<br>Compo</th>
            <th>Moy.<br>Gen.</th>
            <th>Coeff</th>
            <th>Pond.</th>
            <th>Appreciation</th>
            <th>Professeur</th>
            <th>Signature</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($groups as $groupName => $codes)
            @php($groupRows = $rows->filter(fn ($row) => in_array($row['subject']->code, $codes, true)))
            @continue($groupRows->isEmpty())

            @foreach ($groupRows as $row)
                <tr>
                    <td class="discipline">{{ $row['subject']->name }}</td>
                    <td class="center">{{ $format($row['devoir_average']) }}</td>
                    <td class="center">{{ $format($row['composition_average']) }}</td>
                    <td class="center strong">{{ $format($row['average']) }}</td>
                    <td class="center">{{ $format($row['coefficient']) }}</td>
                    <td class="center strong">{{ $format($row['points']) }}</td>
                    <td>{{ $row['appreciation'] }}</td>
                    <td>{{ $row['teacher'] }}</td>
                    <td></td>
                </tr>
            @endforeach

            <tr class="group-row">
                <td colspan="3">{{ $groupName }}</td>
                <td colspan="6" class="center">{{ $format($groupAverage($groupRows)) }}/20</td>
            </tr>
        @endforeach

        <tr class="total-row">
            <td colspan="4">Totaux</td>
            <td class="center">{{ $format($totalCoefficient) }}</td>
            <td class="center">{{ $format($totalPoints) }}</td>
            <td colspan="3"></td>
        </tr>
        <tr>
            <td colspan="2"><strong>Absence:</strong> -</td>
            <td colspan="3"></td>
            <td colspan="2"><strong>Conduite:</strong> Bonne</td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td colspan="5" class="right strong">Moyenne trimestrielle :</td>
            <td class="center strong">{{ $format($reportCard->general_average) }}</td>
            <td class="center strong">Rang:</td>
            <td colspan="2">{{ $reportCard->rank ? $reportCard->rank . ' / ' . $reportCard->class_size : '-' }} - {{ $reportCard->appreciation ?: '-' }}</td>
        </tr>
        <tr>
            <td colspan="5" class="right strong">Moyenne de la classe :</td>
            <td class="center">{{ $format($classStats['average'] ?? null) }}</td>
            <td colspan="3"></td>
        </tr>
    </tbody>
</table>

<table class="footer-grid">
    <tr>
        <td class="sanctions" rowspan="2">
            <div class="section-label">SANCTIONS</div>
            @foreach ($sanctions as $label => $checked)
                <div class="sanction-line">
                    <span>{{ $label }}</span>
                    <span class="box">{{ $checked ? 'X' : '' }}</span>
                </div>
            @endforeach
        </td>
        <td class="small-stats">
            <strong>Meilleure moyenne:</strong><br>
            {{ $format($classStats['best']?->general_average ?? null) }}
        </td>
        <td class="small-stats">
            <strong>Moyenne la plus basse:</strong><br>
            {{ $format($classStats['weakest']?->general_average ?? null) }}
        </td>
        <td class="observations">
            <strong>Observations</strong><br>
            {{ $reportCard->principal_observation ?: ($reportCard->decision ?: '-') }}
        </td>
    </tr>
    <tr>
        <td colspan="3" class="signature">
            Ouagadougou, le {{ $date }}<br>
            <strong>{{ $principalTitle }}</strong><br><br><br>
            <strong>{{ $principalName }}</strong>
        </td>
    </tr>
</table>

<div class="bulletin-note">
    Il n’est délivré qu’un seul bulletin. Il appartient au titulaire d’en faire des copies certifiées conformes.<br>
    <strong>"Bâtir l’excellence"</strong>
</div>
