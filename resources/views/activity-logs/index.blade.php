@extends('layouts.app', [
    'title' => 'Journal d’activité - Lycée Privé Pagnidibsom',
    'active' => 'activity-logs',
    'pageTitle' => 'Journal d’activité',
    'pageSubtitle' => 'Contrôle des modifications effectuées dans le logiciel',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('login-histories.index') }}">Historique connexions</a>
@endsection

@section('content')
    @php($actionLabels = [
        'created' => 'Creation',
        'updated' => 'Modification',
        'deleted' => 'Suppression',
        'password_changed' => 'Mot de passe change',
        'password_reset' => 'Mot de passe reinitialise',
    ])

    <section class="panel">
        <div class="panel-head">
            <h2>Filtres</h2>
            <span class="badge">{{ $logs->total() }} action(s)</span>
        </div>

        <form class="searchbar" method="GET" action="{{ route('activity-logs.index') }}">
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Rechercher">
            <select name="action">
                <option value="">Toutes les actions</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>
                        {{ $actionLabels[$action] ?? ucfirst($action) }}
                    </option>
                @endforeach
            </select>
            <select name="model">
                <option value="">Tous les modules</option>
                @foreach ($models as $model)
                    <option value="{{ $model }}" @selected(($filters['model'] ?? '') === $model)>{{ class_basename($model) }}</option>
                @endforeach
            </select>
            <select name="user_id">
                <option value="">Tous les utilisateurs</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((int) ($filters['user_id'] ?? 0) === $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
            <button class="btn btn-subtle" type="submit">Afficher</button>
            <a class="btn btn-subtle" href="{{ route('activity-logs.index') }}">Réinitialiser</a>
        </form>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Actions recentes</h2>
            <span class="badge">{{ $logs->count() }} ligne(s)</span>
        </div>

        @if ($logs->isEmpty())
            <div class="empty">Aucune action trouvée.</div>
        @else
            <div class="subject-list-scroll">
                <table class="table" style="min-width:1040px">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Element</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td>{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                                <td>
                                    <strong>{{ $log->user?->name ?? 'Systeme' }}</strong><br>
                                    <span class="badge">{{ $log->ip_address ?: '-' }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $log->action === 'deleted' ? 'badge-danger' : ($log->action === 'updated' ? 'badge-warning' : '') }}">
                                        {{ $actionLabels[$log->action] ?? ucfirst($log->action) }}
                                    </span>
                                </td>
                                <td>{{ class_basename($log->auditable_type) }}</td>
                                <td>
                                    <strong>{{ $log->auditable_label ?: '-' }}</strong><br>
                                    <span style="color:var(--muted)">ID {{ $log->auditable_id ?: '-' }}</span>
                                </td>
                                <td>
                                    <a class="btn btn-subtle" href="{{ route('activity-logs.show', $log) }}">Voir</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                {{ $logs->links() }}
            </div>
        @endif
    </section>
@endsection
