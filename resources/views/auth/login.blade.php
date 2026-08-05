@extends('layouts.school', ['title' => 'Connexion - Lycée Privé Pagnidibsom'])

@section('body')
    <main class="auth-shell">
        <section class="auth-brand">
            <div>
                <div class="brand-mark">LPP</div>
                <h1>Lycée Privé Pagnidibsom</h1>
                <p>Gestion scolaire, administrative et financière de l’établissement.</p>
            </div>
            <p>{{ now()->year }} - Plateforme interne</p>
        </section>

        <section class="auth-card-wrap">
            <form class="auth-card" method="POST" action="{{ route('login.store') }}">
                @csrf

                <p class="eyebrow">Accès sécurisé</p>
                <h2>Connexion</h2>

                @if ($errors->any())
                    <p class="error">{{ $errors->first() }}</p>
                @endif

                <div class="field">
                    <label for="username">Identifiant</label>
                    <input id="username" name="username" value="{{ old('username', 'admin') }}" autocomplete="username" required autofocus>
                </div>

                <div class="field">
                    <label for="password">Mot de passe</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                </div>

                <div class="form-row">
                    <label class="check">
                        <input type="checkbox" name="remember" value="1">
                        <span>Garder la session</span>
                    </label>
                </div>

                <button class="btn btn-primary" type="submit">Se connecter</button>
            </form>
        </section>
    </main>
@endsection
