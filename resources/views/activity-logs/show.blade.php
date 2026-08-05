@extends('layouts.app', [
    'title' => 'Detail journal - Lycée Privé Pagnidibsom',
    'active' => 'activity-logs',
    'pageTitle' => 'Détail du journal',
    'pageSubtitle' => 'Lecture claire de l action enregistrée',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('activity-logs.index') }}">Retour au journal</a>
    <a class="btn btn-subtle" href="{{ route('login-histories.index') }}">Historique connexions</a>
@endsection

@section('content')
    @php($actionLabels = [
        'created' => 'Création',
        'updated' => 'Modification',
        'deleted' => 'Suppression',
        'password_changed' => 'Mot de passe change',
        'password_reset' => 'Mot de passe reinitialise',
    ])

    <section class="grid two-col">
        <div class="panel">
            <div class="panel-head">
                <h2>Action</h2>
                <span class="badge">{{ $actionLabels[$log->action] ?? ucfirst($log->action) }}</span>
            </div>

            <div class="detail-grid">
                <div class="detail-item">
                    <span>Date</span>
                    <strong>{{ $log->created_at?->format('d/m/Y H:i:s') }}</strong>
                </div>
                <div class="detail-item">
                    <span>Utilisateur</span>
                    <strong>{{ $log->user?->name ?? 'Systeme' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Module</span>
                    <strong>{{ class_basename($log->auditable_type) }}</strong>
                </div>
                <div class="detail-item">
                    <span>Element</span>
                    <strong>{{ $log->auditable_label ?: '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Identifiant</span>
                    <strong>{{ $log->auditable_id ?: '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Adresse IP</span>
                    <strong>{{ $log->ip_address ?: '-' }}</strong>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Description</h2>
            </div>

            <div class="empty" style="text-align:left">{{ $log->description ?: 'Action enregistrée.' }}</div>
            <div class="detail-item" style="margin-top:16px">
                <span>Navigateur</span>
                <strong style="overflow-wrap:anywhere">{{ $log->user_agent ?: '-' }}</strong>
            </div>
        </div>
    </section>

    <section class="grid two-col" style="margin-top:16px">
        @foreach (['old_values' => 'Avant', 'new_values' => 'Apres'] as $field => $title)
            <div class="panel">
                <div class="panel-head">
                    <h2>{{ $title }}</h2>
                    <span class="badge">{{ count($log->{$field} ?? []) }} champ(s)</span>
                </div>

                @if (empty($log->{$field}))
                    <div class="empty">Aucune donnee.</div>
                @else
                    <div class="subject-list-scroll">
                        <table class="table" style="min-width:620px">
                            <thead>
                                <tr>
                                    <th>Champ</th>
                                    <th>Valeur</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($log->{$field} as $key => $value)
                                    <tr>
                                        <td><strong>{{ str_replace('_', ' ', $key) }}</strong></td>
                                        <td style="overflow-wrap:anywhere">
                                            @if (is_array($value))
                                                {{ json_encode($value, JSON_UNESCAPED_UNICODE) }}
                                            @elseif (is_bool($value))
                                                {{ $value ? 'Oui' : 'Non' }}
                                            @elseif (is_null($value) || $value === '')
                                                -
                                            @else
                                                {{ $value }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    </section>
@endsection
