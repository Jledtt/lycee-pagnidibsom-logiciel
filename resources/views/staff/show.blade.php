@extends('layouts.app', [
    'title' => $user->name . ' - Personnel',
    'active' => 'staff',
    'pageTitle' => $user->name,
    'pageSubtitle' => 'Fiche utilisateur interne',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('staff.index') }}">Retour</a>
    @can('roles.manage')
        <a class="btn btn-subtle" href="{{ route('staff.roles.index') }}">Roles et acces</a>
    @endcan
    <a class="btn btn-primary" href="{{ route('staff.edit', $user) }}">Modifier</a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <section class="grid two-col">
        <div class="panel">
            <div class="panel-head">
                <h2>Compte</h2>
            </div>

            @php($role = $user->roles->first()?->name)
            <div class="detail-grid">
                <div class="detail-item">
                    <span>Nom complet</span>
                    <strong>{{ $user->name }}</strong>
                </div>
                <div class="detail-item">
                    <span>Identifiant</span>
                    <strong>{{ $user->username }}</strong>
                </div>
                <div class="detail-item">
                    <span>Role</span>
                    <strong>{{ $roleLabels[$role] ?? '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Statut</span>
                    <strong>{{ $user->status }}</strong>
                </div>
                <div class="detail-item">
                    <span>E-mail</span>
                    <strong>{{ $user->email }}</strong>
                </div>
                <div class="detail-item">
                    <span>Telephone</span>
                    <strong>{{ $user->phone ?: '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Derniere connexion</span>
                    <strong>{{ $user->last_login_at?->format('d/m/Y H:i') ?? '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Cree le</span>
                    <strong>{{ $user->created_at?->format('d/m/Y H:i') ?? '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Mis a jour</span>
                    <strong>{{ $user->updated_at?->format('d/m/Y H:i') ?? '-' }}</strong>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Acces</h2>
            </div>

            @if ($role === 'admin')
                <div class="empty">Acces total a tous les modules de gestion.</div>
            @elseif ($role === 'direction')
                <div class="empty">Acces aux eleves, inscriptions, rapports, documents et suivis de direction.</div>
            @elseif ($role === 'comptable')
                <div class="empty">Acces aux paiements, recus, impayes et rapports financiers.</div>
            @elseif ($role === 'secretariat')
                <div class="empty">Acces aux eleves, inscriptions, classes et documents administratifs.</div>
            @else
                <div class="empty">Acces limite aux modules pedagogiques prevus pour ce role.</div>
            @endif

            @can('deactivate-staff-user', $user)
                <form method="POST" action="{{ route('staff.destroy', $user) }}" style="margin-top:16px">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger" type="submit">Desactiver le compte</button>
                </form>
            @endcan
        </div>
    </section>

    @can('reset-staff-password', $user)
        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>Reinitialisation du mot de passe</h2>
                <span class="badge">Admin</span>
            </div>

            <form method="POST" action="{{ route('staff.reset-password', $user) }}">
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="field">
                        <label for="password">Nouveau mot de passe</label>
                        <input id="password" name="password" type="password" placeholder="Laisse vide pour generer automatiquement">
                    </div>
                    <div class="field">
                        <label for="password_confirmation">Confirmation</label>
                        <input id="password_confirmation" name="password_confirmation" type="password">
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Reinitialiser le mot de passe</button>
                </div>
            </form>
        </section>
    @endcan
@endsection
