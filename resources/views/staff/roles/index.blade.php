@extends('layouts.app', [
    'title' => 'Rôles et accès - Lycée Privé Pagnidibsom',
    'active' => 'staff',
    'pageTitle' => 'Rôles et accès',
    'pageSubtitle' => 'Voir et modifier les autorisations du personnel',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('staff.index') }}">Personnel</a>
@endsection

@section('content')
    <section class="grid modules">
        <div class="module">
            <strong>Voir</strong>
            <span>Consultation des pages, dossiers et listes.</span>
        </div>
        <div class="module">
            <strong>Modifier</strong>
            <span>Ajout, saisie, correction ou suppression encadree.</span>
        </div>
        <div class="module">
            <strong>Imprimer</strong>
            <span>Génération de PDF, reçus, certificats et exports.</span>
        </div>
        <div class="module">
            <strong>Administrer</strong>
            <span>Paramètres sensibles, utilisateurs, rôles et verrouillages.</span>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2>Roles internes</h2>
            <span class="badge">{{ $roles->count() }} rôle(s)</span>
        </div>

        <div class="subject-list-scroll">
        <table class="table" style="min-width:1080px">
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Ce rôle peut</th>
                    <th>Actions</th>
                    <th>Modules autorises</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    @php($permissions = $role->permissions->pluck('name')->all())
                    @php($actionCounts = collect($permissions)->map(fn ($permission) => $permissionActions[$permission] ?? 'manage')->countBy())
                    <tr>
                        <td>
                            <strong>{{ $roleLabels[$role->name] ?? $role->name }}</strong><br>
                            <span style="color:var(--muted)">{{ count($permissions) }} permission(s)</span>
                        </td>
                        <td>
                            <strong>{{ $roleDescriptions[$role->name] ?? 'Rôle interne configuré.' }}</strong>
                        </td>
                        <td>
                            <div class="searchbar">
                                @foreach ($actionLabels as $action => $label)
                                    @if (($actionCounts[$action] ?? 0) > 0)
                                        <span class="badge">{{ $label }} : {{ $actionCounts[$action] }}</span>
                                    @endif
                                @endforeach
                            </div>
                        </td>
                        <td>
                            <div class="searchbar">
                                @foreach ($permissionGroups as $group => $items)
                                    @php($count = count(array_intersect(array_keys($items), $permissions)))
                                    @if ($count > 0)
                                        <span class="badge">{{ $group }} : {{ $count }}</span>
                                    @endif
                                @endforeach
                            </div>
                        </td>
                        <td><a class="btn btn-subtle" href="{{ route('staff.roles.edit', $role) }}">Modifier</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </section>

    <section class="grid modules" style="margin-top:16px">
        @foreach ($permissionGroups as $group => $items)
            <div class="module">
                <strong>{{ $group }}</strong>
                <span>{{ count($items) }} accès configurables pour voir, modifier, imprimer ou administrer ce module.</span>
            </div>
        @endforeach
    </section>
@endsection
