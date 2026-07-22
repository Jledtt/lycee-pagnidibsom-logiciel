@extends('layouts.app', [
    'title' => 'Bilan de caisse - Lycée Privé Pagnidibsom',
    'active' => 'accounting',
    'pageTitle' => 'Bilan de caisse',
    'pageSubtitle' => 'Synthese des entrees, sorties et solde net',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('accounting.cash-journal') }}">Journal</a>
    <a class="btn btn-subtle" href="{{ route('accounting.expenses.index') }}">Dépenses</a>
    <a class="btn btn-primary" href="{{ route('accounting.balance-sheet.pdf', $filters) }}">PDF</a>
@endsection

@section('content')
    @php($currency = $schoolSettings?->currency ?? 'FCFA')

    <section class="panel">
        <div class="panel-head">
            <h2>Période</h2>
        </div>

        <form class="searchbar" method="GET" action="{{ route('accounting.balance-sheet') }}">
            <input type="date" name="date_from" value="{{ $filters['date_from'] }}">
            <input type="date" name="date_to" value="{{ $filters['date_to'] }}">
            <button class="btn btn-subtle" type="submit">Afficher</button>
            <a class="btn btn-subtle" href="{{ route('accounting.balance-sheet') }}">Aujourd’hui</a>
        </form>
    </section>

    <section class="summary-row" style="margin-top:16px">
        <div class="stat">
            <span>Entrees</span>
            <strong class="money">{{ number_format($summary['income'], 0, ',', ' ') }} {{ $currency }}</strong>
        </div>
        <div class="stat">
            <span>Dépenses</span>
            <strong class="money">{{ number_format($summary['expenses'], 0, ',', ' ') }} {{ $currency }}</strong>
        </div>
        <div class="stat">
            <span>Solde net</span>
            <strong class="money">{{ number_format($summary['balance'], 0, ',', ' ') }} {{ $currency }}</strong>
        </div>
    </section>

    <section class="grid modules" style="margin-top:16px">
        <div class="module">
            <strong>{{ $summary['payment_count'] }}</strong>
            <span>Paiements valides sur la période</span>
        </div>
        <div class="module">
            <strong>{{ $summary['expense_count'] }}</strong>
            <span>Dépenses validées sur la période</span>
        </div>
        <div class="module">
            <strong>{{ $summary['balance'] >= 0 ? 'Positif' : 'Negatif' }}</strong>
            <span>Etat du solde de caisse</span>
        </div>
    </section>

    <section class="grid two-col" style="margin-top:16px">
        <div class="panel">
            <div class="panel-head">
                <h2>Entrees par mode</h2>
            </div>

            @if ($paymentSummary['by_method']->isEmpty())
                <div class="empty">Aucune entree valide sur cette période.</div>
            @else
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mode</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($paymentSummary['by_method'] as $method => $amount)
                            <tr>
                                <td>{{ $methodLabels[$method] ?? $method }}</td>
                                <td class="money">{{ number_format($amount, 0, ',', ' ') }} {{ $currency }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Dépenses par catégorie</h2>
            </div>

            @if ($expenseSummary['by_category']->isEmpty())
                <div class="empty">Aucune depense valide sur cette période.</div>
            @else
                <table class="table">
                    <thead>
                        <tr>
                            <th>Categorie</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($expenseSummary['by_category'] as $category => $amount)
                            <tr>
                                <td>{{ $categoryLabels[$category] ?? $category }}</td>
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
            <h2>Lecture rapide</h2>
        </div>

        <div class="detail-grid">
            <div class="detail-item">
                <span>Formule</span>
                <strong>Entrees - d?penses = solde net</strong>
            </div>
            <div class="detail-item">
                <span>Calcul</span>
                <strong>{{ number_format($summary['income'], 0, ',', ' ') }} - {{ number_format($summary['expenses'], 0, ',', ' ') }} = {{ number_format($summary['balance'], 0, ',', ' ') }} {{ $currency }}</strong>
            </div>
            <div class="detail-item">
                <span>Période</span>
                <strong>{{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d/m/Y') }} au {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d/m/Y') }}</strong>
            </div>
        </div>
    </section>
@endsection
