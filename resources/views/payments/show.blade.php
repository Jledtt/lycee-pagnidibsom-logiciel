@extends('layouts.app', [
    'title' => $payment->receipt_number . ' - Lycee Prive Pagnidibsom',
    'active' => 'payments',
    'pageTitle' => 'Recu ' . $payment->receipt_number,
    'pageSubtitle' => $payment->student->full_name . ' - ' . ($payment->academicYear?->name ?? 'Annee scolaire'),
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('payments.index') }}">Retour</a>
    @can('payments.print_receipt')
        <a class="btn btn-subtle" href="{{ route('payments.receipt', $payment) }}">Recu PDF</a>
    @endcan
    @can('payments.create')
        <a class="btn btn-primary" href="{{ route('payments.create') }}">Nouveau paiement</a>
    @endcan
@endsection

@section('content')
    <section class="summary-row">
        <div class="stat">
            <span>Montant paye</span>
            <strong class="money">{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</strong>
        </div>
        <div class="stat">
            <span>Total paye eleve</span>
            <strong class="money">{{ number_format($summary['paid'], 0, ',', ' ') }} FCFA</strong>
        </div>
        <div class="stat">
            <span>Reste a payer</span>
            <strong class="money">{{ is_null($summary['balance']) ? 'A configurer' : number_format($summary['balance'], 0, ',', ' ') . ' FCFA' }}</strong>
        </div>
    </section>

    <section class="grid two-col" style="margin-top:16px">
        <div class="panel">
            <div class="panel-head">
                <h2>Details du paiement</h2>
                <span class="badge {{ $payment->status === 'valid' ? '' : 'badge-warning' }}">{{ $payment->status }}</span>
            </div>

            <div class="detail-grid">
                <div class="detail-item">
                    <span>Eleve</span>
                    <strong>{{ $payment->student->full_name }}</strong>
                </div>
                <div class="detail-item">
                    <span>Matricule</span>
                    <strong>{{ $payment->student->matricule }}</strong>
                </div>
                <div class="detail-item">
                    <span>Classe</span>
                    <strong>{{ $payment->enrollment?->schoolClass?->name ?? '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Date</span>
                    <strong>{{ $payment->paid_at?->format('d/m/Y H:i') }}</strong>
                </div>
                <div class="detail-item">
                    <span>Mode</span>
                    <strong>{{ $payment->payment_method }}</strong>
                </div>
                <div class="detail-item">
                    <span>Encaisse par</span>
                    <strong>{{ $payment->receiver?->name ?? '-' }}</strong>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Lignes</h2>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Frais</th>
                        <th>Montant</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payment->lines as $line)
                        <tr>
                            <td>{{ $line->feeType?->name ?? '-' }}</td>
                            <td class="money">{{ number_format($line->amount, 0, ',', ' ') }} FCFA</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($payment->notes)
                <div class="detail-item" style="margin-top:16px">
                    <span>Notes</span>
                    <strong>{{ $payment->notes }}</strong>
                </div>
            @endif

            @if ($payment->status !== 'cancelled')
                <form method="POST" action="{{ route('payments.destroy', $payment) }}" style="margin-top:16px" onsubmit="return confirm('Annuler ce paiement ?')">
                    @csrf
                    @method('DELETE')
                    <div class="field">
                        <label for="reason">Motif d'annulation</label>
                        <input id="reason" name="reason" placeholder="Ex: erreur de saisie" required>
                    </div>
                    <button class="btn btn-danger" type="submit">Annuler le paiement</button>
                </form>
            @else
                <div class="detail-item" style="margin-top:16px">
                    <span>Motif annulation</span>
                    <strong>{{ $payment->cancellation_reason ?? '-' }}</strong>
                </div>
            @endif
        </div>
    </section>
@endsection
