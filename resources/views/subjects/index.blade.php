@extends('layouts.app', [
    'title' => 'Matieres et coefficients - Lycee Prive Pagnidibsom',
    'active' => 'subjects',
    'pageTitle' => 'Matieres et coefficients',
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
            <h2>Classe de travail</h2>
            @if ($selectedClass)
                <span class="badge">{{ $selectedClass->name }}</span>
            @endif
        </div>

        @if ($classes->isEmpty())
            <div class="empty">Aucune classe active pour l'annee scolaire.</div>
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
                <span>Matieres actives</span>
                <strong>{{ $classSubjects->where('is_active', true)->count() }}</strong>
            </div>
            <div class="stat">
                <span>Total coefficients</span>
                <strong>{{ number_format($classSubjects->where('is_active', true)->sum('coefficient'), 2, ',', ' ') }}</strong>
            </div>
            <div class="stat">
                <span>Matieres globales</span>
                <strong>{{ $subjects->count() }}</strong>
            </div>
            <div class="stat">
                <span>Proposees pour la classe</span>
                <strong>{{ count($suggestedSubjects) }}</strong>
            </div>
            <div class="stat">
                <span>Annee</span>
                <strong>{{ $academicYear?->name ?? '-' }}</strong>
            </div>
        </section>

        <section class="grid two-col">
            <div class="panel">
                <div class="panel-head">
                    <h2>Matieres de {{ $selectedClass->name }}</h2>
                    <form method="POST" action="{{ route('subjects.defaults') }}">
                        @csrf
                        <input type="hidden" name="school_class_id" value="{{ $selectedClass->id }}">
                        <button class="btn btn-subtle" type="submit">Appliquer la base proposee</button>
                    </form>
                </div>

                @if ($classSubjects->isEmpty())
                    <div class="empty">Aucune matiere affectee a cette classe.</div>
                @else
                    <div class="ledger-list">
                        @foreach ($classSubjects as $classSubject)
                            <div class="ledger-item">
                                <form method="POST" action="{{ route('subjects.class-subjects.update', $classSubject) }}" class="ledger-summary" style="grid-template-columns:minmax(220px,1.5fr) minmax(120px,.6fr) minmax(140px,.8fr) minmax(210px,1fr)">
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
                @endif

                <div class="panel" style="margin-top:16px">
                    <div class="panel-head">
                        <h2>Affecter une matiere</h2>
                    </div>
                    @if ($availableSubjects->isEmpty())
                        <div class="empty">Toutes les matieres actives sont deja affectees a cette classe.</div>
                    @else
                        <form class="form-grid" method="POST" action="{{ route('subjects.class-subjects.store') }}">
                            @csrf
                            <input type="hidden" name="school_class_id" value="{{ $selectedClass->id }}">

                            <div class="field">
                                <label>Matiere</label>
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

                            <div class="form-actions wide">
                                <button class="btn btn-primary" type="submit">Ajouter a la classe</button>
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
                        <label>Nouvelle matiere</label>
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
                        <button class="btn btn-primary" type="submit">Creer</button>
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
