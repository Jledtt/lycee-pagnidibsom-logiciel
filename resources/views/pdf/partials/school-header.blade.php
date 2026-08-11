@php
    $documentSchool = $school ?? $schoolSettings ?? null;
    $documentLogoPath = $documentSchool?->logo_path ?: 'images/logo-pagnidibsom.png';
    $documentLogoSize = $logoSize ?? 78;
    $documentRightLines = collect($rightLines ?? [])->filter(fn ($line) => filled($line))->values();
@endphp

<table style="width:100%; border-collapse:collapse; margin-bottom:{{ $marginBottom ?? 18 }}px;">
    <tr>
        <td style="width:{{ $documentLogoSize + 18 }}px; vertical-align:top;">
            @include('pdf.partials.logo-with-motto', [
                'logoPath' => $documentLogoPath,
                'logoWidth' => $documentLogoSize,
                'mottoSize' => $mottoSize ?? 10,
            ])
        </td>
        <td style="vertical-align:top; text-align:{{ ($centerSchool ?? false) ? 'center' : 'left' }}; font-weight:bold;">
            <div style="font-size:{{ $schoolNameSize ?? 17 }}px; text-transform:uppercase; margin-bottom:5px;">
                {{ str($documentSchool?->school_name ?? 'Lycée Privé Pagnidibsom')->upper() }}
            </div>
            <div style="font-size:{{ $schoolInfoSize ?? 11 }}px; line-height:1.45;">
                {{ $documentSchool?->address ?? '04 Ouagadougou 04 BP 8825' }}<br>
                Tél. : {{ $documentSchool?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}<br>
                E-mail : {{ $documentSchool?->email ?? 'infoslyceepagnidibsom@gmail.com' }}
            </div>
        </td>
        <td style="width:{{ $rightWidth ?? 190 }}px; vertical-align:top; text-align:{{ $rightAlign ?? 'right' }}; font-size:{{ $rightSize ?? 11 }}px; line-height:1.45; font-weight:bold;">
            @foreach ($documentRightLines as $line)
                <div>{{ $line }}</div>
            @endforeach
        </td>
    </tr>
</table>
