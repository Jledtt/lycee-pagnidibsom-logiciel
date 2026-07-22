@extends('layouts.app', [
    'title' => 'Classes - Lycée Privé Pagnidibsom',
    'active' => 'classes',
    'pageTitle' => 'Classes',
    'pageSubtitle' => 'Niveaux, effectifs et capacités de l\'année ' . ($academicYear?->name ?? 'active'),
])

@section('page_actions')
    @can('classes.manage')
        <a class="btn btn-primary" href="{{ route('classes.create') }}">Nouvelle classe</a>
    @endcan
@endsection

@section('content')
    <section class="panel">
        <div class="panel-head">
            <h2>Recherche</h2>
        </div>

        <form class="searchbar" method="GET" action="{{ route('classes.index') }}">
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom ou code de classe">
            <select name="status">
                <option value="">Tous les statuts</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Actives</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactives</option>
                <option value="archived" @selected(($filters['status'] ?? '') === 'archived')>Archivees</option>
            </select>
            <button class="btn btn-subtle" type="submit">Filtrer</button>
            <a class="btn btn-subtle" href="{{ route('classes.index') }}">Réinitialiser</a>
        </form>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Liste des classes</h2>
            <span class="badge">{{ $classes->total() }} classe(s)</span>
        </div>

        @if ($classes->isEmpty())
            <div class="empty">Aucune classe configurée. Crée la première classe avec le bouton "Nouvelle classe".</div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Classe</th>
                        <th>Niveau</th>
                        <th>Effectif</th>
                        <th>Capacite</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($classes as $class)
                        @php($percent = $class->capacity ? min(100, round(($class->enrollments_count / $class->capacity) * 100)) : 0)
                        <tr>
                            <td>
                                <strong>{{ $class->name }}</strong>
                                @if ($class->code)
                                    <span class="badge">{{ $class->code }}</span>
                                @endif
                            </td>
                            <td>{{ $class->level?->name ?? '-' }}</td>
                            <td>
                                <strong>{{ $class->enrollments_count }}</strong>
                                @if ($class->capacity)
                                    <div class="meter" aria-hidden="true"><span style="--value: {{ $percent }}%"></span></div>
                                @endif
                            </td>
                            <td>{{ $class->capacity ?? '-' }}</td>
                            <td>
                                <span class="badge {{ $class->status === 'active' ? '' : 'badge-warning' }}">{{ $class->status }}</span>
                            </td>
                            <td><a class="btn btn-subtle" href="{{ route('classes.show', $class) }}">Voir</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="pagination">
                {{ $classes->links() }}
            </div>
        @endif
    </section>
@endsection
