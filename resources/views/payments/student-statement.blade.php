@extends('layouts.app', [
    'title' => 'Situation financière - ' . $student->full_name,
    'active' => 'payments',
    'pageTitle' => 'Situation financière',
    'pageSubtitle' => $student->full_name . ' - ' . $student->matricule,
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('students.show', $student) }}">Fiche élève</a>
    @can('payments.reports')
        <a class="btn btn-subtle" href="{{ route('payments.students.statement.pdf', $student) }}">PDF</a>
    @endcan
    @can('payments.create')
        <a
            class="btn btn-primary"
            href="{{ route('payments.create', ['student_id' => $student->id]) }}"
            data-dialog-open="student-payment-dialog"
            data-payment-student-id="{{ $student->id }}"
        >Encaisser</a>
    @endcan
@endsection

@section('content')
    <section class="summary-row">
        <div class="stat">
            <span>Total attendu</span>
            <strong class="money">{{ is_null($profile['expected']) ? 'À configurer' : number_format($profile['expected'], 0, ',', ' ') . ' FCFA' }}</strong>
        </div>
        <div class="stat">
            <span>Total paye</span>
            <strong class="money">{{ number_format($profile['paid'], 0, ',', ' ') }} FCFA</strong>
        </div>
        <div class="stat">
            <span>Reste</span>
            <strong class="money">{{ is_null($profile['balance']) ? 'À configurer' : number_format($profile['balance'], 0, ',', ' ') . ' FCFA' }}</strong>
        </div>
    </section>

    <section class="grid two-col" style="margin-top:16px">
        <div class="panel">
            <div class="panel-head">
                <h2>Frais par tranche</h2>
                <span class="badge">{{ $profile['enrollment']?->schoolClass?->name ?? 'Non inscrit' }}</span>
            </div>

            @if ($profile['scheduled_rows']->isEmpty())
                <div class="empty">Aucun tarif configuré pour la classe actuelle.</div>
            @else
                <div class="subject-list-scroll">
                    <table class="table" style="min-width:900px">
                        <thead>
                            <tr>
                                <th>Frais</th>
                                <th>Attendu</th>
                                <th>Paye</th>
                                <th>Reste</th>
                                <th>Statut</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($profile['scheduled_rows'] as $row)
                                <tr>
                                    <td>
                                        <strong>{{ $row['schedule']->period ?: 'Sans période' }}</strong><br>
                                        <span style="color:var(--muted)">{{ $row['schedule']->feeType?->name ?? '-' }}</span>
                                    </td>
                                    <td class="money">{{ number_format($row['expected'], 0, ',', ' ') }} FCFA</td>
                                    <td class="money">{{ number_format($row['paid'], 0, ',', ' ') }} FCFA</td>
                                    <td class="money">{{ number_format($row['remaining'], 0, ',', ' ') }} FCFA</td>
                                    <td>
                                        <span class="badge {{ $row['status'] === 'paid' ? '' : 'badge-warning' }}">
                                            {{ $row['status'] === 'paid' ? 'Paye' : ($row['status'] === 'partial' ? 'Partiel' : 'Impayé') }}
                                        </span>
                                    </td>
                                    <td>
                                        @can('payments.create')
                                            @if ($row['remaining'] > 0)
                                                <a
                                                    class="btn btn-primary"
                                                    href="{{ route('payments.create', ['student_id' => $student->id, 'fee_schedule_id' => $row['schedule']->id, 'amount' => (int) $row['remaining']]) }}"
                                                    data-dialog-open="student-payment-dialog"
                                                    data-payment-student-id="{{ $student->id }}"
                                                    data-payment-schedule-id="{{ $row['schedule']->id }}"
                                                    data-payment-amount="{{ (int) $row['remaining'] }}"
                                                >Solder</a>
                                            @else
                                                <span class="badge">Solde</span>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Historique des reçus</h2>
                <span class="badge">{{ $profile['payments']->count() }} paiement(s)</span>
            </div>

            @if ($profile['payments']->isEmpty())
                <div class="empty">Aucun paiement enregistré pour cet élève.</div>
            @else
                <div class="ledger-list">
                    @foreach ($profile['payments'] as $payment)
                        <div class="ledger-item">
                            <div class="ledger-summary" style="grid-template-columns:minmax(170px,1fr) minmax(110px,.6fr) minmax(120px,.6fr) minmax(120px,.6fr) minmax(120px,.6fr)">
                                <div class="ledger-person">
                                    <strong>{{ $payment->receipt_number }}</strong>
                                    <span>{{ $payment->paid_at?->format('d/m/Y H:i') }}</span>
                                </div>
                                <div class="ledger-metric">
                                    <strong class="money">{{ number_format($payment->amount, 0, ',', ' ') }}</strong>
                                    <span>FCFA</span>
                                </div>
                                <span class="badge {{ $payment->status === 'valid' ? '' : 'badge-warning' }}">{{ $payment->status }}</span>
                                <a class="btn btn-subtle" href="{{ route('payments.show', $payment) }}">Voir</a>
                                @can('payments.print_receipt')
                                    <a class="btn btn-subtle" href="{{ route('payments.receipt', $payment) }}">PDF</a>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    @if ($profile['other_lines']->isNotEmpty())
        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>Paiements hors tranches</h2>
            </div>
            <div class="subject-list-scroll">
            <table class="table" style="min-width:720px">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Frais</th>
                        <th>Reçu</th>
                        <th>Montant</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($profile['other_lines'] as $line)
                        <tr>
                            <td>{{ $line->payment?->paid_at?->format('d/m/Y') }}</td>
                            <td>{{ $line->feeType?->name ?? '-' }}</td>
                            <td>{{ $line->payment?->receipt_number }}</td>
                            <td class="money">{{ number_format($line->amount, 0, ',', ' ') }} FCFA</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </section>
    @endif
@endsection

@can('payments.create')
    @push('dialogs')
        @include('payments.partials.create-dialog', [
            'paymentForm' => $paymentForm,
            'dialogId' => 'student-payment-dialog',
            'formId' => 'student-payment-modal-form',
            'cancelUrl' => route('payments.students.statement', $student),
        ])
    @endpush
@endcan
