@extends('layouts.app', [
    'title' => 'Modifier les acces - Lycee Prive Pagnidibsom',
    'active' => 'staff',
    'pageTitle' => 'Modifier les acces',
    'pageSubtitle' => 'Role : ' . ($roleLabels[$role->name] ?? $role->name),
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('staff.roles.index') }}">Retour</a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    @if ($role->name === 'admin')
        <div class="notice">Le role Admin conserve tous les acces pour eviter de bloquer la gestion du logiciel.</div>
    @endif

    @php($visiblePermissions = $role->name === 'admin' ? collect($permissionGroups)->flatMap(fn ($items) => array_keys($items))->all() : $selectedPermissions)
    @php($actionCounts = collect($visiblePermissions)->map(fn ($permission) => $permissionActions[$permission] ?? 'manage')->countBy())

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>{{ $roleLabels[$role->name] ?? $role->name }}</h2>
                <p style="margin:6px 0 0;color:var(--muted)">{{ $roleDescriptions[$role->name] ?? 'Role interne configure.' }}</p>
            </div>
            <span class="badge">{{ count($visiblePermissions) }} acces actif(s)</span>
        </div>

        <div class="grid modules">
            @foreach ($actionLabels as $action => $label)
                <div class="module">
                    <strong>{{ $actionCounts[$action] ?? 0 }}</strong>
                    <span>{{ $label }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <form method="POST" action="{{ route('staff.roles.update', $role) }}">
        @csrf
        @method('PUT')

        <section class="grid two-col" style="margin-top:16px">
            @foreach ($permissionGroups as $group => $items)
                <div class="panel">
                    <div class="panel-head">
                        <h2>{{ $group }}</h2>
                        <span class="badge">{{ count($items) }} acces</span>
                    </div>

                    <div class="grid" style="grid-template-columns:1fr;gap:10px">
                        @foreach ($items as $permission => $label)
                            @php($action = $permissionActions[$permission] ?? 'manage')
                            <label class="check" style="align-items:flex-start">
                                <input
                                    type="checkbox"
                                    name="permissions[]"
                                    value="{{ $permission }}"
                                    @checked($role->name === 'admin' || in_array($permission, $selectedPermissions, true))
                                    @disabled($role->name === 'admin')
                                >
                                <span>
                                    <span class="badge">{{ $actionLabels[$action] ?? 'Acces' }}</span><br>
                                    <strong>{{ $label }}</strong><br>
                                    <small style="color:var(--muted)">{{ $permission }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </section>

        <div class="form-actions">
            <a class="btn btn-subtle" href="{{ route('staff.roles.index') }}">Annuler</a>
            <button class="btn btn-primary" type="submit">Enregistrer les acces</button>
        </div>
    </form>
@endsection
