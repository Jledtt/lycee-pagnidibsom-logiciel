@extends('layouts.app', [
    'title' => 'Mon profil - Lycée Privé Pagnidibsom',
    'active' => 'profile',
    'pageTitle' => 'Mon profil',
    'pageSubtitle' => 'Informations de connexion et securite du compte',
])

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <section class="grid two-col">
        <form class="panel" method="POST" action="{{ route('profile.update') }}">
            @csrf
            @method('PUT')

            <div class="panel-head">
                <h2>Informations personnelles</h2>
                <button class="btn btn-primary" type="submit">Enregistrer</button>
            </div>

            @php($role = $user->roles->first()?->name)
            <div class="form-grid">
                <div class="field">
                    <label for="name">Nom complet</label>
                    <input id="name" name="name" value="{{ old('name', $user->name) }}" required>
                </div>
                <div class="field">
                    <label for="email">E-mail</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
                </div>
                <div class="field">
                    <label for="phone">Téléphone</label>
                    <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
                </div>
                <div class="detail-item">
                    <span>Identifiant</span>
                    <strong>{{ $user->username }}</strong>
                </div>
                <div class="detail-item">
                    <span>Role</span>
                    <strong>{{ $role ?: '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Dernière connexion</span>
                    <strong>{{ $user->last_login_at?->format('d/m/Y H:i') ?? '-' }}</strong>
                </div>
            </div>
        </form>

        <form class="panel" method="POST" action="{{ route('profile.password.update') }}">
            @csrf
            @method('PUT')

            <div class="panel-head">
                <h2>Mot de passe</h2>
                <button class="btn btn-primary" type="submit">Changer</button>
            </div>

            <div class="form-grid">
                <div class="field wide">
                    <label for="current_password">Mot de passe actuel</label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
                </div>
                <div class="field">
                    <label for="password">Nouveau mot de passe</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password">
                </div>
                <div class="field">
                    <label for="password_confirmation">Confirmation</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                </div>
            </div>
        </form>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Mes dernières connexions</h2>
            <span class="badge">{{ $user->loginHistories->count() }} ligne(s)</span>
        </div>

        @if ($user->loginHistories->isEmpty())
            <div class="empty">Aucune connexion enregistrée.</div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Adresse IP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($user->loginHistories as $history)
                        <tr>
                            <td>{{ $history->created_at?->format('d/m/Y H:i:s') }}</td>
                            <td><span class="badge">{{ $history->status }}</span></td>
                            <td>{{ $history->ip_address ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
