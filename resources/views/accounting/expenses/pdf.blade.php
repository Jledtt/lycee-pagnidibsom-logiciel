<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Dépenses</title>
    <style>
        @page { margin: 18px 22px; }
        body { margin: 0; color: #000; font-family: "DejaVu Sans", sans-serif; font-size: 8px; }
        table { width: 100%; border-collapse: collapse; }
        .list { table-layout: fixed; }
        .header td { vertical-align: top; }
        .logo { width: 58px; height: 58px; object-fit: contain; }
        .school h1 { margin: 0 0 4px; font-size: 17px; text-transform: uppercase; }
        .school p { margin: 0 0 3px; font-weight: bold; }
        .meta { text-align: right; line-height: 1.45; }
        .title { margin: 18px 0 12px; text-align: center; font-size: 20px; font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .summary { margin-bottom: 10px; }
        .summary td { border: 1px solid #000; padding: 6px 8px; font-weight: bold; }
        .list th, .list td { border: 1px solid #000; padding: 4px 3px; vertical-align: top; overflow-wrap: anywhere; word-wrap: break-word; }
        .list th { background: #f1f1f1; text-align: left; font-size: 9px; text-transform: uppercase; }
        .right { text-align: right; }
        .center { text-align: center; }
        .footer { margin-top: 18px; font-size: 10px; }
    </style>
</head>
<body>
    @php($school = $school ?? $schoolSettings)
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')
    @php($currency = $school?->currency ?? 'FCFA')

    <table class="header">
        <tr>
            <td style="width:78px">
                @include('pdf.partials.logo-with-motto', ['logoPath' => $logoPath])
            </td>
            <td class="school">
                <h1>{{ $school?->school_name ?? 'Lycée Privé Pagnidibsom' }}</h1>
                <p>{{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }}</p>
                <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
            </td>
            <td class="meta" style="width:150px">
                <strong>Année scolaire : {{ $academicYear?->name ?? '-' }}</strong><br>
                Période : {{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d/m/Y') }}
                au {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d/m/Y') }}<br>
                Date edition : {{ now()->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>

    <div class="title">État des dépenses</div>

    <table class="summary">
        <tr>
            <td>Total dépenses : {{ number_format($summary['total_valid'], 0, ',', ' ') }} {{ $currency }}</td>
            <td>Dépenses validées : {{ $summary['valid_count'] }}</td>
            <td>Annulations : {{ $summary['cancelled_count'] }}</td>
            <td>Montant annule : {{ number_format($summary['total_cancelled'], 0, ',', ' ') }} {{ $currency }}</td>
        </tr>
    </table>

    <table class="list">
        <thead>
            <tr>
                <th style="width:58px">Date</th>
                <th style="width:75px">Catégorie</th>
                <th>Beneficiaire</th>
                <th style="width:58px">Mode</th>
                <th style="width:78px">Justificatif</th>
                <th style="width:72px">Saisie par</th>
                <th style="width:70px" class="right">Montant</th>
                <th style="width:48px">Statut</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($expenses as $expense)
                <tr>
                    <td>{{ $expense->spent_at?->format('d/m/Y') }}</td>
                    <td>{{ $categoryLabels[$expense->category] ?? $expense->category }}</td>
                    <td>{{ $expense->beneficiary ?: '-' }}</td>
                    <td>{{ $methodLabels[$expense->payment_method] ?? $expense->payment_method }}</td>
                    <td>{{ $expense->proof_reference ?: '-' }}</td>
                    <td>{{ $expense->creator?->name ?? '-' }}</td>
                    <td class="right">{{ number_format((float) $expense->amount, 0, ',', ' ') }} {{ $currency }}</td>
                    <td>{{ $expense->status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="center">Aucune dépense pour ces filtres.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="footer">
        <tr>
            <td>Signature du caissier</td>
            <td style="text-align:center">Contrôle direction</td>
            <td style="text-align:right">{{ $school?->principal_title ?? 'Le Proviseur' }}</td>
        </tr>
    </table>
</body>
</html>
