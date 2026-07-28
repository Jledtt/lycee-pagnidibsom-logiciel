<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin - {{ $reportCard->student->full_name }}</title>
    @include('pdf.partials.standard-styles')
    @include('report-cards._bulletin-styles')
</head>
<body>
    @include('report-cards._bulletin', [
        'annualSummary' => $annualSummary,
        'classStats' => $classStats,
        'reportCard' => $reportCard,
        'school' => $school,
        'subjectRows' => $subjectRows,
    ])
</body>
</html>
