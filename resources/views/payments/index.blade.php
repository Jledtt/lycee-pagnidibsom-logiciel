@extends('layouts.app', [
    'title' => 'Paiements - Lycee Prive Pagnidibsom',
    'active' => 'payments',
    'pageTitle' => 'Paiements',
    'pageSubtitle' => 'Encaissements, recus et suivi de caisse pour ' . ($academicYear?->name ?? 'l\'annee active'),
])

@section('page_actions')
    @can('payments.reports')
        <a class="btn btn-subtle" href="{{ route('accounting.cash-journal') }}">Journal de caisse</a>
        <a class="btn btn-subtle" href="{{ route('accounting.expenses.index') }}">Depenses</a>
        <a class="btn btn-subtle" href="{{ route('reports.installments') }}">Tranches</a>
        <a class="btn btn-subtle" href="{{ route('payments.unpaid') }}">Impayes</a>
    @endcan
    @can('payments.create')
        <a class="btn btn-primary" href="{{ route('payments.create') }}">Nouveau paiement</a>
    @endcan
@endsection

@section('content')
    <section class="summary-row">
        <div class="stat">
            <span>Total encaisse</span>
            <strong class="money">{{ number_format($totalPaid, 0, ',', ' ') }} FCFA</strong>
        </div>
        <div class="stat">
            <span>Paiements affiches</span>
            <strong>{{ $payments->total() }}</strong>
        </div>
        <div class="stat">
            <span>Annee scolaire</span>
            <strong>{{ $academicYear?->name ?? '-' }}</strong>
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Recherche</h2>
        </div>

        <form class="searchbar" method="GET" action="{{ route('payments.index') }}">
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Recu, eleve ou matricule">
            <select name="status">
                <option value="">Tous les statuts</option>
                <option value="valid" @selected(($filters['status'] ?? '') === 'valid')>Valides</option>
                <option value="cancelled" @selected(($filters['status'] ?? '') === 'cancelled')>Annules</option>
            </select>
            <button class="btn btn-subtle" type="submit">Filtrer</button>
            <a class="btn btn-subtle" href="{{ route('payments.index') }}">Reinitialiser</a>
        </form>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Liste des paiements</h2>
            <span class="badge">{{ $payments->total() }} paiement(s)</span>
        </div>

        @if ($payments->isEmpty())
            <div class="empty">Aucun paiement enregistre pour le moment.</div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Recu</th>
                        <th>Date</th>
                        <th>Eleve</th>
                        <th>Classe</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td><strong>{{ $payment->receipt_number }}</strong></td>
                            <td>{{ $payment->paid_at?->format('d/m/Y H:i') }}</td>
                            <td>
                                {{ $payment->student->full_name }}<br>
                                <a class="badge" href="{{ route('payments.students.statement', $payment->student) }}">Situation</a>
                            </td>
                            <td>{{ $payment->enrollment?->schoolClass?->name ?? '-' }}</td>
                            <td class="money">{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</td>
                            <td><span class="badge {{ $payment->status === 'valid' ? '' : 'badge-warning' }}">{{ $payment->status }}</span></td>
                            <td><a class="btn btn-subtle" href="{{ route('payments.show', $payment) }}">Voir</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="pagination">
                {{ $payments->links() }}
            </div>
        @endif
    </section>
@endsection
