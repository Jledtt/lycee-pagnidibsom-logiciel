@extends('layouts.app', [
    'title' => $payment->receipt_number.' - Lycée Privé Pagnidibsom',
    'active' => 'payments',
    'pageTitle' => 'Reçu '.$payment->receipt_number,
    'pageSubtitle' => $payment->student->full_name.' - '.($payment->academicYear?->name ?? 'Année scolaire'),
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('payments.index') }}">Retour</a>
    @can('payments.print_receipt')
        <a class="btn btn-subtle" href="{{ route('payments.receipt', $payment) }}" data-download-feedback="Téléchargement du reçu lancé.">Reçu PDF</a>
    @endcan
    @can('payments.view')
        <a class="btn btn-subtle" href="{{ route('payments.students.statement', $payment->student) }}" data-dialog-open="payment-summary-drawer">Situation élève</a>
    @endcan
    @can('payments.create')
        <a
            class="btn btn-primary"
            href="{{ route('payments.create', ['student_id' => $payment->student_id]) }}"
            data-dialog-open="payment-create-dialog"
            data-payment-student-id="{{ $payment->student_id }}"
        >Nouveau paiement</a>
    @endcan
@endsection

@section('content')
    <section class="summary-row">
        <div class="stat">
            <span>Montant payé</span>
            <strong class="money">{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</strong>
        </div>
        <div class="stat">
            <span>Total payé par l’élève</span>
            <strong class="money">{{ number_format($summary['paid'], 0, ',', ' ') }} FCFA</strong>
        </div>
        <div class="stat">
            <span>Reste à payer</span>
            <strong class="money">{{ is_null($summary['balance']) ? 'À configurer' : number_format($summary['balance'], 0, ',', ' ').' FCFA' }}</strong>
        </div>
    </section>

    <section class="grid two-col" style="margin-top:16px">
        <div class="panel">
            <div class="panel-head">
                <h2>Détails du paiement</h2>
                <span class="badge {{ $payment->status === 'valid' ? '' : 'badge-warning' }}">
                    {{ $payment->status === 'valid' ? 'Validé' : 'Annulé' }}
                </span>
            </div>

            <div class="detail-grid">
                <div class="detail-item">
                    <span>Élève</span>
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
                    <strong>{{ match ($payment->payment_method) {
                        'cash' => 'Espèces',
                        'mobile_money' => 'Mobile money',
                        'bank_transfer' => 'Virement bancaire',
                        default => 'Autre',
                    } }}</strong>
                </div>
                <div class="detail-item">
                    <span>Encaissé par</span>
                    <strong>{{ $payment->receiver?->name ?? '-' }}</strong>
                </div>
            </div>

            @if ($payment->status === 'valid')
                @can('payments.cancel')
                    <div class="payment-danger-zone">
                        <div>
                            <strong>Corriger une erreur d’encaissement</strong>
                            <span>L’annulation conserve le reçu et exige un motif.</span>
                        </div>
                        <button class="btn btn-danger" type="button" data-dialog-open="cancel-payment-dialog">Annuler ce paiement</button>
                    </div>
                @endcan
            @else
                <div class="detail-item" style="margin-top:16px">
                    <span>Motif d’annulation</span>
                    <strong>{{ $payment->cancellation_reason ?? '-' }}</strong>
                </div>
            @endif
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Frais encaissés</h2>
                <span class="badge">{{ $payment->lines->count() }} ligne(s)</span>
            </div>

            <div class="subject-list-scroll">
                <table class="table" style="min-width:620px">
                    <thead>
                        <tr>
                            <th>Frais</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payment->lines as $line)
                            <tr>
                                <td>
                                    <strong>{{ $line->feeSchedule?->period ?: ($line->feeType?->name ?? '-') }}</strong><br>
                                    <span style="color:var(--muted)">{{ $line->feeType?->name ?? '-' }}</span>
                                </td>
                                <td class="money">{{ number_format($line->amount, 0, ',', ' ') }} FCFA</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($payment->notes)
                <div class="detail-item" style="margin-top:16px">
                    <span>Note interne</span>
                    <strong>{{ $payment->notes }}</strong>
                </div>
            @endif
        </div>
    </section>
