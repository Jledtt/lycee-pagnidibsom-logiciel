@extends('layouts.app', [
    'title' => 'Dépense - Lycée Privé Pagnidibsom',
    'active' => 'accounting',
    'pageTitle' => 'Dépense',
    'pageSubtitle' => ($categoryLabels[$expense->category] ?? $expense->category) . ' - ' . $expense->spent_at?->format('d/m/Y'),
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('accounting.expenses.index') }}">Retour</a>
    @can('payments.create')
        <a class="btn btn-primary" href="{{ route('accounting.expenses.create') }}">Nouvelle depense</a>
    @endcan
@endsection

@section('content')
    @php($currency = $schoolSettings?->currency ?? 'FCFA')

    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <section class="grid two-col">
        <div class="panel">
            <div class="panel-head">
                <h2>Details</h2>
                <span class="badge {{ $expense->status === 'valid' ? '' : 'badge-warning' }}">{{ $expense->status }}</span>
            </div>

            <div class="detail-grid">
                <div class="detail-item">
                    <span>Date</span>
                    <strong>{{ $expense->spent_at?->format('d/m/Y') }}</strong>
                </div>
                <div class="detail-item">
                    <span>Categorie</span>
                    <strong>{{ $categoryLabels[$expense->category] ?? $expense->category }}</strong>
                </div>
                <div class="detail-item">
                    <span>Montant</span>
                    <strong class="money">{{ number_format((float) $expense->amount, 0, ',', ' ') }} {{ $currency }}</strong>
                </div>
                <div class="detail-item">
                    <span>Mode</span>
                    <strong>{{ $methodLabels[$expense->payment_method] ?? $expense->payment_method }}</strong>
                </div>
                <div class="detail-item">
                    <span>Beneficiaire</span>
                    <strong>{{ $expense->beneficiary ?: '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Justificatif</span>
                    <strong>{{ $expense->proof_reference ?: '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Saisie par</span>
                    <strong>{{ $expense->creator?->name ?? '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Année scolaire</span>
                    <strong>{{ $expense->academicYear?->name ?? '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Créée le</span>
                    <strong>{{ $expense->created_at?->format('d/m/Y H:i') ?? '-' }}</strong>
                </div>
            </div>

            @if ($expense->notes)
                <div class="detail-item" style="margin-top:16px">
                    <span>Notes</span>
                    <strong>{{ $expense->notes }}</strong>
                </div>
            @endif
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Contrôle</h2>
            </div>

            @if ($expense->status === 'cancelled')
                <div class="detail-grid">
                    <div class="detail-item">
                        <span>Annule le</span>
                        <strong>{{ $expense->cancelled_at?->format('d/m/Y H:i') ?? '-' }}</strong>
                    </div>
                    <div class="detail-item">
                        <span>Annule par</span>
                        <strong>{{ $expense->canceller?->name ?? '-' }}</strong>
                    </div>
                    <div class="detail-item wide">
                        <span>Motif</span>
                        <strong>{{ $expense->cancellation_reason ?: '-' }}</strong>
                    </div>
                </div>
            @else
                <div class="empty">Cette depense est valide.</div>

                @can('payments.cancel')
                    <form method="POST" action="{{ route('accounting.expenses.cancel', $expense) }}" style="margin-top:16px">
                        @csrf
                        @method('PUT')
                        <div class="field">
                            <label for="reason">Motif d’annulation</label>
                            <textarea id="reason" name="reason" required></textarea>
                        </div>
                        <button class="btn btn-danger" type="submit">Annuler la depense</button>
                    </form>
                @endcan
            @endif
        </div>
    </section>
@endsection
