<section class="panel">
    <div class="form-grid">
        <div class="field">
            <label for="name">Nom</label>
            <input id="name" name="name" value="{{ old('name', $academicTrack->name) }}" placeholder="Ex. Série A ou Génie électrique" required>
            @error('name') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="code">Code court</label>
            <input id="code" name="code" value="{{ old('code', $academicTrack->code) }}" placeholder="Ex. A ou ELEC" required>
            <small>Lettres majuscules, chiffres, tiret et tiret bas uniquement.</small>
            @error('code') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="kind">Type</label>
            <select id="kind" name="kind" required>
                <option value="serie" @selected(old('kind', $academicTrack->kind) === 'serie')>Série générale</option>
                <option value="filiere" @selected(old('kind', $academicTrack->kind) === 'filiere')>Filière technique ou professionnelle</option>
            </select>
            @error('kind') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="status">Statut</label>
            <select id="status" name="status" required>
                <option value="active" @selected(old('status', $academicTrack->status) === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $academicTrack->status) === 'inactive')>Désactivée</option>
            </select>
            @error('status') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field wide">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" placeholder="Précisions facultatives sur la série ou la filière">{{ old('description', $academicTrack->description) }}</textarea>
            @error('description') <small class="error">{{ $message }}</small> @enderror
        </div>
    </div>

    <div class="form-actions">
        <a class="btn btn-subtle" href="{{ route('academic-tracks.index') }}">Annuler</a>
        <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
    </div>
</section>
