@extends('layouts.app', [
    'title' => 'Inscription - Lycée Privé Pagnidibsom',
    'active' => 'enrollments',
    'pageTitle' => 'Inscription',
    'pageSubtitle' => $enrollment->student->full_name . ' - ' . ($enrollment->academicYear?->name ?? 'Année scolaire'),
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('enrollments.index') }}">Retour</a>
    @can('students.export')
        <a class="btn btn-subtle" href="{{ route('students.registration-sheet', $enrollment->student) }}">Fiche</a>
        <a class="btn btn-subtle" href="{{ route('students.registration-sheet.pdf', $enrollment->student) }}">PDF</a>
    @endcan
    @can('enrollments.update')
        <a class="btn btn-primary" href="{{ route('enrollments.edit', $enrollment) }}">Modifier</a>
    @endcan
@endsection

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
                <form method="POST" action="{{ route('enrollments.destroy', $enrollment) }}" style="margin-top:16px" onsubmit="return confirm('Annuler cette inscription ?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger" type="submit">Annuler l’inscription</button>
                </form>
            @endif
        </div>
    </section>
@endsection
