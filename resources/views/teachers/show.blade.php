@extends('layouts.app', [
    'title' => $teacher->name . ' - Professeurs',
    'active' => 'teachers',
    'pageTitle' => $teacher->name,
    'pageSubtitle' => 'Dossier professeur et suivi administratif',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('teachers.index') }}">Retour</a>
    <a class="btn btn-subtle" href="{{ route('teachers.pdf', $teacher) }}">Dossier PDF</a>
    @can('teacher_attendance.view')
        <a class="btn btn-subtle" href="{{ route('teacher-work-sessions.index', ['teacher_id' => $teacher->id]) }}">Émargements</a>
    @endcan
    @can('teacher_fees.view')
        <a class="btn btn-primary" href="{{ route('teacher-fees.index', ['teacher_id' => $teacher->id]) }}">Honoraires</a>
    @endcan
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <section class="stats">
        <div class="stat"><span>Spécialité</span><strong>{{ $profile->specialty ?: 'Non renseignée' }}</strong></div>
        <div class="stat"><span>Taux horaire</span><strong class="money">{{ number_format((float) $profile->default_hourly_rate, 0, ',', ' ') }} FCFA</strong></div>
        <div class="stat"><span>Retenue</span><strong>{{ number_format((float) $profile->withholding_tax_rate, 2, ',', ' ') }} %</strong></div>
        <div class="stat"><span>Statut</span><strong>{{ ucfirst($teacher->status) }}</strong></div>
    </section>

    <section class="grid two-col" style="margin-top:16px">
        <div class="panel">
            <div class="panel-head"><h2>Coordonnées et contrat</h2></div>
            @can('teachers.manage')
                <form method="POST" action="{{ route('teachers.profile.update', $teacher) }}">
                    @csrf
                    @method('PUT')
                    <div class="form-grid">
                        <div class="field"><label>Matricule personnel</label><input name="employee_number" value="{{ old('employee_number', $profile->employee_number) }}"></div>
                        <div class="field"><label>Spécialité / discipline</label><input name="specialty" value="{{ old('specialty', $profile->specialty) }}"></div>
                        <div class="field"><label>Type de pièce</label><select name="identity_document_type"><option value="">-</option>@foreach (['CNIB', 'Passeport', 'Autre'] as $type)<option value="{{ $type }}" @selected(old('identity_document_type', $profile->identity_document_type) === $type)>{{ $type }}</option>@endforeach</select></div>
                        <div class="field"><label>Numéro de pièce</label><input name="identity_document_number" value="{{ old('identity_document_number', $profile->identity_document_number) }}"></div>
                        <div class="field"><label>Délivrée le</label><input type="date" name="identity_document_issued_at" value="{{ old('identity_document_issued_at', $profile->identity_document_issued_at?->format('Y-m-d')) }}"></div>
                        <div class="field"><label>Expire le</label><input type="date" name="identity_document_expires_at" value="{{ old('identity_document_expires_at', $profile->identity_document_expires_at?->format('Y-m-d')) }}"></div>
                        <div class="field"><label>Taux horaire par défaut</label><input type="number" min="0" step="1" name="default_hourly_rate" value="{{ old('default_hourly_rate', $profile->default_hourly_rate) }}" required></div>
                        <div class="field"><label>Retenue à la source (%)</label><input type="number" min="0" max="100" step="0.01" name="withholding_tax_rate" value="{{ old('withholding_tax_rate', $profile->withholding_tax_rate) }}" required></div>
                        <div class="field"><label>Mode de paiement</label><select name="payment_method"><option value="">-</option>@foreach (['Espèces', 'Virement', 'Mobile Money', 'Chèque'] as $method)<option value="{{ $method }}" @selected(old('payment_method', $profile->payment_method) === $method)>{{ $method }}</option>@endforeach</select></div>
                        <div class="field"><label>Référence de paiement</label><input name="payment_reference" value="{{ old('payment_reference', $profile->payment_reference) }}" placeholder="Compte, téléphone ou banque"></div>
                        <div class="field"><label>Type de contrat</label><input name="contract_type" value="{{ old('contract_type', $profile->contract_type) }}"></div>
                        <div class="field"><label>Date d’embauche</label><input type="date" name="hired_at" value="{{ old('hired_at', $profile->hired_at?->format('Y-m-d')) }}"></div>
                        <div class="field wide"><label>Adresse</label><textarea name="address">{{ old('address', $profile->address) }}</textarea></div>
                        <div class="field wide"><label>Contact d’urgence</label><input name="emergency_contact" value="{{ old('emergency_contact', $profile->emergency_contact) }}"></div>
                        <div class="field wide"><label>Notes internes</label><textarea name="notes">{{ old('notes', $profile->notes) }}</textarea></div>
                    </div>
                    <div class="form-actions"><button class="btn btn-primary" type="submit">Enregistrer le dossier</button></div>
                </form>
            @else
                <div class="detail-grid">
                    <div class="detail-item"><span>Matricule</span><strong>{{ $profile->employee_number ?: '-' }}</strong></div>
                    <div class="detail-item"><span>Téléphone</span><strong>{{ $teacher->phone ?: '-' }}</strong></div>
                    <div class="detail-item"><span>E-mail</span><strong>{{ $teacher->email }}</strong></div>
                    <div class="detail-item"><span>Contrat</span><strong>{{ $profile->contract_type ?: '-' }}</strong></div>
                </div>
            @endcan
        </div>

        <div>
            <section class="panel">
                <div class="panel-head"><h2>Affectations {{ $academicYear?->name }}</h2><span class="badge">{{ $assignments->count() }}</span></div>
                @forelse ($assignments as $assignment)
                    <div class="form-row" style="margin:0 0 8px">
                        <div class="detail-item" style="flex:1">
                            <span>{{ $assignment->schoolClass?->name }}</span>
                            <strong>{{ $assignment->subject?->name }} · coeff. {{ $assignment->coefficient }}</strong>
                        </div>
                        @can('teachers.manage')
                            <form method="POST" action="{{ route('teachers.assignments.destroy', [$teacher, $assignment]) }}">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">Retirer</button></form>
                        @endcan
                    </div>
                @empty
                    <div class="empty">Aucune matière affectée pour l’année active.</div>
                @endforelse

                @can('teachers.manage')
                    <form method="POST" action="{{ route('teachers.assignments.store', $teacher) }}" style="margin-top:14px">
                        @csrf
                        <div class="form-grid">
                            <div class="field"><label>Classe</label><select name="school_class_id" required><option value="">Choisir</option>@foreach ($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select></div>
                            <div class="field"><label>Matière</label><select name="subject_id" required><option value="">Choisir</option>@foreach ($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></div>
                            <div class="field"><label>Coefficient</label><input type="number" min="0.01" max="20" step="0.01" name="coefficient" value="1" required></div>
                        </div>
                        <button class="btn btn-subtle" type="submit">Ajouter l’affectation</button>
                    </form>
                @endcan
            </section>

            <section class="panel" style="margin-top:16px">
                <div class="panel-head"><h2>Pièces du dossier</h2><span class="badge">{{ $teacher->teacherDocuments->count() }}</span></div>
                @forelse ($teacher->teacherDocuments as $document)
                    <div class="form-row" style="margin:0 0 8px">
                        <a href="{{ route('teacher-documents.download', $document) }}"><strong>{{ $document->name }}</strong><br><span style="color:var(--muted)">{{ $document->document_type }}</span></a>
                        @can('teacher_documents.manage')
                            <form method="POST" action="{{ route('teacher-documents.destroy', $document) }}">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">Supprimer</button></form>
                        @endcan
                    </div>
                @empty
                    <div class="empty">Aucun document téléversé.</div>
                @endforelse

                @can('teacher_documents.manage')
                    <form method="POST" action="{{ route('teacher-documents.store', $teacher) }}" enctype="multipart/form-data" style="margin-top:14px">
                        @csrf
                        <div class="form-grid">
                            <div class="field"><label>Nom du document</label><input name="name" required></div>
                            <div class="field"><label>Type</label><select name="document_type" required>@foreach (['CNIB', 'Passeport', 'Contrat', 'Diplôme', 'Attestation', 'RIB', 'Autre'] as $type)<option>{{ $type }}</option>@endforeach</select></div>
                            <div class="field"><label>Numéro</label><input name="document_number"></div>
                            <div class="field"><label>Expiration</label><input type="date" name="expires_at"></div>
                            <div class="field wide"><label>Fichier PDF ou image</label><input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>
                        </div>
                        <button class="btn btn-subtle" type="submit">Ajouter le document</button>
                    </form>
                @endcan
            </section>
        </div>
    </section>

    <section class="grid two-col" style="margin-top:16px">
        <div class="panel">
            <div class="panel-head"><h2>Derniers émargements</h2><a href="{{ route('teacher-work-sessions.index', ['teacher_id' => $teacher->id]) }}">Voir tout</a></div>
            @forelse ($teacher->teacherWorkSessions as $session)
                <div class="detail-item" style="margin-bottom:8px">
                    <span>{{ $session->session_date->format('d/m/Y') }} · {{ $session->schoolClass?->name }} · {{ $session->subject?->name }}</span>
                    <strong>{{ number_format((float) $session->hours_worked, 2, ',', ' ') }} h · {{ $session->status }}</strong>
                </div>
            @empty
                <div class="empty">Aucune heure enregistrée.</div>
            @endforelse
        </div>
        <div class="panel">
            <div class="panel-head"><h2>Derniers honoraires</h2><a href="{{ route('teacher-fees.index', ['teacher_id' => $teacher->id]) }}">Voir tout</a></div>
            @forelse ($teacher->teacherFeeStatements as $statement)
                <div class="detail-item" style="margin-bottom:8px">
                    <span>{{ $statement->period_month->translatedFormat('F Y') }} · {{ $statement->reference }}</span>
                    <strong>{{ number_format((float) $statement->net_amount, 0, ',', ' ') }} FCFA · {{ $statement->status }}</strong>
                </div>
            @empty
                <div class="empty">Aucun honoraire préparé.</div>
            @endforelse
        </div>
    </section>
@endsection
