@extends('layouts.app', [
    'title' => 'Inscription - Lycée Privé Pagnidibsom',
    'active' => 'enrollments',
    'pageTitle' => 'Inscription',
    'pageSubtitle' => $enrollment->student->full_name . ' - ' . ($enrollment->academicYear?->name ?? 'Année scolaire'),
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('enrollments.index') }}">Retour</a>
    <a class="btn btn-subtle" href="{{ route('enrollments.show', $enrollment) }}" data-dialog-open="enrollment-summary-drawer">Résumé inscription</a>
    @can('payments.view')
        @if ($financialSummary)
            <a class="btn btn-subtle" href="{{ route('payments.students.statement', $enrollment->student) }}" data-dialog-open="enrollment-financial-drawer">Situation financière</a>
        @endif
    @endcan
    @can('students.export')
        <a class="btn btn-subtle" href="{{ route('students.registration-sheet', $enrollment->student) }}">Fiche d’inscription</a>
        <a class="btn btn-subtle" href="{{ route('students.school-card.pdf', $enrollment->student) }}" data-download-feedback="Téléchargement de la carte scolaire lancé.">Carte scolaire</a>
    @endcan
    @if ($canStartPayment)
        <a
            class="btn btn-primary"
            href="{{ route('payments.create', ['student_id' => $enrollment->student_id]) }}"
            data-dialog-open="enrollment-payment-dialog"
            data-payment-student-id="{{ $enrollment->student_id }}"
        >Premier paiement</a>
    @endif
    @can('enrollments.update')
        <a class="btn btn-subtle" href="{{ route('enrollments.edit', $enrollment) }}">Modifier</a>
    @endcan
@endsection

@push('dialogs')
    <x-ui.drawer
        id="enrollment-summary-drawer"
        title="Résumé de l’inscription"
        description="{{ $enrollment->student->full_name }} - {{ $enrollment->academicYear?->name ?? 'Année scolaire' }}"
    >
        <div class="follow-up-summary-list">
            <div><span>Matricule</span><strong>{{ $enrollment->student->matricule }}</strong></div>
            <div><span>Classe</span><strong>{{ $enrollment->schoolClass?->name ?? '-' }}</strong></div>
            <div><span>Niveau</span><strong>{{ $enrollment->schoolClass?->level?->name ?? '-' }}</strong></div>
            <div><span>Date</span><strong>{{ $enrollment->enrollment_date?->format('d/m/Y') ?? '-' }}</strong></div>
            <div><span>Type</span><strong>{{ match ($enrollment->type) {
                'renewal' => 'Réinscription',
                'transfer' => 'Transfert',
                default => 'Nouvelle inscription',
            } }}</strong></div>
            <div><span>Statut</span><strong>{{ $enrollment->status === 'active' ? 'Active' : ucfirst($enrollment->status) }}</strong></div>
        </div>

        <x-slot:footer>
            <button class="btn btn-subtle" type="button" data-dialog-close>Fermer</button>
            <a class="btn btn-primary" href="{{ route('students.show', $enrollment->student) }}">Voir le dossier</a>
        </x-slot:footer>
    </x-ui.drawer>

    @can('payments.view')
        @if ($financialSummary)
            <x-ui.drawer
                id="enrollment-financial-drawer"
                title="Situation financière"
                description="{{ $enrollment->student->full_name }} - {{ $enrollment->academicYear?->name ?? 'Année scolaire' }}"
            >
                <div class="follow-up-summary-list">
                    <div><span>Total attendu</span><strong class="money">{{ is_null($financialSummary['expected']) ? 'À configurer' : number_format($financialSummary['expected'], 0, ',', ' ').' FCFA' }}</strong></div>
                    <div><span>Total payé</span><strong class="money">{{ number_format($financialSummary['paid'], 0, ',', ' ') }} FCFA</strong></div>
                    <div class="follow-up-summary-list__highlight"><span>Reste à payer</span><strong class="money">{{ is_null($financialSummary['balance']) ? 'À configurer' : number_format($financialSummary['balance'], 0, ',', ' ').' FCFA' }}</strong></div>
                </div>

                <x-slot:footer>
                    <button class="btn btn-subtle" type="button" data-dialog-close>Fermer</button>
                    <a class="btn btn-primary" href="{{ route('payments.students.statement', $enrollment->student) }}">Voir la situation complète</a>
                </x-slot:footer>
            </x-ui.drawer>
        @endif
    @endcan

    @if ($canStartPayment)
        @include('payments.partials.create-dialog', [
            'paymentForm' => $paymentForm,
            'dialogId' => 'enrollment-payment-dialog',
            'formId' => 'enrollment-payment-form',
            'cancelUrl' => route('enrollments.show', $enrollment),
        ])
    @endif

    @if (session('enrollment_created'))
        <x-ui.modal
            id="enrollment-created-dialog"
            title="Inscription enregistrée"
            description="{{ $enrollment->student->full_name }} est inscrit(e) en {{ $enrollment->schoolClass?->name ?? 'classe' }}."
            size="large"
            :open="true"
        >
            <div class="creation-follow-up">
                <span>{{ $enrollment->academicYear?->name }}</span>
                <strong>{{ $enrollment->student->matricule }} - {{ $enrollment->schoolClass?->name }}</strong>
            </div>

            <x-slot:footer>
                @if ($canStartPayment)
                    <button
                        class="btn btn-primary"
                        type="button"
                        data-dialog-open="enrollment-payment-dialog"
                        data-payment-student-id="{{ $enrollment->student_id }}"
                    >Premier paiement</button>
                @endif
                @can('students.export')
                    <a class="btn btn-subtle" href="{{ route('students.registration-sheet', $enrollment->student) }}">Fiche d’inscription</a>
                    <a class="btn btn-subtle" href="{{ route('students.school-card.pdf', $enrollment->student) }}" data-download-feedback="Téléchargement de la carte scolaire lancé.">Carte scolaire</a>
                @endcan
                @can('classes.manage')
                    <a class="btn btn-subtle" href="{{ route('classes.show', $enrollment->schoolClass) }}">Voir la classe</a>
                @endcan
            </x-slot:footer>
        </x-ui.modal>
    @endif
