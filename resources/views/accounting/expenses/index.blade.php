@extends('layouts.app', [
    'title' => 'Dépenses - Lycée Privé Pagnidibsom',
    'active' => 'accounting',
    'pageTitle' => 'Dépenses',
    'pageSubtitle' => 'Sorties de caisse et justificatifs',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('accounting.balance-sheet') }}">Bilan</a>
    <a class="btn btn-subtle" href="{{ route('accounting.cash-journal') }}">Journal de caisse</a>
    <a class="btn btn-subtle" href="{{ route('accounting.expenses.pdf', $filters) }}">PDF</a>
    @can('payments.create')
        <a class="btn btn-primary" href="{{ route('accounting.expenses.create') }}">Nouvelle depense</a>
    @endcan
@endsection

@section('content')
    @php($currency = $schoolSettings?->currency ?? 'FCFA')

    <section class="panel">
        <div class="panel-head">
            <h2>Filtres</h2>
        </div>

        <form class="searchbar" method="GET" action="{{ route('accounting.expenses.index') }}">
            <input type="date" name="date_from" value="{{ $filters['date_from'] }}">
            <input type="date" name="date_to" value="{{ $filters['date_to'] }}">

            <select name="category">
                <option value="">Toutes les categories</option>
                @foreach ($categoryLabels as $category => $label)
                    <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $label }}</option>
                @endforeach
            </select>

            <select name="payment_method">
                <option value="">Tous les modes</option>
                @foreach ($methodLabels as $method => $label)
                    <option value="{{ $method }}" @selected($filters['payment_method'] === $method)>{{ $label }}</option>
                @endforeach
            </select>

            <select name="status">
                <option value="">Tous les statuts</option>
                <option value="valid" @selected($filters['status'] === 'valid')>Valides</option>
                <option value="cancelled" @selected($filters['status'] === 'cancelled')>Annulees</option>
            </select>

            <button class="btn btn-subtle" type="submit">Afficher</button>
            <a class="btn btn-subtle" href="{{ route('accounting.expenses.index') }}">Aujourd’hui</a>
        </form>
    </section>

    <section class="summary-row" style="margin-top:16px">
        <div class="stat">
            <span>Total d?penses</span>
            <strong class="money">{{ number_format($summary['total_valid'], 0, ',', ' ') }} {{ $currency }}</strong>
        </div>
        <div class="stat">
            <span>Dépenses validées</span>
            <strong>{{ $summary['valid_count'] }}</strong>
        </div>
        <div class="stat">
            <span>Annulations</span>
            <strong>{{ $summary['cancelled_count'] }}</strong>
        </div>
    </section>

    <section class="grid two-col" style="margin-top:16px">
        <div class="panel">
            <div class="panel-head">
                <h2>Par categorie</h2>
            </div>

            @if ($summary['by_category']->isEmpty())
                <div class="empty">Aucune depense valide sur la période.</div>
            @else
                <table class="table">
                    <thead>
                        <tr>
                            <th>Categorie</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary['by_category'] as $category => $amount)
                            <tr>
                                <td>{{ $categoryLabels[$category] ?? $category }}</td>
                                <td class="money">{{ number_format($amount, 0, ',', ' ') }} {{ $currency }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Par mode de paiement</h2>
            </div>

            @if ($summary['by_method']->isEmpty())
                <div class="empty">Aucune depense valide sur la période.</div>
            @else
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mode</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary['by_method'] as $method => $amount)
                            <tr>
                                <td>{{ $methodLabels[$method] ?? $method }}</td>
                                <td class="money">{{ number_format($amount, 0, ',', ' ') }} {{ $currency }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Liste des d?penses</h2>
            <span class="badge">{{ $expenses->total() }} depense(s)</span>
        </div>

        @if ($expenses->isEmpty())
            <div class="empty">Aucune depense pour ces filtres.</div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Categorie</th>
                        <th>Beneficiaire</th>
                        <th>Mode</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($expenses as $expense)
                        <tr>
                            <td>{{ $expense->spent_at?->format('d/m/Y') }}</td>
                            <td>{{ $categoryLabels[$expense->category] ?? $expense->category }}</td>
                            <td>{{ $expense->beneficiary ?: '-' }}</td>
                            <td>{{ $methodLabels[$expense->payment_method] ?? $expense->payment_method }}</td>
                            <td class="money">{{ number_format((float) $expense->amount, 0, ',', ' ') }} {{ $currency }}</td>
                            <td><span class="badge {{ $expense->status === 'valid' ? '' : 'badge-warning' }}">{{ $expense->status }}</span></td>
                            <td><a class="btn btn-subtle" href="{{ route('accounting.expenses.show', $expense) }}">Voir</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="pagination">
                {{ $expenses->links() }}
            </div>
        @endif
    </section>
@endsection
