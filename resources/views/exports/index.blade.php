@extends('layouts.app', [
    'title' => 'Imports / Exports - Lycée Privé Pagnidibsom',
    'active' => 'exports',
    'pageTitle' => 'Imports / Exports',
    'pageSubtitle' => 'Modèles, imports et fichiers Excel utiles',
])

@section('page_actions')
    @can('students.import')
        <a class="btn btn-subtle" href="{{ route('students.import.template') }}" data-download-feedback="Modèle Excel téléchargé.">
            Modèle élèves
        </a>
        <a class="btn btn-primary" href="{{ route('students.import') }}">Importer élèves</a>
    @endcan
@endsection

@section('content')
    @php
        $baseParams = [
            'academic_year_id' => $selectedYear?->id,
            'school_class_id' => $selectedClass?->id,
        ];
    @endphp

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Sélection globale</h2>
                <p>Les exports utilisent ces filtres par défaut. Chaque bloc peut ensuite affiner son propre fichier.</p>
            </div>
            <span class="badge">{{ $selectedYear?->name ?? 'Année' }}</span>
        </div>

        <form class="filters-form" method="GET" action="{{ route('exports.index') }}">
            <label class="field">
                <span>Année scolaire</span>
                <select name="academic_year_id">
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}" @selected($selectedYear?->id === $year->id)>{{ $year->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>Classe</span>
                <select name="school_class_id">
                    <option value="">Toutes les classes</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected($selectedClass?->id === $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
            </label>

            <div class="form-actions compact-action">
                <button class="btn btn-primary" type="submit">Appliquer</button>
            </div>
        </form>
    </section>

    <p class="notice">
        Les fichiers visibles dépendent du rôle connecté. Les exports financiers restent réservés à la direction, à l'administration et à la comptabilité.
    </p>

    <div class="export-grid">
        @can('students.import')
            <section class="panel export-card">
                <div>
                    <h2>Import élèves</h2>
                    <p>Télécharger un modèle, vérifier les lignes, signaler les erreurs et éviter les doublons avant création.</p>
                </div>
                <div class="button-row">
                    <a class="btn btn-subtle" href="{{ route('students.import.template') }}" data-download-feedback="Modèle Excel téléchargé.">Modèle</a>
                    <a class="btn btn-primary" href="{{ route('students.import') }}">Importer</a>
                </div>
            </section>
        @endcan

        @can('students.export')
            <section class="panel export-card">
                <div>
                    <h2>Élèves par classe</h2>
                    <p>Liste propre des élèves avec matricule, identité, classe, tuteur et contact.</p>
                </div>
                <form method="GET" action="{{ route('exports.students') }}">
                    @include('exports.partials.base-fields', ['baseParams' => $baseParams])
                    <label class="field">
                        <span>Statut</span>
                        <select name="status">
                            <option value="">Tous</option>
                            <option value="active">Actifs</option>
                            <option value="inactive">Inactifs</option>
                        </select>
                    </label>
                    <button class="btn btn-primary" type="submit" data-download-feedback="Export élèves téléchargé.">Télécharger Excel</button>
                </form>
            </section>
        @endcan

        @can('payments.reports')
            <section class="panel export-card">
                <div>
                    <h2>Paiements encaissés</h2>
                    <p>Historique des reçus, montants, modes de paiement et élèves concernés.</p>
                </div>
                <form method="GET" action="{{ route('exports.payments') }}">
                    @include('exports.partials.base-fields', ['baseParams' => $baseParams])
                    <div class="two-fields">
                        <label class="field">
                            <span>Du</span>
                            <input type="date" name="date_from">
                        </label>
                        <label class="field">
                            <span>Au</span>
                            <input type="date" name="date_to">
                        </label>
                    </div>
                    <label class="field">
                        <span>Statut</span>
                        <select name="status">
                            <option value="">Tous</option>
                            <option value="validated">Validés</option>
                            <option value="cancelled">Annulés</option>
                        </select>
                    </label>
                    <button class="btn btn-primary" type="submit" data-download-feedback="Export paiements téléchargé.">Télécharger Excel</button>
                </form>
            </section>

            <section class="panel export-card">
                <div>
                    <h2>Impayés</h2>
                    <p>Situation des restes à payer par élève, classe et montant estimé.</p>
                </div>
                <form method="GET" action="{{ route('exports.unpaid') }}">
                    @include('exports.partials.base-fields', ['baseParams' => $baseParams])
                    <label class="field">
                        <span>Reste minimum</span>
                        <input type="number" name="minimum_balance" min="0" step="500" placeholder="Ex: 10000">
                    </label>
                    <button class="btn btn-primary" type="submit" data-download-feedback="Export impayés téléchargé.">Télécharger Excel</button>
                </form>
            </section>
        @endcan

        @canany(['grades.view', 'report_cards.view'])
            <section class="panel export-card">
                <div>
                    <h2>Notes</h2>
                    <p>Export des notes par classe, période, trimestre et matière.</p>
                </div>
                <form method="GET" action="{{ route('exports.grades') }}">
                    @include('exports.partials.base-fields', ['baseParams' => $baseParams])
                    <div class="two-fields">
                        <label class="field">
                            <span>Trimestre</span>
                            <select name="term_id">
                                <option value="">Tous</option>
                                @foreach ($terms as $term)
                                    <option value="{{ $term->id }}">{{ $term->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">
                            <span>Période</span>
                            <select name="period_id">
                                <option value="">Toutes</option>
                                @foreach ($periods as $period)
                                    <option value="{{ $period->id }}">{{ $period->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <label class="field">
                        <span>Matière</span>
                        <select name="subject_id">
                            <option value="">Toutes</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="btn btn-primary" type="submit" data-download-feedback="Export notes téléchargé.">Télécharger Excel</button>
                </form>
            </section>
        @endcanany

        @canany(['attendance.reports', 'attendance.view'])
            <section class="panel export-card">
                <div>
                    <h2>Absences</h2>
                    <p>Pointages, retards et absences avec période de recherche.</p>
                </div>
                <form method="GET" action="{{ route('exports.attendance') }}">
                    @include('exports.partials.base-fields', ['baseParams' => $baseParams])
                    <div class="two-fields">
                        <label class="field">
                            <span>Du</span>
                            <input type="date" name="date_from">
                        </label>
                        <label class="field">
                            <span>Au</span>
                            <input type="date" name="date_to">
                        </label>
                    </div>
                    <label class="field">
                        <span>Statut</span>
                        <select name="status">
                            <option value="">Tous</option>
                            <option value="absent">Absents</option>
                            <option value="late">Retards</option>
                            <option value="present">Présents</option>
                        </select>
                    </label>
                    <button class="btn btn-primary" type="submit" data-download-feedback="Export absences téléchargé.">Télécharger Excel</button>
                </form>
            </section>
        @endcanany

        @canany(['mock_exams.view', 'mock_exams.print'])
            <section class="panel export-card">
                <div>
                    <h2>Résultats examen blanc</h2>
                    <p>Moyennes, rangs, décision et statut des candidats d'un examen blanc.</p>
                </div>
                <form method="GET" action="{{ route('exports.mock-exams') }}">
                    @include('exports.partials.base-fields', ['baseParams' => $baseParams])
                    <label class="field">
                        <span>Examen blanc</span>
                        <select name="mock_exam_id">
                            <option value="">Tous</option>
                            @foreach ($mockExams as $mockExam)
                                <option value="{{ $mockExam->id }}">{{ $mockExam->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span>Statut</span>
                        <select name="status">
                            <option value="">Tous</option>
                            <option value="passed">Admis</option>
                            <option value="failed">Ajournés</option>
                            <option value="pending">En cours</option>
                        </select>
                    </label>
                    <button class="btn btn-primary" type="submit" data-download-feedback="Export examen blanc téléchargé.">Télécharger Excel</button>
                </form>
            </section>
        @endcanany

        @can('payments.reports')
            <section class="panel export-card">
                <div>
                    <h2>Honoraires professeurs</h2>
                    <p>Heures, taux, montant, paiement et historique des honoraires.</p>
                </div>
                <form method="GET" action="{{ route('exports.teacher-fees') }}">
                    <input type="hidden" name="academic_year_id" value="{{ $selectedYear?->id }}">
                    <label class="field">
                        <span>Examen blanc</span>
                        <select name="mock_exam_id">
                            <option value="">Tous</option>
                            @foreach ($mockExams as $mockExam)
                                <option value="{{ $mockExam->id }}">{{ $mockExam->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span>Statut</span>
                        <select name="fee_status">
                            <option value="">Tous</option>
                            <option value="pending">À payer</option>
                            <option value="paid">Payés</option>
                        </select>
                    </label>
                    <button class="btn btn-primary" type="submit" data-download-feedback="Export honoraires téléchargé.">Télécharger Excel</button>
                </form>
            </section>
        @endcan
    </div>

    <style>
        .export-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 18px;
            margin-top: 18px;
        }

        .export-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 18px;
        }

        .export-card form,
        .button-row,
        .two-fields {
            display: grid;
            gap: 12px;
        }

        .button-row,
        .two-fields {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .compact-action {
            align-self: end;
        }

        @media (max-width: 760px) {
            .export-grid,
            .button-row,
            .two-fields {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endsection
