<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Fiche papier - {{ $assessment->schoolClass->name }} - {{ $assessment->subject->name }}</title>
    <style>
        @page { margin: 16px 18px 24px; }
    </style>
    @include('pdf.partials.standard-styles')
    <style>
        body {
            color: #111;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
        }
        .paper-page {
            page-break-after: always;
        }
        .paper-page.last-page {
            page-break-after: auto;
        }
        .teacher-line {
            margin: 12px 0 13px 20px;
            font-size: 11px;
            font-weight: bold;
        }
        .teacher-name {
            display: inline-block;
            min-width: 245px;
            min-height: 14px;
        }
        .paper-title {
            margin: 0 0 30px;
            font-size: 17px;
            font-weight: bold;
            text-align: center;
            text-decoration: underline;
        }
        .paper-register {
            width: 96%;
            border-collapse: collapse;
            table-layout: auto;
        }
        .paper-register th,
        .paper-register td {
            height: 20px;
            padding: 2px 4px;
            border: 1px solid #111;
            vertical-align: middle;
        }
        .paper-register th {
            font-size: 10px;
            font-weight: bold;
            white-space: nowrap;
        }
        .paper-register td {
            font-size: 9.5px;
        }
        .paper-register .number {
            text-align: center;
        }
        .paper-register .student {
            font-weight: normal;
        }
        .paper-register .repeated {
            text-align: center;
        }
        .paper-register .score {
            text-align: center;
        }
        .paper-register .weighted {
            text-align: center;
        }
        .empty-row td {
            height: 42px;
            text-align: center;
        }
        .page-footer {
            position: fixed;
            right: 0;
            bottom: -16px;
            left: 0;
            font-size: 8px;
            font-style: italic;
        }
        .page-number:after {
            content: counter(page);
        }
    </style>
</head>
<body>
    @foreach ($studentPages as $students)
        <section class="paper-page {{ $loop->last ? 'last-page' : '' }}">
            @include('pdf.partials.school-header', [
                'school' => $school,
                'logoSize' => 126,
                'mottoSize' => 11,
                'marginBottom' => 4,
                'rightLines' => [
                    'Année scolaire : '.$assessment->academicYear?->name,
                    'Classe : '.$assessment->schoolClass->name,
                    'Matière : '.$assessment->subject->name,
                    'Coeff. : '.($coefficient === null ? '' : number_format((float) $coefficient, 2, ',', ' ')),
                ],
                'rightWidth' => 300,
                'rightAlign' => 'left',
                'rightSize' => 11,
                'schoolNameSize' => 16,
                'schoolInfoSize' => 10,
            ])

            <div class="teacher-line">
                Nom de l'enseignant :
                <span class="teacher-name">{{ $teacher?->name }}</span>
            </div>

            <div class="paper-title">Relevé de notes</div>

            <table class="paper-register">
                <colgroup>
                    <col width="4%">
                    <col width="38%">
                    <col width="13%">
                    <col width="7%">
                    <col width="7%">
                    <col width="6.5%">
                    <col width="6.5%">
                    <col width="6.5%">
                    <col width="11.5%">
                </colgroup>
                <thead>
                    <tr>
                        <th class="number" width="4%">N°</th>
                        <th class="student" width="38%">Nom et prénom(s)</th>
                        <th class="repeated" width="13%">Classe doublée</th>
                        <th class="score" width="7%">Dev. N°1</th>
                        <th class="score" width="7%">Dev. N°2</th>
                        <th class="score" width="6.5%">Interro.</th>
                        <th class="score" width="6.5%">Compo.</th>
                        <th class="score" width="6.5%">Moy.</th>
                        <th class="weighted" width="11.5%">N. pondérée</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        @php
                            $repeatedClass = trim((string) $student->repeated_class);
                            $displayRepeatedClass = in_array(mb_strtolower($repeatedClass), ['', 'aucune', 'non'], true)
                                ? ''
                                : $repeatedClass;
                        @endphp
                        <tr>
                            <td class="number">{{ (($loop->parent->iteration - 1) * 24) + $loop->iteration }}</td>
                            <td class="student">{{ $student->full_name }}</td>
                            <td class="repeated">{{ $displayRepeatedClass }}</td>
                            <td class="score"></td>
                            <td class="score"></td>
                            <td class="score"></td>
                            <td class="score"></td>
                            <td class="score"></td>
                            <td class="weighted"></td>
                        </tr>
                    @empty
                        <tr class="empty-row">
                            <td colspan="9">Aucun élève actif dans cette classe.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endforeach

    <div class="page-footer">Page <span class="page-number"></span></div>
</body>
</html>
