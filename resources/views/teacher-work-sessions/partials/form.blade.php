<form
    id="{{ $formId }}"
    method="POST"
    action="{{ route('teacher-work-sessions.store') }}"
    autocomplete="off"
    data-prevent-double-submit
>
    @csrf

    <div class="form-grid">
        <div class="field">
            <label for="{{ $formId }}-teacher">Professeur</label>
            <select id="{{ $formId }}-teacher" name="teacher_id" required>
                <option value="">Choisir</option>
                @foreach ($teachers as $teacher)
                    <option value="{{ $teacher->id }}" @selected((int) old('teacher_id', $filters['teacher_id']) === $teacher->id)>{{ $teacher->name }}</option>
                @endforeach
            </select>
            @error('teacher_id') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="{{ $formId }}-date">Date du cours</label>
            <input
                id="{{ $formId }}-date"
                type="date"
                name="session_date"
                value="{{ old('session_date', $defaultSessionDate) }}"
                @if($academicYear?->starts_at) min="{{ $academicYear->starts_at->toDateString() }}" @endif
                @if($academicYear?->ends_at) max="{{ $academicYear->ends_at->toDateString() }}" @endif
                required
            >
            @error('session_date') <small class="error">{{ $message }}</small> @enderror
            @if($academicYear?->starts_at && $academicYear?->ends_at)
                <small class="field-hint">Dates autorisées : du {{ $academicYear->starts_at->format('d/m/Y') }} au {{ $academicYear->ends_at->format('d/m/Y') }}.</small>
            @endif
        </div>

        <div class="field">
            <label for="{{ $formId }}-class">Classe</label>
            <select id="{{ $formId }}-class" name="school_class_id" required>
                <option value="">Choisir</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected((int) old('school_class_id') === $class->id)>{{ $class->name }}</option>
                @endforeach
            </select>
            @error('school_class_id') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="{{ $formId }}-subject">Matière</label>
            <select id="{{ $formId }}-subject" name="subject_id" required>
                <option value="">Choisir</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected((int) old('subject_id') === $subject->id)>{{ $subject->name }}</option>
                @endforeach
            </select>
            @error('subject_id') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="{{ $formId }}-starts">Début du cours</label>
            <input id="{{ $formId }}-starts" type="time" name="starts_at" value="{{ old('starts_at') }}" required>
            @error('starts_at') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="{{ $formId }}-ends">Fin du cours</label>
            <input id="{{ $formId }}-ends" type="time" name="ends_at" value="{{ old('ends_at') }}" required>
            @error('ends_at') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="{{ $formId }}-hours">Nombre d’heures effectuées</label>
            <input id="{{ $formId }}-hours" type="number" min="0.25" max="250" step="0.25" name="hours_worked" value="{{ old('hours_worked', 1) }}" inputmode="decimal" required>
            @error('hours_worked') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="{{ $formId }}-rate">Taux horaire exceptionnel</label>
            <input id="{{ $formId }}-rate" type="number" min="0" step="1" name="hourly_rate" value="{{ old('hourly_rate') }}" inputmode="numeric" placeholder="Laisser vide pour le taux du dossier…">
            @error('hourly_rate') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="{{ $formId }}-status">Statut</label>
            <select id="{{ $formId }}-status" name="status" required>
                <option value="draft" @selected(old('status', 'draft') === 'draft')>À vérifier</option>
                <option value="validated" @selected(old('status') === 'validated')>Validé</option>
            </select>
            @error('status') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <span class="field-label">Signature papier contrôlée</span>
            <label class="check" for="{{ $formId }}-signed">
                <input id="{{ $formId }}-signed" type="checkbox" name="teacher_signed" value="1" @checked(old('teacher_signed'))>
                Oui, la fiche est signée
            </label>
        </div>

        <div class="field wide">
            <label for="{{ $formId }}-notes">Observation</label>
            <textarea id="{{ $formId }}-notes" name="notes" maxlength="1000" placeholder="Observation facultative…">{{ old('notes') }}</textarea>
            @error('notes') <small class="error">{{ $message }}</small> @enderror
        </div>
    </div>
</form>
