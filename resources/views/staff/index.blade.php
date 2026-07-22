@extends('layouts.app', [
    'title' => 'Personnel - Lycée Privé Pagnidibsom',
    'active' => 'staff',
    'pageTitle' => 'Personnel',
    'pageSubtitle' => 'Comptes utilisateurs, rôles et accès internes',
])

@section('page_actions')
    @can('roles.manage')
        <a class="btn btn-subtle" href="{{ route('staff.roles.index') }}">Rôles et accès</a>
    @endcan
    <a class="btn btn-primary" href="{{ route('staff.create') }}">Nouvel utilisateur</a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <section class="panel">
        <div class="panel-head">
            <h2>Recherche</h2>
        </div>

        <form class="searchbar" method="GET" action="{{ route('staff.index') }}">
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, identifiant, e-mail ou téléphone">
            <select name="role">
                <option value="">Tous les rôles</option>
                @foreach ($roleLabels as $role => $label)
                    <option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status">
                <option value="">Tous les statuts</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Actifs</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactifs</option>
                <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>Suspendus</option>
            </select>
            <button class="btn btn-subtle" type="submit">Filtrer</button>
            <a class="btn btn-subtle" href="{{ route('staff.index') }}">Réinitialiser</a>
        </form>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Comptes du personnel</h2>
            <span class="badge">{{ $users->total() }} compte(s)</span>
        </div>

        @if ($users->isEmpty())
            <div class="empty">Aucun compte trouv?. Cr?e le premier compte avec le bouton "Nouvel utilisateur".</div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Identifiant</th>
                        <th>Role</th>
                        <th>Statut</th>
                        <th>Dernière connexion</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $staffUser)
                        @php($role = $staffUser->roles->first()?->name)
                        <tr>
                            <td>
                                <strong>{{ $staffUser->name }}</strong><br>
                                <span style="color:var(--muted)">{{ $staffUser->email }}</span>
                            </td>
                            <td>{{ $staffUser->username }}</td>
                            <td><span class="badge">{{ $roleLabels[$role] ?? '-' }}</span></td>
                            <td><span class="badge">{{ $staffUser->status }}</span></td>
                            <td>{{ $staffUser->last_login_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td><a class="btn btn-subtle" href="{{ route('staff.show', $staffUser) }}">Voir</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="pagination">
                {{ $users->links() }}
            </div>
        @endif
    </section>
@endsection
