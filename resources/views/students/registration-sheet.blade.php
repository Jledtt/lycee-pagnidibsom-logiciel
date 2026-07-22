<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Fiche d’inscription - {{ $student->full_name }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #000;
            background: #f2f2f2;
            font-family: Georgia, "Times New Roman", serif;
        }
        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 12mm;
            background: #fff;
        }
        .header {
            display: grid;
            grid-template-columns: 45mm 1fr 34mm;
            gap: 8mm;
            align-items: start;
        }
        .logo {
            width: 34mm;
            height: 34mm;
            object-fit: contain;
            display: block;
        }
        .motto {
            margin-top: 8mm;
            font-size: 12pt;
            font-style: italic;
            font-weight: 700;
        }
        .school h1 {
            margin: 0 0 4mm;
            font-family: Arial, sans-serif;
            font-size: 16pt;
            text-transform: uppercase;
        }
        .school p {
            margin: 0 0 3mm;
            font-size: 12pt;
            font-weight: 700;
        }
        .photo {
            height: 30mm;
            border: 1.5px solid #000;
            display: grid;
            place-items: center;
            font-size: 12pt;
        }
        .title {
            margin: 20mm 0 10mm;
            text-align: center;
            font-family: Arial, sans-serif;
            font-size: 22pt;
            font-weight: 900;
            text-decoration: underline;
        }
        .section-title {
            display: flex;
            justify-content: space-between;
            margin: 0 0 6mm;
            font-size: 16pt;
            font-weight: 900;
        }
        .box {
            border: 1.5px solid #000;
            margin-bottom: 5mm;
        }
        .columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }
        .columns > div + div {
            border-left: 1.5px solid #000;
        }
        .cell {
            padding: 4mm 6mm;
            min-height: 40mm;
        }
        .line {
            margin: 0 0 3mm;
            font-size: 14pt;
            line-height: 1.15;
        }
        .line strong {
            font-weight: 400;
        }
        .small-head {
            margin: 0 0 4mm;
            font-size: 14pt;
            font-weight: 900;
        }
        .checks {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3mm 10mm;
            margin-top: 4mm;
        }
        .checkline {
            display: flex;
            justify-content: space-between;
            gap: 8mm;
            font-size: 14pt;
        }
        .square {
            display: inline-block;
            width: 7mm;
            height: 7mm;
            border: 1.5px solid #000;
            text-align: center;
            line-height: 6mm;
            font-family: Arial, sans-serif;
            font-size: 12pt;
        }
        .actions {
            position: sticky;
            top: 0;
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 10px;
            background: #222;
        }
        .actions a,
        .actions button {
            min-height: 36px;
            border: 0;
            border-radius: 4px;
            padding: 0 14px;
            background: #fff;
            color: #111;
            font: 600 14px Arial, sans-serif;
            text-decoration: none;
            cursor: pointer;
        }
        @media print {
            body { background: #fff; }
            .actions { display: none; }
            .sheet { width: auto; min-height: auto; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <a href="{{ route('students.show', $student) }}">Retour</a>
        <a href="{{ route('students.registration-sheet.pdf', $student) }}">Télécharger PDF</a>
        <button onclick="window.print()">Imprimer</button>
    </div>

    <main class="sheet">
        @php($school = $school ?? $schoolSettings)
        @php($logoPath = $school?->logo_path ?: 'images/logo-pagnidibsom.png')
        <header class="header">
            <div>
                <img class="logo" src="{{ asset($logoPath) }}" alt="Logo {{ $school?->school_name ?? 'Lycée Privé Pagnidibsom' }}">
                <div class="motto">{{ $school?->motto ?? '"Bâtir l\'excellence"' }}</div>
            </div>
            <div class="school">
                <h1>{{ $school?->school_name ?? 'Lycée Privé Pagnidibsom' }}</h1>
                <p>{{ $school?->address ?? '04 OUAGADOUGOU 04 BP 8825' }}</p>
                <p>Tel : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}</p>
                <p>E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}</p>
            </div>
            <div class="photo">PHOTO</div>
        </header>

        <div class="title">FICHE d’INSCRIPTION {{ $academicYear?->name ?? '2025-2026' }}</div>

        <div class="section-title">
            <span>1- Identite de l’élève</span>
            <span>Classe: {{ $student->desired_class ?: '.................' }}</span>
        </div>

        <div class="box columns">
            <div class="cell">
                <p class="line">Nom: <strong>{{ $student->last_name }}</strong></p>
                <p class="line">Prénom(s): <strong>{{ $student->first_name }}</strong></p>
                <p class="line">Date de naissance: <strong>{{ $student->birth_date?->format('d/m/Y') }}</strong></p>
                <p class="line">Lieu de naissance: <strong>{{ $student->birth_place }}</strong></p>
                <p class="line">Sexe: <strong>{{ $student->gender_label }}</strong></p>
                <p class="line">Nationalite: <strong>{{ $student->nationality }}</strong></p>
                <p class="line">Ethnie: <strong>{{ $student->ethnicity }}</strong></p>
                <p class="line">Religion: <strong>{{ $student->religion }}</strong></p>
            </div>
            <div class="cell">
                <p class="line">École d’origine: <strong>{{ $student->origin_school }}</strong></p>
                <p class="line">Classe frequentee: <strong>{{ $student->previous_class }}</strong></p>
                <p class="line">Classe déjà redoublee: <strong>{{ $student->repeated_class }}</strong></p>
                <p class="line">Secteur: <strong>{{ $student->sector }}</strong></p>
                <p class="line">Quartier: <strong>{{ $student->district }}</strong></p>
                <p class="line">Tel(dom): <strong>{{ $student->home_phone }}</strong></p>
            </div>
        </div>

        <div class="section-title"><span>2- Parents/Tuteur</span></div>
        <div class="box columns">
            <div class="cell">
                <p class="small-head">Pere/Tuteur</p>
                <p class="line">Nom: <strong>{{ $fatherGuardian?->last_name }}</strong></p>
                <p class="line">Prénom(s): <strong>{{ $fatherGuardian?->first_name }}</strong></p>
                <p class="line">Profession: <strong>{{ $fatherGuardian?->profession }}</strong></p>
                <p class="line">Service: <strong>{{ $fatherGuardian?->service }}</strong></p>
                <p class="line">Tel(portable): <strong>{{ $fatherGuardian?->phone_primary }}</strong></p>
                <p class="line">E-mail: <strong>{{ $fatherGuardian?->email }}</strong></p>
            </div>
            <div class="cell">
                <p class="small-head">Mere/Tutrice</p>
                <p class="line">Nom: <strong>{{ $motherGuardian?->last_name }}</strong></p>
                <p class="line">Prénom(s): <strong>{{ $motherGuardian?->first_name }}</strong></p>
                <p class="line">Profession: <strong>{{ $motherGuardian?->profession }}</strong></p>
                <p class="line">Service: <strong>{{ $motherGuardian?->service }}</strong></p>
                <p class="line">Tel(portable): <strong>{{ $motherGuardian?->phone_primary }}</strong></p>
                <p class="line">E-mail: <strong>{{ $motherGuardian?->email }}</strong></p>
            </div>
        </div>

        <div class="section-title"><span>3- Observations particulieres</span></div>
        <div class="box columns">
            <div class="cell">
                <p class="small-head">Etat de sante (pathologie connue)</p>
                <div class="checks">
                    @foreach ([
                        'asthme' => 'Asthme',
                        'drepanocytose' => 'Drepanocytose',
                        'cardiopathie' => 'Cardiopathie',
                        'hta' => 'HTA',
                        'diabete' => 'Diabete',
                        'epilepsie' => 'Epilepsie',
                    ] as $value => $label)
                        <div class="checkline">
                            <span>{{ $label }}</span>
                            <span class="square">{{ in_array($value, $student->health_conditions ?? [], true) ? 'X' : '' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="cell">
                <p class="small-head">Sport</p>
                <p class="line" style="text-align:center;margin-top:12mm;font-weight:900">Aptitude au sport</p>
                <p class="line" style="text-align:center">
                    Oui <span class="square">{{ $student->sport_aptitude === true ? 'X' : '' }}</span>
                    &nbsp;&nbsp;&nbsp;
                    Non <span class="square">{{ $student->sport_aptitude === false ? 'X' : '' }}</span>
                </p>
            </div>
        </div>

        <div class="section-title"><span>4- Personne a prevenir en cas de besoin</span></div>
        <p class="line">Mr/Mme: <strong>{{ $student->emergency_contact_name }}</strong> <span style="float:right">Contact : <strong>{{ $student->emergency_contact_phone }}</strong></span></p>
        <p class="line">No WhatsApp pour les infos de l’école : <strong>{{ $student->school_info_whatsapp }}</strong></p>
    </main>
</body>
</html>
