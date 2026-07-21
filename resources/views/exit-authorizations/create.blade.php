@extends('layouts.app', [
    'title' => 'Nouvelle autorisation - Lycee Prive Pagnidibsom',
    'active' => 'exit-authorizations',
    'pageTitle' => 'Nouvelle autorisation',
    'pageSubtitle' => 'Autorisation d entree et de sortie selon le modele fourni par le client',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('exit-authorizations.index') }}">Retour</a>
@endsection

@section('content')
    <section class="grid two-col">
        <form method="POST" action="{{ route('exit-authorizations.store') }}" class="panel">
            @csrf

            <div class="panel-head">
                <h2>Document</h2>
                <span class="badge">{{ $academicYear?->name }}</span>
            </div>

            @if ($students->isEmpty())
                <div class="empty">Aucun eleve inscrit disponible.</div>
            @else
                <div class="field">
                    <label for="student_id">Eleve</label>
                    <select id="student_id" name="student_id" required>
                        <option value="">Choisir un eleve</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}" @selected((string) old('student_id', $selectedStudentId) === (string) $student->id)>
                                {{ $student->matricule }} - {{ $student->full_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('student_id') <small class="error">{{ $message }}</small> @enderror
                </div>

                <div class="field">
                    <label for="document_date">Date du document</label>
                    <input id="document_date" name="document_date" type="date" value="{{ old('document_date', now()->toDateString()) }}" required>
                    @error('document_date') <small class="error">{{ $message }}</small> @enderror
                </div>

                <div class="grid two-col">
                    <div class="field">
                        <label for="departure_at">Date et heure de sortie</label>
                        <input id="departure_at" name="departure_at" type="datetime-local" value="{{ old('departure_at') }}">
                        @error('departure_at') <small class="error">{{ $message }}</small> @enderror
                    </div>
                    <div class="field">
                        <label for="return_at">Date et heure de retour</label>
                        <input id="return_at" name="return_at" type="datetime-local" value="{{ old('return_at') }}">
                        @error('return_at') <small class="error">{{ $message }}</small> @enderror
                    </div>
                </div>

                <div class="field">
                    <label for="subject_name">Matiere concernee</label>
                    <input id="subject_name" name="subject_name" value="{{ old('subject_name') }}" placeholder="Ex: Mathematiques, cours de la journee">
                    @error('subject_name') <small class="error">{{ $message }}</small> @enderror
                </div>

                <div class="field">
                    <label for="destination">Indication du lieu</label>
                    <input id="destination" name="destination" value="{{ old('destination') }}" placeholder="Ex: Centre de sante, domicile, administration">
                    @error('destination') <small class="error">{{ $message }}</small> @enderror
                </div>

                <div class="field">
                    <label for="reason">Motif de l absence / sortie</label>
                    <input id="reason" name="reason" value="{{ old('reason') }}" placeholder="Ex: Maladie" required>
                    @error('reason') <small class="error">{{ $message }}</small> @enderror
                </div>

                <div class="field">
                    <label for="notes">Observation</label>
                    <textarea id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                    @error('notes') <small class="error">{{ $message }}</small> @enderror
                </div>

                <div class="form-actions">
                    <a class="btn btn-subtle" href="{{ route('exit-authorizations.index') }}">Annuler</a>
                    <button class="btn btn-primary" type="submit">Generer</button>
                </div>
            @endif
        </form>

        <div class="panel">
            <div class="panel-head">
                <h2>Utilisation</h2>
            </div>
            <div class="detail-item">
                <span>Cas typique</span>
                <strong>Eleve malade, convocation parent, sortie administrative, retard justifie.</strong>
            </div>
            <div class="detail-item">
                <span>Trace</span>
                <strong>Chaque document reste dans l historique avec le nom de l eleve, la date, le motif et l auteur.</strong>
            </div>
            <div class="detail-item">
                <span>Impression</span>
                <strong>Le PDF reprend la structure du document Word AUTORISATION.</strong>
            </div>
        </div>
    </section>
@endsection
