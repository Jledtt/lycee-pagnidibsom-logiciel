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
                    </div>
                </div>

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
            </section>

            <section class="panel" style="margin-top:16px">
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
            </section>
@endsection
