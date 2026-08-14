@extends('layouts.app', [
    'title' => 'Élèves - Lycée Privé Pagnidibsom',
    'active' => 'students',
    'pageTitle' => 'Élèves',
    'pageSubtitle' => 'Dossiers élèves, contacts tuteurs et suivi administratif',
])

@section('page_actions')
    @can('students.import')
        <a class="btn btn-subtle" href="{{ route('students.import') }}" data-tour-target="students-import">Importer</a>
    @endcan
    @can('students.export')
        <a class="btn btn-subtle" href="{{ route('students.export', request()->query()) }}" data-download-feedback="Téléchargement Excel des élèves lancé. Regarde l’icône de téléchargement du navigateur.">Excel</a>
    @endcan
    @can('students.create')
        <a class="btn btn-primary" href="{{ route('students.create') }}" data-tour-target="students-create">Nouvel élève</a>
    @endcan
@endsection

@section('content')
    <section class="panel" data-tour-target="students-search">
        <div class="panel-head">
            <h2>Recherche</h2>
        </div>

        <form class="searchbar" method="GET" action="{{ route('students.index') }}">
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, prénom ou matricule">
            <select name="status">
                <option value="">Tous les statuts</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Actifs</option>
                <option value="transferred" @selected(($filters['status'] ?? '') === 'transferred')>Transferes</option>
                <option value="dropped" @selected(($filters['status'] ?? '') === 'dropped')>Abandons</option>
                <option value="graduated" @selected(($filters['status'] ?? '') === 'graduated')>Diplomes</option>
                <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>Suspendus</option>
            </select>
            <select name="per_page">
                @foreach ([12, 25, 50, 100] as $size)
                    <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 12) === $size)>{{ $size }} par page</option>
                @endforeach
            </select>
            <button class="btn btn-subtle" type="submit">Filtrer</button>
            <a class="btn btn-subtle" href="{{ route('students.index') }}">Réinitialiser</a>
        </form>
    </section>

    <section class="panel" style="margin-top:16px" data-tour-target="students-list">
        <div class="panel-head">
            <h2>Liste des élèves</h2>
            <span class="badge">{{ $students->total() }} dossier(s)</span>
        </div>

        @if ($students->isEmpty())
            <div class="empty">Aucun élève trouvé. Crée le premier dossier avec le bouton “Nouvel élève”.</div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Élève</th>
                        <th>Sexe</th>
                        <th>Classe</th>
                        @if ($fullStudentAccess)
                            <th>Tuteur</th>
                        @endif
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($students as $student)
                        @php($enrollment = $student->enrollments->sortByDesc('id')->first())
                        @php($guardian = $fullStudentAccess ? $student->guardians->first() : null)
                        <tr>
                            <td>{{ $student->matricule }}</td>
                            <td><strong>{{ $student->full_name }}</strong></td>
                            <td>{{ $student->gender_label }}</td>
                            <td>{{ $student->desired_class ?: ($enrollment?->schoolClass?->name ?? '-') }}</td>
                            @if ($fullStudentAccess)
                                <td>{{ $guardian?->full_name ?? '-' }}</td>
                            @endif
                            <td><span class="badge">{{ $student->status }}</span></td>
                            <td><a class="btn btn-subtle" href="{{ route('students.show', $student) }}">Voir</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="pagination">
                {{ $students->links() }}
            </div>
        @endif
    </section>
@endsection
