<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Relevés - {{ $period->name }}</title>
    <style>
        @page { margin: 16px 20px; }
    </style>
    @include('pdf.partials.standard-styles')
    <style>
        .page {
            position: relative;
            min-height: 1040px;
            page-break-after: always;
        }
        .page:last-child { page-break-after: auto; }
        .period-title { margin: 10px 0; font-size: 15px; }
        .subject-list { margin-top: 9px; }
        .subject-list th,
        .subject-list td { padding: 5px 6px; font-size: 9.5px; }
        .group-row td {
            background: #e5e5e5;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .total-row td { background: #eeeeee; font-weight: bold; }
        .observations {
            margin-top: 10px;
            border: 1px solid #555;
            min-height: 52px;
            padding: 8px 9px;
            font-size: 10px;
        }
        .page-balanced .identity-grid td { padding: 6px 7px; font-size: 9.5px; }
        .page-balanced .summary-grid td { padding: 7px 6px; font-size: 10px; }
        .page-balanced .signature-grid {
            position: absolute;
            right: 0;
            bottom: 8px;
            left: 0;
            margin-top: 0;
        }
        .page-balanced .signature-grid td {
            height: 72px;
            border-top: 1px solid #777;
            padding-top: 8px;
        }
        .page-dense {
            min-height: auto;
        }
        .page-dense .subject-list th,
        .page-dense .subject-list td {
            padding: 3px 4px;
            font-size: 8px;
        }
        .page-dense .observations {
            min-height: 34px;
            padding: 5px 7px;
        }
    </style>
</head>
<body>
    @php
        $evaluationOrdinal = (int) $period->position === 1
            ? '1re'
            : ((int) $period->position).'e';
        $termOrdinal = match ((int) $term->position) {
            1 => 'premier',
            2 => 'deuxième',
            3 => 'troisième',
            default => $term->name,
        };
    @endphp

    @foreach ($items as $item)
        @php
            $rows = collect($item['subjectRows']);
            $totalCoefficient = $rows->filter(fn ($row) => $row['average'] !== null)->sum('coefficient');
            $totalPoints = $rows->sum(fn ($row) => $row['points'] ?? 0);
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
        @endphp

        <div class="page {{ $rows->count() > 17 ? 'page-dense' : 'page-balanced' }}">
            @include('pdf.partials.school-header', [
                'school' => $school,
                'logoSize' => 58,
                'marginBottom' => 4,
                'rightLines' => [
                    'Année '.$term->academicYear?->name,
                    'Classe '.$schoolClass->name,
                    $term->name,
                    $period->name,
                ],
                'rightWidth' => 180,
                'rightSize' => 9,
                'schoolNameSize' => 15,
                'schoolInfoSize' => 8,
            ])

            <div class="document-title period-title">
                Relevé de notes / {{ $evaluationOrdinal }} évaluation du {{ $termOrdinal }} trimestre
            </div>

            <table class="identity-grid">
                <tr>
                    <td><strong>Matricule :</strong> {{ $item['student']->matricule }}</td>
                    <td colspan="2"><strong>Nom et prénom(s) :</strong> {{ $item['student']->full_name }}</td>
                    <td><strong>Classe :</strong> {{ $schoolClass->name }}</td>
                </tr>
                <tr>
                    <td><strong>Né(e) le :</strong> {{ $item['student']->birth_date?->format('d/m/Y') ?? '-' }}</td>
                    <td><strong>À :</strong> {{ $item['student']->birth_place ?: '-' }}</td>
                    <td><strong>Statut :</strong> {{ $item['student']->status === 'active' ? 'Actif' : ucfirst($item['student']->status) }}</td>
                    <td><strong>Classe redoublée :</strong> {{ $item['student']->repeated_class ?: '-' }}</td>
                </tr>
            </table>

            <table class="data-grid subject-list">
                <thead>
                    <tr>
                        <th>Matière</th>
                        <th class="center" style="width:54px">Moy. /20</th>
                        <th class="center" style="width:48px">Coeff.</th>
                        <th class="center" style="width:58px">Points</th>
                        <th style="width:120px">Appréciation</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groups as $groupName => $codes)
                        @php($groupRows = $rows->filter(fn ($row) => in_array($row['subject']->code, $codes, true)))
                        @continue($groupRows->isEmpty())
                        <tr class="group-row">
                            <td colspan="5">{{ $groupName }}</td>
                        </tr>
                        @foreach ($groupRows as $row)
                            <tr>
                                <td><strong>{{ $row['subject']->name }}</strong></td>
                                <td class="center">{{ $row['average'] === null ? '-' : number_format($row['average'], 2, ',', ' ') }}</td>
                                <td class="center">{{ number_format($row['coefficient'], 2, ',', ' ') }}</td>
                                <td class="center">{{ $row['points'] === null ? '-' : number_format($row['points'], 2, ',', ' ') }}</td>
                                <td>{{ $row['appreciation'] }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                    <tr class="total-row">
                        <td>Total pondéré</td>
                        <td></td>
                        <td class="center">{{ number_format($totalCoefficient, 2, ',', ' ') }}</td>
                        <td class="center">{{ number_format($totalPoints, 2, ',', ' ') }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            <table class="summary-grid" style="margin-top:7px">
                <tr>
                    <td><span class="summary-label">Moyenne</span>{{ $item['average'] === null ? '-' : number_format($item['average'], 2, ',', ' ').' / 20' }}</td>
                    <td><span class="summary-label">Rang</span>{{ $item['rank'] ? $item['rank'].' / '.$item['classSize'] : '-' }}</td>
                    <td><span class="summary-label">Moyenne de classe</span>{{ $classStats['average'] === null ? '-' : number_format($classStats['average'], 2, ',', ' ') }}</td>
                    <td><span class="summary-label">Meilleure moyenne</span>{{ $classStats['best'] === null ? '-' : number_format($classStats['best'], 2, ',', ' ') }}</td>
                    <td><span class="summary-label">Plus faible moyenne</span>{{ $classStats['weakest'] === null ? '-' : number_format($classStats['weakest'], 2, ',', ' ') }}</td>
                </tr>
            </table>

            <div class="observations">
                <strong>Appréciation générale :</strong>
                {{ $item['average'] === null ? 'Résultats incomplets.' : $item['appreciation'] }}
            </div>

            <table class="signature-grid">
                <tr>
                    <td>Signature des parents</td>
                    <td>Responsable pédagogique</td>
                    <td>{{ $school?->principal_title ?? 'Le Proviseur' }}</td>
                </tr>
            </table>
        </div>
    @endforeach
</body>
</html>
