@extends('layouts.app', [
    'title' => 'Nouveau paiement - Lycee Prive Pagnidibsom',
    'active' => 'payments',
    'pageTitle' => 'Nouveau paiement',
    'pageSubtitle' => 'Enregistrer un encaissement et generer un recu',
])

@section('content')
    @if ($students->isEmpty())
        <div class="empty">Aucun eleve inscrit disponible pour enregistrer un paiement.</div>
    @elseif ($feeTypes->isEmpty())
        <div class="empty">Aucun type de frais configure.</div>
    @else
        <form method="POST" action="{{ route('payments.store') }}">
            @csrf

            <section class="panel">
                <div class="form-grid">
                    <div class="field wide">
                        <label for="student_id">Eleve</label>
                        <select id="student_id" name="student_id" required>
                            <option value="">Choisir un eleve inscrit</option>
                            @foreach ($students as $student)
                                @php($enrollment = $student->enrollments->sortByDesc('id')->first())
                                <option value="{{ $student->id }}" @selected((string) old('student_id', $selectedStudentId) === (string) $student->id)>
                                    {{ $student->matricule }} - {{ $student->full_name }}{{ $enrollment?->schoolClass ? ' / ' . $enrollment->schoolClass->name : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('student_id') <small class="error">{{ $message }}</small> @enderror
                    </div>

                    <div class="field">
                        <label for="paid_at">Date et heure</label>
                        <input id="paid_at" name="paid_at" type="datetime-local" value="{{ old('paid_at', now()->format('Y-m-d\TH:i')) }}">
                        @error('paid_at') <small class="error">{{ $message }}</small> @enderror
                    </div>

                    <div class="field">
                        <label for="payment_method">Mode de paiement</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="cash" @selected(old('payment_method', 'cash') === 'cash')>Especes</option>
                            <option value="mobile_money" @selected(old('payment_method') === 'mobile_money')>Mobile money</option>
                            <option value="bank_transfer" @selected(old('payment_method') === 'bank_transfer')>Virement bancaire</option>
                            <option value="other" @selected(old('payment_method') === 'other')>Autre</option>
                        </select>
                        @error('payment_method') <small class="error">{{ $message }}</small> @enderror
                    </div>
                </div>
            </section>

            <section class="panel" style="margin-top:16px">
                <div class="panel-head">
                    <h2>Lignes de paiement</h2>
                    <span class="badge">Jusqu'a 3 lignes</span>
                </div>

                @error('lines') <p class="error">{{ $message }}</p> @enderror

                <div class="grid" style="grid-template-columns:1fr">
                    @for ($i = 0; $i < 3; $i++)
                        <div class="form-grid">
                            <div class="field">
                                <label for="lines_{{ $i }}_fee_type_id">Type de frais</label>
                                <select id="lines_{{ $i }}_fee_type_id" name="lines[{{ $i }}][fee_type_id]">
                                    <option value="">Choisir</option>
                                    @foreach ($feeTypes as $feeType)
                                        <option value="{{ $feeType->id }}" @selected(old("lines.$i.fee_type_id") == $feeType->id)>{{ $feeType->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field">
                                <label for="lines_{{ $i }}_amount">Montant FCFA</label>
                                <input id="lines_{{ $i }}_amount" name="lines[{{ $i }}][amount]" type="number" min="1" step="1" value="{{ old("lines.$i.amount") }}" placeholder="Ex: 25000">
                            </div>
                        </div>
                    @endfor
                </div>

                <div class="field wide">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" placeholder="Optionnel">{{ old('notes') }}</textarea>
                    @error('notes') <small class="error">{{ $message }}</small> @enderror
                </div>

                <div class="form-actions">
                    <a class="btn btn-subtle" href="{{ route('payments.index') }}">Annuler</a>
                    <button class="btn btn-primary" type="submit">Enregistrer le paiement</button>
                </div>
            </section>
        </form>
    @endif
@endsection
