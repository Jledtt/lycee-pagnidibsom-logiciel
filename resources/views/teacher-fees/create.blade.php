@extends('layouts.app', [
    'title' => 'Préparer les honoraires',
    'active' => 'teacher-fees',
    'pageTitle' => 'Préparer les honoraires',
    'pageSubtitle' => $teacher->name . ' · ' . $periodMonth->translatedFormat('F Y'),
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('teacher-fees.index') }}">Retour</a>
@endsection

@section('content')
    @if ($errors->any())<div class="error">{{ $errors->first() }}</div>@endif

    <section class="stats">
        <div class="stat"><span>Professeur</span><strong>{{ $teacher->name }}</strong></div>
        <div class="stat"><span>Période</span><strong>{{ ucfirst($periodMonth->translatedFormat('F Y')) }}</strong></div>
        <div class="stat"><span>Taux par défaut</span><strong class="money">{{ number_format((float) ($teacher->teacherProfile?->default_hourly_rate ?? 0), 0, ',', ' ') }} FCFA</strong></div>
        <div class="stat"><span>Retenue</span><strong>{{ number_format((float) ($teacher->teacherProfile?->withholding_tax_rate ?? 2), 2, ',', ' ') }} %</strong></div>
    </section>

    <form method="POST" action="{{ route('teacher-fees.store') }}" style="margin-top:16px" id="teacher-fee-form">
        @csrf
        <input type="hidden" name="teacher_id" value="{{ $teacher->id }}">
        <input type="hidden" name="period_month" value="{{ $periodMonth->format('Y-m') }}">

        <section class="panel">
            <div class="panel-head"><h2>Heures validées non encore payées</h2><span class="badge">{{ $sessions->count() }} ligne(s)</span></div>
            @if ($sessions->isEmpty())
                <div class="empty">Aucune heure validée et disponible pour cette période. Enregistre et valide d’abord les émargements.</div>
            @else
                <div style="overflow-x:auto">
                    <table class="table">
                        <thead><tr><th>Sélection</th><th>Date</th><th>Classe</th><th>Matière</th><th>Heures</th><th>Taux horaire</th><th>Montant</th></tr></thead>
                        <tbody>
                            @foreach ($sessions as $session)
                                @php($rate = old('rates.'.$session->id, $session->hourly_rate ?? $teacher->teacherProfile?->default_hourly_rate ?? 0))
                                <tr class="fee-line">
                                    <td><input class="fee-check" type="checkbox" name="session_ids[]" value="{{ $session->id }}" checked></td>
                                    <td>{{ $session->session_date->format('d/m/Y') }}</td>
                                    <td>{{ $session->schoolClass?->name }}</td>
                                    <td>{{ $session->subject?->name }}</td>
                                    <td><strong class="fee-hours" data-hours="{{ $session->hours_worked }}">{{ number_format((float) $session->hours_worked, 2, ',', ' ') }}</strong></td>
                                    <td><input class="fee-rate" style="min-width:130px" type="number" min="1" step="1" name="rates[{{ $session->id }}]" value="{{ $rate }}" required></td>
                                    <td><strong class="fee-amount">0 FCFA</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if ($sessions->isNotEmpty())
            <section class="grid two-col" style="margin-top:16px">
                <div class="panel">
                    <div class="panel-head"><h2>Retenues</h2></div>
                    <div class="form-grid">
                        <div class="field"><label>Retenue à la source (%)</label><input id="tax-rate" type="number" min="0" max="100" step="0.01" name="withholding_tax_rate" value="{{ old('withholding_tax_rate', $teacher->teacherProfile?->withholding_tax_rate ?? 2) }}" required></div>
                        <div class="field"><label>Avance sur honoraires</label><input id="advance-amount" type="number" min="0" step="1" name="advance_amount" value="{{ old('advance_amount', 0) }}"></div>
                        <div class="field"><label>Autre retenue</label><input id="other-deduction" type="number" min="0" step="1" name="other_deduction_amount" value="{{ old('other_deduction_amount', 0) }}"></div>
                        <div class="field wide"><label>Observation</label><textarea name="notes">{{ old('notes') }}</textarea></div>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-head"><h2>Calcul</h2><span class="badge">FCFA</span></div>
                    <div class="detail-grid">
                        <div class="detail-item"><span>Total des heures</span><strong id="total-hours">0 h</strong></div>
                        <div class="detail-item"><span>Montant brut</span><strong id="gross-amount">0 FCFA</strong></div>
                        <div class="detail-item"><span>Retenue à la source</span><strong id="tax-amount">0 FCFA</strong></div>
                        <div class="detail-item"><span>Net à payer</span><strong id="net-amount">0 FCFA</strong></div>
                    </div>
                    <div class="form-actions"><button class="btn btn-primary" type="submit">Créer l’ordre de paiement</button></div>
                </div>
            </section>
        @endif
    </form>

    <script>
        (() => {
            const format = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
            const numberValue = (selector) => Number(document.querySelector(selector)?.value || 0);
            const calculate = () => {
                let gross = 0;
                let hours = 0;

                document.querySelectorAll('.fee-line').forEach((row) => {
                    const checked = row.querySelector('.fee-check').checked;
                    const lineHours = Number(row.querySelector('.fee-hours').dataset.hours || 0);
                    const rate = Number(row.querySelector('.fee-rate').value || 0);
                    const amount = checked ? lineHours * rate : 0;
                    row.querySelector('.fee-amount').textContent = `${format.format(amount)} FCFA`;
                    gross += amount;
                    hours += checked ? lineHours : 0;
                });

                const tax = gross * numberValue('#tax-rate') / 100;
                const net = Math.max(0, gross - tax - numberValue('#advance-amount') - numberValue('#other-deduction'));
                document.querySelector('#total-hours').textContent = `${hours.toLocaleString('fr-FR')} h`;
                document.querySelector('#gross-amount').textContent = `${format.format(gross)} FCFA`;
                document.querySelector('#tax-amount').textContent = `${format.format(tax)} FCFA`;
                document.querySelector('#net-amount').textContent = `${format.format(net)} FCFA`;
            };

            document.querySelector('#teacher-fee-form')?.addEventListener('input', calculate);
            calculate();
        })();
    </script>
@endsection
