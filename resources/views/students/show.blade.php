@extends('layouts.app', [
    'title' => $student->full_name . ' - Lycée Privé Pagnidibsom',
    'active' => 'students',
    'pageTitle' => $student->full_name,
    'pageSubtitle' => 'Matricule ' . $student->matricule,
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('students.index') }}">Retour</a>
    @can('students.export')
        <a class="btn btn-subtle" href="{{ route('certificates.create', ['student_id' => $student->id]) }}">Certificat</a>
        <a class="btn btn-subtle" href="{{ route('students.school-card.pdf', $student) }}">Carte scolaire</a>
        <a class="btn btn-subtle" href="{{ route('students.registration-sheet', $student) }}">Fiche d’inscription</a>
        <a class="btn btn-subtle" href="{{ route('students.registration-sheet.pdf', $student) }}">PDF</a>
    @endcan
    @can('payments.view')
        <a class="btn btn-subtle" href="{{ route('payments.students.statement', $student) }}">Situation financiere</a>
    @endcan
    @can('payments.create')
        <a class="btn btn-subtle" href="{{ route('payments.create', ['student_id' => $student->id]) }}">Encaisser</a>
    @endcan
    @can('attendance.view')
        <a class="btn btn-subtle" href="{{ route('attendance.students.history', $student) }}">Assiduite</a>
    @endcan
    @can('students.update')
        <a class="btn btn-primary" href="{{ route('students.edit', $student) }}">Modifier</a>
    @endcan
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="panel">
        <div class="panel-head">
            <h2>Fiche élève</h2>
            <span class="badge">{{ $student->status }}</span>
        </div>

        <div class="detail-grid">
            <div class="detail-item">
                <span>Matricule</span>
                <strong>{{ $student->matricule }}</strong>
            </div>
            <div class="detail-item">
                <span>Sexe</span>
                <strong>{{ $student->gender_label }}</strong>
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
                <span>École d’origine</span>
                <strong>{{ $student->origin_school ?? '-' }}</strong>
            </div>
            <div class="detail-item">
                <span>Classe frequentee</span>
                <strong>{{ $student->previous_class ?? '-' }}</strong>
            </div>
            <div class="detail-item">
                <span>Classe déjà redoublee</span>
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

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <div>
                <h2>Pi?ces obligatoires</h2>
                <p style="margin:4px 0 0;color:var(--muted)">
                    {{ count($missingRequiredDocuments) === 0 ? 'Dossier administratif complet.' : count($missingRequiredDocuments) . ' pièce(s) encore manquante(s).' }}
                </p>
            </div>
            <span class="badge {{ count($missingRequiredDocuments) === 0 ? '' : 'badge-warning' }}">
                {{ count($missingRequiredDocuments) === 0 ? 'Complet' : 'Incomplet' }}
            </span>
        </div>

        <div class="grid modules" style="margin-top:14px">
            @foreach ($requiredDocumentStatuses as $requiredDocument)
                <div class="module">
                    <strong>{{ $requiredDocument['label'] }}</strong>
                    <span>
                        <span class="badge {{ $requiredDocument['is_received'] ? '' : 'badge-warning' }}">
                            {{ $requiredDocument['is_received'] ? 'Reçu' : 'Manquant' }}
                        </span>
                    </span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="grid two-col" style="margin-top:16px">
        <div class="panel">
            <div class="panel-head">
                <h2>Ajouter un document</h2>
            </div>

            @can('students.update')
                <form method="POST" action="{{ route('students.documents.store', $student) }}" enctype="multipart/form-data">
                    @csrf

                    <div class="form-grid">
                        <div class="field">
                            <label>Nom du document</label>
                            <input name="name" value="{{ old('name') }}" placeholder="Ex: Acte de naissance" required>
                        </div>

                        <div class="field">
                            <label>Type</label>
                            <select name="document_type" required>
                                @foreach ($documentTypeLabels as $type => $label)
                                    <option value="{{ $type }}" @selected(old('document_type') === $type)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field">
                            <label>Statut</label>
                            <select name="status" required>
                                <option value="received" @selected(old('status', 'received') === 'received')>Reçu</option>
                                <option value="missing" @selected(old('status') === 'missing')>Manquant</option>
                                <option value="expired" @selected(old('status') === 'expired')>Expire</option>
                            </select>
                        </div>

                        <div class="field">
                            <label>Date de reception</label>
                            <input type="date" name="received_at" value="{{ old('received_at', now()->toDateString()) }}">
                        </div>

                        <div class="field wide">
                            <label>Fichier PDF ou image</label>
                            <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Ajouter au dossier</button>
                    </div>
                </form>
            @else
                <div class="empty">Tu peux consulter les documents, mais ton role ne permet pas d en ajouter.</div>
            @endcan
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Documents</h2>
                <span class="badge">{{ $student->documents->count() }} pièce(s)</span>
            </div>

            @php($documentTypeLabels = $documentTypeLabels + [
                'school_certificate' => 'Certificat de scolarité',
                'enrollment_certificate' => 'Certificat d inscription',
                'no_debt_certificate' => 'Certificat de non redevance',
            ])
            @php($statusLabels = ['received' => 'Reçu', 'missing' => 'Manquant', 'expired' => 'Expire'])
            @php($certificateTypes = ['school_certificate', 'enrollment_certificate', 'no_debt_certificate'])

            @if ($student->documents->isEmpty())
                <div class="empty">Aucun document rattache a ce dossier.</div>
            @else
                <div class="subject-list-scroll">
                    <table class="table" style="min-width:720px">
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>Type</th>
                                <th>Statut</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($student->documents->sortByDesc('created_at') as $document)
                                <tr>
                                    <td>
                                        <strong>{{ $document->name }}</strong><br>
                                        <span>{{ $document->academicYear?->name ?? '-' }}</span>
                                    </td>
                                    <td>{{ $documentTypeLabels[$document->document_type] ?? $document->document_type }}</td>
                                    <td>
                                        <span class="badge {{ $document->status === 'received' ? '' : 'badge-warning' }}">
                                            {{ $statusLabels[$document->status] ?? $document->status }}
                                        </span>
                                    </td>
                                    <td>{{ $document->received_at?->format('d/m/Y') ?? '-' }}</td>
                                    <td>
                                        <div class="page-actions" style="justify-content:flex-end">
                                            @if ($document->file_path)
                                                <a class="btn btn-subtle" href="{{ route('student-documents.show', $document) }}" target="_blank" rel="noopener">Voir</a>
                                                <a class="btn btn-subtle" href="{{ route('student-documents.download', $document) }}">Télécharger</a>
                                            @elseif (in_array($document->document_type, $certificateTypes, true))
                                                <a class="btn btn-subtle" href="{{ route('certificates.show', $document) }}">Voir</a>
                                                <a class="btn btn-subtle" href="{{ route('certificates.pdf', $document) }}">PDF</a>
                                            @endif
                                            @can('students.update')
                                                <form method="POST" action="{{ route('students.documents.destroy', [$student, $document]) }}" onsubmit="return confirm('Supprimer ce document ?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-danger" type="submit">Supprimer</button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
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
                            <th>Téléphone</th>
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
                    <span>WhatsApp infos école</span>
                    <strong>{{ $student->school_info_whatsapp ?: '-' }}</strong>
                </div>
            </div>
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Actions</h2>
        </div>

        <form method="POST" action="{{ route('students.destroy', $student) }}" onsubmit="return confirm('Archiver ce dossier élève ?')">
            @csrf
            @method('DELETE')
            <button class="btn btn-danger" type="submit">Archiver le dossier</button>
        </form>
    </section>
@endsection
