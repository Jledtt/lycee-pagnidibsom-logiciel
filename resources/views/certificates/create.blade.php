@extends('layouts.app', [
    'title' => 'Générer un certificat - Lycée Privé Pagnidibsom',
    'active' => 'certificates',
    'pageTitle' => 'Générer un certificat',
    'pageSubtitle' => 'Certificat de scolarité, certificat d inscription ou non redevance',
])

@section('content')
    <section class="grid two-col">
        <form method="POST" action="{{ route('certificates.store') }}" class="panel">
            @csrf

            <div class="panel-head">
                <h2>Nouveau certificat</h2>
            </div>

            @if ($students->isEmpty())
                <div class="empty">Aucun élève inscrit disponible. Inscris d’abord un élève dans une classe.</div>
            @else
                <div class="field">
                    <label for="student_id">Élève</label>
                    <select id="student_id" name="student_id" required>
                        <option value="">Choisir un élève</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}" @selected((string) old('student_id', $selectedStudentId) === (string) $student->id)>
                                {{ $student->matricule }} - {{ $student->full_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('student_id') <small class="error">{{ $message }}</small> @enderror
                </div>

                <div class="field">
                    <label for="document_type">Type de document</label>
                    <select id="document_type" name="document_type" required>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('document_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('document_type') <small class="error">{{ $message }}</small> @enderror
                </div>

                <div class="field">
                    <label for="received_at">Date du document</label>
                    <input id="received_at" name="received_at" type="date" value="{{ old('received_at', now()->toDateString()) }}">
                    @error('received_at') <small class="error">{{ $message }}</small> @enderror
                </div>

                <div class="form-actions">
                    <a class="btn btn-subtle" href="{{ route('certificates.index') }}">Annuler</a>
                    <button class="btn btn-primary" type="submit">Générer</button>
                </div>
            @endif
        </form>

        <div class="panel">
            <div class="panel-head">
                <h2>Autres documents a ajouter</h2>
            </div>

            <div class="grid" style="grid-template-columns:1fr">
                <div class="detail-item">
                    <span>Administratif</span>
                    <strong>Attestation de frequentation, certificat de radiation, certificat de transfert, carte scolaire.</strong>
                </div>
                <div class="detail-item">
                    <span>Pedagogique</span>
                    <strong>Bulletins, relevés de notes, listes de classe, convocations aux examens.</strong>
                </div>
                <div class="detail-item">
                    <span>Finances</span>
                    <strong>Quitus, situation de scolarité, etat des impayés, reçus par période.</strong>
                </div>
                <div class="detail-item">
                    <span>Vie scolaire</span>
                    <strong>Absences, sanctions, autorisations de sortie, convocations parents.</strong>
                </div>
            </div>
        </div>
    </section>
@endsection
