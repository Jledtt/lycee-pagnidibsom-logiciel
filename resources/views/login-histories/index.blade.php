@extends('layouts.app', [
    'title' => 'Historique de connexion - Lycée Privé Pagnidibsom',
    'active' => 'activity-logs',
    'pageTitle' => 'Historique de connexion',
    'pageSubtitle' => 'Connexions, échecs et déconnexions du personnel',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('activity-logs.index') }}">Journal d’activité</a>
@endsection

@section('content')
    @php($statusLabels = ['success' => 'Connexion réussie', 'failed' => 'Connexion refusée', 'logout' => 'Déconnexion'])

    <section class="panel">
        <div class="panel-head">
            <h2>Filtres</h2>
            <span class="badge">{{ $histories->total() }} ligne(s)</span>
        </div>

        <form class="searchbar" method="GET" action="{{ route('login-histories.index') }}">
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, identifiant, IP">
            <select name="status">
                <option value="">Tous les statuts</option>
                @foreach ($statusLabels as $status => $label)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="user_id">
                <option value="">Tous les utilisateurs</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((int) ($filters['user_id'] ?? 0) === $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
            <button class="btn btn-subtle" type="submit">Afficher</button>
            <a class="btn btn-subtle" href="{{ route('login-histories.index') }}">Réinitialiser</a>
        </form>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Connexions récentes</h2>
            <span class="badge">{{ $histories->count() }} ligne(s)</span>
        </div>

        @if ($histories->isEmpty())
            <div class="empty">Aucune connexion trouvée.</div>
        @else
            <div class="subject-list-scroll">
                <table class="table" style="min-width:980px">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Utilisateur</th>
                            <th>Identifiant saisi</th>
                            <th>Statut</th>
                            <th>Adresse IP</th>
                            <th>Navigateur</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($histories as $history)
                            <tr>
                                <td>{{ $history->created_at?->format('d/m/Y H:i:s') }}</td>
                                <td>
                                    <strong>{{ $history->user?->name ?? 'Compte non reconnu' }}</strong><br>
                                    <span style="color:var(--muted)">{{ $history->user?->email ?? '-' }}</span>
                                </td>
                                <td>{{ $history->username ?: '-' }}</td>
                                <td>
                                    <span class="badge {{ $history->status === 'failed' ? 'badge-danger' : ($history->status === 'logout' ? 'badge-warning' : '') }}">
                                        {{ $statusLabels[$history->status] ?? $history->status }}
                                    </span>
                                </td>
                                <td>{{ $history->ip_address ?: '-' }}</td>
                                <td style="max-width:360px;color:var(--muted)">{{ $history->user_agent ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                {{ $histories->links() }}
            </div>
        @endif
    </section>
@endsection
