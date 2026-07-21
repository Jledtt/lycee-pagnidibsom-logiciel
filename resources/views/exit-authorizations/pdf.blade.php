<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Autorisation d entree et de sortie</title>
    <style>
        @page { margin: 50px; }
        body {
            margin: 0;
            color: #000;
            font-family: "Times New Roman", "DejaVu Serif", serif;
            font-size: 14px;
            line-height: 1.75;
        }
        .school { text-align: center; font-weight: bold; font-size: 12px; line-height: 1.35; margin-bottom: 34px; }
        .title { text-align: center; font-weight: bold; text-decoration: underline; font-size: 16px; margin: 0 0 34px; }
        .row { margin-bottom: 16px; }
        .label { display: inline-block; min-width: 230px; }
        .line { display: inline-block; min-width: 360px; border-bottom: 1px dotted #000; font-weight: bold; }
        .small-line { display: inline-block; min-width: 80px; border-bottom: 1px dotted #000; text-align: center; font-weight: bold; }
        .signature { margin-top: 58px; text-align: right; }
        .sign-box { display: inline-block; min-width: 230px; text-align: center; }
        .muted { color: #333; }
    </style>
</head>
<body>
    <div class="school">
        {{ str($school?->school_name ?? 'LYCEE PRIVE PAGNIDIBSOM')->upper() }}<br>
        {{ $school?->address ?? '04 OUAGADOUGOU 04 BP 8825' }}<br>
        Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}
    </div>

    <h1 class="title">AUTORISATION D'ENTREE ET DE SORTIE</h1>

    <div class="row"><span class="label">Nom et prenom(s) de l'eleve</span><span class="line">{{ $authorization->student?->full_name }}</span></div>
    <div class="row">
        <span class="label">Date de sortie et de retour</span>
        <span class="line">
            {{ $authorization->departure_at?->format('d/m/Y H:i') ?? '-' }}
            @if ($authorization->return_at)
                au {{ $authorization->return_at->format('d/m/Y H:i') }}
            @endif
        </span>
    </div>
    <div class="row"><span class="label">Matiere concernee</span><span class="line">{{ $authorization->subject_name ?: '-' }}</span></div>
    <div class="row"><span class="label">Classe de l'eleve</span><span class="line">{{ $authorization->schoolClass?->name ?? '-' }}</span></div>
    <div class="row"><span class="label">Indication du lieu</span><span class="line">{{ $authorization->destination ?: '-' }}</span></div>
    <div class="row"><span class="label">Motif de l'absence</span><span class="line">{{ $authorization->reason }}</span></div>
    <div class="row">
        A
        <span class="small-line">{{ $authorization->departure_at?->format('H') ?? '' }}</span>
        heures
        <span class="small-line">{{ $authorization->departure_at?->format('i') ?? '' }}</span>
        mn
    </div>

    @if ($authorization->notes)
        <div class="row muted"><span class="label">Observation</span><span class="line">{{ $authorization->notes }}</span></div>
    @endif

    <div class="signature">
        Ouagadougou, le {{ $authorization->document_date?->format('d/m/Y') ?? now()->format('d/m/Y') }}<br><br>
        <span class="sign-box">La vie scolaire</span>
    </div>
</body>
</html>
