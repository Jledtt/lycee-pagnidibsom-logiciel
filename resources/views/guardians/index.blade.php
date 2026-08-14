@extends('layouts.app', [
    'title' => 'Responsables légaux - Lycée Privé Pagnidibsom',
    'active' => 'guardians',
    'pageTitle' => 'Responsables légaux',
    'pageSubtitle' => 'Parents, tuteurs, contacts et élèves rattachés',
])

@section('page_actions')
    @can('guardians.manage')
        <a class="btn btn-primary" href="{{ route('guardians.create') }}">Nouveau responsable</a>
    @endcan
@endsection

@section('content')
    <section class="panel">
        <form class="searchbar" method="GET" action="{{ route('guardians.index') }}">
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, téléphone ou e-mail">
            <select name="status">
                <option value="">Tous les statuts</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Actifs</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactifs</option>
            </select>
            <button class="btn btn-subtle" type="submit">Filtrer</button>
            <a class="btn btn-subtle" href="{{ route('guardians.index') }}">Réinitialiser</a>
        </form>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Fiches administratives</h2>
            <span class="badge">{{ $guardians->total() }} responsable(s)</span>
        </div>

        @if ($guardians->isEmpty())
            <div class="empty">Aucun responsable légal ne correspond à la recherche.</div>
        @else
            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Responsable</th>
                            <th>Téléphones</th>
                            <th>Profession</th>
                            <th>Élèves</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($guardians as $guardian)
                            <tr>
                                <td>
                                    <strong>{{ $guardian->full_name }}</strong><br>
                                    <span style="color:var(--muted)">{{ $guardian->email ?: 'Aucun e-mail' }}</span>
                                </td>
                                <td>
                                    {{ $guardian->phone_primary }}
                                    @if ($guardian->phone_secondary)<br><span style="color:var(--muted)">{{ $guardian->phone_secondary }}</span>@endif
                                </td>
                                <td>{{ $guardian->profession ?: '-' }}</td>
                                <td>{{ $guardian->students_count }}</td>
                                <td><span class="badge {{ $guardian->status === 'active' ? '' : 'badge-warning' }}">{{ $guardian->status === 'active' ? 'Actif' : 'Inactif' }}</span></td>
                                <td><a class="btn btn-subtle" href="{{ route('guardians.show', $guardian) }}">Ouvrir</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pagination">{{ $guardians->links() }}</div>
        @endif
    </section>
@endsection
