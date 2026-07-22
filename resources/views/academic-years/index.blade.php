@extends('layouts.app', [
    'title' => 'Années scolaires - Lycée Privé Pagnidibsom',
    'active' => 'academic-years',
    'pageTitle' => 'Années scolaires',
    'pageSubtitle' => 'Gestion des années, trimestres et clotures',
])

@section('content')
    @if ($errors->any())
        <div class="error">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="grid two-col">
        <div class="panel">
            <div class="panel-head">
                <h2>Nouvelle année</h2>
            </div>

            <form class="form-grid" method="POST" action="{{ route('academic-years.store') }}">
                @csrf
                <div class="field wide">
                    <label>Nom</label>
                    <input name="name" placeholder="2027-2028" required>
                </div>
                <div class="field">
                    <label>Date debut</label>
                    <input type="date" name="starts_at" required>
                </div>
                <div class="field">
                    <label>Date fin</label>
                    <input type="date" name="ends_at" required>
                </div>
                <div class="field wide">
                    <label class="check">
                        <input type="checkbox" name="create_default_terms" value="1" checked>
                        Créer automatiquement les 3 trimestres
                    </label>
                </div>
                <div class="form-actions wide">
                    <button class="btn btn-primary" type="submit">Créer l année</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Resume</h2>
            </div>
            <div class="summary-row">
                <div class="detail-item">
                    <span>Années</span>
                    <strong>{{ $years->count() }}</strong>
                </div>
                <div class="detail-item">
                    <span>Active</span>
                    <strong>{{ $years->firstWhere('is_active', true)?->name ?? '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Périodes</span>
                    <strong>{{ $years->sum(fn ($year) => $year->terms->count()) }}</strong>
                </div>
            </div>
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Années configurées</h2>
            <span class="badge">{{ $years->count() }} ligne(s)</span>
        </div>

        @if ($years->isEmpty())
            <div class="empty">Aucune année scolaire configurée.</div>
        @else
            <div class="ledger-list">
                @foreach ($years as $year)
                    <details class="ledger-item" @if($year->is_active) open @endif>
                        <summary class="ledger-summary" style="grid-template-columns:minmax(220px,1fr) repeat(3,minmax(120px,.55fr)) minmax(180px,.7fr)">
                            <div class="ledger-person">
                                <strong>{{ $year->name }}</strong>
                                <span>{{ $year->starts_at->format('d/m/Y') }} - {{ $year->ends_at->format('d/m/Y') }}</span>
                            </div>
                            <div class="ledger-metric">
                                <strong>{{ $year->terms->count() }}</strong>
                                <span>Périodes</span>
                            </div>
                            <div class="ledger-metric">
                                <strong>{{ $year->classes_count }}</strong>
                                <span>Classes</span>
                            </div>
                            <div class="ledger-metric">
                                <strong>{{ $year->enrollments_count }}</strong>
                                <span>Inscriptions</span>
                            </div>
                            <span class="badge {{ $year->is_active ? '' : 'badge-warning' }}">
                                {{ $year->is_active ? 'Active' : ucfirst($year->status) }}
                            </span>
                        </summary>

                        <div class="ledger-detail">
                            <div class="ledger-detail-head">
                                <h3>Paramètres de {{ $year->name }}</h3>
                                @unless ($year->is_active)
                                    <form method="POST" action="{{ route('academic-years.activate', $year) }}">
                                        @csrf
                                        @method('PUT')
                                        <button class="btn btn-primary" type="submit">Activer</button>
                                    </form>
                                @endunless
                            </div>

                            <form class="form-grid" method="POST" action="{{ route('academic-years.update', $year) }}">
                                @csrf
                                @method('PUT')
                                <div class="field">
                                    <label>Nom</label>
                                    <input name="name" value="{{ $year->name }}" required>
                                </div>
                                <div class="field">
                                    <label>Statut</label>
                                    <select name="status">
                                        <option value="planned" @selected($year->status === 'planned')>Planifiee</option>
                                        <option value="active" @selected($year->status === 'active')>Active</option>
                                        <option value="closed" @selected($year->status === 'closed')>Fermee</option>
                                        <option value="archived" @selected($year->status === 'archived')>Archivee</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label>Date debut</label>
                                    <input type="date" name="starts_at" value="{{ $year->starts_at->toDateString() }}" required>
                                </div>
                                <div class="field">
                                    <label>Date fin</label>
                                    <input type="date" name="ends_at" value="{{ $year->ends_at->toDateString() }}" required>
                                </div>
                                <div class="form-actions wide">
                                    <button class="btn btn-subtle" type="submit">Enregistrer l année</button>
                                </div>
                            </form>

                            <div class="panel" style="margin-top:16px">
                                <div class="panel-head">
                                    <h2>Périodes</h2>
                                    <span class="badge">{{ $year->terms->count() }} ligne(s)</span>
                                </div>

                                <div class="subject-list-scroll">
                                    <table class="table" style="min-width:880px">
                                        <thead>
                                            <tr>
                                                <th>Nom</th>
                                                <th>Type</th>
                                                <th>Position</th>
                                                <th>Dates</th>
                                                <th>Cloture</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($year->terms as $term)
                                                <tr>
                                                    <form id="term-form-{{ $term->id }}" method="POST" action="{{ route('academic-years.terms.update', $term) }}">
                                                        @csrf
                                                        @method('PUT')
                                                    </form>
                                                    <td><input form="term-form-{{ $term->id }}" name="name" value="{{ $term->name }}" required></td>
                                                    <td>
                                                        <select form="term-form-{{ $term->id }}" name="type">
                                                            <option value="trimestre" @selected($term->type === 'trimestre')>Trimestre</option>
                                                            <option value="semestre" @selected($term->type === 'semestre')>Semestre</option>
                                                            <option value="autre" @selected($term->type === 'autre')>Autre</option>
                                                        </select>
                                                    </td>
                                                    <td><input form="term-form-{{ $term->id }}" type="number" name="position" min="1" max="12" value="{{ $term->position }}" required></td>
                                                    <td>
                                                        <div class="inline-form">
                                                            <input form="term-form-{{ $term->id }}" type="date" name="starts_at" value="{{ $term->starts_at?->toDateString() }}">
                                                            <input form="term-form-{{ $term->id }}" type="date" name="ends_at" value="{{ $term->ends_at?->toDateString() }}">
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <select form="term-form-{{ $term->id }}" name="is_closed">
                                                            <option value="0" @selected(! $term->is_closed)>Ouverte</option>
                                                            <option value="1" @selected($term->is_closed)>Fermee</option>
                                                        </select>
                                                    </td>
                                                    <td><button class="btn btn-subtle" type="submit" form="term-form-{{ $term->id }}">Sauvegarder</button></td>
                                                </tr>
                                            @endforeach

                                            <tr>
                                                <form id="new-term-{{ $year->id }}" method="POST" action="{{ route('academic-years.terms.store') }}">
                                                    @csrf
                                                    <input type="hidden" name="academic_year_id" value="{{ $year->id }}">
                                                </form>
                                                <td><input form="new-term-{{ $year->id }}" name="name" placeholder="Trimestre 4"></td>
                                                <td>
                                                    <select form="new-term-{{ $year->id }}" name="type">
                                                        <option value="trimestre">Trimestre</option>
                                                        <option value="semestre">Semestre</option>
                                                        <option value="autre">Autre</option>
                                                    </select>
                                                </td>
                                                <td><input form="new-term-{{ $year->id }}" type="number" name="position" min="1" max="12" value="{{ $year->terms->count() + 1 }}"></td>
                                                <td>
                                                    <div class="inline-form">
                                                        <input form="new-term-{{ $year->id }}" type="date" name="starts_at">
                                                        <input form="new-term-{{ $year->id }}" type="date" name="ends_at">
                                                    </div>
                                                </td>
                                                <td><span class="badge">Ouverte</span></td>
                                                <td><button class="btn btn-primary" type="submit" form="new-term-{{ $year->id }}">Ajouter</button></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>
        @endif
    </section>
@endsection
