@extends('layouts.app', [
    'title' => 'Tableau de bord - Lycee Prive Pagnidibsom',
    'active' => 'dashboard',
    'pageTitle' => 'Tableau de bord',
    'pageSubtitle' => "Vue administrative de l'annee " . ($academicYear?->name ?? 'active'),
])

@section('content')
            <section class="grid stats">
                <div class="stat">
                    <span>Eleves actifs</span>
                    <strong>{{ number_format($stats['students'], 0, ',', ' ') }}</strong>
                </div>
                <div class="stat">
                    <span>Classes</span>
                    <strong>{{ number_format($stats['classes'], 0, ',', ' ') }}</strong>
                </div>
                <div class="stat">
                    <span>Inscriptions</span>
                    <strong>{{ number_format($stats['enrollments'], 0, ',', ' ') }}</strong>
                </div>
                <div class="stat">
                    <span>Encaissements</span>
                    <strong>{{ number_format($stats['payments'], 0, ',', ' ') }} FCFA</strong>
                </div>
                <div class="stat">
                    <span>Absences du jour</span>
                    <strong>{{ number_format($stats['absences_today'], 0, ',', ' ') }}</strong>
                </div>
            </section>

            <section class="grid stats" style="margin-top:16px">
                <div class="stat">
                    <span>Encaisse aujourd'hui</span>
                    <strong class="money">{{ number_format($financeAlerts['today_paid'], 0, ',', ' ') }} FCFA</strong>
                </div>
                <div class="stat">
                    <span>Paiements du jour</span>
                    <strong>{{ number_format($financeAlerts['today_count'], 0, ',', ' ') }}</strong>
                </div>
                <div class="stat">
                    <span>Reste total estime</span>
                    <strong class="money">{{ number_format($financeAlerts['remaining'], 0, ',', ' ') }} FCFA</strong>
                </div>
                <div class="stat">
                    <span>Classes pointees</span>
                    <strong>{{ $attendanceAlerts['classes_pointed'] }} / {{ $stats['classes'] }}</strong>
                </div>
                <div class="stat">
                    <span>Bulletins en brouillon</span>
                    <strong>{{ number_format($academicAlerts['bulletins_pending'], 0, ',', ' ') }}</strong>
                </div>
            </section>

            <section class="grid two-col">
                <div class="panel">
                    <div class="panel-head">
                        <h2>Modules principaux</h2>
                    </div>

                    <div class="grid modules">
                        @can('students.view')
                            <a class="module" href="{{ route('students.index') }}">
                                <strong>Eleves</strong>
                                <span>Dossiers, parents, documents et historique.</span>
                            </a>
                        @endcan
                        @can('classes.manage')
                            <a class="module" href="{{ route('classes.index') }}">
                                <strong>Classes</strong>
                                <span>Niveaux, capacites, effectifs et affectation des eleves.</span>
                            </a>
                        @endcan
                        @can('enrollments.view')
                            <a class="module" href="{{ route('enrollments.index') }}">
                                <strong>Inscriptions</strong>
                                <span>Nouvelle inscription, reinscription et affectation.</span>
                            </a>
                        @endcan
                        @can('payments.view')
                            <a class="module" href="{{ route('payments.index') }}">
                                <strong>Paiements</strong>
                                <span>Scolarite, recus, impayes et rapports de caisse.</span>
                            </a>
                        @endcan
                        @can('attendance.view')
                            <a class="module" href="{{ route('attendance.index') }}">
                                <strong>Absences</strong>
                                <span>Appel par classe, retards, justificatifs et suivi quotidien.</span>
                            </a>
                        @endcan
                        @can('grades.view')
                            <a class="module" href="{{ route('grades.index') }}">
                                <strong>Notes</strong>
                                <span>Evaluations, saisie des notes et suivi par trimestre.</span>
                            </a>
                        @endcan
                        @can('report_cards.view')
                            <a class="module" href="{{ route('report-cards.index') }}">
                                <strong>Bulletins</strong>
                                <span>Moyennes, rangs et bulletins imprimables par eleve.</span>
                            </a>
                        @endcan
                        @can('payments.reports')
                            <a class="module" href="{{ route('accounting.cash-journal') }}">
                                <strong>Comptabilite</strong>
                                <span>Journal de caisse, depenses, bilan et controles.</span>
                            </a>
                        @endcan
                        @can('settings.manage')
                            <a class="module" href="{{ route('tariffs.index') }}">
                                <strong>Tarifs</strong>
                                <span>Montants par classe, tranches et frais annexes.</span>
                            </a>
                            <a class="module" href="{{ route('subjects.index') }}">
                                <strong>Matieres</strong>
                                <span>Matieres enseignees, coefficients et activation par classe.</span>
                            </a>
                        @endcan
                        @can('students.export')
                            <a class="module" href="{{ route('certificates.index') }}">
                                <strong>Documents</strong>
                                <span>Certificats, attestations et documents administratifs.</span>
                            </a>
                        @endcan
                        @canany(['students.export', 'payments.reports'])
                            <a class="module" href="{{ auth()->user()->can('students.export') ? route('reports.class-list') : route('reports.payment-situation') }}">
                                <strong>Rapports</strong>
                                <span>Listes imprimables par classe et exports administratifs.</span>
                            </a>
                        @endcanany
                        @can('users.manage')
                            <a class="module" href="{{ route('staff.index') }}">
                                <strong>Personnel</strong>
                                <span>Comptes utilisateurs, roles et acces internes.</span>
                            </a>
                        @endcan
                        @can('academic_years.manage')
                            <a class="module" href="{{ route('academic-years.index') }}">
                                <strong>Annees scolaires</strong>
                                <span>Activation des annees, trimestres et clotures.</span>
                            </a>
                        @endcan
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <h2>Finances rapides</h2>
                        @can('payments.reports')
                            <a class="btn btn-subtle" href="{{ route('payments.unpaid') }}">Impayes</a>
                        @endcan
                    </div>

                    <div class="summary-row">
                        <div class="detail-item">
                            <span>Attendu</span>
                            <strong class="money">{{ number_format($financeAlerts['expected'], 0, ',', ' ') }} FCFA</strong>
                        </div>
                        <div class="detail-item">
                            <span>Paye</span>
                            <strong class="money">{{ number_format($financeAlerts['paid'], 0, ',', ' ') }} FCFA</strong>
                        </div>
                        <div class="detail-item">
                            <span>Eleves avec reste</span>
                            <strong>{{ number_format($financeAlerts['unpaid_count'], 0, ',', ' ') }}</strong>
                        </div>
                    </div>

                    <div class="panel-head" style="margin-top:16px">
                        <h2>Plus gros restes</h2>
                    </div>

                    @if ($financeAlerts['top_unpaid']->isEmpty())
                        <div class="empty">Aucun impaye detecte sur les tarifs configures.</div>
                    @else
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Eleve</th>
                                    <th>Classe</th>
                                    <th>Reste</th>
                                    <th></th>
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
                    @endif
                </div>
            </section>

            <section class="grid two-col" style="margin-top:16px">
                <div class="panel">
                    <div class="panel-head">
                        <h2>Vie scolaire aujourd'hui</h2>
                        @can('attendance.view')
                            <a class="btn btn-subtle" href="{{ route('attendance.index') }}">Absences</a>
                        @endcan
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
                            <span>Non pointees</span>
                            <strong>{{ number_format($attendanceAlerts['classes_not_pointed'], 0, ',', ' ') }}</strong>
                        </div>
                    </div>

                    @if ($attendanceAlerts['not_pointed_classes']->isNotEmpty())
                        <div class="panel-head" style="margin-top:16px">
                            <h2>Classes non pointees</h2>
                        </div>
                        <div class="ledger-list">
                            @foreach ($attendanceAlerts['not_pointed_classes'] as $class)
                                <div class="detail-item">
                                    <span>Classe</span>
                                    <strong>{{ $class->name }}</strong>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <h2>Pedagogie</h2>
                        @can('report_cards.view')
                            <a class="btn btn-subtle" href="{{ route('report-cards.index') }}">Bulletins</a>
                        @endcan
                    </div>

                    <div class="summary-row">
                        <div class="detail-item">
                            <span>Evaluations semaine</span>
                            <strong>{{ number_format($academicAlerts['assessments_week'], 0, ',', ' ') }}</strong>
                        </div>
                        <div class="detail-item">
                            <span>Bulletins generes</span>
                            <strong>{{ number_format($academicAlerts['bulletins_generated'], 0, ',', ' ') }}</strong>
                        </div>
                        <div class="detail-item">
                            <span>Evaluations verrouillees</span>
                            <strong>{{ number_format($academicAlerts['locked_assessments'], 0, ',', ' ') }}</strong>
                        </div>
                    </div>

                    <div class="panel-head" style="margin-top:16px">
                        <h2>Alertes configuration</h2>
                    </div>

                    @if ($configurationAlerts['classes_without_tariffs']->isEmpty() && $configurationAlerts['classes_without_subjects']->isEmpty())
                        <div class="empty">Tarifs et matieres semblent configures pour les classes actives.</div>
                    @else
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Classe</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($configurationAlerts['classes_without_tariffs'] as $class)
                                    <tr>
                                        <td><span class="badge badge-warning">Tarifs manquants</span></td>
                                        <td><strong>{{ $class->name }}</strong></td>
                                        <td>
                                            @can('settings.manage')
                                                <a class="btn btn-subtle" href="{{ route('tariffs.edit', $class) }}">Configurer</a>
                                            @else
                                                -
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                                @foreach ($configurationAlerts['classes_without_subjects'] as $class)
                                    <tr>
                                        <td><span class="badge badge-warning">Matieres manquantes</span></td>
                                        <td><strong>{{ $class->name }}</strong></td>
                                        <td>
                                            @can('settings.manage')
                                                <a class="btn btn-subtle" href="{{ route('subjects.index', ['school_class_id' => $class->id]) }}">Configurer</a>
                                            @else
                                                -
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </section>

            <section class="grid two-col" style="margin-top:16px">
                <div class="panel">
                    <div class="panel-head">
                        <h2>Derniers paiements</h2>
                    </div>

                    @if ($recentPayments->isEmpty())
                        <div class="empty">Aucun paiement enregistre pour le moment.</div>
                    @else
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Eleve</th>
                                    <th>Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentPayments as $payment)
                                    <tr>
                                        <td><a href="{{ route('payments.show', $payment) }}"><strong>{{ $payment->student->full_name }}</strong></a></td>
                                        <td>{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <h2>Effectifs par classe</h2>
                    </div>

                    @if ($classes->isEmpty())
                        <div class="empty">Aucune classe configuree pour l'annee active.</div>
                    @else
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Classe</th>
                                    <th>Effectif</th>
                                    <th>Capacite</th>
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
                    @endif
                </div>
            </section>
@endsection
