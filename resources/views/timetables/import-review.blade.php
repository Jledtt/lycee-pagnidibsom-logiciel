@extends('layouts.app', [
    'title' => 'Révision des disponibilités - Lycée Privé Pagnidibsom',
    'active' => 'timetables',
    'pageTitle' => 'Réviser les disponibilités importées',
    'pageSubtitle' => 'Contrôle humain obligatoire avant toute modification des fiches professeurs',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('timetables.planning') }}">Retour à la planification</a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <section class="panel import-review-overview">
        <div class="panel-head">
            <div>
                <span class="eyebrow">{{ strtoupper($preview['source_type'] ?? 'fichier') }} · {{ $academicYear->name }}</span>
                <h2>{{ $preview['filename'] }}</h2>
                <p class="panel-subtitle">Le document original n’est pas conservé. Cet aperçu expire automatiquement après 30 minutes.</p>
            </div>
            <span class="badge {{ ($preview['summary']['invalid'] ?? 0) > 0 ? 'badge-warning' : '' }}">
                {{ ($preview['summary']['invalid'] ?? 0) > 0 ? 'Révision nécessaire' : 'Prêt à importer' }}
            </span>
        </div>

        <div class="import-review-metrics">
            <div><span>Lignes détectées</span><strong>{{ $preview['summary']['total'] }}</strong></div>
            <div><span>Conservées</span><strong>{{ $preview['summary']['selected'] }}</strong></div>
            <div><span>Valides</span><strong>{{ $preview['summary']['valid'] }}</strong></div>
            <div><span>À corriger</span><strong>{{ $preview['summary']['invalid'] }}</strong></div>
            <div><span>Ignorées</span><strong>{{ $preview['summary']['ignored'] }}</strong></div>
        </div>

        @foreach ($preview['warnings'] ?? [] as $warning)
            <div class="planning-diagnostics planning-diagnostics--warning">{{ $warning }}</div>
        @endforeach
    </section>

    <form method="POST" action="{{ route('timetables.planning.import.revise') }}" class="panel import-review-form">
        @csrf
        @method('PATCH')

        <div class="panel-head">
            <div>
                <h2>Correspondances détectées</h2>
                <p class="panel-subtitle">Décoche une ligne inutile ou corrige les champs signalés. Rien n’est encore enregistré.</p>
            </div>
            <button class="btn btn-subtle" type="submit">Revalider les corrections</button>
        </div>

        <div class="subject-list-scroll import-review-scroll">
            <table class="table import-review-table">
                <thead>
                    <tr>
                        <th>Garder</th>
                        <th>Professeur</th>
                        <th>Jour</th>
                        <th>Début</th>
                        <th>Fin</th>
                        <th>Statut</th>
                        <th>Note</th>
                        <th>Contrôle</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($preview['rows'] as $index => $row)
                        <tr class="{{ $row['selected'] && $row['status'] === 'invalid' ? 'import-review-row--invalid' : '' }}">
                            <td>
                                <input type="hidden" name="rows[{{ $index }}][selected]" value="0">
                                <input
                                    type="checkbox"
                                    name="rows[{{ $index }}][selected]"
                                    value="1"
                                    aria-label="Conserver la ligne {{ $row['line'] }}"
                                    @checked($row['selected'])
                                >
                                <input type="hidden" name="rows[{{ $index }}][line]" value="{{ $row['line'] }}">
                                <input type="hidden" name="rows[{{ $index }}][raw]" value="{{ $row['raw'] ?? '' }}">
                            </td>
                            <td>
                                <select name="rows[{{ $index }}][teacher_id]" aria-label="Professeur ligne {{ $row['line'] }}">
                                    <option value="">Choisir</option>
                                    @foreach ($teachers as $teacher)
                                        <option value="{{ $teacher->id }}" @selected((int) ($row['input']['teacher_id'] ?? 0) === $teacher->id)>{{ $teacher->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="rows[{{ $index }}][day]" aria-label="Jour ligne {{ $row['line'] }}">
                                    <option value="">Choisir</option>
                                    @foreach ($days as $dayKey => $dayLabel)
                                        <option value="{{ $dayKey }}" @selected(($row['input']['day'] ?? '') === $dayKey)>{{ $dayLabel }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="time" name="rows[{{ $index }}][starts_at]" value="{{ $row['input']['starts_at'] ?? '' }}" aria-label="Début ligne {{ $row['line'] }}"></td>
                            <td><input type="time" name="rows[{{ $index }}][ends_at]" value="{{ $row['input']['ends_at'] ?? '' }}" aria-label="Fin ligne {{ $row['line'] }}"></td>
                            <td>
                                <select name="rows[{{ $index }}][availability_status]" aria-label="Statut ligne {{ $row['line'] }}">
                                    <option value="">Choisir</option>
                                    @foreach ($availabilityLabels as $statusKey => $statusLabel)
                                        <option value="{{ $statusKey }}" @selected(($row['input']['availability_status'] ?? '') === $statusKey)>{{ $statusLabel }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="text" name="rows[{{ $index }}][note]" value="{{ $row['input']['note'] ?? '' }}" maxlength="500" aria-label="Note ligne {{ $row['line'] }}"></td>
                            <td class="import-review-control">
                                @if (! $row['selected'])
                                    <span class="badge">Ignorée</span>
                                @elseif ($row['status'] === 'valid')
                                    <span class="badge">Valide</span>
                                @else
                                    <span class="badge badge-warning">À corriger</span>
                                    <ul>@foreach ($row['errors'] as $message)<li>{{ $message }}</li>@endforeach</ul>
                                @endif
                                @if (filled($row['raw'] ?? null))
                                    <details>
                                        <summary>Texte détecté</summary>
                                        <small>{{ $row['raw'] }}</small>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty">Aucune disponibilité exploitable n’a été détectée dans ce document.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="import-review-footer">
            <button class="btn btn-subtle" type="submit">Revalider les corrections</button>
        </div>
    </form>

    <section class="panel import-review-commit">
        <div>
            <h2>Confirmer l’import</h2>
            <p class="panel-subtitle">Les disponibilités actuelles des {{ $preview['summary']['teachers'] }} professeur(s) concerné(s) seront remplacées pour {{ $academicYear->name }}.</p>
        </div>
        <div class="page-actions">
            <form method="POST" action="{{ route('timetables.planning.import.clear') }}">
                @csrf
                @method('DELETE')
                <button class="btn btn-subtle" type="submit">Annuler cet import</button>
            </form>
            <form
                method="POST"
                action="{{ route('timetables.planning.import.apply') }}"
                data-confirm
                data-confirm-title="Importer les disponibilités"
                data-confirm-object="{{ $preview['summary']['teachers'] }} professeur(s) — {{ $academicYear->name }}"
                data-confirm-message="Les fiches actuelles des professeurs concernés seront remplacées par les lignes validées de cet aperçu."
                data-confirm-action="Confirmer l’import"
                data-confirm-tone="primary"
            >
                @csrf
                <button class="btn btn-primary" type="submit" @disabled(($preview['summary']['valid'] ?? 0) < 1 || ($preview['summary']['invalid'] ?? 0) > 0)>Importer les lignes validées</button>
            </form>
        </div>
    </section>
@endsection
