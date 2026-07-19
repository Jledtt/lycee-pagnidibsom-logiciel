@extends('layouts.app', [
    'title' => 'Eleves - Lycee Prive Pagnidibsom',
    'active' => 'students',
    'pageTitle' => 'Eleves',
    'pageSubtitle' => 'Dossiers eleves, contacts tuteurs et suivi administratif',
])

@section('page_actions')
    @can('students.import')
        <a class="btn btn-subtle" href="{{ route('students.import') }}">Importer</a>
    @endcan
    @can('students.export')
        <a class="btn btn-subtle" href="{{ route('students.export', request()->query()) }}" data-download-feedback="Telechargement Excel des eleves lance. Regarde l'icone de telechargement du navigateur.">Excel</a>
    @endcan
    @can('students.create')
        <a class="btn btn-primary" href="{{ route('students.create') }}">Nouvel eleve</a>
    @endcan
@endsection

@section('content')
    <section class="panel">
        <div class="panel-head">
            <h2>Recherche</h2>
        </div>

        <form class="searchbar" method="GET" action="{{ route('students.index') }}">
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, prenom ou matricule">
            <select name="status">
                <option value="">Tous les statuts</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Actifs</option>
                <option value="transferred" @selected(($filters['status'] ?? '') === 'transferred')>Transferes</option>
                <option value="dropped" @selected(($filters['status'] ?? '') === 'dropped')>Abandons</option>
                <option value="graduated" @selected(($filters['status'] ?? '') === 'graduated')>Diplomes</option>
                <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>Suspendus</option>
            </select>
            <button class="btn btn-subtle" type="submit">Filtrer</button>
            <a class="btn btn-subtle" href="{{ route('students.index') }}">Reinitialiser</a>
        </form>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Liste des eleves</h2>
            <span class="badge">{{ $students->total() }} dossier(s)</span>
        </div>

        @if ($students->isEmpty())
            <div class="empty">Aucun eleve trouve. Cree le premier dossier avec le bouton “Nouvel eleve”.</div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Eleve</th>
                        <th>Sexe</th>
                        <th>Classe</th>
                        <th>Tuteur</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($students as $student)
                        @php($enrollment = $student->enrollments->sortByDesc('id')->first())
                        @php($guardian = $student->guardians->first())
                        <tr>
                            <td>{{ $student->matricule }}</td>
                            <td><strong>{{ $student->full_name }}</strong></td>
                            <td>{{ $student->gender_label }}</td>
                            <td>{{ $student->desired_class ?: ($enrollment?->schoolClass?->name ?? '-') }}</td>
                            <td>{{ $guardian?->full_name ?? '-' }}</td>
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
