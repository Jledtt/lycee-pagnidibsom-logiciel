@extends('layouts.app', [
    'title' => 'Journal de caisse - Lycee Prive Pagnidibsom',
    'active' => 'accounting',
    'pageTitle' => 'Journal de caisse',
    'pageSubtitle' => 'Encaissements, annulations et total de caisse',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('payments.index') }}">Paiements</a>
    <a class="btn btn-primary" href="{{ route('accounting.cash-journal.pdf', $filters) }}">PDF</a>
@endsection

@section('content')
    @php($currency = $schoolSettings?->currency ?? 'FCFA')

    <section class="panel">
        <div class="panel-head">
            <h2>Filtres</h2>
        </div>

        <form class="searchbar" method="GET" action="{{ route('accounting.cash-journal') }}">
            <input type="date" name="date_from" value="{{ $filters['date_from'] }}">
            <input type="date" name="date_to" value="{{ $filters['date_to'] }}">

            <select name="school_class_id">
                <option value="">Toutes les classes</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected($filters['school_class_id'] === $class->id)>{{ $class->name }}</option>
                @endforeach
            </select>

            <select name="received_by">
                <option value="">Tous les caissiers</option>
                @foreach ($cashiers as $cashier)
                    <option value="{{ $cashier->id }}" @selected($filters['received_by'] === $cashier->id)>{{ $cashier->name }}</option>
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
                <option value="cancelled" @selected($filters['status'] === 'cancelled')>Annules</option>
            </select>

            <button class="btn btn-subtle" type="submit">Afficher</button>
            <a class="btn btn-subtle" href="{{ route('accounting.cash-journal') }}">Aujourd'hui</a>
        </form>
    </section>

    <section class="summary-row" style="margin-top:16px">
        <div class="stat">
            <span>Total encaisse</span>
            <strong class="money">{{ number_format($summary['total_valid'], 0, ',', ' ') }} {{ $currency }}</strong>
        </div>
        <div class="stat">
            <span>Paiements valides</span>
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
                <h2>Par mode de paiement</h2>
            </div>

            @if ($summary['by_method']->isEmpty())
                <div class="empty">Aucun encaissement valide sur la periode.</div>
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

        <div class="panel">
            <div class="panel-head">
                <h2>Par type de frais</h2>
            </div>

            @if ($summary['by_fee_type']->isEmpty())
                <div class="empty">Aucune ligne de frais sur la periode.</div>
            @else
                <table class="table">
                    <thead>
                        <tr>
                            <th>Frais</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary['by_fee_type'] as $feeType => $amount)
                            <tr>
                                <td>{{ $feeType }}</td>
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
            <h2>Operations de caisse</h2>
            <span class="badge">{{ $payments->total() }} operation(s)</span>
        </div>

        @if ($payments->isEmpty())
            <div class="empty">Aucun mouvement de caisse pour ces filtres.</div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Recu</th>
                        <th>Eleve</th>
                        <th>Classe</th>
                        <th>Mode</th>
                        <th>Caissier</th>
                        <th>Montant</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td>{{ $payment->paid_at?->format('d/m/Y H:i') }}</td>
                            <td><a href="{{ route('payments.show', $payment) }}"><strong>{{ $payment->receipt_number }}</strong></a></td>
                            <td>{{ $payment->student?->full_name ?? '-' }}</td>
                            <td>{{ $payment->enrollment?->schoolClass?->name ?? '-' }}</td>
                            <td>{{ $methodLabels[$payment->payment_method] ?? $payment->payment_method }}</td>
                            <td>{{ $payment->receiver?->name ?? '-' }}</td>
                            <td class="money">{{ number_format((float) $payment->amount, 0, ',', ' ') }} {{ $currency }}</td>
                            <td><span class="badge {{ $payment->status === 'valid' ? '' : 'badge-warning' }}">{{ $payment->status }}</span></td>
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
