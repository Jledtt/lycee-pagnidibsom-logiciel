<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $typeLabel }}</title>
    <style>
        @page { margin: 38px 46px; }
        body {
            margin: 0;
            color: #000;
            font-family: "DejaVu Serif", serif;
            font-size: 14px;
            line-height: 1.55;
        }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 46px; }
        .header td { vertical-align: top; }
        .logo { width: 82px; height: 82px; object-fit: contain; }
        .school { font-family: "DejaVu Sans", sans-serif; font-weight: bold; }
        .school h1 { margin: 0 0 6px; font-size: 15px; text-transform: uppercase; }
        .school p { margin: 0 0 4px; font-size: 12px; }
        .motto { width: 90px; margin-top: 12px; font-size: 12px; line-height: 1.35; font-style: italic; font-weight: bold; }
        .year { text-align: right; font-family: "DejaVu Sans", sans-serif; font-weight: bold; font-size: 13px; }
        .title {
            margin: 0 0 30px;
            text-align: center;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 20px;
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
        }
        .content { margin-top: 10px; font-size: 15px; text-align: justify; }
        .line { margin: 0 0 12px; }
        .signature { width: 100%; margin-top: 42px; border-collapse: collapse; }
        .signature td { width: 50%; vertical-align: top; }
        .right { text-align: center; }
        .principal { margin-top: 62px; font-weight: bold; }
        .strong { font-weight: bold; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width:100px">
                <img class="logo" src="{{ public_path('images/logo-pagnidibsom.png') }}" alt="Logo">
                <div class="motto">"Batir l'excellence"</div>
            </td>
            <td class="school">
                <h1>Lycee Prive Pagnidibsom</h1>
                <p>04 Ouagadougou 04 BP 8825</p>
                <p>Tel : (+226) 72 81 61 59 / 78 42 62 06</p>
                <p>E-mail : infoslyceepagnidibsom@gmail.com</p>
            </td>
            <td class="year">
                Annee scolaire: {{ $certificate->academicYear?->name ?? '-' }}<br>
                Burkina Faso<br>
                La Patrie ou la Mort Nous Vaincrons
            </td>
        </tr>
    </table>

    <div class="title">{{ $typeLabel }}</div>

    <div class="content">
        @if ($certificate->document_type === 'school_certificate')
            <p class="line">
                Le Proviseur du <span class="strong">LYCEE PRIVE PAGNIDIBSOM</span> certifie que :
            </p>
            <p class="line">
                <span class="strong">{{ $student->full_name }}</span>, ne(e) le
                <span class="strong">{{ $student->birth_date?->format('d/m/Y') ?? '-' }}</span>
                a <span class="strong">{{ $student->birth_place ?? '-' }}</span>,
                fils(le) de <span class="strong">{{ $fatherGuardian?->full_name ?? '-' }}</span>
                et de <span class="strong">{{ $motherGuardian?->full_name ?? '-' }}</span>,
                est eleve de son etablissement.
            </p>
            <p class="line">
                Classe frequentee : <span class="strong">{{ $enrollment?->schoolClass?->name ?? '-' }}</span>
                &nbsp;&nbsp;&nbsp; No Matricule : <span class="strong">{{ $student->matricule }}</span>
            </p>
        @elseif ($certificate->document_type === 'enrollment_certificate')
            <p class="line">
                Je soussigne(e) <span class="strong">{{ $principalName }}</span>, Proviseur du
                <span class="strong">LYCEE PRIVE PAGNIDIBSOM</span>, certifie que
                <span class="strong">{{ $student->full_name }}</span>, ne(e) le
                <span class="strong">{{ $student->birth_date?->format('d/m/Y') ?? '-' }}</span>
                a <span class="strong">{{ $student->birth_place ?? '-' }}</span>, est eleve de son etablissement.
            </p>
            <p class="line">
                Elle / Il s'est inscrit(e) pour le compte de l'annee
                <span class="strong">{{ $certificate->academicYear?->name ?? '-' }}</span>
                en classe de <span class="strong">{{ $enrollment?->schoolClass?->name ?? '-' }}</span>.
            </p>
            <p class="line">
                En foi de quoi le present certificat lui est delivre pour servir et valoir ce que de droit.
            </p>
        @else
            <p class="line">
                Je soussigne(e) <span class="strong">{{ $principalName }}</span>, Proviseur du/de
                <span class="strong">LYCEE PRIVE PAGNIDIBSOM</span>, certifie que
                <span class="strong">{{ $student->full_name }}</span>, ne(e) le
                <span class="strong">{{ $student->birth_date?->format('d/m/Y') ?? '-' }}</span>
                a <span class="strong">{{ $student->birth_place ?? '-' }}</span>,
                numero matricule <span class="strong">{{ $student->matricule }}</span>,
                frequente regulierement notre etablissement et est inscrit cette annee en classe de
                <span class="strong">{{ $enrollment?->schoolClass?->name ?? '-' }}</span>.
            </p>
            <p class="line">
                Il est a jour de tout paiement des frais de scolarite enregistres dans notre etablissement,
                en foi de quoi le present certificat lui est delivre pour servir ce que de droit.
            </p>
        @endif
    </div>

    <table class="signature">
        <tr>
            <td></td>
            <td class="right">
                Ouagadougou, le {{ $certificate->received_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}<br>
                <span class="strong">Le Proviseur</span>
                <div class="principal">{{ $principalName }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
