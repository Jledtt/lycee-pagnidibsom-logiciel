@extends('layouts.app', [
    'title' => 'Matières et coefficients - Lycée Privé Pagnidibsom',
    'active' => 'subjects',
    'pageTitle' => 'Matières et coefficients',
    'pageSubtitle' => 'Parametrage par classe pour les notes et bulletins',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('dashboard') }}">Tableau de bord</a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Importer les matières depuis un PDF</h2>
                <p class="muted" style="margin:6px 0 0">Le fichier est d’abord analysé. Aucune donnée n’est modifiée avant ta confirmation.</p>
            </div>
            @if ($pdfImportPreview)
                <span class="badge">{{ $pdfImportPreview['source_name'] }}</span>
            @endif
        </div>

        @if (! $pdfImportPreview)
            <form class="form-grid" method="POST" action="{{ route('subjects.import-pdf.preview') }}" enctype="multipart/form-data">
                @csrf
                <div class="field wide">
                    <label for="subjects-pdf">Document PDF des matières par classe</label>
                    <input id="subjects-pdf" type="file" name="subjects_pdf" accept="application/pdf,.pdf" required>
                    <small>PDF texte uniquement, 5 Mo maximum. Les classes doivent déjà exister dans l’année scolaire active.</small>
                </div>
                <div class="form-actions wide">
                    <button class="btn btn-primary" type="submit">Analyser le PDF</button>
                </div>
            </form>
        @else
            <div class="page-actions" style="justify-content:flex-start;margin-bottom:14px">
                <span class="badge">{{ $pdfImportPreview['summary']['classes'] }} classe(s) reconnue(s)</span>
                <span class="badge">{{ $pdfImportPreview['summary']['valid'] }} matière(s) valide(s)</span>
                @if ($pdfImportPreview['summary']['invalid'] > 0)
                    <span class="badge badge-warning">{{ $pdfImportPreview['summary']['invalid'] }} ligne(s) ignorée(s)</span>
                @endif
                <span class="badge">{{ $pdfImportPreview['academic_year_name'] }}</span>
            </div>

            @foreach ($pdfImportPreview['warnings'] as $warning)
                <div class="error" style="margin-bottom:12px">{{ $warning }}</div>
            @endforeach

            <div style="overflow-x:auto">
                <table class="table" style="min-width:760px">
                    <thead>
                        <tr>
                            <th>Classe</th>
                            <th>Matières détectées</th>
                            <th>Résultat prévu</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pdfImportPreview['groups'] as $group)
                            <tr>
                                <td><strong>{{ $group['class_name'] }}</strong></td>
                                <td>{{ implode(', ', $group['subjects']) }}</td>
                                <td>
                                    {{ $group['changes'] === []
                                        ? 'Toutes les matières sont déjà enregistrées.'
                                        : implode(' · ', $group['changes']) }}
                                </td>
                                <td>
                                    <span class="badge {{ $group['valid'] ? '' : 'badge-warning' }}">
                                        {{ $group['valid'] ? 'Prête' : 'Classe introuvable' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="page-actions" style="justify-content:flex-start;margin-top:16px">
                <form method="POST" action="{{ route('subjects.import-pdf.store') }}"
                    data-confirm
                    data-confirm-title="Importer les matières"
                    data-confirm-object="{{ $pdfImportPreview['summary']['valid'] }} matière(s) — {{ $pdfImportPreview['academic_year_name'] }}"
                    data-confirm-message="Les matières reconnues seront ajoutées ou réactivées. Les coefficients, horaires et professeurs déjà enregistrés seront conservés."
                    data-confirm-action="Confirmer l’import"
                    data-confirm-tone="primary">
                    @csrf
                    <button class="btn btn-primary" type="submit" @disabled($pdfImportPreview['summary']['valid'] < 1)>Confirmer l’import</button>
                </form>
                <form method="POST" action="{{ route('subjects.import-pdf.cancel') }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-subtle" type="submit">Annuler l’aperçu</button>
                </form>
            </div>
        @endif
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Classe de travail</h2>
            @if ($selectedClass)
                <span class="badge">{{ $selectedClass->name }}</span>
            @endif
        </div>

        @if ($classes->isEmpty())
            <div class="empty">Aucune classe active pour l’année scolaire.</div>
        @else
            <form class="searchbar" method="GET" action="{{ route('subjects.index') }}">
                <select name="school_class_id">
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected($selectedClass?->id === $class->id)>
                            {{ $class->name }}{{ $class->level ? ' - ' . $class->level->name : '' }}
                        </option>
                    @endforeach
                </select>
                <button class="btn btn-subtle" type="submit">Afficher</button>
            </form>
        @endif
    </section>

    @if ($selectedClass)
        <section class="grid stats" style="margin-top:16px">
            <div class="stat">
                <span>Matières actives</span>
                <strong>{{ $classSubjects->where('is_active', true)->count() }}</strong>
            </div>
            <div class="stat">
                <span>Total coefficients</span>
                <strong>{{ number_format($classSubjects->where('is_active', true)->sum('coefficient'), 2, ',', ' ') }}</strong>
            </div>
            <div class="stat">
                <span>Heures par semaine</span>
                <strong>{{ number_format($classSubjects->where('is_active', true)->sum('weekly_hours'), 2, ',', ' ') }}</strong>
            </div>
            <div class="stat">
                <span>Matières globales</span>
                <strong>{{ $subjects->count() }}</strong>
            </div>
            <div class="stat">
                <span>Proposées pour la classe</span>
                <strong>{{ count($suggestedSubjects) }}</strong>
            </div>
            <div class="stat">
                <span>Année</span>
                <strong>{{ $academicYear?->name ?? '-' }}</strong>
            </div>
        </section>

        <section class="grid two-col">
            <div class="panel">
                <div class="panel-head">
                    <h2>Matières de {{ $selectedClass->name }}</h2>
                    <form method="POST" action="{{ route('subjects.defaults') }}">
                        @csrf
                        <input type="hidden" name="school_class_id" value="{{ $selectedClass->id }}">
                        <button class="btn btn-subtle" type="submit">Appliquer la base proposee</button>
                    </form>
                </div>

                @if ($classSubjects->isEmpty())
                    <div class="empty">Aucune matière affectée à cette classe.</div>
                @else
                    <div class="subject-list-scroll">
                        <div class="subject-list-inner ledger-list">
                            @foreach ($classSubjects as $classSubject)
                                <div class="ledger-item">
                                    <form method="POST" action="{{ route('subjects.class-subjects.update', $classSubject) }}" class="ledger-summary" style="grid-template-columns:minmax(220px,1.5fr) minmax(130px,.55fr) minmax(150px,.6fr) minmax(150px,.6fr) minmax(240px,1fr)">
                                        @csrf
                                        @method('PUT')

                                        <div class="ledger-person">
                                            <strong>{{ $classSubject->subject->name }}</strong>
                                            <span>{{ $classSubject->subject->code ?? 'Sans code' }}</span>
                                        </div>

                                        <div class="field" style="margin-bottom:0">
                                            <label>Coefficient</label>
                                            <input type="number" name="coefficient" min="0" max="99.99" step="0.25" value="{{ old('coefficient', $classSubject->coefficient) }}">
                                        </div>

                                        <div class="field" style="margin-bottom:0">
                                            <label>Heures / semaine</label>
                                            <input type="number" name="weekly_hours" min="0" max="60" step="0.25" value="{{ old('weekly_hours', $classSubject->weekly_hours) }}" placeholder="À définir">
                                        </div>

                                        <div class="field" style="margin-bottom:0">
                                            <label>Statut</label>
                                            <select name="is_active">
                                                <option value="1" @selected($classSubject->is_active)>Active</option>
                                                <option value="0" @selected(! $classSubject->is_active)>Inactive</option>
                                            </select>
                                        </div>

                                        <div class="page-actions" style="justify-content:flex-end">
                                            <button class="btn btn-primary" type="submit">Enregistrer</button>
                                            <button class="btn btn-danger" type="submit" form="delete-class-subject-{{ $classSubject->id }}">Retirer</button>
                                        </div>
                                    </form>
                                    <form id="delete-class-subject-{{ $classSubject->id }}" method="POST" action="{{ route('subjects.class-subjects.destroy', $classSubject) }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="panel" style="margin-top:16px">
                    <div class="panel-head">
                        <h2>Affecter une matière</h2>
                    </div>
                    @if ($availableSubjects->isEmpty())
                        <div class="empty">Toutes les matières actives sont déjà affectées à cette classe.</div>
                    @else
                        <form class="form-grid" method="POST" action="{{ route('subjects.class-subjects.store') }}">
                            @csrf
                            <input type="hidden" name="school_class_id" value="{{ $selectedClass->id }}">

                            <div class="field">
                                <label>Matière</label>
                                <select name="subject_id" required>
                                    @foreach ($availableSubjects as $subject)
                                        <option value="{{ $subject->id }}">{{ $subject->name }}{{ $subject->code ? ' (' . $subject->code . ')' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field">
                                <label>Coefficient</label>
                                <input type="number" name="coefficient" min="0" max="99.99" step="0.25" value="1" required>
                            </div>

                            <div class="field">
                                <label>Heures par semaine</label>
                                <input type="number" name="weekly_hours" min="0.25" max="60" step="0.25" placeholder="Ex : 4">
                            </div>

                            <div class="form-actions wide">
                                <button class="btn btn-primary" type="submit">Ajouter à la classe</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2>Liste globale</h2>
                </div>

                <form class="form-grid" method="POST" action="{{ route('subjects.store') }}">
                    @csrf
                    <input type="hidden" name="school_class_id" value="{{ $selectedClass->id }}">
                    <div class="field">
                        <label>Nouvelle matière</label>
                        <input name="name" placeholder="Ex: Espagnol" required>
                    </div>
                    <div class="field">
                        <label>Code</label>
                        <input name="code" placeholder="ESP">
                    </div>
                    <div class="field">
                        <label>Statut</label>
                        <select name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Créer</button>
                    </div>
                </form>

                <div class="ledger-list" style="margin-top:16px">
                    @foreach ($subjects as $subject)
                        <details class="ledger-item">
                            <summary class="ledger-summary" style="grid-template-columns:minmax(180px,1fr) minmax(88px,.4fr) minmax(96px,.45fr)">
                                <div class="ledger-person">
                                    <strong>{{ $subject->name }}</strong>
                                    <span>{{ $subject->classSubjects_count ?? '' }}</span>
                                </div>
                                <span class="badge">{{ $subject->code ?? '-' }}</span>
                                <span class="badge {{ $subject->status === 'active' ? '' : 'badge-warning' }}">{{ $subject->status === 'active' ? 'Active' : 'Inactive' }}</span>
                            </summary>
                            <div class="ledger-detail">
                                <form class="form-grid" method="POST" action="{{ route('subjects.update', $subject) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="school_class_id" value="{{ $selectedClass->id }}">
                                    <div class="field">
                                        <label>Nom</label>
                                        <input name="name" value="{{ $subject->name }}" required>
                                    </div>
                                    <div class="field">
                                        <label>Code</label>
                                        <input name="code" value="{{ $subject->code }}">
                                    </div>
                                    <div class="field">
                                        <label>Statut</label>
                                        <select name="status">
                                            <option value="active" @selected($subject->status === 'active')>Active</option>
                                            <option value="inactive" @selected($subject->status === 'inactive')>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="form-actions">
                                        <button class="btn btn-primary" type="submit">Modifier</button>
                                    </div>
                                </form>
                            </div>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
