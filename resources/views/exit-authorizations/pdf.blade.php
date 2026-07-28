<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Autorisation d’entrée et de sortie</title>
    <style>
        @page { margin: 34px 48px; }
        body {
            margin: 0;
            color: #000;
            font-family: "Times New Roman", "DejaVu Serif", serif;
            font-size: 13px;
            line-height: 1.55;
        }
        .title { text-align: center; font-weight: bold; text-decoration: underline; font-size: 16px; margin: 0 0 26px; }
        .row { margin-bottom: 13px; }
        .label { display: inline-block; width: 230px; }
        .line { display: inline-block; width: 390px; border-bottom: 1px dotted #000; font-weight: bold; padding-left: 4px; }
        .small-line { display: inline-block; width: 78px; border-bottom: 1px dotted #000; text-align: center; font-weight: bold; }
        .signature { margin-top: 42px; text-align: right; }
        .sign-box { display: inline-block; min-width: 230px; text-align: center; }
        .muted { color: #333; }
    </style>
</head>
<body>
    @php($school = $school ?? $schoolSettings ?? null)

    @include('pdf.partials.school-header', [
        'school' => $school,
        'logoSize' => 96,
        'centerSchool' => true,
        'schoolNameSize' => 13,
        'schoolInfoSize' => 12,
        'rightWidth' => 120,
        'marginBottom' => 24,
    ])

    <h1 class="title">AUTORISATION D’ENTRÉE ET DE SORTIE</h1>

    <div class="row"><span class="label">Nom et prénom(s) de l’élève</span><span class="line">{{ $authorization->student?->full_name }}</span></div>
    <div class="row">
        <span class="label">Date de sortie et de retour</span>
        <span class="line">
            {{ $authorization->departure_at?->format('d/m/Y H:i') ?? '-' }}
            @if ($authorization->return_at)
                au {{ $authorization->return_at->format('d/m/Y H:i') }}
            @endif
        </span>
    </div>
    <div class="row"><span class="label">Matière concernée</span><span class="line">{{ $authorization->subject_name ?: '-' }}</span></div>
    <div class="row"><span class="label">Classe de l’élève</span><span class="line">{{ $authorization->schoolClass?->name ?? '-' }}</span></div>
    <div class="row"><span class="label">Indication du lieu</span><span class="line">{{ $authorization->destination ?: '-' }}</span></div>
    <div class="row"><span class="label">Motif de l’absence</span><span class="line">{{ $authorization->reason }}</span></div>
    <div class="row">
        À
        <span class="small-line">{{ $authorization->departure_at?->format('H') ?? '' }}</span>
        heures
        <span class="small-line">{{ $authorization->departure_at?->format('i') ?? '' }}</span>
        mn
    </div>

    @if ($authorization->notes)
        <div class="row muted"><span class="label">Observation</span><span class="line">{{ $authorization->notes }}</span></div>
    @endif

    <div class="signature">
        {{ $school?->city ?? 'Ouagadougou' }}, le {{ $authorization->document_date?->format('d/m/Y') ?? now()->format('d/m/Y') }}<br><br>
        <span class="sign-box">La vie scolaire</span>
    </div>
</body>
</html>
