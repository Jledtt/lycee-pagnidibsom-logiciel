@extends('layouts.app', [
    'title' => 'Roles et acces - Lycee Prive Pagnidibsom',
    'active' => 'staff',
    'pageTitle' => 'Roles et acces',
    'pageSubtitle' => 'Voir et modifier les autorisations du personnel',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('staff.index') }}">Personnel</a>
@endsection

@section('content')
    <section class="panel">
        <div class="panel-head">
            <h2>Roles internes</h2>
            <span class="badge">{{ $roles->count() }} role(s)</span>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Acces autorises</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    @php($permissions = $role->permissions->pluck('name')->all())
                    <tr>
                        <td>
                            <strong>{{ $roleLabels[$role->name] ?? $role->name }}</strong><br>
                            <span style="color:var(--muted)">{{ count($permissions) }} permission(s)</span>
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
    </section>

    <section class="grid modules" style="margin-top:16px">
        @foreach ($permissionGroups as $group => $items)
            <div class="module">
                <strong>{{ $group }}</strong>
                <span>{{ count($items) }} acces configurables</span>
            </div>
        @endforeach
    </section>
@endsection
