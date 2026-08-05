<section class="panel">
    <div class="form-grid">
        <div class="field">
            <label for="student_id">Élève</label>
            <select id="student_id" name="student_id" required @disabled($enrollment->exists)>
                <option value="">Choisir un élève</option>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}" @selected((string) old('student_id', $enrollment->student_id) === (string) $student->id)>
                        {{ $student->matricule }} - {{ $student->full_name }}
                    </option>
                @endforeach
            </select>
            @if ($enrollment->exists)
                <input type="hidden" name="student_id" value="{{ $enrollment->student_id }}">
            @endif
            @error('student_id') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="school_class_id">Classe</label>
            <select id="school_class_id" name="school_class_id" required>
                <option value="">Choisir une classe</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected((string) old('school_class_id', $enrollment->school_class_id) === (string) $class->id)>
                        {{ $class->name }}{{ $class->level ? ' - ' . $class->level->name : '' }}
                    </option>
                @endforeach
            </select>
            @error('school_class_id') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="enrollment_date">Date d’inscription</label>
            <input id="enrollment_date" name="enrollment_date" type="date" value="{{ old('enrollment_date', optional($enrollment->enrollment_date)->format('Y-m-d') ?? now()->toDateString()) }}">
            @error('enrollment_date') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="type">Type</label>
            <select id="type" name="type" required>
                <option value="new" @selected(old('type', $enrollment->type) === 'new')>Nouvelle inscription</option>
                <option value="renewal" @selected(old('type', $enrollment->type) === 'renewal')>Réinscription</option>
                <option value="transfer" @selected(old('type', $enrollment->type) === 'transfer')>Transfert</option>
            </select>
            @error('type') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="status">Statut</label>
            <select id="status" name="status" required>
                <option value="active" @selected(old('status', $enrollment->status) === 'active')>Active</option>
                <option value="pending" @selected(old('status', $enrollment->status) === 'pending')>En attente</option>
                <option value="completed" @selected(old('status', $enrollment->status) === 'completed')>Terminee</option>
                <option value="cancelled" @selected(old('status', $enrollment->status) === 'cancelled')>Annulée</option>
            </select>
            @error('status') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="previous_school">École precedente</label>
            <input id="previous_school" name="previous_school" value="{{ old('previous_school', $enrollment->previous_school) }}" placeholder="Optionnel">
            @error('previous_school') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field wide">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" placeholder="Observation interne">{{ old('notes', $enrollment->notes) }}</textarea>
            @error('notes') <small class="error">{{ $message }}</small> @enderror
        </div>
    </div>

    <div class="form-actions">
        <a class="btn btn-subtle" href="{{ route('enrollments.index') }}">Annuler</a>
        <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
    </div>
</section>
