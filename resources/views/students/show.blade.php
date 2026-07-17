@extends('layouts.app', [
    'title' => $student->full_name . ' - Lycee Prive Pagnidibsom',
    'active' => 'students',
    'pageTitle' => $student->full_name,
    'pageSubtitle' => 'Matricule ' . $student->matricule,
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('students.index') }}">Retour</a>
    @can('students.export')
        <a class="btn btn-subtle" href="{{ route('certificates.create', ['student_id' => $student->id]) }}">Certificat</a>
        <a class="btn btn-subtle" href="{{ route('students.registration-sheet', $student) }}">Fiche d'inscription</a>
        <a class="btn btn-subtle" href="{{ route('students.registration-sheet.pdf', $student) }}">PDF</a>
    @endcan
    @can('students.update')
        <a class="btn btn-primary" href="{{ route('students.edit', $student) }}">Modifier</a>
    @endcan
@endsection

@section('content')
    <section class="panel">
        <div class="panel-head">
            <h2>Fiche eleve</h2>
            <span class="badge">{{ $student->status }}</span>
        </div>

        <div class="detail-grid">
            <div class="detail-item">
                <span>Matricule</span>
                <strong>{{ $student->matricule }}</strong>
            </div>
            <div class="detail-item">
                <span>Sexe</span>
                <strong>{{ $student->gender === 'female' ? 'Fille' : 'Garcon' }}</strong>
            </div>
            <div class="detail-item">
                <span>Classe demandee</span>
                <strong>{{ $student->desired_class ?: ($currentEnrollment?->schoolClass?->name ?? 'Non inscrit') }}</strong>
            </div>
            <div class="detail-item">
                <span>Date de naissance</span>
                <strong>{{ $student->birth_date?->format('d/m/Y') ?? '-' }}</strong>
            </div>
            <div class="detail-item">
                <span>Lieu de naissance</span>
                <strong>{{ $student->birth_place ?? '-' }}</strong>
            </div>
            <div class="detail-item">
                <span>Adresse</span>
                <strong>{{ $student->address ?? '-' }}</strong>
            </div>
            <div class="detail-item">
                <span>Ecole d'origine</span>
                <strong>{{ $student->origin_school ?? '-' }}</strong>
            </div>
            <div class="detail-item">
                <span>Classe frequentee</span>
                <strong>{{ $student->previous_class ?? '-' }}</strong>
            </div>
            <div class="detail-item">
                <span>Classe deja redoublee</span>
                <strong>{{ $student->repeated_class ?? '-' }}</strong>
            </div>
            <div class="detail-item">
                <span>Nationalite</span>
                <strong>{{ $student->nationality ?? '-' }}</strong>
            </div>
            <div class="detail-item">
                <span>Ethnie</span>
                <strong>{{ $student->ethnicity ?? '-' }}</strong>
            </div>
            <div class="detail-item">
                <span>Religion</span>
                <strong>{{ $student->religion ?? '-' }}</strong>
            </div>
            <div class="detail-item">
                <span>Secteur</span>
                <strong>{{ $student->sector ?? '-' }}</strong>
            </div>
            <div class="detail-item">
                <span>Quartier</span>
                <strong>{{ $student->district ?? '-' }}</strong>
            </div>
            <div class="detail-item">
                <span>Tel domicile</span>
                <strong>{{ $student->home_phone ?? '-' }}</strong>
            </div>
        </div>
    </section>

    <section class="grid two-col">
        <div class="panel">
            <div class="panel-head">
                <h2>Parents et tuteurs</h2>
            </div>

            @if ($student->guardians->isEmpty())
                <div class="empty">Aucun tuteur rattache a ce dossier.</div>
            @else
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Telephone</th>
                            <th>Lien</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($student->guardians as $guardian)
                            <tr>
                                <td>{{ $guardian->full_name }}</td>
                                <td>{{ $guardian->phone_primary }}</td>
                                <td>{{ $guardian->pivot->relationship }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Situation rapide</h2>
            </div>

            <div class="detail-grid" style="grid-template-columns:1fr">
                <div class="detail-item">
                    <span>Paiements valides</span>
                    <strong>{{ number_format($student->payments->where('status', 'valid')->sum('amount'), 0, ',', ' ') }} FCFA</strong>
                </div>
                <div class="detail-item">
                    <span>Absences / retards</span>
                    <strong>{{ $student->attendanceRecords->whereIn('status', ['absent', 'late'])->count() }}</strong>
                </div>
                <div class="detail-item">
                    <span>Notes medicales</span>
                    <strong>{{ $student->health_notes ?: '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Pathologies connues</span>
                    <strong>{{ $student->health_conditions ? implode(', ', $student->health_conditions) : '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Aptitude au sport</span>
                    <strong>{{ is_null($student->sport_aptitude) ? '-' : ($student->sport_aptitude ? 'Oui' : 'Non') }}</strong>
                </div>
                <div class="detail-item">
                    <span>Urgence</span>
                    <strong>{{ $student->emergency_contact_name ?: '-' }} {{ $student->emergency_contact_phone ? '- ' . $student->emergency_contact_phone : '' }}</strong>
                </div>
                <div class="detail-item">
                    <span>WhatsApp infos ecole</span>
                    <strong>{{ $student->school_info_whatsapp ?: '-' }}</strong>
                </div>
            </div>
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Actions</h2>
        </div>

        <form method="POST" action="{{ route('students.destroy', $student) }}" onsubmit="return confirm('Archiver ce dossier eleve ?')">
            @csrf
            @method('DELETE')
            <button class="btn btn-danger" type="submit">Archiver le dossier</button>
        </form>
    </section>
@endsection
