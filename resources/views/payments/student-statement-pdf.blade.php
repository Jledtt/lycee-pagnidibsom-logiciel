<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Situation financière - {{ $student->full_name }}</title>
    <style>
        @page { margin: 18px 22px; }
        body { margin: 0; color: #000; font-family: "DejaVu Sans", sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .logo { width: 58px; height: 58px; object-fit: contain; }
        .school h1 { margin: 0 0 4px; font-size: 17px; text-transform: uppercase; }
        .school p { margin: 0 0 3px; font-weight: bold; }
        .meta { text-align: right; line-height: 1.45; }
        .title { margin: 18px 0 12px; text-align: center; font-size: 18px; font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .summary td, .list th, .list td { border: 1px solid #000; padding: 6px 5px; vertical-align: top; }
        .list th { background: #f1f1f1; text-align: left; font-size: 9px; text-transform: uppercase; }
        .money { text-align: right; white-space: nowrap; }
        .footer { margin-top: 18px; font-size: 10px; }
    </style>
</head>
<body>
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')

    <table class="header">
        <tr>
            <td style="width:78px">@include('pdf.partials.logo-with-motto', ['logoPath' => $logoPath])</td>
            <td class="school">
                <h1>{{ $school?->school_name ?? 'Lycée Privé Pagnidibsom' }}</h1>
                <p>{{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }}</p>
                <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
            </td>
            <td class="meta" style="width:210px">
                <strong>Année scolaire : {{ $academicYear?->name ?? '-' }}</strong><br>
                Classe : {{ $profile['enrollment']?->schoolClass?->name ?? '-' }}<br>
                Date : {{ now()->format('d/m/Y') }}
            </td>
        </tr>
    </table>

    <div class="title">Situation financière de l’élève</div>

    <table class="summary">
        <tr>
            <td>Élève : <strong>{{ $student->full_name }}</strong></td>
            <td>Matricule : <strong>{{ $student->matricule }}</strong></td>
            <td>Classe : <strong>{{ $profile['enrollment']?->schoolClass?->name ?? '-' }}</strong></td>
        </tr>
        <tr>
            <td>Total attendu : <strong>{{ is_null($profile['expected']) ? '-' : number_format($profile['expected'], 0, ',', ' ') . ' FCFA' }}</strong></td>
            <td>Total paye : <strong>{{ number_format($profile['paid'], 0, ',', ' ') }} FCFA</strong></td>
            <td>Reste : <strong>{{ is_null($profile['balance']) ? '-' : number_format($profile['balance'], 0, ',', ' ') . ' FCFA' }}</strong></td>
        </tr>
    </table>

    <p><strong>Detail par tranche</strong></p>
    <table class="list">
        <thead>
            <tr>
                <th>Frais</th>
                <th class="money">Attendu</th>
                <th class="money">Paye</th>
                <th class="money">Reste</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($profile['scheduled_rows'] as $row)
                <tr>
                    <td>{{ $row['schedule']->period ?: 'Sans période' }} - {{ $row['schedule']->feeType?->name ?? '-' }}</td>
                    <td class="money">{{ number_format($row['expected'], 0, ',', ' ') }} FCFA</td>
                    <td class="money">{{ number_format($row['paid'], 0, ',', ' ') }} FCFA</td>
                    <td class="money">{{ number_format($row['remaining'], 0, ',', ' ') }} FCFA</td>
                    <td>{{ $row['status'] === 'paid' ? 'Paye' : ($row['status'] === 'partial' ? 'Partiel' : 'Impayé') }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Aucun tarif configuré.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p><strong>Historique des paiements</strong></p>
    <table class="list">
        <thead>
            <tr>
                <th>Date</th>
                <th>Reçu</th>
                <th>Mode</th>
                <th class="money">Montant</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($profile['payments'] as $payment)
                <tr>
                    <td>{{ $payment->paid_at?->format('d/m/Y') }}</td>
                    <td>{{ $payment->receipt_number }}</td>
                    <td>{{ $payment->payment_method }}</td>
                    <td class="money">{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</td>
                    <td>{{ $payment->status }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Aucun paiement.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="footer">
        <tr>
            <td>Document généré par le logiciel de gestion scolaire.</td>
            <td style="text-align:right">{{ $school?->principal_title ?? 'Le Proviseur' }}</td>
        </tr>
    </table>
</body>
</html>
