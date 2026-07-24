@extends('layouts.app', [
    'title' => 'Centre d’impression - Lycée Privé Pagnidibsom',
    'active' => 'print-center',
    'pageTitle' => 'Centre d’impression',
    'pageSubtitle' => 'Tous les documents officiels au même endroit',
])

@section('content')
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Filtres</h2>
                <span>Choisis une année, une classe, un élève ou un type de document.</span>
            </div>
            <span class="badge">{{ $academicYear?->name ?? 'Année non définie' }}</span>
        </div>

        <form method="GET" action="{{ route('print-center.index') }}" class="filters-grid">
            <div class="form-group">
                <label>Année scolaire</label>
                <select name="academic_year_id">
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}" @selected($academicYear?->id === $year->id)>{{ $year->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Classe</label>
                <select name="school_class_id">
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected($selectedClass?->id === $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Type de document</label>
                <select name="document_type">
                    @foreach ($documentTypes as $key => $label)
                        <option value="{{ $key }}" @selected($documentType === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Élève</label>
                <input type="search" name="student" value="{{ $studentSearch }}" placeholder="Nom, prénom ou matricule">
            </div>
            <div class="form-actions">
                <button class="btn" type="submit">Afficher</button>
                <a class="btn btn-subtle" href="{{ route('print-center.index') }}">Réinitialiser</a>
            </div>
        </form>
    </section>

    @if ($selectedClass && in_array($documentType, ['all', 'students', 'finance', 'attendance'], true))
        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <div>
                    <h2>Documents de la classe</h2>
                    <span>{{ $selectedClass->name }} - PDF et Excel rapides.</span>
                </div>
                <span class="badge">{{ $students->count() }} élève(s) affiché(s)</span>
            </div>

            <div class="quick-actions">
                @can('students.export')
                    <a class="quick-action" href="{{ route('reports.class-list.pdf', ['school_class_id' => $selectedClass->id]) }}" data-download-feedback="Téléchargement de la liste de classe lancé.">
                        <strong>Liste de classe</strong>
                        <span>PDF</span>
                    </a>
                    <a class="quick-action" href="{{ route('reports.class-list.export', ['school_class_id' => $selectedClass->id]) }}" data-download-feedback="Export Excel de la liste de classe lancé.">
                        <strong>Liste de classe</strong>
                        <span>Excel</span>
                    </a>
                    <a class="quick-action" href="{{ route('reports.missing-documents.pdf', ['school_class_id' => $selectedClass->id]) }}" data-download-feedback="Téléchargement des pièces manquantes lancé.">
                        <strong>Pièces manquantes</strong>
                        <span>PDF</span>
                    </a>
                    <a class="quick-action" href="{{ route('reports.missing-documents.export', ['school_class_id' => $selectedClass->id]) }}" data-download-feedback="Export Excel des pièces manquantes lancé.">
                        <strong>Pièces manquantes</strong>
                        <span>Excel</span>
                    </a>
                @endcan

                @can('payments.reports')
                    <a class="quick-action" href="{{ route('reports.payment-situation.pdf', ['school_class_id' => $selectedClass->id]) }}" data-download-feedback="Téléchargement de la situation financière lancé.">
                        <strong>Situation financière</strong>
                        <span>PDF</span>
                    </a>
                    <a class="quick-action" href="{{ route('reports.payment-situation.export', ['school_class_id' => $selectedClass->id]) }}" data-download-feedback="Export Excel de la situation financière lancé.">
                        <strong>Situation financière</strong>
                        <span>Excel</span>
                    </a>
                @endcan

                @can('attendance.reports')
                    <a class="quick-action" href="{{ route('attendance.pdf', ['school_class_id' => $selectedClass->id]) }}" data-download-feedback="Téléchargement des absences lancé.">
                        <strong>Absences</strong>
                        <span>PDF</span>
                    </a>
                    <a class="quick-action" href="{{ route('attendance.export', ['school_class_id' => $selectedClass->id]) }}" data-download-feedback="Export Excel des absences lancé.">
                        <strong>Absences</strong>
                        <span>Excel</span>
                    </a>
                @endcan
            </div>
        </section>
    @endif

    @if (in_array($documentType, ['all', 'students'], true))
        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <div>
                    <h2>Documents par élève</h2>
                    <span>Accès direct aux fiches, cartes, certificats et dossiers scannés.</span>
                </div>
                <span class="badge">{{ $students->count() }} résultat(s)</span>
            </div>

            @forelse ($students as $student)
                <div class="detail-item" style="display:block;margin-bottom:12px">
                    <div class="panel-head" style="padding:0;border:0;margin-bottom:10px">
                        <div>
                            <strong>{{ $student->full_name }}</strong>
                            <span>{{ $student->matricule }} - {{ $student->enrollments->first()?->schoolClass?->name ?? 'Classe non renseignée' }}</span>
                        </div>
                        <span class="badge">{{ $student->documents->count() }} pièce(s)</span>
                    </div>
                    <div class="page-actions">
                        <a class="btn btn-subtle" href="{{ route('students.show', $student) }}">Dossier</a>
                        <a class="btn btn-subtle" href="{{ route('students.registration-sheet.pdf', $student) }}" data-download-feedback="Téléchargement de la fiche d’inscription lancé.">Fiche PDF</a>
                        <a class="btn btn-subtle" href="{{ route('students.school-card.pdf', $student) }}" data-download-feedback="Téléchargement de la carte scolaire lancé.">Carte PDF</a>
                        <a class="btn btn-subtle" href="{{ route('certificates.create', ['student_id' => $student->id]) }}">Certificat</a>
                    </div>
                </div>
            @empty
                <div class="empty">Aucun élève trouvé avec ces filtres.</div>
            @endforelse
        </section>
    @endif

    <section class="grid two-col" style="margin-top:16px">
        @can('students.export')
            <div class="panel">
                <div class="panel-head">
                    <h2>Élèves et inscriptions</h2>
                    <span class="badge">{{ $academicYear?->name }}</span>
                </div>
                <div class="ledger-list">
                    <a class="ledger-item" href="{{ route('students.index') }}">
                        <div class="ledger-person"><strong>Fiches élèves</strong><span>Fiche d’inscription, carte scolaire, photos et documents scannés.</span></div>
                    </a>
                    <a class="ledger-item" href="{{ route('certificates.index') }}">
                        <div class="ledger-person"><strong>Certificats</strong><span>Scolarité, inscription, non-redevance et autres documents.</span></div>
                    </a>
                    <a class="ledger-item" href="{{ route('exit-authorizations.index') }}">
                        <div class="ledger-person"><strong>Autorisations entrée / sortie</strong><span>Document pour absence, maladie, sortie ou retour autorisé.</span></div>
                    </a>
                </div>
            </div>
        @endcan

        @can('payments.reports')
            <div class="panel">
                <div class="panel-head">
                    <h2>Finances</h2>
                    <span class="badge">FCFA</span>
                </div>
                <div class="ledger-list">
                    <a class="ledger-item" href="{{ route('reports.payment-situation') }}">
                        <div class="ledger-person"><strong>Situation financière par classe</strong><span>Payé, reste, impayés, export Excel et PDF.</span></div>
                    </a>
                    <a class="ledger-item" href="{{ route('reports.installments') }}">
                        <div class="ledger-person"><strong>Tranches et impayés</strong><span>Vue par élève avec détail des frais non soldés.</span></div>
                    </a>
                    <a class="ledger-item" href="{{ route('accounting.cash-journal') }}">
                        <div class="ledger-person"><strong>État mensuel / journal de caisse</strong><span>Encaissements, dépenses, soldes et impressions comptables.</span></div>
                    </a>
                </div>
            </div>
        @endcan
    </section>

    @if (in_array($documentType, ['all', 'exams'], true))
        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>Examens</h2>
                <span class="badge">{{ $exams->count() }} session(s)</span>
            </div>

            @can('mock_exams.print')
                @forelse ($exams as $exam)
                    <div class="detail-item" style="display:block;margin-bottom:12px">
                        <div class="panel-head" style="padding:0;border:0;margin-bottom:10px">
                            <div>
                                <strong>{{ $exam->name }}</strong>
                                <span>{{ $exam->exam_type_label }} - {{ $exam->classes->pluck('name')->join(', ') ?: 'Classes non renseignées' }}</span>
                            </div>
                            <span class="badge">{{ $exam->result_status_label }}</span>
                        </div>
                        <div class="page-actions">
                            <a class="btn btn-subtle" href="{{ route('mock-exams.candidates.pdf', $exam) }}" data-download-feedback="Téléchargement candidats lancé.">Candidats</a>
                            <a class="btn btn-subtle" href="{{ route('mock-exams.rooms.pdf', $exam) }}" data-download-feedback="Téléchargement salles lancé.">Salles</a>
                            <a class="btn btn-subtle" href="{{ route('mock-exams.anonymity.pdf', $exam) }}" data-download-feedback="Téléchargement anonymats lancé.">Anonymats</a>
                            <a class="btn btn-subtle" href="{{ route('mock-exams.surveillance-pv.pdf', $exam) }}" data-download-feedback="Téléchargement PV lancé.">PV surveillance</a>
                            <a class="btn btn-subtle" href="{{ route('mock-exams.copy-receipt.pdf', $exam) }}" data-download-feedback="Téléchargement bordereau lancé.">Bordereau copies</a>
                            <a class="btn btn-subtle" href="{{ route('mock-exams.results.pdf', [$exam, 'provisoire']) }}" data-download-feedback="Téléchargement des résultats provisoires lancé.">Résultats provisoires</a>
                            <a class="btn btn-subtle" href="{{ route('mock-exams.results.pdf', [$exam, 'definitif']) }}" data-download-feedback="Téléchargement résultats définitifs lancé.">Résultats définitifs</a>
                            <a class="btn btn-subtle" href="{{ route('mock-exams.jury-decision.pdf', $exam) }}" data-download-feedback="Téléchargement décision jury lancé.">Décision jury</a>
                            <a class="btn btn-subtle" href="{{ route('mock-exams.teacher-fees.pdf', $exam) }}" data-download-feedback="Téléchargement honoraires lancé.">Honoraires</a>
                        </div>
                    </div>
                @empty
                    <div class="empty">Aucun examen créé pour le moment.</div>
                @endforelse
            @else
                <div class="empty">Ton rôle ne peut pas imprimer les documents d’examen.</div>
            @endcan
        </section>
    @endif

    <section class="grid two-col" style="margin-top:16px">
        @can('report_cards.print')
            @if (in_array($documentType, ['all', 'grades'], true))
                <div class="panel">
                    <div class="panel-head"><h2>Notes et bulletins</h2></div>
                    <div class="ledger-list">
                        <a class="ledger-item" href="{{ route('grades.index') }}">
                            <div class="ledger-person"><strong>Évaluations</strong><span>PDF et Excel des devoirs mensuels.</span></div>
                        </a>
                        <a class="ledger-item" href="{{ route('report-cards.index') }}">
                            <div class="ledger-person"><strong>Bulletins</strong><span>Bulletins par élève ou par classe.</span></div>
                        </a>
                        <a class="ledger-item" href="{{ route('class-council.index') }}">
                            <div class="ledger-person"><strong>Conseil de classe</strong><span>PV, classement, relevés et décisions.</span></div>
                        </a>
                        @if ($selectedClass)
                            <a class="ledger-item" href="{{ route('class-council.annual-redemptions', ['school_class_id' => $selectedClass->id]) }}">
                                <div class="ledger-person"><strong>Rachats conseil</strong><span>Liste des élèves proches de 10/20 selon un seuil choisi.</span></div>
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        @endcan

        @can('attendance.reports')
            @if (in_array($documentType, ['all', 'attendance'], true))
                <div class="panel">
                    <div class="panel-head"><h2>Vie scolaire</h2></div>
                    <div class="ledger-list">
                        <a class="ledger-item" href="{{ route('attendance.index') }}">
                            <div class="ledger-person"><strong>Rapports d’absences</strong><span>Pointage, PDF par classe et historique élève.</span></div>
                        </a>
                        <a class="ledger-item" href="{{ route('exit-authorizations.index') }}">
                            <div class="ledger-person"><strong>Autorisations d’absence</strong><span>Sorties, retours, motifs et PDF officiel.</span></div>
                        </a>
                        <a class="ledger-item" href="{{ route('teacher-attendance-sheets.index') }}">
                            <div class="ledger-person"><strong>Fiche d’émargement professeurs</strong><span>Suivi quotidien des heures faites par cours.</span></div>
                        </a>
                    </div>
                </div>
            @endif
        @endcan
    </section>
@endsection
