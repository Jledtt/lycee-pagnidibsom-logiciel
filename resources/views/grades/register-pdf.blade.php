<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Registre de notes - {{ $assessment->subject->name }}</title>
    <style>
        @page { margin: 16px 18px 26px; }
    </style>
    @include('pdf.partials.standard-styles')
    <style>
        body { font-size: 8px; }
        .register-section { page-break-after: always; }
        .register-section:last-of-type { page-break-after: auto; }
        .register { margin-top: 7px; }
        .register th,
        .register td { padding: 3px; overflow-wrap: anywhere; word-wrap: break-word; }
        .assessment-heading {
            width: 58px;
            text-align: center !important;
        }
        .page-footer {
            position: fixed;
            right: 0;
            bottom: -16px;
            left: 0;
            color: #555;
            font-size: 7px;
            text-align: center;
        }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
    @php($assessmentChunks = $assessments->chunk(5)->values())

    @foreach ($assessmentChunks as $assessmentChunk)
        <section class="register-section">
            @include('pdf.partials.school-header', [
                'school' => $school,
                'logoSize' => 54,
                'marginBottom' => 3,
                'rightLines' => [
                    'Année '.$assessment->academicYear?->name,
                    'Classe '.$assessment->schoolClass->name,
                    $assessment->term->name,
                    $assessment->termPeriod?->name,
                ],
                'rightWidth' => 175,
                'rightSize' => 8,
                'schoolNameSize' => 14,
                'schoolInfoSize' => 7,
            ])

            <div class="document-title">Registre récapitulatif des notes</div>
            <div class="document-subtitle">
                {{ $assessment->subject->name }} - coefficient {{ number_format($coefficient, 2, ',', ' ') }}
                @if ($assessmentChunks->count() > 1)
                    - partie {{ $loop->iteration }} / {{ $assessmentChunks->count() }}
                @endif
            </div>

            <table class="data-grid register">
                <thead>
                    <tr>
                        <th class="center" style="width:24px">N°</th>
                        <th style="width:76px">Matricule</th>
                        <th>Nom et prénom(s)</th>
                        @foreach ($assessmentChunk as $item)
                            <th class="assessment-heading">
                                {{ $item->title }}<br>
                                <span class="muted">/{{ number_format((float) $item->max_score, 0, ',', ' ') }}</span>
                            </th>
                        @endforeach
                        <th class="center" style="width:46px">Moy.<br>/20</th>
                        <th class="center" style="width:38px">Coeff.</th>
                        <th class="center" style="width:48px">Note<br>pondérée</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="center">{{ $loop->iteration }}</td>
                            <td>{{ $row['student']->matricule }}</td>
                            <td class="strong">{{ $row['student']->full_name }}</td>
                            @foreach ($assessmentChunk as $item)
                                @php($grade = $row['grades'][$item->id] ?? null)
                                <td class="center">
                                    @if (! $grade)
                                        -
                                    @elseif (! $grade->isCounted())
                                        {{ match ($grade->resolvedStatus()) {
                                            \App\Models\Grade::STATUS_ABSENT => 'ABS',
                                            \App\Models\Grade::STATUS_DISPENSED => 'DSP',
                                            \App\Models\Grade::STATUS_SICK => 'MAL',
                                            default => '-',
                                        } }}
                                    @else
                                        {{ number_format((float) $grade->score, 2, ',', ' ') }}
                                    @endif
                                </td>
                            @endforeach
                            <td class="center strong">{{ $row['average'] === null ? '-' : number_format($row['average'], 2, ',', ' ') }}</td>
                            <td class="center">{{ number_format($coefficient, 2, ',', ' ') }}</td>
                            <td class="center strong">{{ $row['weighted'] === null ? '-' : number_format($row['weighted'], 2, ',', ' ') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $assessmentChunk->count() + 6 }}" class="center">Aucun élève actif dans cette classe.</td>
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
        </section>
    @endforeach

    <div class="page-footer">
        {{ $assessment->schoolClass->name }} - {{ $assessment->subject->name }} - page <span class="page-number"></span>
    </div>
</body>
</html>
