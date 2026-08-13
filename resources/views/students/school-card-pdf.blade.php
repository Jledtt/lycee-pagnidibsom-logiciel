<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Carte scolaire</title>
    <style>
        @page { margin: 18px; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: #101010;
            font-family: "DejaVu Sans", sans-serif;
            background: #fff;
        }

        .sheet {
            width: 100%;
            padding-top: 26px;
        }

        .card {
            position: relative;
            width: 720px;
            height: 450px;
            margin: 0 auto;
            overflow: hidden;
            border: 1.5px solid #8b1e2d;
            border-radius: 22px;
            background: #fff;
        }

        .year-banner {
            position: absolute;
            top: 0;
            right: 0;
            width: 476px;
            height: 58px;
            color: #fff;
            font-size: 23px;
            font-weight: bold;
            line-height: 48px;
            padding-top: 4px;
            text-align: center;
        }

        .year-label {
            position: absolute;
            top: 0;
            left: 0;
            width: 292px;
            height: 58px;
            background: #8b1e2d;
        }

        .year-value {
            position: absolute;
            top: 0;
            right: 0;
            width: 184px;
            height: 58px;
            background: #e6a817;
            color: #1d1718;
        }

        .school-mark {
            position: absolute;
            top: 7px;
            left: 14px;
            width: 216px;
            color: #8b1e2d;
            font-size: 11px;
            font-weight: bold;
            line-height: 1.25;
            text-align: center;
        }

        .brand-logo,
        .brand-short-name {
            display: inline-block;
            vertical-align: middle;
        }

        .brand-logo {
            width: 74px;
        }

        .brand-short-name {
            max-width: 124px;
            margin-left: 4px;
            color: #8b1e2d;
            font-size: 14px;
            text-align: left;
        }

        .photo-box {
            position: absolute;
            left: 0;
            bottom: 46px;
            width: 244px;
            height: 322px;
            overflow: hidden;
            background: #f8edf0;
            color: #767b85;
            font-size: 19px;
            font-weight: bold;
            line-height: 1;
            text-align: center;
        }

        .photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-placeholder {
            position: absolute;
            top: 145px;
            left: 0;
            width: 100%;
            line-height: 1.2;
        }

        .identity {
            position: absolute;
            top: 70px;
            left: 266px;
            right: 20px;
            height: 326px;
        }

        .identity-row {
            min-height: 39px;
            font-size: 19px;
            font-weight: bold;
            line-height: 1.18;
        }

        .identity-row--lead {
            min-height: 37px;
            font-size: 21px;
        }

        .identity-label {
            display: inline-block;
            width: 118px;
            font-family: Georgia, "DejaVu Serif", serif;
            font-weight: bold;
        }

        .identity-value {
            display: inline-block;
            max-width: 305px;
            vertical-align: top;
        }

        .identity-value--uppercase { text-transform: uppercase; }
        .identity-value--compact { font-size: 15px; }

        .emergency {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 46px;
            overflow: hidden;
            padding: 0 18px;
            border-top: 4px solid #e6a817;
            background: #8b1e2d;
            color: #fff;
            font-size: 19px;
            font-weight: bold;
            line-height: 34px;
            padding-top: 4px;
            text-align: center;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="card">
            <div class="school-mark">
                <div class="brand-logo">
                    @include('pdf.partials.logo-with-motto', [
                        'school' => $school,
                        'logoWidth' => 46,
                        'mottoSize' => 6,
                    ])
                </div>
                <div class="brand-short-name">{{ $school?->short_name ?: 'LPP' }}</div>
            </div>

            <div class="year-banner">
                <div class="year-label">Année scolaire</div>
                <div class="year-value">{{ $academicYear?->name ?? '-' }}</div>
            </div>

            <div class="photo-box">
                @if ($photoPath && file_exists($photoPath))
                    <img src="{{ $photoPath }}" alt="Photo de l'élève">
                @else
                    <div class="photo-placeholder">PHOTO</div>
                @endif
            </div>

            <div class="identity">
                <div class="identity-row identity-row--lead">
                    <span class="identity-label">Classe</span>:
                    <span class="identity-value">{{ $className ?? '-' }}</span>
                </div>
                <div class="identity-row identity-row--lead">
                    <span class="identity-label">N° Mle</span>:
                    <span class="identity-value">{{ $student->matricule }}</span>
                </div>
                <div class="identity-row">
                    <span class="identity-label">Nom</span>:
                    <span class="identity-value identity-value--uppercase {{ mb_strlen($student->last_name) > 24 ? 'identity-value--compact' : '' }}">{{ $student->last_name }}</span>
                </div>
                <div class="identity-row">
                    <span class="identity-label">Prénom(s)</span>:
                    <span class="identity-value {{ mb_strlen($student->first_name) > 24 ? 'identity-value--compact' : '' }}">{{ $student->first_name }}</span>
                </div>
                <div class="identity-row">
                    <span class="identity-label">Né(e) le</span>:
                    <span class="identity-value">{{ $student->birth_date?->format('d/m/Y') ?? '-' }}</span>
                </div>
                <div class="identity-row">
                    <span class="identity-label">À</span>:
                    <span class="identity-value">{{ $student->birth_place ?: '-' }}</span>
                </div>
                <div class="identity-row">
                    <span class="identity-label">Père</span>:
                    <span class="identity-value {{ mb_strlen($fatherName ?: '') > 24 ? 'identity-value--compact' : '' }}">{{ $fatherName ?: '-' }}</span>
                </div>
                <div class="identity-row">
                    <span class="identity-label">Mère</span>:
                    <span class="identity-value {{ mb_strlen($motherName ?: '') > 24 ? 'identity-value--compact' : '' }}">{{ $motherName ?: '-' }}</span>
                </div>
            </div>

            <div class="emergency">Urgence : {{ $emergencyContact }}</div>
        </div>
    </div>
</body>
</html>
