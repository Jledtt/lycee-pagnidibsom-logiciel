<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $typeLabel }}</title>
    <style>
        @page { margin: 30px 42px; }
        body {
            margin: 0;
            color: #000;
            font-family: "Times New Roman", "DejaVu Serif", serif;
            font-size: 14px;
            line-height: 1.52;
        }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 34px; }
        .header td { vertical-align: top; }
        .logo { width: 108px; height: 108px; object-fit: contain; }
        .school { font-weight: bold; }
        .school h1 { margin: 0 0 7px; font-size: 17px; text-transform: uppercase; }
        .school p { margin: 0 0 5px; font-size: 13px; }
        .motto { width: 90px; margin-top: 12px; font-size: 12px; line-height: 1.35; font-style: italic; font-weight: bold; }
        .year { text-align: right; font-weight: bold; font-size: 13px; }
        .title {
            margin: 0 0 26px;
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
        }
        .content { margin-top: 10px; font-size: 14px; text-align: justify; }
        .line { margin: 0 0 14px; }
        .signature { width: 100%; margin-top: 34px; border-collapse: collapse; }
        .signature td { width: 50%; vertical-align: top; }
        .right { text-align: center; }
        .principal { margin-top: 54px; font-weight: bold; }
        .strong { font-weight: bold; }
        .sign-title { font-weight: bold; text-decoration: underline; }
    </style>
</head>
<body>
    @php($school = $school ?? $schoolSettings)
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')
    @php($principalName = ($principalName ?? null) ?: ($school?->principal_name ?: 'Yamdaogo TINTILA'))
    @php($accountantName = $school?->accountant_name ?: 'Le Comptable')
    <table class="header">
        <tr>
            <td style="width:128px">
                <img class="logo" src="{{ public_path($logoPath) }}" alt="Logo">
                <div class="motto">{{ $school?->motto ?? '"Batir l\'excellence"' }}</div>
            </td>
            <td class="school">
                <h1>{{ $school?->school_name ?? 'Lycee Prive Pagnidibsom' }}</h1>
                <p>{{ $school?->address ?? '04 Ouagadougou 04 BP 8825' }}</p>
                <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
            </td>
            <td class="year">
                Annee scolaire: {{ $certificate->academicYear?->name ?? '-' }}<br>
                No certificat: {{ $certificate->document_number ?? '-' }}<br>
                {{ $school?->country ?? 'Burkina Faso' }}<br>
                {{ $school?->national_motto ?? 'La Patrie ou la Mort Nous Vaincrons' }}
            </td>
        </tr>
    </table>

    <div class="title">{{ $typeLabel }}</div>

    <div class="content">
        @if ($certificate->document_type === 'school_certificate')
            <p class="line">
                Je soussigne(e) <span class="strong">{{ $principalName }}</span>, {{ $school?->principal_title ?? 'Le Proviseur' }} du
                <span class="strong">{{ str($school?->school_name ?? 'LYCEE PRIVE PAGNIDIBSOM')->upper() }}</span>
                certifie que <span class="strong">{{ $student->full_name }}</span> ne(e) le
                <span class="strong">{{ $student->birth_date?->format('d/m/Y') ?? '-' }}</span>
                a <span class="strong">{{ $student->birth_place ?? '-' }}</span> de
                <span class="strong">{{ $fatherGuardian?->full_name ?? '-' }}</span> et de
                <span class="strong">{{ $motherGuardian?->full_name ?? '-' }}</span> est regulierement inscrit(e) dans mon
                etablissement sous le numero matricule : <span class="strong">{{ $student->matricule }}</span>
                en classe de <span class="strong">{{ $enrollment?->schoolClass?->name ?? '-' }}</span>.
            </p>
            <p class="line">
                En foi de quoi le present certificat est etabli pour servir et valoir ce que de droit.
            </p>
        @elseif ($certificate->document_type === 'enrollment_certificate')
            <p class="line">
                Je soussigne(e) <span class="strong">{{ $principalName }}</span>, {{ $school?->principal_title ?? 'Proviseur' }} du
                <span class="strong">{{ str($school?->school_name ?? 'LYCEE PRIVE PAGNIDIBSOM')->upper() }}</span>, certifie que
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
                Je soussigne(e) <span class="strong">{{ $accountantName }}</span>, Comptable du
                <span class="strong">{{ str($school?->school_name ?? 'LYCEE PRIVE PAGNIDIBSOM')->upper() }}</span>
                atteste que <span class="strong">{{ $student->full_name }}</span> ne(e) le
                <span class="strong">{{ $student->birth_date?->format('d/m/Y') ?? '-' }}</span>
                a <span class="strong">{{ $student->birth_place ?? '-' }}</span> de
                <span class="strong">{{ $fatherGuardian?->full_name ?? '-' }}</span> et de
                <span class="strong">{{ $motherGuardian?->full_name ?? '-' }}</span>
            </p>
            <p class="line">
                en classe de <span class="strong">{{ $enrollment?->schoolClass?->name ?? '-' }}</span>.
            </p>
            <p class="line">
                Est a jour de ses frais de scolarite par consequent n'est pas redevable a l'etablissement.
            </p>
        @endif
    </div>

    <table class="signature">
        <tr>
            <td></td>
            <td class="right">
                {{ $school?->city ?? 'Ouagadougou' }}, le {{ $certificate->received_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}<br>
                <span class="sign-title">
                    {{ $certificate->document_type === 'no_debt_certificate' ? 'La Comptabilite' : ($school?->principal_title ?? 'Le Proviseur') }}
                </span>
                <div class="principal">{{ $certificate->document_type === 'no_debt_certificate' ? $accountantName : $principalName }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
