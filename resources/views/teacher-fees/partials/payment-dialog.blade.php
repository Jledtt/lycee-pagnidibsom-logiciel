<x-ui.modal
    id="teacher-fee-payment-dialog"
    title="Payer les honoraires"
    description="{{ $statement->beneficiary_name }} - {{ $statement->period_month->translatedFormat('F Y') }}"
    size="medium"
    :open="session('teacher_fee_payment_open') || $errors->hasAny(['paid_at', 'payment_method', 'payment_reference'])"
>
    <div class="follow-up-summary-list">
        <div><span>Montant brut</span><strong class="money">{{ number_format((float) $statement->gross_amount, 0, ',', ' ') }} FCFA</strong></div>
        <div><span>Retenue à la source</span><strong class="money">- {{ number_format((float) $statement->withholding_tax_amount, 0, ',', ' ') }} FCFA</strong></div>
        <div><span>Avance</span><strong class="money">- {{ number_format((float) $statement->advance_amount, 0, ',', ' ') }} FCFA</strong></div>
        <div><span>Autre retenue</span><strong class="money">- {{ number_format((float) $statement->other_deduction_amount, 0, ',', ' ') }} FCFA</strong></div>
        <div class="follow-up-summary-list__highlight"><span>Net à payer</span><strong class="money">{{ number_format((float) $statement->net_amount, 0, ',', ' ') }} FCFA</strong></div>
    </div>

    <form id="teacher-fee-payment-form" method="POST" action="{{ route('teacher-fees.pay', $statement) }}" data-prevent-double-submit>
        @csrf
        @method('PUT')
        <div class="form-grid" style="margin-top:18px">
            <div class="field">
                <label for="teacher-fee-paid-at">Date de paiement</label>
                <input id="teacher-fee-paid-at" type="date" name="paid_at" value="{{ old('paid_at', now()->toDateString()) }}" required>
                @error('paid_at') <small class="error">{{ $message }}</small> @enderror
            </div>
            <div class="field">
                <label for="teacher-fee-payment-method">Mode de paiement</label>
                <select id="teacher-fee-payment-method" name="payment_method" required>
                    @foreach (['Espèces', 'Virement', 'Mobile Money', 'Chèque'] as $method)
                        <option value="{{ $method }}" @selected(old('payment_method', $statement->payment_method) === $method)>{{ $method }}</option>
                    @endforeach
                </select>
                @error('payment_method') <small class="error">{{ $message }}</small> @enderror
            </div>
            <div class="field wide">
                <label for="teacher-fee-payment-reference">Référence</label>
                <input id="teacher-fee-payment-reference" name="payment_reference" value="{{ old('payment_reference', $statement->payment_reference) }}" maxlength="120" autocomplete="off" placeholder="Exemple : référence du virement…">
                @error('payment_reference') <small class="error">{{ $message }}</small> @enderror
            </div>
        </div>
    </form>

    <x-slot:footer>
        <button class="btn btn-subtle" type="button" data-dialog-close>Annuler</button>
        <button class="btn btn-primary" type="submit" form="teacher-fee-payment-form" data-submitting-label="Paiement…">Confirmer le paiement</button>
    </x-slot:footer>
</x-ui.modal>
