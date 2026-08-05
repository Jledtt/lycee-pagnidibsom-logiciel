@extends('layouts.app', [
    'title' => 'Paiements - Lycée Privé Pagnidibsom',
    'active' => 'payments',
    'pageTitle' => 'Paiements',
    'pageSubtitle' => 'Encaissements, reçus et suivi de caisse pour ' . ($academicYear?->name ?? 'l\'année active'),
])

@section('page_actions')
    @can('payments.reports')
        <a class="btn btn-subtle" href="{{ route('accounting.cash-journal') }}">Journal de caisse</a>
        <a class="btn btn-subtle" href="{{ route('accounting.expenses.index') }}">Dépenses</a>
        <a class="btn btn-subtle" href="{{ route('reports.installments') }}">Tranches</a>
        <a class="btn btn-subtle" href="{{ route('payments.unpaid') }}">Impayés</a>
        <a class="btn btn-subtle" href="{{ route('payments.export', request()->query()) }}" data-download-feedback="Téléchargement Excel des paiements lancé. Regarde l’icône de téléchargement du navigateur.">Excel</a>
    @endcan
    @can('payments.create')
        <a class="btn btn-primary" href="{{ route('payments.create') }}" data-dialog-open="payment-create-dialog">Nouveau paiement</a>
    @endcan
@endsection

@section('content')
    <section class="summary-row">
        <div class="stat">
            <span>Total encaisse</span>
            <strong class="money">{{ number_format($totalPaid, 0, ',', ' ') }} FCFA</strong>
        </div>
        <div class="stat">
            <span>Paiements affichés</span>
            <strong>{{ $payments->total() }}</strong>
        </div>
        <div class="stat">
            <span>Année scolaire</span>
            <strong>{{ $academicYear?->name ?? '-' }}</strong>
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Recherche</h2>
        </div>

        <form class="searchbar" method="GET" action="{{ route('payments.index') }}">
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Reçu, élève ou matricule">
            <select name="status">
                <option value="">Tous les statuts</option>
                <option value="valid" @selected(($filters['status'] ?? '') === 'valid')>Valides</option>
                <option value="cancelled" @selected(($filters['status'] ?? '') === 'cancelled')>Annules</option>
            </select>
            <button class="btn btn-subtle" type="submit">Filtrer</button>
            <a class="btn btn-subtle" href="{{ route('payments.index') }}">Réinitialiser</a>
        </form>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Liste des paiements</h2>
            <span class="badge">{{ $payments->total() }} paiement(s)</span>
        </div>

        @if ($payments->isEmpty())
            <div class="empty">Aucun paiement enregistré pour le moment.</div>
        @else
            <div class="subject-list-scroll">
            <table class="table" style="min-width:980px">
                <thead>
                    <tr>
                        <th>Reçu</th>
                        <th>Date</th>
                        <th>Élève</th>
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
            </div>

            <div class="pagination">
                {{ $payments->links() }}
            </div>
        @endif
    </section>
@endsection

@can('payments.create')
    @push('dialogs')
        @include('payments.partials.create-dialog', [
            'paymentForm' => $paymentForm,
            'dialogId' => 'payment-create-dialog',
            'formId' => 'payment-create-modal-form',
            'cancelUrl' => route('payments.index'),
        ])
    @endpush
@endcan
