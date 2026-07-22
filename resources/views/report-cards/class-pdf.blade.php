<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletins de classe</title>
    <style>
        @page { margin: 16px 20px; }
        body { margin: 0; color: #111; font-family: "DejaVu Serif", serif; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        .bulletin-header td { vertical-align: middle; }
        .logo-cell { width: 120px; text-align: center; }
        .logo { width: 95px; height: 95px; object-fit: contain; }
        .school-box { border: 1.4px solid #111; text-align: center; padding: 8px 10px; background: #f6d4c1; line-height: 1.25; }
        .school-box h1 { margin: 0 0 4px; font-size: 19px; font-family: "DejaVu Sans", sans-serif; text-transform: uppercase; letter-spacing: 1px; }
        .year-cell { width: 150px; text-align: center; font-size: 11px; }
        .identity { margin-top: 4px; border: 1.2px solid #111; font-size: 10px; }
        .identity td { padding: 3px 6px; }
        .bulletin-title { margin: 5px 0 2px; text-align: center; font-weight: bold; text-decoration: underline; font-size: 16px; }
        .marks th, .marks td { border: 1px solid #111; padding: 3px 4px; vertical-align: middle; }
        .marks th { font-weight: bold; text-align: center; background: #f4f4f4; }
        .discipline { width: 27%; text-align: left !important; }
        .center { text-align: center; }
        .right { text-align: right; }
        .strong { font-weight: bold; }
        .group-row td { background: #e9e9e9; font-weight: bold; font-size: 10px; }
        .total-row td { font-weight: bold; }
        .footer-grid { margin-top: 0; border: 1px solid #111; }
        .footer-grid td { border: 1px solid #111; padding: 5px 6px; vertical-align: top; }
        .sanctions { width: 36%; }
        .section-label { text-align: center; font-weight: bold; text-decoration: underline; margin-bottom: 4px; }
        .sanction-line { clear: both; height: 18px; line-height: 18px; }
        .sanction-line span:first-child { float: left; }
        .box { float: right; display: inline-block; width: 20px; height: 16px; border: 1px solid #111; text-align: center; line-height: 16px; font-weight: bold; }
        .small-stats { width: 17%; text-align: center; }
        .observations { width: 30%; text-align: center; }
        .signature { text-align: center; height: 82px; font-style: italic; }
        .bulletin-note { margin-top: 4px; text-align: center; font-size: 9px; font-style: italic; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    @forelse ($items as $item)
        @include('report-cards._bulletin', [
            'classStats' => $item['classStats'],
            'reportCard' => $item['reportCard'],
            'school' => $school,
            'subjectRows' => $item['subjectRows'],
        ])

        @if (! $loop->last)
            <div class="page-break"></div>
        @endif
    @empty
        <p>Aucun bulletin généré pour cette classe.</p>
    @endforelse
</body>
</html>
