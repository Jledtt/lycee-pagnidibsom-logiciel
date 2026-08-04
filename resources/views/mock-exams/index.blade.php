@extends('layouts.app', [
    'title' => 'Examens - Lycée Privé Pagnidibsom',
    'active' => 'mock-exams',
    'pageTitle' => 'Examens',
    'pageSubtitle' => 'Examens trimestriels, BEPC blanc et BAC blanc separes du module notes',
])

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    @php
        $workspaceSections = [
            'overview' => 'Vue d’ensemble',
            'candidates' => 'Candidats',
            'subjects' => 'Épreuves et notes',
            'jury' => 'Jury',
            'documents' => 'Documents',
        ];
        $activeWorkspace = request('section', 'overview');
        $activeWorkspace = array_key_exists($activeWorkspace, $workspaceSections) ? $activeWorkspace : 'overview';
    @endphp

    <details class="panel exam-create-disclosure" @if ($errors->any() || $exams->isEmpty()) open @endif>
        <summary class="exam-disclosure-summary">
            <span>
                <strong>Créer une nouvelle session</strong>
                <small>Examen trimestriel, BEPC blanc ou BAC blanc</small>
            </span>
            <span class="badge">{{ $academicYear->name }}</span>
        </summary>

        <form class="form-grid exam-disclosure-body" method="POST" action="{{ route('mock-exams.store') }}">
            @csrf

            <div class="field">
                <label>Nom</label>
                <input name="name" value="{{ old('name', 'Examen trimestriel N 1') }}" required>
            </div>

            <div class="field">
                <label>Type</label>
                <select name="exam_type" required>
                    <option value="trimestriel" @selected(old('exam_type', 'trimestriel') === 'trimestriel')>Examen trimestriel</option>
                    <option value="bepc_blanc" @selected(old('exam_type') === 'bepc_blanc')>BEPC blanc</option>
                    <option value="bac_blanc" @selected(old('exam_type') === 'bac_blanc')>BAC blanc</option>
                </select>
            </div>

            <div class="field">
                <label>Trimestre lie</label>
                <select name="term_id">
                    <option value="">Non lie</option>
                    @foreach ($terms as $term)
                        <option value="{{ $term->id }}" @selected((int) old('term_id') === $term->id)>{{ $term->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label>Date debut</label>
                <input type="date" name="starts_on" value="{{ old('starts_on') }}">
            </div>

            <div class="field">
                <label>Date fin</label>
                <input type="date" name="ends_on" value="{{ old('ends_on') }}">
            </div>

            <div class="field wide">
                <label>Classes concernées</label>
                <div class="subject-list-scroll">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;min-width:620px">
                        @foreach ($classes as $class)
                            <label class="detail-item check" style="margin:0;align-items:center">
                                <input type="checkbox" name="school_class_ids[]" value="{{ $class->id }}" @checked(in_array($class->id, old('school_class_ids', $suggestedClassIds), true))>
                                <span style="margin:0;text-transform:none;font-size:14px">{{ $class->name }}{{ $class->level ? ' - '.$class->level->name : '' }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <p style="margin:8px 0 0;color:var(--muted)">Conseil : sélectionne les classes concernées. Les 3e servent au BEPC blanc et les Terminales au futur BAC blanc.</p>
            </div>

            <div class="field wide">
                <label>Notes internes</label>
                <textarea name="notes" placeholder="Ex: simulation interne, ne compte pas dans la moyenne trimestrielle">{{ old('notes') }}</textarea>
            </div>

            <div class="form-actions wide">
                <button class="btn btn-primary" type="submit">Créer la session</button>
            </div>
        </form>
    </details>

    <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>Sessions créées</h2>
                <span class="badge">{{ $exams->count() }} session(s)</span>
            </div>

            @if ($exams->isEmpty())
                <div class="empty">Aucune session d’examen blanc pour le moment.</div>
            @else
                <div class="ledger-list">
                    @foreach ($exams as $exam)
                        <a class="ledger-item {{ $selectedExam?->id === $exam->id ? 'is-selected' : '' }}" href="{{ route('mock-exams.index', ['mock_exam_id' => $exam->id]) }}" @if ($selectedExam?->id === $exam->id) aria-current="page" @endif>
                            <div class="ledger-summary" style="grid-template-columns:minmax(220px,1.4fr) minmax(140px,.7fr) minmax(140px,.7fr) minmax(130px,.7fr)">
                                <div class="ledger-person">
                                    <strong>{{ $exam->name }}</strong>
                                    <span>{{ $exam->exam_type_label }} - {{ $exam->status_label }}</span>
                                </div>
                                <div class="ledger-metric">
                                    <strong>{{ $exam->candidates_count }}</strong>
                                    <span>Candidats</span>
                                </div>
                                <div class="ledger-metric">
                                    <strong>{{ $exam->subjects_count }}</strong>
                                    <span>Matières</span>
                                </div>
                                <div class="ledger-metric">
                                    <strong>{{ $exam->classes->pluck('name')->join(', ') ?: '-' }}</strong>
                                    <span>Classes</span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
    </section>

    <section class="panel exam-workspace" style="margin-top:16px">
            <div class="panel-head">
                <h2>{{ $selectedExam ? $selectedExam->name : 'Session sélectionnée' }}</h2>
                @if ($selectedExam)
                    <span class="badge">{{ $selectedExam->exam_type_label }}</span>
                @endif
            </div>

            @if (! $selectedExam)
                <div class="empty">Crée ou sélectionne une session.</div>
            @else
                @php($canEditExam = auth()->user()->can('mock_exams.manage') && (! $selectedExam->is_locked || auth()->user()->hasRole('admin')))
                <div class="summary-row">
                    <div class="stat">
                        <span>Candidats</span>
                        <strong>{{ $selectedExam->candidates->count() }}</strong>
                    </div>
                    <div class="stat">
                        <span>Matières</span>
                        <strong>{{ $selectedExam->subjects->count() }}</strong>
                    </div>
                    <div class="stat">
                        <span>Classes</span>
                        <strong>{{ $selectedExam->classes->count() }}</strong>
                    </div>
                    <div class="stat">
                        <span>Résultats</span>
                        <strong>{{ $selectedExam->result_status_label }}</strong>
                    </div>
                </div>

                @if ($selectedExam->is_locked)
                    <p class="notice">Session verrouillee : seul un administrateur peut encore effectuer une correction.</p>
                @endif

                <nav class="exam-workspace-tabs" aria-label="Rubriques de la session">
                    @foreach ($workspaceSections as $section => $label)
                        <a
                            class="exam-workspace-tab {{ $activeWorkspace === $section ? 'is-active' : '' }}"
                            href="{{ route('mock-exams.index', ['mock_exam_id' => $selectedExam->id, 'section' => $section]) }}"
                            @if ($activeWorkspace === $section) aria-current="page" @endif
                        >{{ $label }}</a>
                    @endforeach
                </nav>

                @if ($activeWorkspace === 'overview')
                    <div class="exam-overview-grid">
                        <div class="exam-workflow-section">
                            <div class="exam-section-heading">
                                <div>
                                    <h3>Préparer la session</h3>
                                    <p>Candidats, anonymats et répartition dans les salles.</p>
                                </div>
                            </div>

                            <div class="exam-action-stack">
                                <form method="POST" action="{{ route('mock-exams.candidates.sync', $selectedExam) }}">
                                    @csrf
                                    <button class="btn btn-subtle" type="submit" @disabled(! $canEditExam)>Synchroniser les candidats</button>
                                </form>

                                <form class="exam-inline-tool" method="POST" action="{{ route('mock-exams.anonymity.generate', $selectedExam) }}">
                                    @csrf
                                    <div class="field">
                                        <label for="exam-prefix">Préfixe des anonymats</label>
                                        <input id="exam-prefix" name="prefix" value="X">
                                    </div>
                                    <button class="btn btn-subtle" type="submit" @disabled(! $canEditExam)>Générer</button>
                                </form>

                                <form class="exam-inline-tool" method="POST" action="{{ route('mock-exams.rooms.distribute', $selectedExam) }}">
                                    @csrf
                                    <div class="field">
                                        <label for="exam-room-count">Nombre de salles</label>
                                        <input id="exam-room-count" type="number" name="room_count" min="1" max="30" value="2">
                                    </div>
                                    <button class="btn btn-subtle" type="submit" @disabled(! $canEditExam)>Répartir</button>
                                </form>
                            </div>
                        </div>

                        <div class="exam-workflow-section">
                            <div class="exam-section-heading">
                                <div>
                                    <h3>État des résultats</h3>
                                    <p>Fais progresser la session après les contrôles nécessaires.</p>
                                </div>
                                <span class="badge">{{ $selectedExam->result_status_label }}</span>
                            </div>

                            @can('mock_exams.manage')
                                <div class="exam-status-actions">
                                    @foreach ([
                                        'provisoire' => 'Marquer provisoire',
                                        'corrige' => 'Marquer corrigé',
                                        'définitif' => 'Valider définitivement',
                                        'verrouille' => 'Verrouiller la session',
                                    ] as $status => $label)
                                        <form method="POST" action="{{ route('mock-exams.result-status.update', $selectedExam) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="result_status" value="{{ $status }}">
                                            <button class="btn {{ $status === 'verrouille' ? 'btn-primary' : 'btn-subtle' }}" type="submit">{{ $label }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            @endcan
                        </div>
                    </div>
                @endif

                @if ($selectedExam->notes)
                    <p class="notice" style="margin-top:16px">{{ $selectedExam->notes }}</p>
                @endif
            @endif
    </section>

    @if ($selectedExam)
        @if ($activeWorkspace === 'candidates')
            <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>Candidats</h2>
                <span class="badge">{{ $selectedExam->candidates->count() }} ligne(s)</span>
            </div>

            <div class="subject-list-scroll">
                <table class="table" style="min-width:900px">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Anonymat</th>
                            <th>Matricule</th>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Salle</th>
                            <th>Statut</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($selectedExam->candidates->sortBy([['schoolClass.name', 'asc'], ['student.last_name', 'asc']]) as $candidate)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><span class="badge">{{ $candidate->anonymous_code ?: '-' }}</span></td>
                                <td>{{ $candidate->student?->matricule }}</td>
                                <td><strong>{{ $candidate->student?->full_name }}</strong></td>
                                <td>{{ $candidate->schoolClass?->name }}</td>
                                <td>{{ $candidate->room_name ?: '-' }}</td>
                                <td><span class="badge">{{ $candidate->status }}</span></td>
                                <td>
                                    @can('mock_exams.print')
                                        <a class="btn btn-subtle" href="{{ route('mock-exams.candidates.transcript.pdf', [$selectedExam, $candidate]) }}" data-download-feedback="Téléchargement du relevé individuel lancé.">Relevé PDF</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">Aucun candidat. Clique sur synchroniser candidats.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            </section>
        @endif

        @if ($activeWorkspace === 'subjects')
            <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>Suivi PV, copies et honoraires</h2>
                <span class="badge">{{ $selectedExam->subjects->count() }} matière(s)</span>
            </div>

            @forelse ($selectedExam->subjects->sortBy('position') as $subject)
                <form class="detail-item" style="display:block;margin-bottom:12px" method="POST" action="{{ route('mock-exams.subjects.tracking.update', $subject) }}">
                    @csrf
                    @method('PUT')

                    <div class="panel-head" style="padding:0;border:0;margin-bottom:12px">
                        <div>
                            <strong>{{ $subject->subject?->name }}</strong>
                            <span>{{ $subject->exam_part_label }} - Note / {{ number_format($subject->max_score, 0, ',', ' ') }} - Coef {{ number_format($subject->coefficient, 2, ',', ' ') }}</span>
                        </div>
                        <div class="page-actions">
                            <a class="btn btn-subtle" href="{{ route('mock-exams.subjects.scores', [$selectedExam, $subject]) }}">Saisie notes</a>
                            @can('mock_exams.print')
                                <a class="btn btn-subtle" href="{{ route('mock-exams.subjects.scores.pdf', [$selectedExam, $subject]) }}" data-download-feedback="Téléchargement de la feuille de notes lancé.">PDF notes</a>
                            @endcan
                            <span class="badge">{{ $subject->fee_status === 'paid' ? 'Honoraire paye' : ($subject->fee_status === 'approved' ? 'Honoraire valide' : 'A traiter') }}</span>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="field">
                            <label>Date epreuve</label>
                            <input type="date" name="exam_date" value="{{ old('exam_date', $subject->exam_date?->format('Y-m-d')) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field">
                            <label>Début</label>
                            <input type="time" name="starts_at" value="{{ old('starts_at', $subject->starts_at) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field">
                            <label>Fin</label>
                            <input type="time" name="ends_at" value="{{ old('ends_at', $subject->ends_at) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field">
                            <label>Surveillant 1</label>
                            <input name="supervisor_one" value="{{ old('supervisor_one', $subject->supervisor_one) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field">
                            <label>Surveillant 2</label>
                            <input name="supervisor_two" value="{{ old('supervisor_two', $subject->supervisor_two) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field">
                            <label>Copies attendues</label>
                            <input type="number" min="0" name="expected_copies" value="{{ old('expected_copies', $subject->expected_copies ?? $selectedExam->candidates->count()) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field">
                            <label>Copies reçues</label>
                            <input type="number" min="0" name="received_copies" value="{{ old('received_copies', $subject->received_copies) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field">
                            <label>Absents</label>
                            <input type="number" min="0" name="absent_count" value="{{ old('absent_count', $subject->absent_count) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field">
                            <label>Copies reçues le</label>
                            <input type="datetime-local" name="copies_received_at" value="{{ old('copies_received_at', $subject->copies_received_at?->format('Y-m-d\TH:i')) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field">
                            <label>Reçu par</label>
                            <input name="copy_receiver_name" value="{{ old('copy_receiver_name', $subject->copy_receiver_name) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field">
                            <label>Professeur correcteur</label>
                            <input name="correction_teacher_name" value="{{ old('correction_teacher_name', $subject->correction_teacher_name) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field">
                            <label>Quantité honoraire</label>
                            <input type="number" min="0" step="0.01" name="fee_quantity" value="{{ old('fee_quantity', $subject->fee_quantity) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field">
                            <label>Unité</label>
                            <select name="fee_quantity_unit" @disabled(! $canEditExam)>
                                @foreach (['copies' => 'Copies', 'heures' => 'Heures', 'séances' => 'Séances'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('fee_quantity_unit', $subject->fee_quantity_unit) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Taux honoraire</label>
                            <input type="number" min="0" step="1" name="fee_rate" value="{{ old('fee_rate', $subject->fee_rate) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field">
                            <label>Montant</label>
                            <input type="number" min="0" step="1" name="fee_amount" value="{{ old('fee_amount', $subject->fee_amount) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field">
                            <label>Retenue à la source</label>
                            <input type="number" min="0" step="1" name="fee_withholding_amount" value="{{ old('fee_withholding_amount', $subject->fee_withholding_amount) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field">
                            <label>Avance déjà versée</label>
                            <input type="number" min="0" step="1" name="fee_advance_amount" value="{{ old('fee_advance_amount', $subject->fee_advance_amount) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field">
                            <label>Autre déduction</label>
                            <input type="number" min="0" step="1" name="fee_other_deduction_amount" value="{{ old('fee_other_deduction_amount', $subject->fee_other_deduction_amount) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field">
                            <label>Type de pièce</label>
                            <select name="beneficiary_identity_type" @disabled(! $canEditExam)>
                                <option value="">Non renseigné</option>
                                @foreach (['CNIB', 'Passeport', 'Autre'] as $identityType)
                                    <option value="{{ $identityType }}" @selected(old('beneficiary_identity_type', $subject->beneficiary_identity_type) === $identityType)>{{ $identityType }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Numéro de pièce</label>
                            <input name="beneficiary_identity_number" value="{{ old('beneficiary_identity_number', $subject->beneficiary_identity_number) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field">
                            <label>Statut honoraire</label>
                            <select name="fee_status" @disabled(! $canEditExam)>
                                <option value="pending" @selected($subject->fee_status === 'pending')>A payer</option>
                                <option value="approved" @selected($subject->fee_status === 'approved')>Valide</option>
                                <option value="paid" @selected($subject->fee_status === 'paid')>Paye</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Paye le</label>
                            <input type="datetime-local" name="fee_paid_at" value="{{ old('fee_paid_at', $subject->fee_paid_at?->format('Y-m-d\TH:i')) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field">
                            <label>Reference paiement</label>
                            <input name="fee_payment_reference" value="{{ old('fee_payment_reference', $subject->fee_payment_reference) }}" @disabled(! $canEditExam)>
                        </div>
                        <div class="field wide">
                            <label>Incidents / observations</label>
                            <textarea name="incident_notes" @disabled(! $canEditExam)>{{ old('incident_notes', $subject->incident_notes) }}</textarea>
                        </div>
                        @can('mock_exams.manage')
                            <div class="form-actions wide">
                                <button class="btn btn-primary" type="submit" @disabled(! $canEditExam)>Enregistrer</button>
                            </div>
                        @endcan
                    </div>
                </form>
            @empty
                <div class="empty">Aucune matière. Les matières actives des classes seront reprises automatiquement à la création.</div>
            @endforelse
            </section>
        @endif

        @if ($activeWorkspace === 'jury')
            <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>Décisions du jury</h2>
                <span class="badge">{{ $selectedExam->candidates->count() }} candidat(s)</span>
            </div>

            <form method="POST" action="{{ route('mock-exams.jury-decisions.update', $selectedExam) }}">
                @csrf
                @method('PUT')

                <div class="subject-list-scroll">
                    <table class="table" style="min-width:980px">
                        <thead>
                            <tr>
                                <th>Élève</th>
                                <th>Classe</th>
                                <th>Anonymat</th>
                                <th>Décision</th>
                                <th>Observation</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($selectedExam->candidates->sortBy([['schoolClass.name', 'asc'], ['student.last_name', 'asc']]) as $candidate)
                                <tr>
                                    <td>
                                        <strong>{{ $candidate->student?->full_name }}</strong><br>
                                        <span class="badge">{{ $candidate->student?->matricule }}</span>
                                    </td>
                                    <td>{{ $candidate->schoolClass?->name }}</td>
                                    <td>{{ $candidate->anonymous_code ?: '-' }}</td>
                                    <td>
                                        <select name="candidates[{{ $candidate->id }}][jury_decision]" @disabled(! $canEditExam)>
                                            <option value="">A determiner</option>
                                            @foreach ($juryDecisionLabels as $value => $label)
                                                <option value="{{ $value }}" @selected($candidate->jury_decision === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input name="candidates[{{ $candidate->id }}][jury_observation]" value="{{ $candidate->jury_observation }}" placeholder="Observation du jury" @disabled(! $canEditExam)>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">Aucun candidat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @can('mock_exams.manage')
                    <div class="form-actions" style="margin-top:14px">
                        <button class="btn btn-primary" type="submit" @disabled(! $canEditExam)>Enregistrer les décisions</button>
                    </div>
                @endcan
            </form>
            </section>
        @endif

        @if ($activeWorkspace === 'documents')
            <section class="panel" style="margin-top:16px">
                <div class="panel-head">
                    <div>
                        <h2>Documents de la session</h2>
                        <span>Choisis uniquement le document nécessaire à l’étape en cours.</span>
                    </div>
                    <span class="badge">PDF</span>
                </div>

                <div class="exam-document-groups">
                    <div class="exam-document-group">
                        <div class="exam-section-heading">
                            <div>
                                <h3>Organisation</h3>
                                <p>Avant et pendant les épreuves.</p>
                            </div>
                        </div>
                        <div class="exam-document-links">
                            <a class="btn btn-subtle" href="{{ route('mock-exams.candidates.pdf', $selectedExam) }}" data-download-feedback="Téléchargement de la liste des candidats lancé.">Liste des candidats</a>
                            <a class="btn btn-subtle" href="{{ route('mock-exams.rooms.pdf', $selectedExam) }}" data-download-feedback="Téléchargement de la répartition par salle lancé.">Répartition des salles</a>
                            <a class="btn btn-subtle" href="{{ route('mock-exams.anonymity.pdf', $selectedExam) }}" data-download-feedback="Téléchargement de la liste des anonymats lancé.">Liste des anonymats</a>
                            <a class="btn btn-subtle" href="{{ route('mock-exams.surveillance-pv.pdf', $selectedExam) }}" data-download-feedback="Téléchargement du PV de surveillance lancé.">PV de surveillance</a>
                            <a class="btn btn-subtle" href="{{ route('mock-exams.copy-receipt.pdf', $selectedExam) }}" data-download-feedback="Téléchargement du bordereau des copies lancé.">Bordereau des copies</a>
                        </div>
                    </div>

                    <div class="exam-document-group">
                        <div class="exam-section-heading">
                            <div>
                                <h3>Résultats et jury</h3>
                                <p>Après la saisie et le contrôle des notes.</p>
                            </div>
                        </div>
                        <div class="exam-document-links">
                            <a class="btn btn-primary" href="{{ route('mock-exams.transcripts.pdf', $selectedExam) }}" data-download-feedback="Téléchargement des relevés individuels lancé.">Relevés individuels</a>
                            <a class="btn btn-subtle" href="{{ route('mock-exams.decision-lists.pdf', [$selectedExam, 'admis']) }}" data-download-feedback="Téléchargement de la liste des admis lancé.">Liste des admis</a>
                            <a class="btn btn-subtle" href="{{ route('mock-exams.decision-lists.pdf', [$selectedExam, 'second-tour']) }}" data-download-feedback="Téléchargement de la liste du second tour lancé.">Liste du second tour</a>
                            <a class="btn btn-subtle" href="{{ route('mock-exams.decision-lists.pdf', [$selectedExam, 'ajournes']) }}" data-download-feedback="Téléchargement de la liste des ajournés lancé.">Liste des ajournés</a>
                            <a class="btn btn-subtle" href="{{ route('mock-exams.results.pdf', [$selectedExam, 'provisoire']) }}" data-download-feedback="Téléchargement des résultats provisoires lancé.">Résultats provisoires</a>
                            <a class="btn btn-subtle" href="{{ route('mock-exams.results.pdf', [$selectedExam, 'definitif']) }}" data-download-feedback="Téléchargement des résultats définitifs lancé.">Résultats définitifs</a>
                            <a class="btn btn-subtle" href="{{ route('mock-exams.jury-decision.pdf', $selectedExam) }}" data-download-feedback="Téléchargement de la décision du jury lancé.">Décision du jury</a>
                        </div>
                    </div>

                    <div class="exam-document-group">
                        <div class="exam-section-heading">
                            <div>
                                <h3>Honoraires</h3>
                                <p>État des correcteurs et montants liés à la session.</p>
                            </div>
                        </div>
                        <div class="exam-document-links">
                            <a class="btn btn-subtle" href="{{ route('mock-exams.teacher-fees.pdf', $selectedExam) }}" data-download-feedback="Téléchargement des honoraires professeurs lancé.">Honoraires des professeurs</a>
                        </div>
                    </div>
                </div>
            </section>
        @endif
    @endif
@endsection
