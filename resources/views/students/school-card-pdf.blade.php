<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Carte scolaire</title>
    <style>
        @page { margin: 18px; }
        body {
            margin: 0;
            color: #111;
            font-family: "DejaVu Sans", sans-serif;
            background: #fff;
        }
        .sheet {
            width: 100%;
            height: 100%;
            padding-top: 20px;
        }
        .card {
            position: relative;
            width: 720px;
            height: 430px;
            margin: 0 auto;
            border: 2px solid #111;
            overflow: hidden;
            background: #fff;
        }
        .top-title {
            color: #f28c1d;
            text-align: center;
            font-size: 28px;
            line-height: 1;
            font-weight: 900;
            letter-spacing: 5px;
            text-transform: uppercase;
            padding-top: 14px;
        }
        .school-row {
            width: 100%;
            border-collapse: collapse;
            margin-top: -2px;
        }
        .school-row td { border: 0; vertical-align: middle; }
        .logo-cell { width: 116px; text-align: center; }
        .logo {
            width: 82px;
            height: 82px;
            object-fit: contain;
        }
        .school-info {
            text-align: center;
            font-size: 14px;
            line-height: 1.28;
            font-weight: 900;
            letter-spacing: 3px;
            text-transform: uppercase;
        }
        .separator {
            height: 0;
            border-top: 3px solid #111;
            margin-top: 8px;
        }
        .content {
            position: relative;
            padding: 16px 28px 48px;
            height: 214px;
        }
        .watermark {
            position: absolute;
            left: 255px;
            top: 55px;
            width: 185px;
            height: 185px;
            opacity: .08;
        }
        .info {
            width: 500px;
            font-size: 21px;
            line-height: 1.45;
            font-weight: 900;
        }
        .info .label { display: inline-block; min-width: 160px; }
        .photo-box {
            position: absolute;
            right: 24px;
            top: 18px;
            width: 142px;
            height: 172px;
            border: 2px solid #111;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            line-height: 172px;
        }
        .photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .signature {
            position: absolute;
            right: 185px;
            bottom: 48px;
            width: 190px;
            text-align: center;
            font-size: 17px;
            font-weight: 900;
        }
        .stamp {
            margin: 0 auto 6px;
            width: 86px;
            height: 62px;
            border: 3px solid #1c4774;
            border-radius: 50%;
            color: #1c4774;
            font-size: 11px;
            line-height: 1.15;
            padding-top: 24px;
            transform: rotate(-10deg);
        }
        .bottom {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 36px;
            padding: 0 24px;
            background: #f28c1d;
            border-top: 2px solid #e07910;
            font-size: 18px;
            line-height: 36px;
            font-weight: 900;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    @php($school = $school ?? $schoolSettings)
    @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')
    @php($logoFullPath = public_path($logoPath))

    <div class="sheet">
        <div class="card">
            <div class="top-title">{{ str($school?->school_name ?? 'Lycée Privé Pagnidibsom')->upper() }}</div>
            <table class="school-row">
                <tr>
                    <td class="logo-cell">
                        @if (file_exists($logoFullPath))
                            @include('pdf.partials.logo-with-motto', [
                                'logoPath' => $logoPath,
                                'mottoSize' => 7,
                            ])
                        @endif
                    </td>
                    <td class="school-info">
                        Enseignement technique et general<br>
                        Autorisation : N° 2021-01552/MENAPLN/SG/DEP<br>
                        {{ $school?->postal_box ?? '04 BP 8825' }} {{ str($school?->city ?? 'Ouagadougou')->upper() }} 04-BF<br>
                        Email: {{ $school?->email ?? 'lyceepagnidibsom@gmail.com' }}<br>
                        Tel: {{ $school?->phone ?? '+226 72 81 61 59 / 78 42 62 06' }}
                    </td>
                    <td class="logo-cell">
                        @if (file_exists($logoFullPath))
                            @include('pdf.partials.logo-with-motto', [
                                'logoPath' => $logoPath,
                                'mottoSize' => 7,
                            ])
                        @endif
                    </td>
                </tr>
            </table>

            <div class="separator"></div>

            <div class="content">
                @if (file_exists($logoFullPath))
                    <img class="watermark" src="{{ $logoFullPath }}" alt="">
                @endif

                <div class="info">
                    <div><span class="label">Nom:</span> {{ str($student->last_name)->upper() }}</div>
                    <div><span class="label">Prénom(s):</span> {{ $student->first_name }}</div>
                    <div><span class="label">Ne(e) le:</span> {{ $student->birth_date?->format('d/m/Y') ?? '-' }}</div>
                    <div><span class="label">A:</span> {{ $student->birth_place ?? '-' }}</div>
                    <div><span class="label">Classe:</span> {{ $className ?? '-' }}</div>
                    <div><span class="label">Année scolaire:</span> {{ $academicYear?->name ?? '-' }}</div>
                </div>

                <div class="photo-box">
                    @if ($photoPath && file_exists($photoPath))
                        <img src="{{ $photoPath }}" alt="Photo">
                    @else
                        PHOTO
                    @endif
                </div>

                <div class="signature">
                    <div class="stamp">{{ $school?->principal_title ?? 'Le Proviseur' }}</div>
                    {{ $principalName }}
                </div>
            </div>

            <div class="bottom">
                Personne a prevenir en cas de besoin: {{ $emergencyContact }}
            </div>
        </div>
    </div>
</body>
</html>
