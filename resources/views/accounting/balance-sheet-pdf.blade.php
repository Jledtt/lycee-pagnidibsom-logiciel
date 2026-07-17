<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bilan de caisse</title>
    <style>
        @page { margin: 18px 22px; }
        body { margin: 0; color: #000; font-family: "DejaVu Sans", sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .logo { width: 58px; height: 58px; object-fit: contain; }
        .school h1 { margin: 0 0 4px; font-size: 17px; text-transform: uppercase; }
        .school p { margin: 0 0 3px; font-weight: bold; }
        .meta { text-align: right; line-height: 1.45; }
        .title { margin: 18px 0 12px; text-align: center; font-size: 20px; font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .summary { margin-bottom: 12px; }
        .summary td { border: 1px solid #000; padding: 9px 10px; font-weight: bold; font-size: 13px; }
        .section-title { margin: 14px 0 7px; font-size: 13px; font-weight: bold; text-transform: uppercase; }
        .list th, .list td { border: 1px solid #000; padding: 6px 5px; vertical-align: top; }
        .list th { background: #f1f1f1; text-align: left; font-size: 10px; text-transform: uppercase; }
        .right { text-align: right; }
        .footer { margin-top: 22px; font-size: 10px; }
    </style>
</head>
<body>
    @php($school = $school ?? $schoolSettings)
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')
    @php($currency = $school?->currency ?? 'FCFA')

    <table class="header">
        <tr>
            <td style="width:78px">
                <img class="logo" src="{{ public_path($logoPath) }}" alt="Logo">
            </td>
            <td class="school">
                <h1>{{ $school?->school_name ?? 'Lycee Prive Pagnidibsom' }}</h1>
                <p>{{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }}</p>
                <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
            </td>
            <td class="meta" style="width:230px">
                <strong>Annee scolaire : {{ $academicYear?->name ?? '-' }}</strong><br>
                Periode : {{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d/m/Y') }}
                au {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d/m/Y') }}<br>
                Date edition : {{ now()->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>

    <div class="title">Bilan de caisse</div>

    <table class="summary">
        <tr>
            <td>Entrees : {{ number_format($summary['income'], 0, ',', ' ') }} {{ $currency }}</td>
            <td>Depenses : {{ number_format($summary['expenses'], 0, ',', ' ') }} {{ $currency }}</td>
            <td>Solde net : {{ number_format($summary['balance'], 0, ',', ' ') }} {{ $currency }}</td>
        </tr>
        <tr>
            <td>Paiements valides : {{ $summary['payment_count'] }}</td>
            <td>Depenses valides : {{ $summary['expense_count'] }}</td>
            <td>Etat : {{ $summary['balance'] >= 0 ? 'Positif' : 'Negatif' }}</td>
        </tr>
    </table>

    <div class="section-title">Entrees par mode de paiement</div>
    <table class="list">
        <thead>
            <tr>
                <th>Mode</th>
                <th class="right">Montant</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($paymentSummary['by_method'] as $method => $amount)
                <tr>
                    <td>{{ $methodLabels[$method] ?? $method }}</td>
                    <td class="right">{{ number_format($amount, 0, ',', ' ') }} {{ $currency }}</td>
                </tr>
            @empty
                <tr>
                    <td>Aucune entree valide</td>
                    <td class="right">0 {{ $currency }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Depenses par categorie</div>
    <table class="list">
        <thead>
            <tr>
                <th>Categorie</th>
                <th class="right">Montant</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($expenseSummary['by_category'] as $category => $amount)
                <tr>
                    <td>{{ $categoryLabels[$category] ?? $category }}</td>
                    <td class="right">{{ number_format($amount, 0, ',', ' ') }} {{ $currency }}</td>
                </tr>
            @empty
                <tr>
                    <td>Aucune depense valide</td>
                    <td class="right">0 {{ $currency }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="footer">
        <tr>
            <td>Signature du caissier</td>
            <td style="text-align:center">Controle direction</td>
            <td style="text-align:right">{{ $school?->principal_title ?? 'Le Proviseur' }}</td>
        </tr>
    </table>
</body>
</html>
