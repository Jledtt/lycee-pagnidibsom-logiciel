<section class="panel">
    <div class="panel-head">
        <h2>Identité et coordonnées</h2>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="last_name">Nom</label>
            <input id="last_name" name="last_name" value="{{ old('last_name', $guardian->last_name) }}" required>
            @error('last_name') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="first_name">Prénom(s)</label>
            <input id="first_name" name="first_name" value="{{ old('first_name', $guardian->first_name) }}">
            @error('first_name') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="phone_primary">Téléphone principal</label>
            <input id="phone_primary" name="phone_primary" type="tel" value="{{ old('phone_primary', $guardian->phone_primary) }}" placeholder="Ex : 70 00 00 00" required>
            @error('phone_primary') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="phone_secondary">Téléphone secondaire</label>
            <input id="phone_secondary" name="phone_secondary" type="tel" value="{{ old('phone_secondary', $guardian->phone_secondary) }}">
            @error('phone_secondary') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="email">Adresse e-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email', $guardian->email) }}">
            @error('email') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="profession">Profession</label>
            <input id="profession" name="profession" value="{{ old('profession', $guardian->profession) }}">
            @error('profession') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="service">Service ou employeur</label>
            <input id="service" name="service" value="{{ old('service', $guardian->service) }}">
            @error('service') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="status">Statut de la fiche</label>
            <select id="status" name="status" required>
                <option value="active" @selected(old('status', $guardian->status) === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $guardian->status) === 'inactive')>Inactive</option>
            </select>
            @error('status') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field wide">
            <label for="address">Adresse</label>
            <textarea id="address" name="address" rows="3">{{ old('address', $guardian->address) }}</textarea>
            @error('address') <small class="error">{{ $message }}</small> @enderror
        </div>
    </div>
</section>
