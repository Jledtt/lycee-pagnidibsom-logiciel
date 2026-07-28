<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $payment->receipt_number }}</title>
    <style>
        @page { margin: 16px 18px; }
        body {
            margin: 0;
            color: #000;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10px;
        }
        table { width: 100%; border-collapse: collapse; }
        .title { margin: 8px 0 9px; text-align: center; font-size: 18px; font-weight: bold; text-decoration: underline; }
        .box { border: 1px solid #000; margin-bottom: 8px; }
        .box td { border: 1px solid #000; padding: 5px 6px; }
        .lines th, .lines td { border: 1px solid #000; padding: 5px 6px; }
        .lines th { text-align: left; background: #f2f2f2; font-size: 9px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .total { font-size: 12px; font-weight: bold; }
        .muted { color: #444; font-size: 9px; }
        .footer { margin-top: 12px; font-size: 10px; }
    </style>
</head>
<body>
    @php($school = $school ?? $schoolSettings ?? null)
    @php($currency = $school?->currency ?? 'FCFA')
    @php($paymentBySchedule = $payment->lines->filter(fn ($line) => ! is_null($line->fee_schedule_id))->groupBy('fee_schedule_id')->map(fn ($lines) => (float) $lines->sum('amount')))
    @php($directLines = $payment->lines->filter(fn ($line) => is_null($line->fee_schedule_id))->values())
    @php($scheduledRows = $profile['scheduled_rows'] ?? collect())

    @include('pdf.partials.school-header', [
        'school' => $school,
        'logoSize' => 76,
        'marginBottom' => 6,
        'rightLines' => [
            'N° '.$payment->receipt_number,
            $payment->paid_at?->format('d/m/Y H:i'),
        ],
        'rightWidth' => 155,
    ])

    <div class="title">REÇU DE PAIEMENT</div>

    <table class="box">
        <tr>
            <td><strong>Élève :</strong> {{ $payment->student->full_name }}</td>
            <td><strong>Matricule :</strong> {{ $payment->student->matricule }}</td>
        </tr>
        <tr>
            <td><strong>Classe :</strong> {{ $payment->enrollment?->schoolClass?->name ?? '-' }}</td>
            <td><strong>Année :</strong> {{ $payment->academicYear?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Mode :</strong> {{ $payment->payment_method }}</td>
            <td><strong>Caissier :</strong> {{ $payment->receiver?->name ?? '-' }}</td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Désignation</th>
                <th class="right" style="width:90px">Attendu</th>
                <th class="right" style="width:90px">Payé reçu</th>
                <th class="right" style="width:90px">Déjà payé</th>
                <th class="right" style="width:90px">Reste</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($scheduledRows as $row)
                @php($schedule = $row['schedule'])
                @php($receiptPaid = (float) ($paymentBySchedule[$schedule->id] ?? 0))
                <tr>
                    <td>
                        {{ $schedule->period ?: 'Sans période' }}
                        @if ($schedule->feeType?->name)
                            - {{ $schedule->feeType->name }}
                        @endif
                    </td>
                    <td class="right">{{ number_format($row['expected'], 0, ',', ' ') }} {{ $currency }}</td>
                    <td class="right">{{ $receiptPaid > 0 ? number_format($receiptPaid, 0, ',', ' ').' '.$currency : '-' }}</td>
                    <td class="right">{{ number_format($row['paid'], 0, ',', ' ') }} {{ $currency }}</td>
                    <td class="right">{{ number_format($row['remaining'], 0, ',', ' ') }} {{ $currency }}</td>
                </tr>
            @empty
                @foreach ($payment->lines as $line)
                    <tr>
                        <td>
                            {{ $line->feeSchedule?->period ?: ($line->feeType?->name ?? '-') }}
                            @if ($line->feeSchedule?->period && $line->feeType?->name)
                                - {{ $line->feeType->name }}
                            @endif
                        </td>
                        <td class="right">-</td>
                        <td class="right">{{ number_format($line->amount, 0, ',', ' ') }} {{ $currency }}</td>
                        <td class="right">-</td>
                        <td class="right">-</td>
                    </tr>
                @endforeach
            @endforelse

            @foreach ($directLines as $line)
                <tr>
                    <td>{{ $line->feeType?->name ?? 'Autres frais' }}</td>
                    <td class="right">-</td>
                    <td class="right">{{ number_format($line->amount, 0, ',', ' ') }} {{ $currency }}</td>
                    <td class="right">-</td>
                    <td class="right">-</td>
                </tr>
            @endforeach

            <tr>
                <td class="total">Total payé sur ce reçu</td>
                <td class="right">-</td>
                <td class="right total">{{ number_format($payment->amount, 0, ',', ' ') }} {{ $currency }}</td>
                <td class="right">-</td>
                <td class="right">-</td>
            </tr>
            <tr>
                <td>Total déjà payé par l’élève</td>
                <td class="right">{{ is_null($summary['expected']) ? '-' : number_format($summary['expected'], 0, ',', ' ').' '.$currency }}</td>
                <td class="right">-</td>
                <td class="right">{{ number_format($summary['paid'], 0, ',', ' ') }} {{ $currency }}</td>
                <td class="right">{{ is_null($summary['balance']) ? 'Frais à configurer' : number_format($summary['balance'], 0, ',', ' ').' '.$currency }}</td>
            </tr>
        </tbody>
    </table>

    <table class="footer">
        <tr>
            <td>Signature du parent</td>
            <td class="right">Signature et cachet</td>
        </tr>
    </table>
</body>
</html>
