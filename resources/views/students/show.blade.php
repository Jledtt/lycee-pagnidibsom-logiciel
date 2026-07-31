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
        <a class="btn btn-subtle" href="{{ route('payments.students.statement', $student) }}" data-dialog-open="student-financial-drawer">Situation financière</a>
    @endcan
    @if ($currentEnrollment)
        @can('enrollments.view')
            <a class="btn btn-subtle" href="{{ route('enrollments.show', $currentEnrollment) }}" data-dialog-open="student-enrollment-drawer">Résumé inscription</a>
        @endcan
    @endif
    @can('students.update')
        <a class="btn btn-subtle" href="#student-documents" data-dialog-open="student-document-dialog">Ajouter une pièce</a>
    @endcan
    @can('payments.create')
        <a class="btn btn-subtle" href="{{ route('payments.create', ['student_id' => $student->id]) }}">Encaisser</a>
    @endcan
    @can('attendance.view')
        <a class="btn btn-subtle" href="{{ route('attendance.students.history', $student) }}">Assiduité</a>
    @endcan
    @can('students.update')
        <a class="btn btn-primary" href="{{ route('students.edit', $student) }}">Modifier</a>
    @endcan
@endsection

@push('dialogs')
    @can('students.update')
        @include('students.partials.document-dialog', [
            'student' => $student,
            'documentTypeLabels' => $documentTypeLabels,
        ])
    @endcan

    @if ($currentEnrollment)
        @can('enrollments.view')
            <x-ui.drawer
                id="student-enrollment-drawer"
                title="Résumé de l’inscription"
                description="{{ $currentEnrollment->academicYear?->name ?? 'Année scolaire active' }}"
            >
                <div class="follow-up-summary-list">
                    <div><span>Élève</span><strong>{{ $student->full_name }}</strong></div>
                    <div><span>Classe</span><strong>{{ $currentEnrollment->schoolClass?->name ?? '-' }}</strong></div>
                    <div><span>Niveau</span><strong>{{ $currentEnrollment->schoolClass?->level?->name ?? '-' }}</strong></div>
                    <div><span>Date d’inscription</span><strong>{{ $currentEnrollment->enrollment_date?->format('d/m/Y') ?? '-' }}</strong></div>
                    <div><span>Type</span><strong>{{ match ($currentEnrollment->type) {
                        'renewal' => 'Réinscription',
                        'transfer' => 'Transfert',
                        default => 'Nouvelle inscription',
                    } }}</strong></div>
                    <div><span>Statut</span><strong>{{ $currentEnrollment->status === 'active' ? 'Active' : ucfirst($currentEnrollment->status) }}</strong></div>
                </div>

                <x-slot:footer>
                    <button class="btn btn-subtle" type="button" data-dialog-close>Fermer</button>
                    <a class="btn btn-primary" href="{{ route('enrollments.show', $currentEnrollment) }}">Voir l’inscription</a>
                </x-slot:footer>
            </x-ui.drawer>
        @endcan
    @endif

    @can('payments.view')
        @if ($financialSummary)
            <x-ui.drawer
                id="student-financial-drawer"
                title="Situation financière"
                description="{{ $student->full_name }} - {{ $academicYear?->name ?? 'Année active' }}"
            >
                <div class="follow-up-summary-list">
                    <div><span>Classe</span><strong>{{ $financialSummary['enrollment']?->schoolClass?->name ?? 'Non inscrit' }}</strong></div>
                    <div><span>Total attendu</span><strong class="money">{{ is_null($financialSummary['expected']) ? 'À configurer' : number_format($financialSummary['expected'], 0, ',', ' ').' FCFA' }}</strong></div>
                    <div><span>Total payé</span><strong class="money">{{ number_format($financialSummary['paid'], 0, ',', ' ') }} FCFA</strong></div>
                    <div class="follow-up-summary-list__highlight"><span>Reste à payer</span><strong class="money">{{ is_null($financialSummary['balance']) ? 'À configurer' : number_format($financialSummary['balance'], 0, ',', ' ').' FCFA' }}</strong></div>
                </div>

                <x-slot:footer>
                    <button class="btn btn-subtle" type="button" data-dialog-close>Fermer</button>
                    <a class="btn btn-primary" href="{{ route('payments.students.statement', $student) }}">Voir la situation complète</a>
                </x-slot:footer>
            </x-ui.drawer>
        @endif
    @endcan

    @if (session('student_created'))
        <x-ui.modal
            id="student-created-dialog"
            title="Dossier élève créé"
            description="{{ $student->full_name }} possède maintenant le matricule {{ $student->matricule }}."
            size="medium"
            :open="true"
        >
            <div class="creation-follow-up">
                <span>Prochaine étape</span>
                <strong>Compléter le dossier ou inscrire l’élève dans une classe.</strong>
            </div>

            <x-slot:footer>
                <button class="btn btn-subtle" type="button" data-dialog-close>Voir le dossier</button>
                @can('students.update')
                    <button class="btn btn-subtle" type="button" data-dialog-open="student-document-dialog">Ajouter des documents</button>
                @endcan
                @can('enrollments.create')
                    <a class="btn btn-primary" href="{{ route('enrollments.create', ['student_id' => $student->id]) }}">Inscrire maintenant</a>
                @endcan
            </x-slot:footer>
        </x-ui.modal>
    @endif
@endpush

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

        @php($studentPhotoDocument = $student->documents
            ->where('document_type', 'photo')
            ->where('status', 'received')
            ->whereNotNull('file_path')
            ->sortByDesc('created_at')
            ->first())
        <div class="student-profile-strip">
            <div class="student-photo-frame">
                @if ($studentPhotoDocument)
                    <img src="{{ route('student-documents.show', $studentPhotoDocument) }}" alt="Photo de {{ $student->full_name }}">
                @elseif ($student->photo_path && ! \Illuminate\Support\Str::startsWith($student->photo_path, 'media:'))
                    <img src="{{ $student->photo_path }}" alt="Photo de {{ $student->full_name }}">
                @else
                    <span>PHOTO</span>
                @endif
            </div>
            <div>
                <h3>{{ $student->full_name }}</h3>
                <p>{{ $currentEnrollment?->schoolClass?->name ?? $student->desired_class ?? 'Non inscrit' }} · {{ $student->matricule }}</p>
            </div>
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
                <h2>Pièces obligatoires</h2>
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

    <section class="grid two-col" id="student-documents" style="margin-top:16px">
        <div class="panel">
            <div class="panel-head">
                <h2>Dossier documentaire</h2>
                <span class="badge {{ count($missingRequiredDocuments) === 0 ? '' : 'badge-warning' }}">
                    {{ $student->documents->count() }} pièce(s)
                </span>
            </div>

            @can('students.update')
                <div class="document-quick-summary">
                    <div>
                        <span>Pièces attendues</span>
                        <strong>{{ count($requiredDocumentStatuses) }}</strong>
                    </div>
                    <div>
                        <span>Encore manquantes</span>
                        <strong>{{ count($missingRequiredDocuments) }}</strong>
                    </div>
                </div>
                <button class="btn btn-primary" type="button" data-dialog-open="student-document-dialog">Ajouter une pièce</button>
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
                'enrollment_certificate' => 'Certificat d’inscription',
                'no_debt_certificate' => 'Certificat de non-redevance',
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
