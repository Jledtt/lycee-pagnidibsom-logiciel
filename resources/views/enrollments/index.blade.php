@extends('layouts.app', [
    'title' => 'Inscriptions - Lycee Prive Pagnidibsom',
    'active' => 'enrollments',
    'pageTitle' => 'Inscriptions',
    'pageSubtitle' => 'Suivi des affectations eleves par classe pour ' . ($academicYear?->name ?? 'l\'annee active'),
])

@section('page_actions')
    <a class="btn btn-primary" href="{{ route('enrollments.create') }}">Nouvelle inscription</a>
@endsection

@section('content')
    <section class="panel">
        <div class="panel-head">
            <h2>Recherche</h2>
        </div>

        <form class="searchbar" method="GET" action="{{ route('enrollments.index') }}">
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, prenom ou matricule">
            <select name="school_class_id">
                <option value="">Toutes les classes</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected((string) ($filters['school_class_id'] ?? '') === (string) $class->id)>{{ $class->name }}</option>
                @endforeach
            </select>
            <select name="status">
                <option value="">Tous les statuts</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Actives</option>
                <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>En attente</option>
                <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>Terminees</option>
                <option value="cancelled" @selected(($filters['status'] ?? '') === 'cancelled')>Annulees</option>
            </select>
            <button class="btn btn-subtle" type="submit">Filtrer</button>
            <a class="btn btn-subtle" href="{{ route('enrollments.index') }}">Reinitialiser</a>
        </form>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Liste des inscriptions</h2>
            <span class="badge">{{ $enrollments->total() }} inscription(s)</span>
        </div>

        @if ($enrollments->isEmpty())
            <div class="empty">Aucune inscription trouvee pour le moment.</div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Eleve</th>
                        <th>Classe</th>
                        <th>Type</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($enrollments as $enrollment)
                        <tr>
                            <td>{{ $enrollment->enrollment_date?->format('d/m/Y') ?? '-' }}</td>
                            <td>
                                <strong>{{ $enrollment->student->full_name }}</strong><br>
                                <span class="badge">{{ $enrollment->student->matricule }}</span>
                            </td>
                            <td>{{ $enrollment->schoolClass?->name ?? '-' }}</td>
                            <td>{{ $enrollment->type }}</td>
                            <td><span class="badge {{ $enrollment->status === 'active' ? '' : 'badge-warning' }}">{{ $enrollment->status }}</span></td>
                            <td><a class="btn btn-subtle" href="{{ route('enrollments.show', $enrollment) }}">Voir</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="pagination">
                {{ $enrollments->links() }}
            </div>
        @endif
    </section>
@endsection
