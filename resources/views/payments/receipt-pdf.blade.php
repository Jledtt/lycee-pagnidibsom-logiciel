<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $payment->receipt_number }}</title>
    <style>
        @page { margin: 12px 16px; }
    </style>
    @include('pdf.partials.standard-styles')
    <style>
        body { font-size: {{ $receiptLines->count() > 8 ? '7.5px' : '8.5px' }}; }
        .document-title { margin: 3px 0 5px; font-size: 15px; }
        .identity-grid td { padding: 3px 5px; }
        .receipt-lines { margin-top: 5px; }
        .receipt-lines th,
        .receipt-lines td { padding: {{ $receiptLines->count() > 8 ? '2px 4px' : '3px 5px' }}; }
        .receipt-total td { background: #eeeeee; font-size: 10px; font-weight: bold; }
        .summary-grid { margin-top: 5px; }
        .summary-grid td { padding: 4px 5px; }
        .amount-words { margin-top: 5px; font-size: 8px; }
        .signature-grid { margin-top: 6px; }
        .signature-grid td { height: 52px; }
    </style>
</head>
<body>
    @php($currency = $school?->currency ?? 'FCFA')

    @include('pdf.partials.school-header', [
        'school' => $school,
        'logoSize' => 46,
        'marginBottom' => 2,
        'rightLines' => [
            'N° '.$payment->receipt_number,
            $payment->paid_at?->format('d/m/Y à H:i'),
            'Année '.$payment->academicYear?->name,
        ],
        'rightWidth' => 112,
        'rightSize' => 8,
        'schoolNameSize' => 13,
        'schoolInfoSize' => 7,
    ])

    <div class="document-title">Reçu de paiement</div>

    <table class="identity-grid">
        <tr>
            <td><strong>Élève :</strong> {{ $payment->student->full_name }}</td>
            <td><strong>Matricule :</strong> {{ $payment->student->matricule }}</td>
            <td><strong>Classe :</strong> {{ $payment->enrollment?->schoolClass?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Mode :</strong> {{ $methodLabels[$payment->payment_method] ?? $payment->payment_method }}</td>
            <td><strong>Caissier :</strong> {{ $payment->receiver?->name ?? '-' }}</td>
            <td><strong>Statut :</strong> {{ $payment->status === 'valid' ? 'Valide' : ucfirst($payment->status) }}</td>
        </tr>
    </table>

    <table class="data-grid receipt-lines">
        <thead>
            <tr>
                <th>Désignation du frais payé</th>
                <th class="right" style="width:100px">Montant payé</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($receiptLines as $line)
                <tr>
                    <td>{{ $line['designation'] }}</td>
                    <td class="right">{{ number_format($line['amount'], 0, ',', ' ') }} {{ $currency }}</td>
                </tr>
            @empty
                <tr>
                    <td>Aucun détail disponible</td>
                    <td class="right">{{ number_format((float) $payment->amount, 0, ',', ' ') }} {{ $currency }}</td>
                </tr>
            @endforelse
            <tr class="receipt-total">
                <td>Total de ce reçu</td>
                <td class="right">{{ number_format((float) $payment->amount, 0, ',', ' ') }} {{ $currency }}</td>
            </tr>
        </tbody>
    </table>

    <table class="summary-grid">
        <tr>
            <td>
                <span class="summary-label">Total attendu</span>
                {{ is_null($summary['expected']) ? 'À configurer' : number_format($summary['expected'], 0, ',', ' ').' '.$currency }}
            </td>
            <td>
                <span class="summary-label">Total déjà payé</span>
                {{ number_format($summary['paid'], 0, ',', ' ') }} {{ $currency }}
            </td>
            <td>
                <span class="summary-label">Reste global</span>
                {{ is_null($summary['balance']) ? 'À configurer' : number_format($summary['balance'], 0, ',', ' ').' '.$currency }}
            </td>
        </tr>
    </table>

    <div class="amount-words">
        <strong>Arrêté le présent reçu à la somme de :</strong> {{ $amountInWords }}.
    </div>

    <table class="signature-grid">
        <tr>
            <td>Signature du parent</td>
            <td>Signature et cachet de la caisse</td>
        </tr>
    </table>
</body>
</html>
