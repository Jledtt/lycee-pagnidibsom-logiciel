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

    <form method="POST" action="{{ route('staff.roles.update', $role) }}">
        @csrf
        @method('PUT')

        <section class="grid two-col">
            @foreach ($permissionGroups as $group => $items)
                <div class="panel">
                    <div class="panel-head">
                        <h2>{{ $group }}</h2>
                        <span class="badge">{{ count($items) }} acces</span>
                    </div>

                    <div class="grid" style="grid-template-columns:1fr;gap:10px">
                        @foreach ($items as $permission => $label)
                            <label class="check" style="align-items:flex-start">
                                <input
                                    type="checkbox"
                                    name="permissions[]"
                                    value="{{ $permission }}"
                                    @checked($role->name === 'admin' || in_array($permission, $selectedPermissions, true))
                                    @disabled($role->name === 'admin')
                                >
                                <span>
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
