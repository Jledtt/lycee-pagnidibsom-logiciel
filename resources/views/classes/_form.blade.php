<section class="panel">
    <div class="form-grid">
        <div class="field">
            <label for="name">Nom de la classe</label>
            <input id="name" name="name" value="{{ old('name', $schoolClass->name) }}" placeholder="Ex: 6e A" required>
            @error('name') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="code">Code</label>
            <input id="code" name="code" value="{{ old('code', $schoolClass->code) }}" placeholder="Ex: 6A">
            @error('code') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="level_id">Niveau</label>
            <select id="level_id" name="level_id" required>
                <option value="">Choisir un niveau</option>
                @foreach ($levels as $level)
                    <option value="{{ $level->id }}" @selected((string) old('level_id', $schoolClass->level_id) === (string) $level->id)>
                        {{ $level->name }}{{ $level->cycle ? ' - ' . $level->cycle : '' }}
                    </option>
                @endforeach
            </select>
            @error('level_id') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="academic_track_id">Série ou filière</label>
            <select id="academic_track_id" name="academic_track_id">
                <option value="">Aucune</option>
                @foreach ($academicTracks as $academicTrack)
                    <option value="{{ $academicTrack->id }}" @selected((string) old('academic_track_id', $schoolClass->academic_track_id) === (string) $academicTrack->id)>
                        {{ $academicTrack->name }} ({{ $academicTrack->code }}){{ $academicTrack->status === 'inactive' ? ' - désactivée' : '' }}
                    </option>
                @endforeach
            </select>
            <small>Facultatif pour les classes qui ne dépendent d’aucune série ou filière.</small>
            @error('academic_track_id') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="capacity">Capacité</label>
            <input id="capacity" name="capacity" type="number" min="1" max="500" value="{{ old('capacity', $schoolClass->capacity) }}" placeholder="Ex: 60">
            @error('capacity') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="status">Statut</label>
            <select id="status" name="status" required>
                <option value="active" @selected(old('status', $schoolClass->status) === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $schoolClass->status) === 'inactive')>Inactive</option>
                <option value="archived" @selected(old('status', $schoolClass->status) === 'archived')>Archivee</option>
            </select>
            @error('status') <small class="error">{{ $message }}</small> @enderror
        </div>
    </div>

    <div class="form-actions">
        <a class="btn btn-subtle" href="{{ route('classes.index') }}">Annuler</a>
        <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
    </div>
</section>
