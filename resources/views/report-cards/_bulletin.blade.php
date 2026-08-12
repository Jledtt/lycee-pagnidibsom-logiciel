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
    $principalName = $school?->principal_name ?? '';
    $groups = [
        'Matières littéraires' => ['FR', 'ANG', 'ALL', 'HG', 'PHILO'],
        'Matières scientifiques' => ['MATH', 'SVT', 'PC'],
        'Matières complémentaires' => ['EPS', 'ECM', 'TIC', 'ART', 'TECH'],
    ];
    $knownCodes = collect($groups)->flatten()->all();
    $ungrouped = $rows->reject(fn ($row) => in_array($row['subject']->code, $knownCodes, true));
    if ($ungrouped->isNotEmpty()) {
        $groups['Autres matières'] = $ungrouped->pluck('subject.code')->all();
    }
    $groupAverage = function ($groupRows) {
        $rated = collect($groupRows)->filter(fn ($row) => $row['average'] !== null);
        $coefficient = $rated->sum('coefficient');

        return $coefficient <= 0
            ? null
            : round($rated->sum(fn ($row) => ((float) $row['average']) * ((float) $row['coefficient'])) / $coefficient, 2);
    };
    $sanctions = [
        'Félicitations' => $average !== null && $average >= 16,
        'Encouragements' => $average !== null && $average >= 14 && $average < 16,
        'Tableau d’honneur' => $average !== null && $average >= 12 && $average < 14,
        'Avertissement travail' => $average !== null && $average < 10,
        'Blâme travail' => $average !== null && $average < 8,
    ];
@endphp

@include('pdf.partials.school-header', [
    'school' => $school,
    'logoSize' => 48,
    'marginBottom' => 2,
    'rightLines' => [
        'Année '.$academicYear?->name,
        $term?->name,
        'Classe '.$schoolClass->name,
    ],
    'rightWidth' => 145,
    'rightSize' => 8,
    'schoolNameSize' => 14,
    'schoolInfoSize' => 7,
])

<div class="bulletin-title">Bulletin de notes - {{ $term->name }}</div>

<table class="identity-grid">
    <tr>
        <td colspan="2"><strong>Nom et prénom(s) :</strong> {{ $student->last_name }} {{ $student->first_name }}</td>
        <td><strong>Matricule :</strong> {{ $student->matricule ?: '-' }}</td>
        <td><strong>Effectif :</strong> {{ $reportCard->class_size ?: '-' }}</td>
    </tr>
    <tr>
        <td><strong>Né(e) le :</strong> {{ $student->birth_date?->format('d/m/Y') ?? '-' }}</td>
        <td><strong>À :</strong> {{ $student->birth_place ?: '-' }}</td>
        <td><strong>Statut :</strong> {{ $student->status === 'active' ? 'Actif' : ucfirst($student->status) }}</td>
        <td><strong>Classe redoublée :</strong> {{ $student->repeated_class ?: '-' }}</td>
    </tr>
</table>

<table class="marks">
    <thead>
        <tr>
            <th class="discipline">Disciplines</th>
            <th>Moy.<br>devoirs</th>
            <th>Moy.<br>composition</th>
            <th>Moy.<br>générale</th>
            <th>Coeff.</th>
            <th>Points</th>
            <th>Appréciation</th>
            <th>Professeur</th>
            <th>Visa</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($groups as $groupName => $codes)
            @php($groupRows = $rows->filter(fn ($row) => in_array($row['subject']->code, $codes, true)))
            @continue($groupRows->isEmpty())

            <tr class="group-row">
                <td colspan="7">{{ $groupName }}</td>
                <td colspan="2" class="center">{{ $format($groupAverage($groupRows)) }} / 20</td>
            </tr>
            @foreach ($groupRows as $row)
                <tr>
                    <td>{{ $row['subject']->name }}</td>
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
        @endforeach

        <tr class="total-row">
            <td colspan="4">Totaux</td>
            <td class="center">{{ $format($totalCoefficient) }}</td>
            <td class="center">{{ $format($totalPoints) }}</td>
            <td colspan="3"></td>
        </tr>
        <tr>
            <td colspan="2"><strong>Absences :</strong> -</td>
            <td colspan="2"><strong>Conduite :</strong> Bonne</td>
            <td colspan="2"><strong>Moyenne :</strong> {{ $format($reportCard->general_average) }} / 20</td>
            <td colspan="3"><strong>Rang :</strong> {{ $reportCard->rank_label ? $reportCard->rank_label.' / '.$reportCard->class_size : '-' }}</td>
        </tr>
        <tr>
            <td colspan="3"><strong>Appréciation générale :</strong> {{ $reportCard->appreciation ?: '-' }}</td>
            <td colspan="3"><strong>Moyenne de classe :</strong> {{ $format($classStats['average'] ?? null) }}</td>
            <td colspan="3"><strong>Extrêmes :</strong> {{ $format($classStats['weakest'] ?? null) }} à {{ $format($classStats['best'] ?? null) }}</td>
        </tr>
    </tbody>
</table>

@if ($annualSummary)
    <table class="summary-grid annual-summary">
        <tr>
            @foreach ($annualSummary['term_averages'] as $termAverage)
                <td>
                    <span class="summary-label">{{ $termAverage['name'] }}</span>
                    {{ $format($termAverage['average']) }}
                </td>
            @endforeach
            <td><span class="summary-label">Moyenne annuelle</span>{{ $format($annualSummary['annual_average']) }}</td>
            <td><span class="summary-label">Rang annuel</span>{{ $annualSummary['annual_rank_label'] ? $annualSummary['annual_rank_label'].' / '.$annualSummary['class_size'] : '-' }}</td>
            <td><span class="summary-label">Moyenne annuelle classe</span>{{ $format($annualSummary['annual_class_average']) }}</td>
            <td><span class="summary-label">Décision finale</span>{{ $annualSummary['decision'] }}</td>
        </tr>
    </table>
@endif

<table class="footer-grid">
    <tr>
        <td class="sanctions" rowspan="2">
            <div class="section-label">Distinctions et sanctions</div>
            @foreach ($sanctions as $label => $checked)
                <div class="sanction-line">
                    <span>{{ $label }}</span>
                    <span class="check-box">{{ $checked ? 'X' : '' }}</span>
                </div>
            @endforeach
        </td>
        <td class="observations">
            <strong>Observation du proviseur</strong><br>
            {{ $reportCard->principal_observation ?: ($reportCard->decision ?: '-') }}
        </td>
    </tr>
    <tr>
        <td class="signature">
            Ouagadougou, le {{ now()->format('d/m/Y') }}<br>
            <strong>{{ $principalTitle }}</strong><br><br>
            <strong>{{ $principalName }}</strong>
        </td>
    </tr>
</table>

<div class="bulletin-note">
    Il n’est délivré qu’un seul bulletin. Il appartient au titulaire d’en faire des copies certifiées conformes.
    <strong>« {{ trim($school?->motto ?: 'Bâtir l’excellence', '" ') }} »</strong>
</div>
