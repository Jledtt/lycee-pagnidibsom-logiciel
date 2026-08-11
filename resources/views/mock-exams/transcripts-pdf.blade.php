<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Relevés de notes - {{ $exam->name }}</title>
    <style>
        @page { margin: 14px 55px 18px; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: #111;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 8.4px;
            line-height: 1.18;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .page {
            position: relative;
            height: 742px;
            page-break-after: always;
        }

        .page:last-child { page-break-after: auto; }

        .header {
            height: 151px;
            position: relative;
        }

        .header-logo {
            left: 24px;
            position: absolute;
            top: 8px;
            width: 112px;
            text-align: center;
        }

        .header-school {
            left: 154px;
            position: absolute;
            top: 0;
            width: 560px;
            font-weight: bold;
        }

        .school-name {
            margin-bottom: 5px;
            font-size: 14px;
            text-transform: uppercase;
        }

        .school-details {
            font-size: 10px;
            line-height: 1.55;
        }

        .header-session {
            position: absolute;
            right: 0;
            top: 0;
            width: 230px;
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            line-height: 1.75;
        }

        .document-title {
            position: absolute;
            top: 115px;
            left: 0;
            width: 100%;
            text-align: center;
            font-size: 15px;
            font-weight: bold;
        }

        .identity {
            height: 72px;
            padding: 0 7px;
            font-size: 9.2px;
            font-weight: bold;
        }

        .identity-row {
            height: 19px;
            white-space: nowrap;
        }

        .identity-label {
            display: inline-block;
            min-width: 105px;
        }

        .identity-pv {
            float: right;
            min-width: 260px;
            text-align: left;
        }

        .identity-birth-date {
            display: inline-block;
            width: 190px;
        }

        .results-grid {
            position: absolute;
            top: 223px;
            left: 0;
            height: 530px;
            table-layout: fixed;
        }

        .results-grid > tbody > tr > td {
            height: 530px;
            padding: 0;
            vertical-align: top;
        }

        .first-round { width: 36%; }
        .control-round { width: 36%; }
        .summary-round { width: 28%; }

        .panel {
            height: 530px;
            border: 1px solid #222;
            border-right: 0;
            overflow: hidden;
            position: relative;
        }

        .summary-round .panel { border-right: 1px solid #222; }

        .panel-title {
            height: 25px;
            border-bottom: 1px solid #222;
            text-align: center;
            font-size: 10.4px;
            font-weight: bold;
            line-height: 24px;
        }

        .score-table {
            table-layout: fixed;
        }

        .score-table th,
        .score-table td {
            height: 22px;
            border-right: 1px solid #222;
            border-bottom: 1px solid #222;
            padding: 1px 4px;
            vertical-align: middle;
        }

        .score-table th:last-child,
        .score-table td:last-child { border-right: 0; }

        .score-table th {
            height: 24px;
            background: #bdbdbd;
            font-size: 8.5px;
            font-weight: bold;
            text-align: left;
        }

        .score-table .subject { width: 60%; }
        .score-table .score { width: 13%; text-align: center; }
        .score-table .coefficient { width: 10%; text-align: center; }
        .score-table .points { width: 17%; text-align: center; }

        .summary-round .score-table .subject { width: 52%; }
        .summary-round .score-table .score { width: 17%; }
        .summary-round .score-table .coefficient { width: 13%; }
        .summary-round .score-table .points { width: 18%; }

        .totals-table {
            table-layout: fixed;
            font-size: 8.5px;
            font-weight: bold;
        }

        .totals-table td {
            height: 24px;
            border-right: 1px solid #222;
            border-bottom: 1px solid #222;
            padding: 2px 4px;
            vertical-align: middle;
        }

        .totals-table td:last-child { border-right: 0; }
        .totals-label { width: 74%; }
        .totals-coefficient { width: 10%; text-align: center; }
        .totals-points { width: 16%; text-align: center; }

        .average-table {
            table-layout: fixed;
            font-size: 8.5px;
            font-weight: bold;
        }

        .average-table td {
            height: 24px;
            border-right: 1px solid #222;
            border-bottom: 1px solid #222;
            padding: 2px 4px;
        }

        .average-table td:last-child { border-right: 0; }

        .decision-block {
            height: 170px;
            padding: 5px 8px;
            text-align: center;
            font-size: 8.6px;
            font-weight: bold;
        }

        .decision-line { min-height: 30px; }

        .decision-label {
            margin-right: 7px;
            text-decoration: underline;
        }

        .jury-date { margin-top: 4px; }
        .jury-title { margin-top: 12px; }
        .jury-name { margin-top: 65px; }

        .first-round .panel > .score-table,
        .summary-round .panel > .score-table,
        .control-top,
        .control-message,
        .retained-table,
        .notice,
        .totals-table,
        .average-table,
        .decision-block,
        .contest-block {
            position: absolute;
            left: 0;
            width: 100%;
        }

        .first-round .panel > .score-table { top: 25px; }
        .totals-table { top: 310px; }
        .average-table { top: 335px; }
        .decision-block { top: 360px; }

        .control-top { top: 25px; height: 80px; }

        .control-message {
            top: 105px;
            height: 210px;
            padding: 136px 28px 0;
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            line-height: 1.55;
        }

        .retained-table { top: 311px; height: 80px; }

        .notice {
            top: 385px;
            height: 135px;
            padding: 2px 35px 0;
            text-align: center;
            font-size: 8.7px;
            line-height: 1.45;
        }

        .contest-block {
            top: 280px;
            height: 250px;
            padding-top: 72px;
            text-align: center;
            font-size: 10px;
            font-weight: bold;
        }

        .contest-title {
            display: inline-block;
            margin-bottom: 10px;
            border-bottom: 1px solid #111;
            font-size: 11px;
        }

        .summary-round .panel > .score-table { top: 25px; }
    </style>
</head>
<body>
    @php
        $subjects = $exam->subjects->sortBy('position')->values();
        $minimumRows = 10;
        $subjectRows = max($minimumRows, $subjects->count());
        $academicYearNumbers = [];
        preg_match_all('/\d{4}/', (string) $exam->academicYear?->name, $academicYearNumbers);
        $sessionYear = $exam->ends_on?->format('Y')
            ?? $exam->starts_on?->format('Y')
            ?? $exam->academicYear?->ends_on?->format('Y')
            ?? collect($academicYearNumbers[0] ?? [])->last()
            ?? $exam->academicYear?->name;
        $principalName = $school?->principal_name ?: 'Yamdaogo TINTILA';
        $city = $school?->city ?: 'Ouagadougou';

        $formatNumber = static function ($value, bool $keepDecimals = true): string {
            if ($value === null) {
                return '--';
            }

            $number = (float) $value;

            if (! $keepDecimals && floor($number) === $number) {
                return number_format($number, 0, '.', '');
            }

            return number_format($number, 2, '.', '');
        };

        $recapSubjects = $subjects
            ->map(fn ($subject) => trim((string) $subject->subject?->name))
            ->reject(fn (string $name) => $name === '' || $name === '-')
            ->reduce(function ($labels, string $name) {
                $normalized = str($name)->ascii()->lower()->toString();

                if (str_contains($normalized, 'langue-comprehension') || $normalized === 'expression') {
                    if (! in_array('Français', $labels, true)) {
                        $labels[] = 'Français';
                    }

                    return $labels;
                }

                if (! in_array($name, $labels, true)) {
                    $labels[] = $name;
                }

                return $labels;
            }, []);
    @endphp

    @foreach ($items as $item)
        @php
            $candidate = $item['candidate'];
            $student = $candidate->student;
            $decisionText = match ($item['decision_key']) {
                'admitted' => 'Admis(e) à l\'issue des épreuves du premier tour',
                'second_round' => 'Autorisé(e) à subir les épreuves du second tour',
                'rejected' => 'Ajourné(e) à l\'issue des épreuves du premier tour',
                default => $item['decision'],
            };
        @endphp

        <div class="page">
            <div class="header">
                <div class="header-logo">
                    @include('pdf.partials.logo-with-motto', [
                        'school' => $school,
                        'logoWidth' => 105,
                        'mottoSize' => 10,
                    ])
                </div>
                <div class="header-school">
                    <div class="school-name">{{ str($school?->school_name ?? 'Lycée Privé Pagnidibsom')->upper() }}</div>
                    <div class="school-details">
                        {{ str($school?->address ?? '04 Ouagadougou 04 BP 8825')->upper() }}<br>
                        Tél : {{ $school?->phone ?? '(+226) 72 81 61 59 / 78 42 62 06' }}<br>
                        E-mail : {{ $school?->email ?? 'infoslyceepagnidibsom@gmail.com' }}
                    </div>
                </div>
                <div class="header-session">
                    {{ str($exam->name)->upper() }}<br>
                    Session de : {{ $sessionYear }}
                </div>
            </div>

            <div class="document-title">RELEVE DE NOTES</div>

            <div class="identity">
                <div class="identity-row">
                    <span class="identity-label">Établissement d'origine :</span>
                    {{ str($school?->school_name ?? 'Lycée Privé Pagnidibsom')->upper() }}
                </div>
                <div class="identity-row">
                    <span class="identity-label">Nom et prénom(s):</span>
                    {{ $student?->full_name }}
                </div>
                <div class="identity-row">
                    <span class="identity-birth-date">
                        <span class="identity-label">Né(e) le :</span>
                        {{ $student?->birth_date?->format('d/m/Y') ?? '-' }}
                    </span>
                    à&nbsp;&nbsp;&nbsp; {{ $student?->birth_place ?: '-' }}
                    <span class="identity-pv">PV : {{ $item['pv_number'] }}</span>
                </div>
            </div>

            <table class="results-grid">
                <tr>
                    <td class="first-round">
                        <div class="panel">
                            <div class="panel-title">Epreuves du premier tour</div>
                            <table class="score-table">
                                <thead>
                                    <tr>
                                        <th class="subject">MATIERES</th>
                                        <th class="score">/20</th>
                                        <th class="coefficient">COEF</th>
                                        <th class="points">POND</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @for ($rowIndex = 0; $rowIndex < $subjectRows; $rowIndex++)
                                        @php
                                            $subject = $subjects->get($rowIndex);
                                            $score = $subject ? $item['scores']->get($subject->id) : null;
                                            $normalized = (! $score || $score->is_absent || $score->score === null || ! $subject)
                                                ? null
                                                : ((float) $score->score / (float) $subject->max_score) * 20;
                                            $points = ($normalized === null || ! $subject)
                                                ? null
                                                : $normalized * (float) $subject->coefficient;
                                        @endphp
                                        <tr>
                                            <td>{{ $subject?->subject?->name ?? '' }}</td>
                                            <td class="score">
                                                @if ($score?->is_absent)
                                                    ABS
                                                @elseif ($subject)
                                                    {{ $formatNumber($normalized) }}
                                                @endif
                                            </td>
                                            <td class="coefficient">{{ $subject ? $formatNumber($subject->coefficient, false) : '' }}</td>
                                            <td class="points">{{ $subject ? $formatNumber($points) : '' }}</td>
                                        </tr>
                                    @endfor
                                </tbody>
                            </table>

                            <table class="totals-table">
                                <tr>
                                    <td class="totals-label">TOTAL PREMIER TOUR</td>
                                    <td class="totals-coefficient">{{ $formatNumber($item['used_coefficients'], false) }}</td>
                                    <td class="totals-points">{{ $formatNumber($item['weighted_total']) }}</td>
                                </tr>
                            </table>

                            <table class="average-table">
                                <tr>
                                    <td style="width:74%;">MOYENNE PREMIER TOUR</td>
                                    <td style="width:16%; text-align:center;">{{ $formatNumber($item['average']) }}</td>
                                    <td style="width:10%; text-align:center;">20</td>
                                </tr>
                            </table>

                            <div class="decision-block">
                                <div class="decision-line">
                                    <span class="decision-label">Décision du jury</span>{{ $decisionText }}
                                </div>
                                <div class="jury-date">{{ $city }} le {{ now()->format('d/m/Y') }}</div>
                                <div class="jury-title">Le Président du Jury</div>
                                <div class="jury-name">{{ $principalName }}</div>
                            </div>
                        </div>
                    </td>

                    <td class="control-round">
                        <div class="panel">
                            <div class="panel-title">Epreuves de l'écrit de contrôle</div>
                            <div class="control-top">
                                <table class="score-table">
                                    <thead>
                                        <tr>
                                            <th class="subject">MATIERES</th>
                                            <th class="score">/20</th>
                                            <th class="coefficient">COEF</th>
                                            <th class="points">POND</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach (['Français', 'Mathématiques'] as $controlSubject)
                                            <tr>
                                                <td>{{ $controlSubject }}</td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="control-message">
                                Meilleures notes retenues à l'issue<br>
                                des épreuves de l'écrit de contrôle
                            </div>

                            <div class="retained-table">
                                <table class="score-table">
                                    <thead>
                                        <tr>
                                            <th class="subject">MATIERES</th>
                                            <th class="score">/20</th>
                                            <th class="coefficient">COEF</th>
                                            <th class="points">POND</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach (['Français', 'Mathématiques'] as $controlSubject)
                                            <tr>
                                                <td>{{ $controlSubject }}</td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="notice">
                                NB: Le présent relevé n'a pas de valeur<br>
                                d'attestation de niveau; il ne doit<br>
                                comporter ni rature, ni surcharge, sous<br>
                                peine de nullité.
                            </div>
                        </div>
                    </td>

                    <td class="summary-round">
                        <div class="panel">
                            <div class="panel-title">Récapitulatif</div>
                            <table class="score-table">
                                <thead>
                                    <tr>
                                        <th class="subject">MATIERES</th>
                                        <th class="score">/20</th>
                                        <th class="coefficient">COEF</th>
                                        <th class="points">POND</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @for ($rowIndex = 0; $rowIndex < 9; $rowIndex++)
                                        <tr>
                                            <td>{{ $recapSubjects[$rowIndex] ?? '' }}</td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                    @endfor
                                </tbody>
                            </table>

                            <div class="contest-block">
                                <div class="contest-title">Concours scolaires</div><br>
                                Total concours
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
</body>
</html>
