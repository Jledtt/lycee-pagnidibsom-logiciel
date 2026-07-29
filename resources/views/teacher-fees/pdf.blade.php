<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $statement->reference }}</title>
    <style>@page { margin: 24px 28px; }</style>
    @include('pdf.partials.standard-styles')
    <style>
        body { font-size: 10px; }
        .identity { margin: 9px 0; font-size: 11px; line-height: 1.7; }
        .fee-table th, .fee-table td { padding: 5px 6px; }
        .summary { width: 54%; margin: 0 0 0 auto; }
        .summary td { padding: 5px 6px; }
        .summary tr:last-child td { background: #e5e5e5; font-size: 12px; font-weight: bold; }
        .amount-words { margin-top: 13px; font-size: 11px; }
        .signatures { width: 100%; margin-top: 28px; text-align: center; }
        .signatures td { width: 33.33%; height: 70px; vertical-align: top; }
        .footer-note { margin-top: 12px; font-size: 8px; color: #555; }
    </style>
</head>
<body>
    @include('pdf.partials.school-header', [
        'school' => $school,
        'logoSize' => 70,
        'marginBottom' => 8,
        'rightLines' => [
            'Année scolaire : '.$statement->academicYear?->name,
            'Période : '.ucfirst($statement->period_month->translatedFormat('F Y')),
            'N° : '.$statement->reference,
        ],
        'rightWidth' => 210,
        'rightSize' => 10,
        'schoolNameSize' => 16,
        'schoolInfoSize' => 9,
    ])

    <div class="document-title">Ordre de paiement des honoraires</div>

    <div class="identity">
        <strong>Professeur :</strong> {{ $statement->beneficiary_name }}<br>
        <strong>Discipline :</strong> {{ $statement->teacher?->teacherProfile?->specialty ?: $groupedLines->pluck('subject')->unique()->join(', ') }}<br>
        <strong>{{ $statement->identity_document_type ?: 'Pièce d’identité' }} :</strong>
        {{ $statement->identity_document_number ?: 'Non renseignée' }}
    </div>

    <table class="data-grid fee-table">
        <thead>
            <tr>
                <th>Classes</th>
                <th>Discipline</th>
                <th class="right" style="width:90px">Nombre d’heures</th>
                <th class="right" style="width:90px">Taux</th>
                <th class="right" style="width:105px">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($groupedLines as $line)
                <tr>
                    <td>{{ $line['class'] }}</td>
                    <td>{{ $line['subject'] }}</td>
                    <td class="right">{{ number_format($line['hours'], 2, ',', ' ') }}</td>
                    <td class="right">{{ number_format($line['rate'], 0, ',', ' ') }}</td>
                    <td class="right">{{ number_format($line['amount'], 0, ',', ' ') }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="4"><strong>Total cours</strong></td>
                <td class="right"><strong>{{ number_format((float) $statement->gross_amount, 0, ',', ' ') }}</strong></td>
            </tr>
        </tbody>
    </table>

    <table class="data-grid summary">
        <tr><td>Retenue à la source ({{ number_format((float) $statement->withholding_tax_rate, 2, ',', ' ') }} %)</td><td class="right">{{ number_format((float) $statement->withholding_tax_amount, 0, ',', ' ') }}</td></tr>
        <tr><td>Avance sur honoraires</td><td class="right">{{ number_format((float) $statement->advance_amount, 0, ',', ' ') }}</td></tr>
        <tr><td>Autre retenue</td><td class="right">{{ number_format((float) $statement->other_deduction_amount, 0, ',', ' ') }}</td></tr>
        <tr><td>Net à payer</td><td class="right">{{ number_format((float) $statement->net_amount, 0, ',', ' ') }} FCFA</td></tr>
    </table>

    <p class="amount-words"><strong>Total en lettres :</strong> {{ $amountInWords }}.</p>

    <table class="signatures">
        <tr>
            <td>Paiement reçu par :<br><strong>{{ $statement->beneficiary_name }}</strong><br><br>Date et signature</td>
            <td>Ouagadougou, le {{ ($statement->paid_at ?? now())->format('d/m/Y') }}<br><br><strong>L’Intendant</strong></td>
            <td><br><br><strong>Visa du proviseur</strong></td>
        </tr>
    </table>

    <p class="footer-note">
        Statut : {{ strtoupper($statement->status) }}
        @if ($statement->payment_method) · Mode : {{ $statement->payment_method }} @endif
        @if ($statement->payment_reference) · Référence : {{ $statement->payment_reference }} @endif
    </p>
</body>
</html>
