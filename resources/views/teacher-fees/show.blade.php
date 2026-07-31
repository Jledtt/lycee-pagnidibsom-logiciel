@extends('layouts.app', [
    'title' => $statement->reference . ' - Honoraires',
    'active' => 'teacher-fees',
    'pageTitle' => $statement->reference,
    'pageSubtitle' => $statement->beneficiary_name . ' · ' . $statement->period_month->translatedFormat('F Y'),
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('teacher-fees.index') }}">Retour</a>
    <a class="btn btn-subtle" href="{{ route('teacher-fees.pdf', $statement) }}" data-download-feedback="Téléchargement de l’ordre d’honoraires lancé.">PDF</a>
    @if ($statement->status === 'approved')
        @can('teacher_fees.pay')
            <button class="btn btn-primary" type="button" data-dialog-open="teacher-fee-payment-dialog">Payer les honoraires</button>
        @endcan
    @endif
@endsection

@section('content')
    @if ($errors->any())<div class="error">{{ $errors->first() }}</div>@endif

    <section class="stats">
        <div class="stat"><span>Montant brut</span><strong class="money">{{ number_format((float) $statement->gross_amount, 0, ',', ' ') }} FCFA</strong></div>
        <div class="stat"><span>Retenue à la source</span><strong class="money">{{ number_format((float) $statement->withholding_tax_amount, 0, ',', ' ') }} FCFA</strong></div>
        <div class="stat"><span>Net à payer</span><strong class="money">{{ number_format((float) $statement->net_amount, 0, ',', ' ') }} FCFA</strong></div>
        <div class="stat"><span>Statut</span><strong>{{ ucfirst($statement->status) }}</strong></div>
    </section>

    <section class="grid two-col" style="margin-top:16px">
        <div class="panel">
            <div class="panel-head"><h2>Détail des heures</h2><span class="badge">{{ number_format((float) $statement->lines->sum('hours'), 2, ',', ' ') }} h</span></div>
            <div style="overflow-x:auto">
                <table class="table">
                    <thead><tr><th>Description</th><th>Heures</th><th>Taux</th><th>Montant</th></tr></thead>
                    <tbody>
                        @foreach ($statement->lines as $line)
                            <tr>
                                <td>{{ $line->description }}</td>
                                <td>{{ number_format((float) $line->hours, 2, ',', ' ') }}</td>
                                <td>{{ number_format((float) $line->hourly_rate, 0, ',', ' ') }}</td>
                                <td><strong>{{ number_format((float) $line->amount, 0, ',', ' ') }} FCFA</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <section class="panel">
                <div class="panel-head"><h2>Synthèse</h2></div>
                <div class="detail-grid">
                    <div class="detail-item"><span>Retenue {{ number_format((float) $statement->withholding_tax_rate, 2, ',', ' ') }} %</span><strong>{{ number_format((float) $statement->withholding_tax_amount, 0, ',', ' ') }} FCFA</strong></div>
                    <div class="detail-item"><span>Avance</span><strong>{{ number_format((float) $statement->advance_amount, 0, ',', ' ') }} FCFA</strong></div>
                    <div class="detail-item"><span>Autre retenue</span><strong>{{ number_format((float) $statement->other_deduction_amount, 0, ',', ' ') }} FCFA</strong></div>
                    <div class="detail-item"><span>Pièce</span><strong>{{ $statement->identity_document_type }} {{ $statement->identity_document_number }}</strong></div>
                    <div class="detail-item"><span>Validation</span><strong>{{ $statement->approved_at?->format('d/m/Y H:i') ?? '-' }}</strong></div>
                    <div class="detail-item"><span>Paiement</span><strong>{{ $statement->paid_at?->format('d/m/Y H:i') ?? '-' }}</strong></div>
                </div>
            </section>

            @if ($statement->status === 'draft')
                @can('teacher_fees.approve')
                    <section class="panel" style="margin-top:16px">
                        <div class="panel-head"><h2>Validation administrative</h2></div>
                        <form method="POST" action="{{ route('teacher-fees.approve', $statement) }}">@csrf @method('PUT')<button class="btn btn-primary" type="submit">Valider l’ordre de paiement</button></form>
                    </section>
                @endcan
                @can('teacher_fees.manage')
                    <form method="POST" action="{{ route('teacher-fees.destroy', $statement) }}" style="margin-top:12px">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">Supprimer le brouillon</button></form>
                @endcan
            @endif

        </div>
    </section>
@endsection

@push('dialogs')
    @if ($statement->status === 'approved')
        @can('teacher_fees.pay')
            @include('teacher-fees.partials.payment-dialog')
        @endcan
    @endif

    @if (session('teacher_fee_paid'))
        <x-ui.modal
            id="teacher-fee-paid-dialog"
            title="Honoraires payés"
            description="La dépense comptable a été créée automatiquement."
            size="small"
            :open="true"
        >
            <div class="payment-success-summary">
                <span>Net payé</span>
                <strong class="money">{{ number_format((float) $statement->net_amount, 0, ',', ' ') }} FCFA</strong>
                <small>{{ $statement->beneficiary_name }}</small>
            </div>

            <x-slot:footer>
                <button class="btn btn-subtle" type="button" data-dialog-close>Fermer</button>
                <a class="btn btn-subtle" href="{{ route('teacher-fees.pdf', $statement) }}" data-download-feedback="Téléchargement de l’ordre d’honoraires lancé.">PDF</a>
                @can('teachers.view')
                    <a class="btn btn-subtle" href="{{ route('teachers.show', $statement->teacher) }}">Dossier professeur</a>
                @endcan
                @if ($statement->expense)
                    @can('payments.reports')
                        <a class="btn btn-primary" href="{{ route('accounting.expenses.show', $statement->expense) }}">Dépense comptable</a>
                    @endcan
                @endif
            </x-slot:footer>
        </x-ui.modal>
    @endif
@endpush
