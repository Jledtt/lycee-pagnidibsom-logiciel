@extends('layouts.app', [
    'title' => $schoolClass->name . ' - Lycee Prive Pagnidibsom',
    'active' => 'classes',
    'pageTitle' => $schoolClass->name,
    'pageSubtitle' => ($schoolClass->level?->name ?? 'Niveau non defini') . ' - ' . ($academicYear?->name ?? 'Annee active'),
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('classes.index') }}">Retour</a>
    <a class="btn btn-primary" href="{{ route('classes.edit', $schoolClass) }}">Modifier</a>
@endsection

@section('content')
    <section class="grid two-col">
        <div class="panel">
            <div class="panel-head">
                <h2>Eleves rattaches</h2>
                <span class="badge">{{ $schoolClass->enrollments->count() }} eleve(s)</span>
            </div>

            @if ($schoolClass->enrollments->isEmpty())
                <div class="empty">Aucun eleve rattache a cette classe pour le moment.</div>
            @else
                <table class="table">
                    <thead>
                        <tr>
                            <th>Matricule</th>
                            <th>Eleve</th>
                            <th>Inscription</th>
                            <th>Contact</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($schoolClass->enrollments as $enrollment)
                            @php($guardian = $enrollment->student->guardians->first())
                            <tr>
                                <td>{{ $enrollment->student->matricule }}</td>
                                <td>
                                    <a href="{{ route('students.show', $enrollment->student) }}">
                                        <strong>{{ $enrollment->student->full_name }}</strong>
                                    </a>
                                </td>
                                <td>{{ $enrollment->enrollment_date?->format('d/m/Y') ?? '-' }}</td>
                                <td>{{ $guardian?->phone_primary ?? '-' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('classes.students.detach', [$schoolClass, $enrollment]) }}" onsubmit="return confirm('Retirer cet eleve de la classe ?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger" type="submit">Retirer</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Rattacher un eleve</h2>
            </div>

            @if ($availableStudents->isEmpty())
                <div class="empty">Tous les eleves actifs sont deja rattaches a une classe pour cette annee.</div>
            @else
                <form method="POST" action="{{ route('classes.students.attach', $schoolClass) }}">
                    @csrf

                    <div class="field">
                        <label for="student_id">Eleve</label>
                        <select id="student_id" name="student_id" required>
                            <option value="">Choisir un eleve</option>
                            @foreach ($availableStudents as $student)
                                <option value="{{ $student->id }}" @selected(old('student_id') == $student->id)>
                                    {{ $student->matricule }} - {{ $student->full_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('student_id') <small class="error">{{ $message }}</small> @enderror
                    </div>

                    <div class="field">
                        <label for="enrollment_date">Date d'inscription</label>
                        <input id="enrollment_date" name="enrollment_date" type="date" value="{{ old('enrollment_date', now()->toDateString()) }}">
                        @error('enrollment_date') <small class="error">{{ $message }}</small> @enderror
                    </div>

                    <div class="field">
                        <label for="type">Type</label>
                        <select id="type" name="type" required>
                            <option value="new" @selected(old('type') === 'new')>Nouvelle inscription</option>
                            <option value="renewal" @selected(old('type') === 'renewal')>Reinscription</option>
                            <option value="transfer" @selected(old('type') === 'transfer')>Transfert</option>
                        </select>
                        @error('type') <small class="error">{{ $message }}</small> @enderror
                    </div>

                    <div class="field">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" placeholder="Optionnel">{{ old('notes') }}</textarea>
                        @error('notes') <small class="error">{{ $message }}</small> @enderror
                    </div>

                    <button class="btn btn-primary" type="submit">Rattacher a la classe</button>
                </form>
            @endif
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Informations</h2>
        </div>

        <div class="detail-grid">
            <div class="detail-item">
                <span>Niveau</span>
                <strong>{{ $schoolClass->level?->name ?? '-' }}</strong>
            </div>
            <div class="detail-item">
                <span>Code</span>
                <strong>{{ $schoolClass->code ?? '-' }}</strong>
            </div>
            <div class="detail-item">
                <span>Capacite</span>
                <strong>{{ $schoolClass->capacity ?? '-' }}</strong>
            </div>
            <div class="detail-item">
                <span>Statut</span>
                <strong>{{ $schoolClass->status }}</strong>
            </div>
        </div>

        <form method="POST" action="{{ route('classes.destroy', $schoolClass) }}" style="margin-top:16px" onsubmit="return confirm('Archiver cette classe ?')">
            @csrf
            @method('DELETE')
            <button class="btn btn-danger" type="submit">Archiver la classe</button>
        </form>
    </section>
@endsection
