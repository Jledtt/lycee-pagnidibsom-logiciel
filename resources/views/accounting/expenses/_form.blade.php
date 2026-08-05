@if ($errors->any())
    <div class="error">{{ $errors->first() }}</div>
@endif

<section class="panel">
    <div class="panel-head">
        <h2>Informations de la dépense</h2>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="spent_at">Date</label>
            <input id="spent_at" name="spent_at" type="date" value="{{ old('spent_at', $expense->spent_at?->format('Y-m-d') ?? now()->toDateString()) }}" required>
        </div>

        <div class="field">
            <label for="category">Categorie</label>
            <select id="category" name="category" required>
                @foreach ($categoryLabels as $category => $label)
                    <option value="{{ $category }}" @selected(old('category', $expense->category) === $category)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="amount">Montant</label>
            <input id="amount" name="amount" type="number" min="1" step="1" value="{{ old('amount', $expense->amount) }}" required>
        </div>

        <div class="field">
            <label for="payment_method">Mode de paiement</label>
            <select id="payment_method" name="payment_method" required>
                @foreach ($methodLabels as $method => $label)
                    <option value="{{ $method }}" @selected(old('payment_method', $expense->payment_method ?? 'cash') === $method)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="beneficiary">Beneficiaire</label>
            <input id="beneficiary" name="beneficiary" value="{{ old('beneficiary', $expense->beneficiary) }}" placeholder="Nom de la personne ou du fournisseur">
        </div>

        <div class="field">
            <label for="proof_reference">Reference justificatif</label>
            <input id="proof_reference" name="proof_reference" value="{{ old('proof_reference', $expense->proof_reference) }}" placeholder="Numéro facture, reçu ou bon">
        </div>

        <div class="field wide">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes">{{ old('notes', $expense->notes) }}</textarea>
        </div>
    </div>
</section>

<div class="form-actions">
    <a class="btn btn-subtle" href="{{ route('accounting.expenses.index') }}">Annuler</a>
    <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
</div>
