@php
    use App\Support\BulletinNumberFormatter;

    $student = $reportCard->student;
    $schoolClass = $reportCard->schoolClass;
    $academicYear = $reportCard->academicYear;
    $termLabels = [1 => '1er', 2 => '2ème', 3 => '3ème'];
    $termLabel = $termLabels[$termPosition] ?? $termPosition.'ème';
    $rankedCount = $reportCard->class_size_ranked ?? $reportCard->class_size;
    $unrankedCount = $reportCard->class_size_unranked ?? 0;
    $principalName = $school?->principal_name ?: 'Yamdaogo TINTILA';
    $city = $school?->city ?: 'Ouagadougou';
@endphp

<section class="bulletin-page">
    @include('pdf.partials.school-header', [
        'school' => $school,
        'logoSize' => 70,
        'mottoSize' => 8,
        'marginBottom' => 4,
        'rightLines' => [],
        'rightWidth' => 12,
        'schoolNameSize' => 14,
        'schoolInfoSize' => 8,
    ])

    <table class="top-grid">
        <tr>
            <td>
                <strong>Classe :</strong> {{ $schoolClass->name }}<br>
                <strong>Classes redoublées :</strong> {{ $student->repeated_class ?: '-' }}
            </td>
            <td>
                <strong>Année Scolaire :</strong> {{ $academicYear->name }}<br>
                <strong>Effectif :</strong> C: {{ $rankedCount ?? '-' }} / NC: {{ $unrankedCount }}
            </td>
        </tr>
    </table>

    <table class="identity-grid bulletin-identity">
        <tr>
            <td>
                <strong>Nom et prénom(s) :</strong>
                {{ mb_strtoupper($student->last_name) }} {{ $student->first_name }}
            </td>
            <td><strong>Matricule :</strong> {{ $student->matricule ?: '-' }}</td>
        </tr>
        <tr>
            <td>
                <strong>Né(e) le :</strong> {{ $student->birth_date?->format('d/m/Y') ?? '-' }}
                <strong>à</strong> {{ $student->birth_place ?: '-' }}
            </td>
            <td>
                @if ($student->is_scholarship)
                    <strong>Boursier</strong>
                @endif
            </td>
        </tr>
    </table>

    <div class="bulletin-title">Bulletin du {{ $termLabel }} Trimestre</div>

    <table class="marks">
        <thead>
            <tr>
                <th class="discipline">Disciplines</th>
                <th>Moy.<br>Devoir</th>
                <th>Moy.<br>Compo</th>
                <th>Moy.<br>Gén.</th>
                <th>Coeff</th>
                <th>Pond.</th>
                <th>Appréciation</th>
                <th>Professeur</th>
                <th>Signature</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($subjectGroups as $group)
                <tr class="group-row">
                    <td colspan="9">{{ mb_strtoupper($group['name']) }}</td>
                </tr>

                @foreach ($group['rows'] as $row)
                    <tr class="subject-row">
                        <td>{{ $row['subject']->name }}</td>
                        <td class="center">{{ BulletinNumberFormatter::decimal($row['devoir_average']) }}</td>
                        <td class="center">{{ BulletinNumberFormatter::decimal($row['composition_average']) }}</td>
                        <td class="center strong">{{ BulletinNumberFormatter::decimal($row['average']) }}</td>
                        <td class="center">{{ BulletinNumberFormatter::coefficient($row['coefficient']) }}</td>
                        <td class="center strong">{{ BulletinNumberFormatter::decimal($row['points']) }}</td>
                        <td>{{ $row['appreciation'] }}</td>
                        <td>{{ $row['teacher'] }}</td>
                        <td></td>
                    </tr>
                @endforeach

                <tr class="group-summary">
                    <td colspan="9">
                        Bilan {{ $group['name'] }} :
                        <strong>{{ BulletinNumberFormatter::decimal($group['average']) }}/20</strong>
                    </td>
                </tr>
            @endforeach

            <tr class="total-row">
                <td colspan="4">Totaux</td>
                <td class="center">{{ BulletinNumberFormatter::coefficient($totalCoefficients) }}</td>
                <td class="center">{{ BulletinNumberFormatter::decimal($totalPoints) }}</td>
                <td colspan="3"></td>
            </tr>
            <tr class="information-row">
                <td colspan="4"><strong>Absence :</strong> {{ BulletinNumberFormatter::decimal($reportCard->absence_hours ?? 0) }}</td>
                <td colspan="5"><strong>Conduite :</strong> {{ $reportCard->conduct ?: '-' }}</td>
            </tr>
        </tbody>
    </table>

    <table class="term-summary-grid">
        <tr>
            <td><strong>Total pondéré :</strong> {{ BulletinNumberFormatter::decimal($totalPoints) }}</td>
            <td><strong>Moyenne trimestrielle :</strong> {{ BulletinNumberFormatter::decimal($reportCard->general_average) }}</td>
            <td>
                <strong>Rang :</strong>
                {{ $reportCard->rank_label ? $reportCard->rank_label.' / '.($rankedCount ?? '-') : '-' }}
            </td>
            <td><strong>Appréciation générale :</strong> {{ $reportCard->appreciation ?: '-' }}</td>
        </tr>
    </table>

    @if ($termPosition >= 2)
        <table class="recall-grid">
            <tr>
                <th>Rappel(s)</th>
                @foreach ($recalls as $recall)
                    <td>
                        Moyenne du {{ $recall['position'] === 1 ? '1er' : $recall['position'].'ème' }} Trimestre :
                        <strong>{{ BulletinNumberFormatter::decimal($recall['average']) }}</strong>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    @if ($termPosition >= 3 && $annualSummary)
        <table class="annual-grid">
            <tr>
                <td>
                    <strong>Plus forte moyenne :</strong> {{ BulletinNumberFormatter::decimal($classStats['highest']) }}<br>
                    <strong>Moyenne la plus basse :</strong> {{ BulletinNumberFormatter::decimal($classStats['lowest']) }}
                </td>
                <td>
                    <strong>Moyenne Annuelle :</strong> {{ BulletinNumberFormatter::decimal($annualSummary['annual_average']) }}<br>
                    <strong>Classement Annuel :</strong>
                    {{ $annualSummary['annual_rank_label'] ? $annualSummary['annual_rank_label'].' / '.$annualSummary['class_size'] : '-' }}<br>
                    <strong>Moyenne Annuelle de la classe :</strong> {{ BulletinNumberFormatter::decimal($annualSummary['annual_class_average']) }}<br>
                    <strong>Meilleure moyenne annuelle :</strong> {{ BulletinNumberFormatter::decimal($annualSummary['highest_annual_average']) }}
                    @if (($annualSummary['terms_count'] ?? 0) < 3 && ($annualSummary['terms_count'] ?? 0) > 0)
                        <span class="annual-note">(calculée sur {{ $annualSummary['terms_count'] }} trimestres)</span>
                    @endif
                </td>
                <td class="annual-decision">
                    <span>Décision de fin d'année</span>
                    <strong>{{ $annualSummary['decision'] ?: '-' }}</strong>
                </td>
            </tr>
        </table>
    @endif

    <div class="principal-observation">
        <strong>Observation du proviseur :</strong>
        {{ $reportCard->principal_observation ?: '-' }}
    </div>

    <table class="footer-grid">
        <tr>
            <td class="sanctions">
                <div class="section-label">SANCTIONS</div>
                <table class="sanctions-table">
                    @foreach (\App\Models\ReportCard::distinctions() as $distinction)
                        <tr>
                            <td>{{ $distinction }}</td>
                            <td class="check-cell"><span class="check-box">{{ $reportCard->distinction === $distinction ? 'X' : '' }}</span></td>
                        </tr>
                    @endforeach
                </table>
            </td>
            <td class="signature">
                {{ $city }}, le {{ $generatedAt->format('d/m/Y') }}<br>
                <span class="principal-title">Le Proviseur</span>
                <div class="signature-space"></div>
                <strong>{{ $principalName }}</strong>
            </td>
        </tr>
    </table>

    <div class="bulletin-note">
        Il n'est délivré qu'un seul bulletin. Il appartient au titulaire d'en faire des copies certifiées conformes.<br>
        <strong>« {{ trim($school?->motto ?: "Bâtir l'excellence", '" ') }} »</strong>
    </div>
</section>
