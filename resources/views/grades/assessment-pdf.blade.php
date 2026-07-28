<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Notes - {{ $assessment->title }}</title>
    <style>
        @page { margin: 18px 22px 28px; }
    </style>
    @include('pdf.partials.standard-styles')
    <style>
        .assessment-meta { margin-bottom: 7px; }
        .assessment-meta td { font-weight: bold; }
        .student-list th,
        .student-list td { padding: 4px; }
        .student-list .student-name { font-weight: bold; }
        .page-footer {
            position: fixed;
            right: 0;
            bottom: -17px;
            left: 0;
            color: #555;
            font-size: 8px;
            text-align: center;
        }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
    @php($statusLabels = $statusLabels ?? \App\Models\Grade::statusLabels())

    @include('pdf.partials.school-header', [
        'school' => $school,
        'logoSize' => 58,
        'marginBottom' => 4,
        'rightLines' => [
            'Année '.$assessment->academicYear?->name,
            'Classe '.$assessment->schoolClass->name,
            $assessment->term->name,
            $assessment->termPeriod?->name,
        ],
        'rightWidth' => 180,
        'rightSize' => 9,
        'schoolNameSize' => 15,
        'schoolInfoSize' => 8,
    ])

    <div class="document-title">Feuille de notes</div>
    <div class="document-subtitle">
        {{ $assessment->subject->name }} - {{ $assessment->title }}
    </div>

    <table class="identity-grid assessment-meta">
        <tr>
            <td>Type : {{ $assessment->assessmentType->name }}</td>
            <td>Note maximale : {{ number_format((float) $assessment->max_score, 0, ',', ' ') }}</td>
            <td>Coefficient : {{ $coefficient === null ? '-' : number_format((float) $coefficient, 2, ',', ' ') }}</td>
            <td>Date : {{ $assessment->assessment_date?->format('d/m/Y') ?? '-' }}</td>
        </tr>
        <tr>
            <td colspan="2">Enseignant : {{ $assessment->teacher?->name ?? '-' }}</td>
            <td>Effectif : {{ $students->count() }}</td>
            <td>Moyenne /20 : {{ $average === null ? '-' : number_format($average, 2, ',', ' ') }}</td>
        </tr>
    </table>

    <table class="summary-grid" style="margin-bottom:7px">
        <tr>
            <td><span class="summary-label">Notes saisies</span>{{ $enteredCount }}</td>
            <td><span class="summary-label">Absents</span>{{ $absentCount }}</td>
            <td><span class="summary-label">Non comptés</span>{{ $excludedCount }}</td>
            <td><span class="summary-label">À compléter</span>{{ max(0, $students->count() - $enteredCount - $absentCount - $excludedCount) }}</td>
        </tr>
    </table>

    <table class="data-grid student-list">
        <thead>
            <tr>
                <th class="center" style="width:28px">N°</th>
                <th style="width:92px">Matricule</th>
                <th>Nom et prénom(s)</th>
                <th style="width:78px">Classe redoublée</th>
                <th class="center" style="width:60px">Note</th>
                <th class="center" style="width:68px">Statut</th>
                <th style="width:125px">Commentaire</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($students as $student)
                @php($grade = $gradesByStudent->get($student->id))
                @php($status = $grade?->resolvedStatus() ?? \App\Models\Grade::STATUS_GRADED)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>{{ $student->matricule }}</td>
                    <td class="student-name">{{ $student->full_name }}</td>
                    <td>{{ $student->repeated_class ?: '-' }}</td>
                    <td class="center">
                        @if ($grade && ! $grade->isCounted())
                            -
                        @elseif ($grade?->score !== null)
                            {{ number_format((float) $grade->score, 2, ',', ' ') }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="center">{{ $grade ? ($statusLabels[$status] ?? $status) : 'À saisir' }}</td>
                    <td>{{ $grade?->comment ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="center">Aucun élève actif dans cette classe.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="signature-grid">
        <tr>
            <td>Signature de l’enseignant</td>
            <td>Contrôle pédagogique</td>
            <td>{{ $school?->principal_title ?? 'Le Proviseur' }}</td>
        </tr>
    </table>

    <div class="page-footer">
        {{ $assessment->schoolClass->name }} - {{ $assessment->subject->name }} - page <span class="page-number"></span>
    </div>
</body>
</html>
