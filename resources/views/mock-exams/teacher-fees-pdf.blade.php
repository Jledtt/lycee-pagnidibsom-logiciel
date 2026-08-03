<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $exam->name }}</title>
    <style>
        @page { margin: 16px 20px 26px; }
    </style>
    @include('pdf.partials.standard-styles')
    <style>
        body { font-size: 6.5px; }
        .fees { margin-top: 7px; }
        .fees { table-layout: fixed; }
        .fees th,
        .fees td { padding: 2px; overflow-wrap: anywhere; word-wrap: break-word; }
        .fees tfoot td { background: #eeeeee; font-weight: bold; }
        .amount-words { margin-top: 7px; font-size: 8px; }
        .page-footer {
            position: fixed;
            right: 0;
            bottom: -17px;
            left: 0;
            color: #555;
            font-size: 7px;
            text-align: center;
        }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
    @php($currency = $school?->currency ?? 'FCFA')
    @php($classNames = $exam->classes->pluck('name')->join(', '))

    @include('pdf.partials.school-header', [
        'school' => $school,
        'logoSize' => 54,
        'marginBottom' => 3,
        'rightLines' => [
            'Année '.$exam->academicYear?->name,
            'Session '.$exam->name,
            $exam->exam_type_label,
        ],
        'rightWidth' => 190,
        'rightSize' => 8,
        'schoolNameSize' => 14,
        'schoolInfoSize' => 7,
    ])

    <div class="document-title">Bordereau des honoraires</div>
    <div class="document-subtitle">
        Classes concernées : {{ $classNames ?: '-' }}
    </div>

    <table class="data-grid fees">
        <thead>
            <tr>
                <th class="center" style="width:3%">N°</th>
                <th style="width:12%">Bénéficiaire</th>
                <th style="width:10%">Identité</th>
                <th style="width:14%">Discipline / activité</th>
                <th class="center" style="width:7%">Quantité</th>
                <th class="right" style="width:7%">Taux</th>
                <th class="right" style="width:8%">Brut</th>
                <th class="right" style="width:7%">Retenue</th>
                <th class="right" style="width:7%">Avance</th>
                <th class="right" style="width:7%">Autre</th>
                <th class="right" style="width:8%">Net</th>
                <th style="width:10%">Signature</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($feeRows as $row)
                @php($subject = $row['subject'])
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td><strong>{{ $subject->correction_teacher_name ?: '-' }}</strong></td>
                    <td>
                        {{ $subject->beneficiary_identity_type ?: '-' }}
                        @if ($subject->beneficiary_identity_number)
                            <br>{{ $subject->beneficiary_identity_number }}
                        @endif
                    </td>
                    <td>
                        <strong>{{ $subject->subject?->name ?? '-' }}</strong><br>
                        <span class="muted">{{ $subject->exam_part_label }}</span>
                    </td>
                    <td class="center">
                        {{ number_format($row['quantity'], 2, ',', ' ') }}
                        {{ $subject->fee_quantity_unit ?: 'copies' }}
                    </td>
                    <td class="right">{{ number_format((float) ($subject->fee_rate ?? 0), 0, ',', ' ') }}</td>
                    <td class="right">{{ number_format($row['gross'], 0, ',', ' ') }}</td>
                    <td class="right">{{ number_format((float) ($subject->fee_withholding_amount ?? 0), 0, ',', ' ') }}</td>
                    <td class="right">{{ number_format((float) ($subject->fee_advance_amount ?? 0), 0, ',', ' ') }}</td>
                    <td class="right">{{ number_format((float) ($subject->fee_other_deduction_amount ?? 0), 0, ',', ' ') }}</td>
                    <td class="right strong">{{ number_format($row['net'], 0, ',', ' ') }}</td>
                    <td></td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="center">Aucun honoraire à préparer.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="right">Totaux ({{ $currency }})</td>
                <td class="right">{{ number_format((float) $feeRows->sum('gross'), 0, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $feeRows->sum(fn ($row) => $row['subject']->fee_withholding_amount ?? 0), 0, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $feeRows->sum(fn ($row) => $row['subject']->fee_advance_amount ?? 0), 0, ',', ' ') }}</td>
                <td class="right">{{ number_format((float) $feeRows->sum(fn ($row) => $row['subject']->fee_other_deduction_amount ?? 0), 0, ',', ' ') }}</td>
                <td class="right">{{ number_format($totalNet, 0, ',', ' ') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="amount-words">
        <strong>Net total arrêté à :</strong> {{ $amountInWords }}.
    </div>

    <table class="signature-grid">
        <tr>
            <td>Préparé par</td>
            <td>Contrôle de l’intendance</td>
            <td>Visa de la direction</td>
        </tr>
    </table>

    <div class="page-footer">
        {{ $exam->name }} - bordereau des honoraires - page <span class="page-number"></span>
    </div>
</body>
</html>
