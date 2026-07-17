<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Fiche d'inscription</title>
    <style>
        @page {
            margin: 14px 18px;
        }

        body {
            margin: 0;
            color: #000;
            font-family: "DejaVu Serif", serif;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header td {
            vertical-align: top;
        }

        .logo {
            width: 74px;
            height: 74px;
            object-fit: contain;
        }

        .school h1 {
            margin: 0 0 4px;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 20px;
            text-transform: uppercase;
        }

        .school p {
            margin: 0 0 3px;
            font-size: 13px;
            font-weight: bold;
        }

        .photo {
            width: 104px;
            height: 56px;
            padding-top: 32px;
            border: 1.5px solid #000;
            text-align: center;
            line-height: 16px;
            font-size: 13px;
        }

        .motto {
            margin-top: 10px;
            font-style: italic;
            font-weight: bold;
        }

        .title {
            margin: 26px 0 16px;
            text-align: center;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 24px;
            font-weight: bold;
            text-decoration: underline;
        }

        .section-title {
            margin: 0 0 7px;
            font-size: 18px;
            font-weight: bold;
        }

        .right-title {
            float: right;
            font-size: 17px;
        }

        .box {
            border: 1.5px solid #000;
            margin-bottom: 8px;
        }

        .box td {
            width: 50%;
            padding: 7px 10px;
            vertical-align: top;
            border-left: 1.5px solid #000;
        }

        .box td:first-child {
            border-left: 0;
        }

        .line {
            margin: 0 0 4px;
            font-size: 14px;
            line-height: 1.18;
        }

        .line strong {
            font-weight: normal;
        }

        .subhead {
            margin: 0 0 5px;
            font-size: 15px;
            font-weight: bold;
        }

        .condition {
            width: 100%;
            margin-top: 4px;
        }

        .condition td {
            width: 50%;
            border: 0;
            padding: 2px 8px 2px 0;
            font-size: 14px;
        }

        .square {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 1.5px solid #000;
            text-align: center;
            line-height: 14px;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            font-weight: bold;
        }

        .sport {
            text-align: center;
            padding-top: 14px;
        }

        .footer-lines {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 170px">
                <img class="logo" src="{{ public_path('images/logo-pagnidibsom.png') }}" alt="Logo">
                <div class="motto">"Batir l'excellence"</div>
            </td>
            <td class="school">
                <h1>Lycee Prive Pagnidibsom</h1>
                <p>04 OUAGADOUGOU 04 BP 8825</p>
                <p>Tel : (+226) 72 81 61 59 / 78 42 62 06</p>
                <p>E-mail : infoslyceepagnidibsom@gmail.com</p>
            </td>
            <td style="width: 125px">
                <div class="photo">PHOTO</div>
            </td>
        </tr>
    </table>

    <div class="title">FICHE D'INSCRIPTION {{ $academicYear?->name ?? '2025-2026' }}</div>

    <div class="section-title">
        1- Identite de l'eleve
        <span class="right-title">Classe: {{ $student->desired_class ?: '.................' }}</span>
    </div>

    <table class="box">
        <tr>
            <td>
                <p class="line">Nom: <strong>{{ $student->last_name }}</strong></p>
                <p class="line">Prenom(s): <strong>{{ $student->first_name }}</strong></p>
                <p class="line">Date de naissance: <strong>{{ $student->birth_date?->format('d/m/Y') }}</strong></p>
                <p class="line">Lieu de naissance: <strong>{{ $student->birth_place }}</strong></p>
                <p class="line">Sexe: <strong>{{ $student->gender === 'female' ? 'Fille' : 'Garcon' }}</strong></p>
                <p class="line">Nationalite: <strong>{{ $student->nationality }}</strong></p>
                <p class="line">Ethnie: <strong>{{ $student->ethnicity }}</strong></p>
                <p class="line">Religion: <strong>{{ $student->religion }}</strong></p>
            </td>
            <td>
                <p class="line">Ecole d'origine: <strong>{{ $student->origin_school }}</strong></p>
                <p class="line">Classe frequentee: <strong>{{ $student->previous_class }}</strong></p>
                <p class="line">Classe deja redoublee: <strong>{{ $student->repeated_class }}</strong></p>
                <p class="line">Secteur: <strong>{{ $student->sector }}</strong></p>
                <p class="line">Quartier: <strong>{{ $student->district }}</strong></p>
                <p class="line">Tel(dom): <strong>{{ $student->home_phone }}</strong></p>
            </td>
        </tr>
    </table>

    <div class="section-title">2- Parents/Tuteur</div>

    <table class="box">
        <tr>
            <td>
                <p class="subhead">Pere/Tuteur</p>
                <p class="line">Nom: <strong>{{ $fatherGuardian?->last_name }}</strong></p>
                <p class="line">Prenom(s): <strong>{{ $fatherGuardian?->first_name }}</strong></p>
                <p class="line">Profession: <strong>{{ $fatherGuardian?->profession }}</strong></p>
                <p class="line">Service: <strong>{{ $fatherGuardian?->service }}</strong></p>
                <p class="line">Tel(portable): <strong>{{ $fatherGuardian?->phone_primary }}</strong></p>
                <p class="line">E-mail: <strong>{{ $fatherGuardian?->email }}</strong></p>
            </td>
            <td>
                <p class="subhead">Mere/Tutrice</p>
                <p class="line">Nom: <strong>{{ $motherGuardian?->last_name }}</strong></p>
                <p class="line">Prenom(s): <strong>{{ $motherGuardian?->first_name }}</strong></p>
                <p class="line">Profession: <strong>{{ $motherGuardian?->profession }}</strong></p>
                <p class="line">Service: <strong>{{ $motherGuardian?->service }}</strong></p>
                <p class="line">Tel(portable): <strong>{{ $motherGuardian?->phone_primary }}</strong></p>
                <p class="line">E-mail: <strong>{{ $motherGuardian?->email }}</strong></p>
            </td>
        </tr>
    </table>

    <div class="section-title">3- Observations particulieres</div>

    <table class="box">
        <tr>
            <td>
                <p class="subhead">Etat de sante (pathologie connue)</p>
                <table class="condition">
                    @foreach ([
                        ['asthme', 'Asthme', 'drepanocytose', 'Drepanocytose'],
                        ['cardiopathie', 'Cardiopathie', 'hta', 'HTA'],
                        ['diabete', 'Diabete', 'epilepsie', 'Epilepsie'],
                    ] as $row)
                        <tr>
                            <td>{{ $row[1] }} <span class="square">{{ in_array($row[0], $student->health_conditions ?? [], true) ? 'X' : '' }}</span></td>
                            <td>{{ $row[3] }} <span class="square">{{ in_array($row[2], $student->health_conditions ?? [], true) ? 'X' : '' }}</span></td>
                        </tr>
                    @endforeach
                </table>
            </td>
            <td>
                <p class="subhead">Sport</p>
                <div class="sport">
                    <p class="line"><strong>Aptitude au sport</strong></p>
                    <p class="line">
                        Oui <span class="square">{{ $student->sport_aptitude === true ? 'X' : '' }}</span>
                        &nbsp;&nbsp;&nbsp;
                        Non <span class="square">{{ $student->sport_aptitude === false ? 'X' : '' }}</span>
                    </p>
                </div>
            </td>
        </tr>
    </table>

    <div class="section-title">4- Personne a prevenir en cas de besoin</div>

    <div class="footer-lines">
        <p class="line">
            Mr/Mme: <strong>{{ $student->emergency_contact_name }}</strong>
            <span style="float: right;">Contact : <strong>{{ $student->emergency_contact_phone }}</strong></span>
        </p>
        <p class="line">No WhatsApp pour les infos de l'ecole : <strong>{{ $student->school_info_whatsapp }}</strong></p>
    </div>
</body>
</html>
