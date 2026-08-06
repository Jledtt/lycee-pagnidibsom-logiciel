@php
    $students = $paymentForm['students'];
    $feeTypes = $paymentForm['feeTypes'];
    $selectedStudentId = old('student_id', $paymentForm['selectedStudentId']);
    $selectedScheduleId = old('lines.0.fee_schedule_id', $paymentForm['selectedScheduleId']);
    $selectedAmount = old('lines.0.amount', $paymentForm['selectedAmount']);
@endphp

@if ($students->isEmpty())
    <div class="empty">Aucun élève inscrit n’est disponible pour enregistrer un paiement.</div>
@elseif ($feeTypes->isEmpty())
    <div class="empty">Aucun type de frais n’est configuré.</div>
@else
    <form
        id="{{ $formId }}"
        method="POST"
        action="{{ route('payments.store') }}"
        data-payment-form
        data-prevent-double-submit
    >
        @csrf

        <script type="application/json" data-payment-profiles>@json($paymentForm['profiles'])</script>

        <section class="payment-form-section" aria-labelledby="{{ $formId }}-context-title">
            <div class="payment-form-section__heading">
                <h3 id="{{ $formId }}-context-title">Encaissement</h3>
                <span>{{ $academicYear?->name ?? 'Année active' }}</span>
            </div>

            <div class="form-grid">
                <div class="field wide">
                    <label for="{{ $formId }}-student">Élève</label>
                    <select id="{{ $formId }}-student" name="student_id" required data-payment-student autocomplete="off">
                        <option value="">Choisir un élève inscrit</option>
                        @foreach ($students as $student)
                            @php($enrollment = $student->enrollments->sortByDesc('id')->first())
                            <option value="{{ $student->id }}" @selected((string) $selectedStudentId === (string) $student->id)>
                                {{ $student->matricule }} - {{ $student->full_name }}{{ $enrollment?->schoolClass ? ' / '.$enrollment->schoolClass->name : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('student_id') <small class="error">{{ $message }}</small> @enderror
                </div>

                <div class="field">
                    <label for="{{ $formId }}-paid-at">Date et heure</label>
                    <input id="{{ $formId }}-paid-at" name="paid_at" type="datetime-local" value="{{ old('paid_at', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}" autocomplete="off">
                    @error('paid_at') <small class="error">{{ $message }}</small> @enderror
                </div>

                <div class="field">
                    <label for="{{ $formId }}-method">Mode de paiement</label>
                    <select id="{{ $formId }}-method" name="payment_method" required autocomplete="off">
                        <option value="cash" @selected(old('payment_method', 'cash') === 'cash')>Espèces</option>
                        <option value="mobile_money" @selected(old('payment_method') === 'mobile_money')>Mobile money</option>
                        <option value="bank_transfer" @selected(old('payment_method') === 'bank_transfer')>Virement bancaire</option>
                        <option value="other" @selected(old('payment_method') === 'other')>Autre</option>
                    </select>
                    @error('payment_method') <small class="error">{{ $message }}</small> @enderror
                </div>
            </div>
        </section>

        <section class="payment-form-section" aria-labelledby="{{ $formId }}-lines-title">
            <div class="payment-form-section__heading">
                <h3 id="{{ $formId }}-lines-title">Frais payés</h3>
                <span>3 lignes maximum</span>
            </div>

            @error('lines') <p class="error">{{ $message }}</p> @enderror

            <div class="payment-lines">
                @for ($i = 0; $i < 3; $i++)
                    <div class="payment-line">
                        <div class="field">
                            <label for="{{ $formId }}-schedule-{{ $i }}">Tranche ou frais</label>
                            <select
                                id="{{ $formId }}-schedule-{{ $i }}"
                                name="lines[{{ $i }}][fee_schedule_id]"
                                data-payment-schedule
                                data-old-value="{{ $i === 0 ? $selectedScheduleId : old("lines.$i.fee_schedule_id") }}"
                                autocomplete="off"
                            >
                                <option value="">Choisir d’abord un élève</option>
                            </select>
                            @error("lines.$i.fee_schedule_id") <small class="error">{{ $message }}</small> @enderror
                        </div>

                        <div class="field">
                            <label for="{{ $formId }}-amount-{{ $i }}">Montant FCFA</label>
                            <input
                                id="{{ $formId }}-amount-{{ $i }}"
                                name="lines[{{ $i }}][amount]"
                                type="number"
                                min="1"
                                step="1"
                                inputmode="numeric"
                                value="{{ $i === 0 ? $selectedAmount : old("lines.$i.amount") }}"
                                placeholder="Choisir une tranche…"
                                data-payment-amount
                                autocomplete="off"
                            >
                            @error("lines.$i.amount") <small class="error">{{ $message }}</small> @enderror
                        </div>
                    </div>
                @endfor
            </div>
        </section>

        <section class="payment-form-section payment-form-section--last">
            <div class="field wide">
                <label for="{{ $formId }}-notes">Note interne</label>
                <textarea id="{{ $formId }}-notes" name="notes" placeholder="Information facultative…" autocomplete="off">{{ old('notes') }}</textarea>
                @error('notes') <small class="error">{{ $message }}</small> @enderror
            </div>
        </section>

        <div class="payment-form-actions">
            <div class="payment-form-total" aria-live="polite">
                <span>Total à encaisser</span>
                <strong data-payment-total>0 FCFA</strong>
            </div>
            <div class="payment-form-commands">
                @if ($cancelUrl)
                    <a class="btn btn-subtle" href="{{ $cancelUrl }}" @if (! empty($dialogId)) data-dialog-close @endif>Annuler</a>
                @endif
                <button class="btn btn-primary" type="submit" data-submitting-label="Enregistrement…">Enregistrer le paiement</button>
            </div>
        </div>
    </form>
@endif
