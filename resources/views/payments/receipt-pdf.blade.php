<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Recu {{ $payment->receipt_number }}</title>
    <style>
        @page { margin: 18px; }
        body {
            margin: 0;
            color: #000;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 12px;
        }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .logo { width: 58px; height: 58px; object-fit: contain; }
        .school h1 { margin: 0 0 4px; font-size: 18px; text-transform: uppercase; }
        .school p { margin: 0 0 3px; font-weight: bold; }
        .title { margin: 18px 0 12px; text-align: center; font-size: 22px; font-weight: bold; text-decoration: underline; }
        .box { border: 1.2px solid #000; margin-bottom: 10px; }
        .box td, .lines th, .lines td { border: 1.2px solid #000; padding: 7px; }
        .lines th { text-align: left; background: #f2f2f2; }
        .right { text-align: right; }
        .total { font-size: 16px; font-weight: bold; }
        .footer { margin-top: 18px; }
    </style>
</head>
<body>
    @php($school = $school ?? $schoolSettings)
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')
    <table class="header">
        <tr>
            <td style="width:80px">
                <img class="logo" src="{{ public_path($logoPath) }}" alt="Logo">
            </td>
            <td class="school">
                <h1>{{ $school?->school_name ?? 'Lycee Prive Pagnidibsom' }}</h1>
                <p>{{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }}</p>
                <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
            </td>
            <td style="width:160px; text-align:right">
                <strong>No {{ $payment->receipt_number }}</strong><br>
                {{ $payment->paid_at?->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>

    <div class="title">RECU DE PAIEMENT</div>

    <table class="box">
        <tr>
            <td><strong>Eleve :</strong> {{ $payment->student->full_name }}</td>
            <td><strong>Matricule :</strong> {{ $payment->student->matricule }}</td>
        </tr>
        <tr>
            <td><strong>Classe :</strong> {{ $payment->enrollment?->schoolClass?->name ?? '-' }}</td>
            <td><strong>Annee :</strong> {{ $payment->academicYear?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Mode :</strong> {{ $payment->payment_method }}</td>
            <td><strong>Caissier :</strong> {{ $payment->receiver?->name ?? '-' }}</td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Designation</th>
                <th class="right">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($payment->lines as $line)
                <tr>
                    <td>
                        {{ $line->feeSchedule?->period ?: ($line->feeType?->name ?? '-') }}
                        @if ($line->feeSchedule?->period && $line->feeType?->name)
                            - {{ $line->feeType->name }}
                        @endif
                    </td>
                    <td class="right">{{ number_format($line->amount, 0, ',', ' ') }} {{ $school?->currency ?? 'FCFA' }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="total">Total paye</td>
                <td class="right total">{{ number_format($payment->amount, 0, ',', ' ') }} {{ $school?->currency ?? 'FCFA' }}</td>
            </tr>
            <tr>
                <td>Total deja paye par l'eleve</td>
                <td class="right">{{ number_format($summary['paid'], 0, ',', ' ') }} {{ $school?->currency ?? 'FCFA' }}</td>
            </tr>
            <tr>
                <td>Reste a payer</td>
                <td class="right">{{ is_null($summary['balance']) ? 'Frais a configurer' : number_format($summary['balance'], 0, ',', ' ') . ' ' . ($school?->currency ?? 'FCFA') }}</td>
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
