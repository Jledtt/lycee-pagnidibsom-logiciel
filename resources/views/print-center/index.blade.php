@extends('layouts.app', [
    'title' => 'Centre d impression - Lycee Prive Pagnidibsom',
    'active' => 'print-center',
    'pageTitle' => 'Centre d impression',
    'pageSubtitle' => 'Tous les documents officiels au meme endroit',
])

@section('content')
    <section class="grid two-col">
        @can('students.export')
            <div class="panel">
                <div class="panel-head">
                    <h2>Eleves et inscriptions</h2>
                    <span class="badge">{{ $academicYear?->name }}</span>
                </div>
                <div class="ledger-list">
                    <a class="ledger-item" href="{{ route('students.index') }}">
                        <div class="ledger-person"><strong>Fiches eleves</strong><span>Fiche d inscription, carte scolaire, documents scannes.</span></div>
                    </a>
                    <a class="ledger-item" href="{{ route('certificates.index') }}">
                        <div class="ledger-person"><strong>Certificats</strong><span>Scolarite, inscription, non redevance et autres documents.</span></div>
                    </a>
                    <a class="ledger-item" href="{{ route('exit-authorizations.index') }}">
                        <div class="ledger-person"><strong>Autorisations entree / sortie</strong><span>Document pour absence, maladie, sortie ou retour autorise.</span></div>
                    </a>
                    @if ($firstClass)
                        <a class="ledger-item" href="{{ route('reports.class-list.pdf', ['school_class_id' => $firstClass->id]) }}" data-download-feedback="Telechargement de la liste de classe lance.">
                            <div class="ledger-person"><strong>Liste de classe PDF</strong><span>{{ $firstClass->name }} par defaut, changeable dans Rapports.</span></div>
                        </a>
                    @endif
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
                        <div class="ledger-person"><strong>Situation financiere par classe</strong><span>Paye, reste, impaye, export Excel et PDF.</span></div>
                    </a>
                    <a class="ledger-item" href="{{ route('reports.installments') }}">
                        <div class="ledger-person"><strong>Tranches et impayes</strong><span>Vue par eleve avec detail des frais non soldes.</span></div>
                    </a>
                    <a class="ledger-item" href="{{ route('accounting.cash-journal') }}">
                        <div class="ledger-person"><strong>Etat mensuel / journal de caisse</strong><span>Encaissements, depenses, soldes et impressions comptables.</span></div>
                    </a>
                </div>
            </div>
        @endcan
    </section>

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
                            <span>{{ $exam->exam_type_label }} - {{ $exam->classes->pluck('name')->join(', ') ?: 'Classes non renseignees' }}</span>
                        </div>
                        <span class="badge">{{ $exam->result_status_label }}</span>
                    </div>
                    <div class="page-actions">
                        <a class="btn btn-subtle" href="{{ route('mock-exams.candidates.pdf', $exam) }}" data-download-feedback="Telechargement candidats lance.">Candidats</a>
                        <a class="btn btn-subtle" href="{{ route('mock-exams.rooms.pdf', $exam) }}" data-download-feedback="Telechargement salles lance.">Salles</a>
                        <a class="btn btn-subtle" href="{{ route('mock-exams.anonymity.pdf', $exam) }}" data-download-feedback="Telechargement anonymats lance.">Anonymats</a>
                        <a class="btn btn-subtle" href="{{ route('mock-exams.surveillance-pv.pdf', $exam) }}" data-download-feedback="Telechargement PV lance.">PV surveillance</a>
                        <a class="btn btn-subtle" href="{{ route('mock-exams.copy-receipt.pdf', $exam) }}" data-download-feedback="Telechargement bordereau lance.">Bordereau copies</a>
                        <a class="btn btn-subtle" href="{{ route('mock-exams.results.pdf', [$exam, 'provisoire']) }}" data-download-feedback="Telechargement resultats provisoires lance.">Resultats provisoires</a>
                        <a class="btn btn-subtle" href="{{ route('mock-exams.results.pdf', [$exam, 'definitif']) }}" data-download-feedback="Telechargement resultats definitifs lance.">Resultats definitifs</a>
                        <a class="btn btn-subtle" href="{{ route('mock-exams.jury-decision.pdf', $exam) }}" data-download-feedback="Telechargement decision jury lance.">Decision jury</a>
                        <a class="btn btn-subtle" href="{{ route('mock-exams.teacher-fees.pdf', $exam) }}" data-download-feedback="Telechargement honoraires lance.">Honoraires</a>
                    </div>
                </div>
            @empty
                <div class="empty">Aucun examen cree pour le moment.</div>
            @endforelse
        @else
            <div class="empty">Ton role ne peut pas imprimer les documents d examen.</div>
        @endcan
    </section>

    <section class="grid two-col" style="margin-top:16px">
        @can('report_cards.print')
            <div class="panel">
                <div class="panel-head"><h2>Notes et bulletins</h2></div>
                <div class="ledger-list">
                    <a class="ledger-item" href="{{ route('grades.index') }}">
                        <div class="ledger-person"><strong>Evaluations</strong><span>PDF et Excel des devoirs mensuels.</span></div>
                    </a>
                    <a class="ledger-item" href="{{ route('report-cards.index') }}">
                        <div class="ledger-person"><strong>Bulletins</strong><span>Bulletins par eleve ou par classe.</span></div>
                    </a>
                    <a class="ledger-item" href="{{ route('class-council.index') }}">
                        <div class="ledger-person"><strong>Conseil de classe</strong><span>PV, classement, releves et decisions.</span></div>
                    </a>
                </div>
            </div>
        @endcan

        @can('attendance.reports')
            <div class="panel">
                <div class="panel-head"><h2>Absences</h2></div>
                <div class="ledger-list">
                    <a class="ledger-item" href="{{ route('attendance.index') }}">
                        <div class="ledger-person"><strong>Rapports d absences</strong><span>Pointage, PDF par classe et historique eleve.</span></div>
                    </a>
                    <a class="ledger-item" href="{{ route('exit-authorizations.index') }}">
                        <div class="ledger-person"><strong>Autorisations d absence</strong><span>Sorties, retours, motifs et PDF officiel.</span></div>
                    </a>
                </div>
            </div>
        @endcan

        @can('timetables.print')
            <div class="panel">
                <div class="panel-head"><h2>Vie scolaire</h2></div>
                <div class="ledger-list">
                    <a class="ledger-item" href="{{ route('teacher-attendance-sheets.index') }}">
                        <div class="ledger-person"><strong>Fiche d emargement professeurs</strong><span>Suivi quotidien des heures faites par cours.</span></div>
                    </a>
                </div>
            </div>
        @endcan
    </section>
@endsection
