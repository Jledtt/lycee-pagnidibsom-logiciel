@if ($errors->any())
    <div class="error">
        {{ $errors->first() }}
    </div>
@endif

<section class="panel">
    <div class="panel-head">
        <h2>Informations du compte</h2>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="name">Nom complet</label>
            <input id="name" name="name" value="{{ old('name', $user->name) }}" required>
        </div>

        <div class="field">
            <label for="username">Identifiant de connexion</label>
            <input id="username" name="username" value="{{ old('username', $user->username) }}" required>
        </div>

        <div class="field">
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
        </div>

        <div class="field">
            <label for="phone">Telephone</label>
            <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
        </div>

        <div class="field">
            <label for="role">Role</label>
            <select id="role" name="role" required>
                @foreach ($roleLabels as $role => $label)
                    <option value="{{ $role }}" @selected(old('role', $selectedRole) === $role)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="status">Statut</label>
            <select id="status" name="status" required>
                <option value="active" @selected(old('status', $user->status ?? 'active') === 'active')>Actif</option>
                <option value="inactive" @selected(old('status', $user->status) === 'inactive')>Inactif</option>
                <option value="suspended" @selected(old('status', $user->status) === 'suspended')>Suspendu</option>
            </select>
        </div>
    </div>
</section>

<section class="panel" style="margin-top:16px">
    <div class="panel-head">
        <h2>Mot de passe</h2>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="password">Mot de passe</label>
            <input id="password" name="password" type="password" @if (! $user->exists) required @endif>
        </div>

        <div class="field">
            <label for="password_confirmation">Confirmation</label>
            <input id="password_confirmation" name="password_confirmation" type="password" @if (! $user->exists) required @endif>
        </div>

        @if ($user->exists)
            <div class="field wide">
                <span class="badge badge-warning">Laisse vide pour conserver le mot de passe actuel.</span>
            </div>
        @endif
    </div>
</section>

<div class="form-actions">
    <a class="btn btn-subtle" href="{{ route('staff.index') }}">Annuler</a>
    <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
</div>
