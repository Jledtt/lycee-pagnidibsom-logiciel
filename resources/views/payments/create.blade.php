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
                                <label for="lines_{{ $i }}_fee_schedule_id">Tranche / frais</label>
                                <select id="lines_{{ $i }}_fee_schedule_id" name="lines[{{ $i }}][fee_schedule_id]" data-schedule-select data-old-value="{{ old("lines.$i.fee_schedule_id") }}">
                                    <option value="">Choisir d'abord un eleve</option>
                                </select>
                            </div>

                            <div class="field">
                                <label for="lines_{{ $i }}_amount">Montant FCFA</label>
                                <input id="lines_{{ $i }}_amount" name="lines[{{ $i }}][amount]" type="number" min="1" step="1" value="{{ old("lines.$i.amount") }}" placeholder="Choisir une tranche" data-amount-input>
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

    <script>
        const paymentProfiles = @json($paymentProfiles);
        const studentSelect = document.getElementById('student_id');
        const scheduleSelects = Array.from(document.querySelectorAll('[data-schedule-select]'));

        function formatMoney(value) {
            return new Intl.NumberFormat('fr-FR').format(value) + ' FCFA';
        }

        function fillSchedules() {
            const schedules = paymentProfiles[studentSelect.value] || [];

            scheduleSelects.forEach((select) => {
                const oldValue = select.dataset.oldValue || '';
                select.innerHTML = '';

                const emptyOption = document.createElement('option');
                emptyOption.value = '';
                emptyOption.textContent = schedules.length ? 'Choisir une tranche' : 'Aucune tranche configuree';
                select.appendChild(emptyOption);

                schedules.forEach((schedule) => {
                    const option = document.createElement('option');
                    option.value = schedule.id;
                    option.dataset.remaining = schedule.remaining;
                    option.textContent = `${schedule.label} - reste ${formatMoney(schedule.remaining)} / ${formatMoney(schedule.amount)}`;
                    option.disabled = schedule.remaining <= 0;
                    if (String(schedule.id) === oldValue) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });

                updateAmount(select);
            });
        }

        function updateAmount(select) {
            const input = select.closest('.form-grid').querySelector('[data-amount-input]');
            const selected = select.options[select.selectedIndex];

            if (! input || ! selected || ! selected.dataset.remaining) {
                return;
            }

            if (! input.value) {
                input.value = Math.max(Number(selected.dataset.remaining || 0), 0);
            }
        }

        studentSelect?.addEventListener('change', fillSchedules);
        scheduleSelects.forEach((select) => {
            select.addEventListener('change', () => {
                const input = select.closest('.form-grid').querySelector('[data-amount-input]');
                if (input) {
                    input.value = '';
                }
                updateAmount(select);
            });
        });
        fillSchedules();
    </script>
@endsection
