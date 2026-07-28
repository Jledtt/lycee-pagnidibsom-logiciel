<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletins de classe</title>
    @include('pdf.partials.standard-styles')
    @include('report-cards._bulletin-styles')
</head>
<body>
    @forelse ($items as $item)
        @include('report-cards._bulletin', [
            'annualSummary' => $item['annualSummary'],
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
