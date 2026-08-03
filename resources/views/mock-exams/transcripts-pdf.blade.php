<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Relevés de notes - {{ $exam->name }}</title>
    <style>
        @page { margin: 18px 22px; }
    </style>
    @include('pdf.partials.standard-styles')
    <style>
        body { font-size: 9px; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .document-title { margin: 10px 0; font-size: 16px; }
        .identity td { padding: 5px 6px; }
        .scores { margin-top: 8px; table-layout: fixed; }
        .scores th, .scores td { padding: 4px 5px; }
        .scores .subject { width: auto; }
        .scores .number { width: 74px; text-align: center; }
        .total-row td { background: #eeeeee; font-weight: bold; }
        .decision {
            margin-top: 9px;
            border: 1px solid #555;
            padding: 8px;
            font-size: 10px;
        }
        .notice { margin-top: 10px; font-size: 8px; line-height: 1.45; }
        .signature { margin-top: 16px; text-align: right; line-height: 1.5; }
        .signature-name { display: block; margin-top: 40px; font-weight: bold; }
    </style>
</head>
<body>
    @foreach ($items as $item)
        @php
            $candidate = $item['candidate'];
            $student = $candidate->student;
            $format = fn ($value) => $value === null ? '-' : number_format((float) $value, 2, ',', ' ');
        @endphp

        <div class="page">
            @include('pdf.partials.school-header', [
                'school' => $school,
                'logoSize' => 56,
                'marginBottom' => 4,
                'rightLines' => [
                    $exam->name,
                    'Session '.$exam->academicYear?->name,
                    $exam->exam_type_label,
                ],
                'rightWidth' => 190,
                'rightSize' => 9,
                'schoolNameSize' => 15,
                'schoolInfoSize' => 8,
            ])

            <div class="document-title">Relevé de notes - Examen blanc</div>

            <table class="identity-grid identity">
                <tr>
                    <td colspan="2"><strong>Établissement d’origine :</strong> {{ $school?->school_name ?? 'Lycée Privé Pagnidibsom' }}</td>
                    <td><strong>PV :</strong> {{ $item['pv_number'] }}</td>
                </tr>
                <tr>
                    <td colspan="2"><strong>Nom et prénom(s) :</strong> {{ $student?->full_name }}</td>
                    <td><strong>Classe :</strong> {{ $candidate->schoolClass?->name }}</td>
                </tr>
                <tr>
                    <td><strong>Né(e) le :</strong> {{ $student?->birth_date?->format('d/m/Y') ?? '-' }}</td>
                    <td><strong>À :</strong> {{ $student?->birth_place ?: '-' }}</td>
                    <td><strong>Anonymat :</strong> {{ $candidate->anonymous_code ?: '-' }}</td>
                </tr>
            </table>

            <table class="data-grid scores">
                <thead>
                    <tr>
                        <th class="subject">Épreuves du premier tour</th>
                        <th class="number">Note / 20</th>
                        <th class="number">Coefficient</th>
                        <th class="number">Points</th>
                        <th style="width:115px">Observation</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($exam->subjects->sortBy('position') as $subject)
                        @php
                            $score = $item['scores']->get($subject->id);
                            $normalized = (! $score || $score->is_absent || $score->score === null)
                                ? null
                                : ((float) $score->score / (float) $subject->max_score) * 20;
                            $points = $normalized === null ? null : $normalized * (float) $subject->coefficient;
                        @endphp
                        <tr>
                            <td><strong>{{ $subject->subject?->name }}</strong></td>
                            <td class="center">{{ $score?->is_absent ? 'ABS' : $format($normalized) }}</td>
                            <td class="center">{{ $format($subject->coefficient) }}</td>
                            <td class="center">{{ $format($points) }}</td>
                            <td>{{ $score?->observation }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td>Total du premier tour</td>
                        <td></td>
                        <td class="center">{{ $format($item['used_coefficients']) }}</td>
                        <td class="center">{{ $format($item['weighted_total']) }}</td>
                        <td></td>
                    </tr>
                    <tr class="total-row">
                        <td>Moyenne du premier tour</td>
                        <td class="center">{{ $format($item['average']) }} / 20</td>
                        <td colspan="2" class="center">Rang : {{ $item['rank'] ?? '-' }} / {{ $items->count() > 1 ? $items->count() : $exam->candidates->count() }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            <div class="decision">
                <strong>Décision du jury :</strong> {{ $item['decision'] }}
                @if ($candidate->jury_observation)
                    <br><strong>Observation :</strong> {{ $candidate->jury_observation }}
                @endif
            </div>

            <div class="notice">
                Le présent relevé est un document interne d’examen blanc. Il ne constitue ni une attestation de niveau ni un diplôme.
            </div>

            <div class="signature">
                {{ $school?->city ?: 'Ouagadougou' }}, le {{ now()->format('d/m/Y') }}<br>
                Le Président du jury
                <span class="signature-name">{{ $school?->principal_name ?: 'Yamdaogo TINTILA' }}</span>
            </div>
        </div>
    @endforeach
</body>
</html>
