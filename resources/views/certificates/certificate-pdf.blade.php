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
    @php($school = $school ?? $schoolSettings ?? null)
    @php($principalName = ($principalName ?? null) ?: ($school?->principal_name ?: 'Yamdaogo TINTILA'))
    @php($accountantName = $school?->accountant_name ?: 'Le Comptable')
    @php($studentGender = strtolower((string) ($student->gender ?? '')))
    @php($parentWording = match ($studentGender) {
        'female', 'feminin', 'féminin', 'fille' => 'fille de',
        'male', 'masculin', 'garçon', 'garcon' => 'fils de',
        default => 'enfant de',
    })
    @php($schoolName = str($school?->school_name ?? 'Lycée Privé Pagnidibsom')->upper())

    @include('pdf.partials.school-header', [
        'school' => $school,
        'logoSize' => 108,
        'schoolNameSize' => 17,
        'schoolInfoSize' => 13,
        'rightWidth' => 210,
        'rightSize' => 13,
        'marginBottom' => 34,
        'rightLines' => [
            'Année scolaire : '.($certificate->academicYear?->name ?? '-'),
            'N° certificat : '.($certificate->document_number ?? '-'),
            $school?->country ?? 'Burkina Faso',
            $school?->national_motto ?? 'La Patrie ou la Mort Nous Vaincrons',
        ],
    ])

    <div class="title">{{ $typeLabel }}</div>

    <div class="content">
        @if ($certificate->document_type === 'school_certificate')
            <p class="line">
                Je soussigné(e) <span class="strong">{{ $principalName }}</span>, {{ $school?->principal_title ?? 'Le Proviseur' }} du
                <span class="strong">{{ $schoolName }}</span>, certifie que
                <span class="strong">{{ $student->full_name }}</span>, né(e) le
                <span class="strong">{{ $student->birth_date?->format('d/m/Y') ?? '-' }}</span>
                à <span class="strong">{{ $student->birth_place ?? '-' }}</span>,
                {{ $parentWording }} <span class="strong">{{ $fatherGuardian?->full_name ?? '-' }}</span>
                et de <span class="strong">{{ $motherGuardian?->full_name ?? '-' }}</span>,
                est régulièrement inscrit(e) dans mon établissement sous le numéro matricule
                <span class="strong">{{ $student->matricule }}</span>,
                en classe de <span class="strong">{{ $enrollment?->schoolClass?->name ?? '-' }}</span>.
            </p>
            <p class="line">
                En foi de quoi, le présent certificat est établi pour servir et valoir ce que de droit.
            </p>
        @elseif ($certificate->document_type === 'enrollment_certificate')
            <p class="line">
                Je soussigné(e) <span class="strong">{{ $principalName }}</span>, {{ $school?->principal_title ?? 'Le Proviseur' }} du
                <span class="strong">{{ $schoolName }}</span>, certifie que
                <span class="strong">{{ $student->full_name }}</span>, né(e) le
                <span class="strong">{{ $student->birth_date?->format('d/m/Y') ?? '-' }}</span>
                à <span class="strong">{{ $student->birth_place ?? '-' }}</span>,
                est élève de mon établissement.
            </p>
            <p class="line">
                Elle / Il est inscrit(e) pour le compte de l’année scolaire
                <span class="strong">{{ $certificate->academicYear?->name ?? '-' }}</span>
                en classe de <span class="strong">{{ $enrollment?->schoolClass?->name ?? '-' }}</span>.
            </p>
            <p class="line">
                En foi de quoi, le présent certificat lui est délivré pour servir et valoir ce que de droit.
            </p>
        @else
            <p class="line">
                Je soussigné(e) <span class="strong">{{ $accountantName }}</span>, Comptable du
                <span class="strong">{{ $schoolName }}</span>, atteste que
                <span class="strong">{{ $student->full_name }}</span>, né(e) le
                <span class="strong">{{ $student->birth_date?->format('d/m/Y') ?? '-' }}</span>
                à <span class="strong">{{ $student->birth_place ?? '-' }}</span>,
                {{ $parentWording }} <span class="strong">{{ $fatherGuardian?->full_name ?? '-' }}</span>
                et de <span class="strong">{{ $motherGuardian?->full_name ?? '-' }}</span>,
                en classe de <span class="strong">{{ $enrollment?->schoolClass?->name ?? '-' }}</span>,
                est à jour de ses frais de scolarité.
            </p>
            <p class="line">
                Par conséquent, il n’est pas redevable à l’établissement.
            </p>
        @endif
    </div>

    <table class="signature">
        <tr>
            <td></td>
            <td class="right">
                {{ $school?->city ?? 'Ouagadougou' }}, le {{ $certificate->received_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}<br>
                <span class="sign-title">
                    {{ $certificate->document_type === 'no_debt_certificate' ? 'La Comptabilité' : ($school?->principal_title ?? 'Le Proviseur') }}
                </span>
                <div class="principal">{{ $certificate->document_type === 'no_debt_certificate' ? $accountantName : $principalName }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