@endsection

@push('dialogs')
    @can('payments.view')
        <x-ui.drawer
            id="payment-summary-drawer"
            title="Situation financière"
            description="{{ $payment->student->full_name }} - {{ $payment->student->matricule }}"
        >
            <div class="payment-summary-list">
                <div>
                    <span>Classe</span>
                    <strong>{{ $summary['enrollment']?->schoolClass?->name ?? 'Non inscrit' }}</strong>
                </div>
                <div>
                    <span>Total attendu</span>
                    <strong class="money">{{ is_null($summary['expected']) ? 'À configurer' : number_format($summary['expected'], 0, ',', ' ').' FCFA' }}</strong>
                </div>
                <div>
                    <span>Total payé</span>
                    <strong class="money">{{ number_format($summary['paid'], 0, ',', ' ') }} FCFA</strong>
                </div>
                <div class="payment-summary-list__balance">
                    <span>Reste à payer</span>
                    <strong class="money">{{ is_null($summary['balance']) ? 'À configurer' : number_format($summary['balance'], 0, ',', ' ').' FCFA' }}</strong>
                </div>
            </div>

            <x-slot:footer>
                <button class="btn btn-subtle" type="button" data-dialog-close>Fermer</button>
                <a class="btn btn-primary" href="{{ route('payments.students.statement', $payment->student) }}">Voir la situation complète</a>
            </x-slot:footer>
        </x-ui.drawer>
    @endcan

    @can('payments.create')
        @include('payments.partials.create-dialog', [
            'paymentForm' => $paymentForm,
            'dialogId' => 'payment-create-dialog',
            'formId' => 'payment-create-modal-form',
            'cancelUrl' => route('payments.show', $payment),
        ])
    @endcan

    @if ($payment->status === 'valid')
        @can('payments.cancel')
            <x-ui.modal
                id="cancel-payment-dialog"
                title="Annuler le paiement"
                description="Le reçu restera dans l’historique avec le motif saisi."
                size="small"
                :open="session('cancel_payment_open') || $errors->has('reason')"
            >
                <form id="cancel-payment-form" method="POST" action="{{ route('payments.destroy', $payment) }}" data-prevent-double-submit>
                    @csrf
                    @method('DELETE')
                    <div class="field">
                        <label for="cancel-payment-reason">Motif d’annulation</label>
                        <textarea
                            id="cancel-payment-reason"
                            name="reason"
                            minlength="5"
                            placeholder="Exemple : montant incorrect ou doublon de reçu…"
                            required
                        >{{ old('reason') }}</textarea>
                        <small>Écrivez au moins 5 caractères.</small>
                        @error('reason') <small class="error">{{ $message }}</small> @enderror
                    </div>
                </form>

                <x-slot:footer>
                    <button class="btn btn-subtle" type="button" data-dialog-close>Conserver le paiement</button>
                    <button class="btn btn-danger" type="submit" form="cancel-payment-form" data-submitting-label="Annulation…">Confirmer l’annulation</button>
                </x-slot:footer>
            </x-ui.modal>
        @endcan
    @endif

    @if (session('payment_created'))
        <x-ui.modal
            id="payment-success-dialog"
            title="Paiement enregistré"
            description="Le reçu {{ $payment->receipt_number }} a été créé avec succès."
            size="small"
            :open="true"
        >
            <div class="payment-success-summary">
                <span>Montant encaissé</span>
                <strong class="money">{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</strong>
                <small>{{ $payment->student->full_name }}</small>
            </div>

            <x-slot:footer>
                <button class="btn btn-subtle" type="button" data-dialog-close>Fermer</button>
                @can('payments.view')
                    <a class="btn btn-subtle" href="{{ route('payments.students.statement', $payment->student) }}" data-dialog-open="payment-summary-drawer">Situation élève</a>
                @endcan
                @can('payments.print_receipt')
                    <a class="btn btn-primary" href="{{ route('payments.receipt', $payment) }}" data-download-feedback="Téléchargement du reçu lancé.">Télécharger le reçu</a>
                @endcan
            </x-slot:footer>
        </x-ui.modal>
    @endif
@endpush
