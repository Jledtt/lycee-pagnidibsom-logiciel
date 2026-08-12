@extends('layouts.app', [
    'title' => 'Tableau de bord - Lycée Privé Pagnidibsom',
    'active' => 'dashboard',
    'pageTitle' => 'Tableau de bord',
    'pageSubtitle' => 'Actions, alertes et suivi de l’année ' . ($academicYear?->name ?? 'active'),
])

@section('page_actions')
    @canany(['students.export', 'payments.reports', 'mock_exams.print', 'report_cards.print', 'attendance.reports'])
        <a class="btn btn-subtle" href="{{ route('print-center.index') }}">Centre d’impression</a>
    @endcanany
@endsection

@section('content')
    <section class="panel">
        <div class="panel-head" data-tour-target="dashboard-quick-actions">
            <div>
                <h2>Actions rapides</h2>
                <p style="margin:4px 0 0;color:var(--muted)">Les opérations les plus fréquentes sont accessibles ici.</p>
            </div>
        </div>

        <div class="quick-actions">
            @can('students.create')
                <a class="action-card" href="{{ route('students.create') }}">
                    <strong>Ajouter élève</strong>
                    <span>Créer un nouveau dossier élève.</span>
                </a>
            @endcan
            @can('students.import')
                <a class="action-card" href="{{ route('students.import') }}">
                    <strong>Importer élèves</strong>
                    <span>Charger une liste CSV/Excel.</span>
                </a>
            @endcan
            @can('enrollments.create')
                <a class="action-card" href="{{ route('enrollments.create') }}">
                    <strong>Inscrire</strong>
                    <span>Affecter un élève à une classe.</span>
                </a>
            @endcan
            @can('payments.create')
                <a class="action-card" href="{{ route('payments.create') }}">
                    <strong>Encaisser</strong>
                    <span>Enregistrer un paiement et reçu.</span>
                </a>
            @endcan
            @can('students.export')
                <a class="action-card" href="{{ route('certificates.create') }}">
                    <strong>Certificat</strong>
                    <span>Générer un document administratif.</span>
                </a>
            @endcan
            @can('attendance.create')
                <a class="action-card" href="{{ route('attendance.index') }}">
                    <strong>Pointage</strong>
                    <span>Présences, absences et retards.</span>
                </a>
            @endcan
            @can('grades.view')
                <a class="action-card" href="{{ route('grades.index') }}">
                    <strong>Saisie notes</strong>
                    <span>Créer une évaluation mensuelle ou saisir les notes.</span>
                </a>
            @endcan
            @can('report_cards.view')
                <a class="action-card" href="{{ route('report-cards.index') }}">
                    <strong>Bulletins</strong>
                    <span>Générer ou imprimer les bulletins.</span>
                </a>
            @endcan
            @can('mock_exams.view')
                <a class="action-card" href="{{ route('mock-exams.index') }}">
                    <strong>Examens blancs</strong>
                    <span>Candidats, anonymats, PV et résultats.</span>
                </a>
            @endcan
        </div>
    </section>

    @canany(['students.view', 'classes.manage', 'enrollments.view', 'payments.reports', 'attendance.view', 'settings.manage', 'grades.view', 'report_cards.view'])
        <section class="grid two-col">
            <div class="panel">
                <div class="panel-head" data-tour-target="dashboard-alerts">
                    <h2>Alertes importantes</h2>
                    <span class="badge">À traiter</span>
                </div>

                <div class="alert-stack">
                    @can('payments.reports')
                        @if ($financeAlerts['unpaid_count'] > 0)
                            <div class="alert-item">
                                <div>
                                    <strong>{{ number_format($financeAlerts['unpaid_count'], 0, ',', ' ') }} élève(s) avec reste à payer</strong>
                                    <span>Reste estimé : {{ number_format($financeAlerts['remaining'], 0, ',', ' ') }} FCFA.</span>
                                </div>
                                <a class="btn btn-subtle" href="{{ route('payments.unpaid') }}">Voir</a>
                            </div>
                        @endif
                    @endcan

                    @can('attendance.view')
                        @if ($attendanceAlerts['absences_today'] > 0 || $attendanceAlerts['late_today'] > 0)
                            <div class="alert-item">
                                <div>
                                    <strong>Vie scolaire du jour</strong>
                                    <span>{{ $attendanceAlerts['absences_today'] }} absent(s), {{ $attendanceAlerts['late_today'] }} retard(s).</span>
                                </div>
                                <a class="btn btn-subtle" href="{{ route('attendance.index') }}">Voir</a>
                            </div>
                        @endif

                        @if ($attendanceAlerts['classes_not_pointed'] > 0)
                            <div class="alert-item">
                                <div>
                                    <strong>{{ $attendanceAlerts['classes_not_pointed'] }} classe(s) non pointée(s)</strong>
                                    <span>Le pointage du jour n’est pas complet.</span>
                                </div>
                                <a class="btn btn-subtle" href="{{ route('attendance.index') }}">Pointer</a>
                            </div>
                        @endif
                    @endcan

                    @can('report_cards.view')
                        @if ($academicAlerts['bulletins_pending'] > 0)
                            <div class="alert-item">
                                <div>
                                    <strong>{{ number_format($academicAlerts['bulletins_pending'], 0, ',', ' ') }} bulletin(s) en brouillon</strong>
                                    <span>À vérifier avant validation ou impression.</span>
                                </div>
                                <a class="btn btn-subtle" href="{{ route('report-cards.index') }}">Ouvrir</a>
                            </div>
                        @endif
                    @endcan

                    @can('settings.manage')
                        @if ($configurationAlerts['classes_without_tariffs_count'] > 0)
                            <div class="alert-item">
                                <div>
                                    <strong>{{ $configurationAlerts['classes_without_tariffs_count'] }} classe(s) sans tarif</strong>
                                    <span>Les états financiers seront incomplets tant que les tarifs ne sont pas configurés.</span>
                                </div>
                                <a class="btn btn-subtle" href="{{ route('tariffs.index') }}">Configurer</a>
                            </div>
                        @endif

                        @if ($configurationAlerts['classes_without_subjects']->isNotEmpty())
                            <div class="alert-item">
                                <div>
                                    <strong>{{ $configurationAlerts['classes_without_subjects']->count() }} classe(s) sans matières</strong>
                                    <span>Les notes et bulletins ont besoin des matières et coefficients.</span>
                                </div>
                                <a class="btn btn-subtle" href="{{ route('subjects.index') }}">Configurer</a>
                            </div>
                        @endif
                    @endcan

                    @if (
                        (! auth()->user()->can('payments.reports') || $financeAlerts['unpaid_count'] === 0)
                        && (! auth()->user()->can('attendance.view') || ($attendanceAlerts['absences_today'] === 0 && $attendanceAlerts['late_today'] === 0 && $attendanceAlerts['classes_not_pointed'] === 0))
                        && (! auth()->user()->can('report_cards.view') || $academicAlerts['bulletins_pending'] === 0)
                        && (! auth()->user()->can('settings.manage') || ($configurationAlerts['classes_without_tariffs_count'] === 0 && $configurationAlerts['classes_without_subjects']->isEmpty()))
                    )
                        <div class="empty">Aucune alerte urgente pour le moment.</div>
                    @endif
                </div>
            </div>

            <div class="panel">
                <div class="panel-head" data-tour-target="dashboard-indicators">
                    <h2>Chiffres utiles</h2>
                    <span class="badge">{{ $academicYear?->name ?? 'Année active' }}</span>
                </div>

                <div class="summary-row">
                    @can('students.view')
                        <div class="detail-item">
                            <span>Élèves actifs</span>
                            <strong>{{ number_format($stats['students'], 0, ',', ' ') }}</strong>
                        </div>
                    @endcan
                    @can('classes.manage')
                        <div class="detail-item">
                            <span>Classes</span>
                            <strong>{{ number_format($stats['classes'], 0, ',', ' ') }}</strong>
                        </div>
                    @endcan
                    @can('enrollments.view')
                        <div class="detail-item">
                            <span>Inscriptions</span>
                            <strong>{{ number_format($stats['enrollments'], 0, ',', ' ') }}</strong>
                        </div>
                    @endcan
                    @can('payments.reports')
                        <div class="detail-item">
                            <span>Encaisse du jour</span>
                            <strong class="money">{{ number_format($financeAlerts['today_paid'], 0, ',', ' ') }} FCFA</strong>
                        </div>
                    @endcan
                    @can('attendance.view')
                        <div class="detail-item">
                            <span>Absences du jour</span>
                            <strong>{{ number_format($stats['absences_today'], 0, ',', ' ') }}</strong>
                        </div>
                    @endcan
                    @can('grades.view')
                        <div class="detail-item">
                            <span>Évaluations semaine</span>
                            <strong>{{ number_format($academicAlerts['assessments_week'], 0, ',', ' ') }}</strong>
                        </div>
                    @endcan
                </div>
            </div>
        </section>
    @endcanany

    @can('payments.reports')
        <section class="grid stats finance-stats" style="margin-top:16px">
            <div class="stat">
                <span>Total attendu</span>
                <strong class="money">
                    <span class="money-amount">{{ number_format($financeAlerts['expected'], 0, ',', ' ') }}</span>
                    <span class="money-currency">FCFA</span>
                </strong>
            </div>
            <div class="stat">
                <span>Total payé</span>
                <strong class="money">
                    <span class="money-amount">{{ number_format($financeAlerts['paid'], 0, ',', ' ') }}</span>
                    <span class="money-currency">FCFA</span>
                </strong>
            </div>
            <div class="stat">
                <span>Reste estimé</span>
                <strong class="money">
                    <span class="money-amount">{{ number_format($financeAlerts['remaining'], 0, ',', ' ') }}</span>
                    <span class="money-currency">FCFA</span>
                </strong>
            </div>
            <div class="stat">
                <span>Paiements du jour</span>
                <strong>{{ number_format($financeAlerts['today_count'], 0, ',', ' ') }}</strong>
            </div>
            <div class="stat">
                <span>Élèves avec reste</span>
                <strong>{{ number_format($financeAlerts['unpaid_count'], 0, ',', ' ') }}</strong>
            </div>
        </section>
    @endcan

    <section class="grid two-col">
        @can('payments.reports')
            <div class="panel">
                <div class="panel-head">
                    <h2>Plus gros restes à payer</h2>
                    <a class="btn btn-subtle" href="{{ route('payments.unpaid') }}">Tous les impayés</a>
                </div>

                @if ($financeAlerts['top_unpaid']->isEmpty())
                    <div class="empty">Aucun impayé détecté sur les tarifs configurés.</div>
                @else
                    <div class="table-scroll">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Élève</th>
                                    <th>Classe</th>
                                    <th>Reste</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($financeAlerts['top_unpaid'] as $row)
                                    <tr>
                                        <td><strong>{{ $row['student']?->full_name }}</strong></td>
                                        <td>{{ $row['class']?->name ?? '-' }}</td>
                                        <td class="money">{{ number_format($row['balance'], 0, ',', ' ') }} FCFA</td>
                                        <td>
                                            @can('payments.view')
                                                <a class="btn btn-subtle" href="{{ route('payments.students.statement', $row['student']) }}">Voir</a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endcan

        @can('attendance.view')
            <div class="panel">
                <div class="panel-head">
                    <h2>Vie scolaire aujourd’hui</h2>
                    <a class="btn btn-subtle" href="{{ route('attendance.index') }}">Ouvrir</a>
                </div>

                <div class="summary-row">
                    <div class="detail-item">
                        <span>Absents</span>
                        <strong>{{ number_format($attendanceAlerts['absences_today'], 0, ',', ' ') }}</strong>
                    </div>
                    <div class="detail-item">
                        <span>Retards</span>
                        <strong>{{ number_format($attendanceAlerts['late_today'], 0, ',', ' ') }}</strong>
                    </div>
                    <div class="detail-item">
                        <span>Classes pointées</span>
                        <strong>{{ $attendanceAlerts['classes_pointed'] }} / {{ $stats['classes'] }}</strong>
                    </div>
                </div>

                @if ($attendanceAlerts['not_pointed_classes']->isNotEmpty())
                    <div class="ledger-list" style="margin-top:14px">
                        @foreach ($attendanceAlerts['not_pointed_classes'] as $class)
                            <div class="detail-item">
                                <span>Classe non pointée</span>
                                <strong>{{ $class->name }}</strong>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endcan

        @canany(['classes.manage', 'students.view'])
            <div class="panel">
                <div class="panel-head">
                    <h2>Effectifs par classe</h2>
                    @can('classes.manage')
                        <a class="btn btn-subtle" href="{{ route('classes.index') }}">Classes</a>
                    @endcan
                </div>

                @if ($classes->isEmpty())
                    <div class="empty">Aucune classe configurée pour l’année active.</div>
                @else
                    <div class="table-scroll">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Classe</th>
                                    <th>Effectif</th>
                                    <th>Capacité</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($classes as $class)
                                    <tr>
                                        <td>
                                            @can('classes.manage')
                                                <a href="{{ route('classes.show', $class) }}"><strong>{{ $class->name }}</strong></a>
                                            @else
                                                <strong>{{ $class->name }}</strong>
                                            @endcan
                                        </td>
                                        <td>{{ $class->enrollments_count }}</td>
                                        <td>{{ $class->capacity ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endcanany

        @can('payments.view')
            <div class="panel">
                <div class="panel-head">
                    <h2>Derniers paiements</h2>
                    <a class="btn btn-subtle" href="{{ route('payments.index') }}">Paiements</a>
                </div>

                @if ($recentPayments->isEmpty())
                    <div class="empty">Aucun paiement enregistré pour le moment.</div>
                @else
                    <div class="table-scroll">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Élève</th>
                                    <th>Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentPayments as $payment)
                                    <tr>
                                        <td><a href="{{ route('payments.show', $payment) }}"><strong>{{ $payment->student->full_name }}</strong></a></td>
                                        <td class="money">{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endcan

        @canany(['grades.view', 'report_cards.view', 'settings.manage'])
            <div class="panel">
                <div class="panel-head">
                    <h2>Notes et bulletins</h2>
                    @can('report_cards.view')
                        <a class="btn btn-subtle" href="{{ route('report-cards.index') }}">Bulletins</a>
                    @endcan
                </div>

                <div class="summary-row">
                    @can('grades.view')
                        <div class="detail-item">
                            <span>Évaluations semaine</span>
                            <strong>{{ number_format($academicAlerts['assessments_week'], 0, ',', ' ') }}</strong>
                        </div>
                        <div class="detail-item">
                            <span>Évaluations verrouillées</span>
                            <strong>{{ number_format($academicAlerts['locked_assessments'], 0, ',', ' ') }}</strong>
                        </div>
                    @endcan
                    @can('report_cards.view')
                        <div class="detail-item">
                            <span>Bulletins générés</span>
                            <strong>{{ number_format($academicAlerts['bulletins_generated'], 0, ',', ' ') }}</strong>
                        </div>
                    @endcan
                </div>

                @can('settings.manage')
                    @if ($configurationAlerts['classes_without_tariffs']->isNotEmpty() || $configurationAlerts['classes_without_subjects']->isNotEmpty())
                        <div class="table-scroll" style="margin-top:14px">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Classe</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($configurationAlerts['classes_without_tariffs'] as $class)
                                        <tr>
                                            <td><span class="badge badge-warning">Tarifs manquants</span></td>
                                            <td><strong>{{ $class->name }}</strong></td>
                                            <td><a class="btn btn-subtle" href="{{ route('tariffs.edit', $class) }}">Configurer</a></td>
                                        </tr>
                                    @endforeach
                                    @foreach ($configurationAlerts['classes_without_subjects'] as $class)
                                        <tr>
                                            <td><span class="badge badge-warning">Matières manquantes</span></td>
                                            <td><strong>{{ $class->name }}</strong></td>
                                            <td><a class="btn btn-subtle" href="{{ route('subjects.index', ['school_class_id' => $class->id]) }}">Configurer</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endcan
            </div>
        @endcanany
    </section>
@endsection