@endpush

@section('content')
    <section class="grid two-col">
        <div class="panel">
            <div class="panel-head">
                <h2>Détails de l’inscription</h2>
                <span class="badge {{ $enrollment->status === 'active' ? '' : 'badge-warning' }}">{{ $enrollment->status }}</span>
            </div>

            <div class="detail-grid">
                <div class="detail-item">
                    <span>Élève</span>
                    <strong>{{ $enrollment->student->full_name }}</strong>
                </div>
                <div class="detail-item">
                    <span>Matricule</span>
                    <strong>{{ $enrollment->student->matricule }}</strong>
                </div>
                <div class="detail-item">
                    <span>Classe</span>
                    <strong>{{ $enrollment->schoolClass?->name ?? '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Niveau</span>
                    <strong>{{ $enrollment->schoolClass?->level?->name ?? '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Année scolaire</span>
                    <strong>{{ $enrollment->academicYear?->name ?? '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Date</span>
                    <strong>{{ $enrollment->enrollment_date?->format('d/m/Y') ?? '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Type</span>
                    <strong>{{ $enrollment->type }}</strong>
                </div>
                <div class="detail-item">
                    <span>École precedente</span>
                    <strong>{{ $enrollment->previous_school ?? '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Saisie par</span>
                    <strong>{{ $enrollment->creator?->name ?? '-' }}</strong>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Actions</h2>
            </div>

            <div class="grid" style="grid-template-columns:1fr">
                <a class="btn btn-subtle" href="{{ route('students.show', $enrollment->student) }}">Ouvrir le dossier élève</a>
                @can('classes.manage')
                    <a class="btn btn-subtle" href="{{ route('classes.show', $enrollment->schoolClass) }}">Ouvrir la classe</a>
                @endcan
                @can('students.export')
                    <a class="btn btn-subtle" href="{{ route('students.registration-sheet.pdf', $enrollment->student) }}">Télécharger la fiche PDF</a>
                @endcan
            </div>

            @if ($enrollment->notes)
                <div class="detail-item" style="margin-top:16px">
                    <span>Notes</span>
                    <strong>{{ $enrollment->notes }}</strong>
                </div>
            @endif

            @if ($enrollment->status !== 'cancelled')
                <form
                    method="POST"
                    action="{{ route('enrollments.destroy', $enrollment) }}"
                    style="margin-top:16px"
                    data-confirm
                    data-confirm-title="Annuler l’inscription"
                    data-confirm-object="{{ $enrollment->student->full_name }} — {{ $enrollment->schoolClass?->name ?? 'Classe non renseignée' }}"
                    data-confirm-message="L’inscription passera au statut annulé. Le dossier élève et les paiements déjà enregistrés resteront conservés."
                    data-confirm-action="Annuler l’inscription"
                    data-prevent-double-submit
                >
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger" type="submit">Annuler l’inscription</button>
                </form>
            @endif
        </div>
    </section>
@endsection
