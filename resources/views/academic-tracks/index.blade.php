@extends('layouts.app', [
    'title' => 'Séries et filières - Lycée Privé Pagnidibsom',
    'active' => 'academic-tracks',
    'pageTitle' => 'Séries et filières',
    'pageSubtitle' => 'Référentiel configurable utilisé par les classes et leurs matières',
])

@section('page_actions')
    @can('academic_tracks.manage')
        <a class="btn btn-primary" href="{{ route('academic-tracks.create') }}">Nouvelle série ou filière</a>
    @endcan
@endsection

@section('content')
    <section class="panel">
        <form class="searchbar" method="GET" action="{{ route('academic-tracks.index') }}">
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom ou code">
            <select name="kind">
                <option value="">Tous les types</option>
                <option value="serie" @selected(($filters['kind'] ?? '') === 'serie')>Séries</option>
                <option value="filiere" @selected(($filters['kind'] ?? '') === 'filiere')>Filières</option>
            </select>
            <select name="status">
                <option value="">Tous les statuts</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Actives</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Désactivées</option>
            </select>
            <button class="btn btn-subtle" type="submit">Filtrer</button>
            <a class="btn btn-subtle" href="{{ route('academic-tracks.index') }}">Réinitialiser</a>
        </form>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Référentiel pédagogique</h2>
            <span class="badge">{{ $tracks->total() }} élément(s)</span>
        </div>

        @if ($tracks->isEmpty())
            <div class="empty">Aucune série ou filière ne correspond à la recherche.</div>
        @else
            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Classes</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tracks as $academicTrack)
                            <tr>
                                <td><strong>{{ $academicTrack->name }}</strong></td>
                                <td><span class="badge">{{ $academicTrack->code }}</span></td>
                                <td>{{ $academicTrack->kind === 'serie' ? 'Série' : 'Filière' }}</td>
                                <td>{{ $academicTrack->school_classes_count }}</td>
                                <td><span class="badge {{ $academicTrack->status === 'active' ? '' : 'badge-warning' }}">{{ $academicTrack->status === 'active' ? 'Active' : 'Désactivée' }}</span></td>
                                <td>
                                    @can('academic_tracks.manage')
                                        <a class="btn btn-subtle" href="{{ route('academic-tracks.edit', $academicTrack) }}">Modifier</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pagination">{{ $tracks->links() }}</div>
        @endif
    </section>
@endsection
