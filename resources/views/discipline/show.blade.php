@extends('layouts.app', [
    'title' => $record->title . ' - Discipline',
    'active' => 'discipline',
    'pageTitle' => $record->title,
    'pageSubtitle' => $record->student?->full_name . ' · ' . $record->record_date?->format('d/m/Y'),
])

@php
    $typeLabels = ['observation' => 'Observation', 'warning' => 'Avertissement', 'sanction' => 'Sanction'];
    $statusLabels = ['active' => 'En cours', 'resolved' => 'Résolu', 'cancelled' => 'Annulé'];
@endphp

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('discipline.index') }}">Retour</a>
    <a class="btn btn-subtle" href="{{ route('students.show', $record->student) }}">Dossier élève</a>
    @can('discipline.manage')
        @if ($record->status === 'active')
            <a class="btn btn-primary" href="{{ route('discipline.edit', $record) }}">Modifier</a>
        @endif
    @endcan
@endsection

@push('dialogs')
    @can('discipline.manage')
        @if ($record->status === 'active')
            <x-ui.modal id="discipline-resolve-dialog" title="Résoudre l’incident" description="Indique la mesure prise avant de clôturer cet incident." size="medium" :open="$errors->has('action_taken')">
                <form id="discipline-resolve-form" method="POST" action="{{ route('discipline.resolve', $record) }}" data-prevent-double-submit>
                    @csrf
                    <div class="field">
                        <label for="resolve_action_taken">Mesure prise</label>
                        <textarea id="resolve_action_taken" name="action_taken" rows="6" maxlength="5000" required>{{ old('action_taken', $record->action_taken) }}</textarea>
                        @error('action_taken')<span class="error">{{ $message }}</span>@enderror
                    </div>
                </form>
                <x-slot:footer>
                    <button class="btn btn-subtle" type="button" data-dialog-close>Annuler</button>
                    <button class="btn btn-primary" type="submit" form="discipline-resolve-form">Confirmer la résolution</button>
                </x-slot:footer>
            </x-ui.modal>

            <x-ui.modal id="discipline-cancel-dialog" title="Annuler l’incident" description="L’incident restera dans l’historique avec son motif d’annulation." size="medium" :open="$errors->has('cancellation_reason')">
                <form id="discipline-cancel-form" method="POST" action="{{ route('discipline.cancel', $record) }}" data-prevent-double-submit>
                    @csrf
                    <div class="field">
                        <label for="cancellation_reason">Motif d’annulation</label>
                        <textarea id="cancellation_reason" name="cancellation_reason" rows="5" maxlength="2000" required>{{ old('cancellation_reason') }}</textarea>
                        @error('cancellation_reason')<span class="error">{{ $message }}</span>@enderror
                    </div>
                </form>
                <x-slot:footer>
                    <button class="btn btn-subtle" type="button" data-dialog-close>Retour</button>
                    <button class="btn btn-danger" type="submit" form="discipline-cancel-form">Annuler l’incident</button>
                </x-slot:footer>
            </x-ui.modal>
        @endif
    @endcan
@endpush

@section('content')
    <section class="stats">
        <div class="stat"><span>Élève</span><strong>{{ $record->student?->full_name }}</strong></div>
        <div class="stat"><span>Classe</span><strong>{{ $record->schoolClass?->name ?? '-' }}</strong></div>
        <div class="stat"><span>Nature</span><strong>{{ $typeLabels[$record->type] ?? $record->type }}</strong></div>
        <div class="stat"><span>Statut</span><strong>{{ $statusLabels[$record->status] ?? $record->status }}</strong></div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head"><h2>Faits enregistrés</h2></div>
        <div class="detail-grid">
            <div class="detail-item"><span>Date</span><strong>{{ $record->record_date?->format('d/m/Y') }}</strong></div>
            <div class="detail-item"><span>Année scolaire</span><strong>{{ $record->academicYear?->name }}</strong></div>
            <div class="detail-item"><span>Enregistré par</span><strong>{{ $record->creator?->name ?? 'Compte supprimé' }}</strong></div>
            <div class="detail-item wide"><span>Description</span><strong style="white-space:pre-wrap">{{ $record->description ?: 'Aucune description complémentaire.' }}</strong></div>
        </div>
    </section>

    @if ($record->action_taken || $record->status === 'resolved')
        <section class="panel" style="margin-top:16px">
            <div class="panel-head"><h2>Mesure prise</h2></div>
            <div class="detail-grid">
                <div class="detail-item wide"><span>Décision ou mesure</span><strong style="white-space:pre-wrap">{{ $record->action_taken ?: '-' }}</strong></div>
                @if ($record->resolved_at)
                    <div class="detail-item"><span>Résolu le</span><strong>{{ $record->resolved_at->format('d/m/Y à H:i') }}</strong></div>
                    <div class="detail-item"><span>Résolu par</span><strong>{{ $record->resolver?->name ?? 'Compte supprimé' }}</strong></div>
                @endif
            </div>
        </section>
    @endif

    @if ($record->status === 'cancelled')
        <section class="panel" style="margin-top:16px">
            <div class="panel-head"><h2>Annulation</h2></div>
            <div class="detail-grid">
                <div class="detail-item wide"><span>Motif</span><strong style="white-space:pre-wrap">{{ $record->cancellation_reason }}</strong></div>
                <div class="detail-item"><span>Annulé le</span><strong>{{ $record->cancelled_at?->format('d/m/Y à H:i') }}</strong></div>
                <div class="detail-item"><span>Annulé par</span><strong>{{ $record->canceller?->name ?? 'Compte supprimé' }}</strong></div>
            </div>
        </section>
    @endif

    @can('discipline.manage')
        @if ($record->status === 'active')
            <section class="panel" style="margin-top:16px">
                <div class="panel-head"><h2>Traiter l’incident</h2></div>
                <div class="page-actions">
                    <button class="btn btn-primary" type="button" data-dialog-open="discipline-resolve-dialog">Marquer comme résolu</button>
                    <button class="btn btn-danger" type="button" data-dialog-open="discipline-cancel-dialog">Annuler l’incident</button>
                </div>
            </section>
        @endif
    @endcan
@endsection
