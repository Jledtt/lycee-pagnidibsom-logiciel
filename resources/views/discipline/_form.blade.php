@php
    $typeLabels = [
        'observation' => 'Observation',
        'warning' => 'Avertissement',
        'sanction' => 'Sanction',
    ];
@endphp

<div class="form-grid">
    @if ($record->exists)
        <div class="field wide">
            <span class="field-label">Élève et classe</span>
            <strong>{{ $record->student?->full_name }} · {{ $record->student?->matricule }} · {{ $record->schoolClass?->name ?? '-' }}</strong>
        </div>
    @else
        <div class="field wide">
            <label for="student_id">Élève</label>
            <select id="student_id" name="student_id" required>
                <option value="">Choisir un élève inscrit</option>
                @foreach ($students as $student)
                    @php($enrollment = $student->enrollments->first())
                    <option value="{{ $student->id }}" @selected((int) old('student_id', $selectedStudentId ?? 0) === $student->id)>
                        {{ $student->last_name }} {{ $student->first_name }} · {{ $student->matricule }} · {{ $enrollment?->schoolClass?->name ?? 'Sans classe' }}
                    </option>
                @endforeach
            </select>
            @error('student_id')<span class="error">{{ $message }}</span>@enderror
        </div>
    @endif

    <div class="field">
        <label for="type">Nature de l’incident</label>
        <select id="type" name="type" required>
            @foreach ($typeLabels as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $record->type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type')<span class="error">{{ $message }}</span>@enderror
    </div>

    <div class="field">
        <label for="record_date">Date de l’incident</label>
        <input id="record_date" name="record_date" type="date" value="{{ old('record_date', $record->record_date?->format('Y-m-d')) }}" required>
        @error('record_date')<span class="error">{{ $message }}</span>@enderror
    </div>

    <div class="field wide">
        <label for="title">Objet</label>
        <input id="title" name="title" value="{{ old('title', $record->title) }}" maxlength="190" required placeholder="Ex. : comportement inapproprié en classe">
        @error('title')<span class="error">{{ $message }}</span>@enderror
    </div>

    <div class="field wide">
        <label for="description">Description des faits</label>
        <textarea id="description" name="description" rows="6" maxlength="5000" placeholder="Décrire les faits de manière précise et factuelle.">{{ old('description', $record->description) }}</textarea>
        @error('description')<span class="error">{{ $message }}</span>@enderror
    </div>

    <div class="field wide">
        <label for="action_taken">Mesure immédiate ou observation</label>
        <textarea id="action_taken" name="action_taken" rows="4" maxlength="5000" placeholder="Facultatif tant que l’incident n’est pas résolu.">{{ old('action_taken', $record->action_taken) }}</textarea>
        @error('action_taken')<span class="error">{{ $message }}</span>@enderror
    </div>
</div>
