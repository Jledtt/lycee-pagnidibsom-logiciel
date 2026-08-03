@php
    $brandingSchool = $school ?? $schoolSettings ?? null;
    $brandingLogoPath = $logoPath ?? $brandingSchool?->logo_path ?: 'images/logo-pagnidibsom.png';
    $brandingMotto = trim($brandingSchool?->motto ?: 'Bâtir l\'excellence', "\" \t\n\r\0\x0B");
    $brandingLogoWidth = $logoWidth ?? null;
@endphp

<div style="text-align:center; line-height:1.15;">
    <img
        class="{{ $logoClass ?? 'logo' }}"
        src="{{ public_path($brandingLogoPath) }}"
        alt="Logo"
        @if ($brandingLogoWidth) style="width:{{ $brandingLogoWidth }}px; height:{{ $brandingLogoWidth }}px; object-fit:contain;" @endif
    >
    <div style="margin-top:4px; text-align:center; font-size:{{ $mottoSize ?? 8 }}px; font-style:italic; font-weight:bold; line-height:1.2;">
        {{ $brandingMotto }}
    </div>
</div>
