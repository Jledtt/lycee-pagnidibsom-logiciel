@extends('layouts.app', [
    'title' => 'Emplois du temps - Lycee Prive Pagnidibsom',
    'active' => 'timetables',
    'pageTitle' => 'Emplois du temps',
    'pageSubtitle' => 'Creation, modification et impression des grilles hebdomadaires',
])

@section('page_actions')
    @if ($timetable)
        @can('timetables.print')
            <a class="btn btn-primary" href="{{ route('timetables.pdf', $timetable) }}" data-download-feedback="Telechargement PDF de l emploi du temps lance.">PDF</a>
        @endcan
        @can('timetables.manage')
            <a class="btn btn-subtle" href="{{ route('timetables.edit', $timetable) }}">Modifier</a>
        @endcan
    @endif
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <section class="panel">
        <div class="panel-head">
            <h2>Classe</h2>
            <span class="badge">{{ $academicYear?->name ?? 'Annee non configuree' }}</span>
        </div>

        @if ($classes->isEmpty())
            <div class="empty">Aucune classe active. Cree d'abord les classes de l'annee scolaire.</div>
        @else
            <form class="searchbar" method="GET" action="{{ route('timetables.index') }}">
                <select name="school_class_id" required>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected($selectedClass?->id === $class->id)>
                            {{ $class->name }}{{ $class->level ? ' - ' . $class->level->name : '' }}
                        </option>
                    @endforeach
                </select>
                <button class="btn btn-subtle" type="submit">Afficher</button>
            </form>

            <div class="page-actions" style="margin-top:12px">
                @can('timetables.manage')
                    @if (! $timetable)
                        <form method="POST" action="{{ route('timetables.store') }}">
                            @csrf
                            <input type="hidden" name="school_class_id" value="{{ $selectedClass?->id }}">
                            <input type="hidden" name="title" value="Emploi du temps">
                            <button class="btn btn-primary" type="submit">Creer une grille vide</button>
                        </form>
                    @endif

                    @if ($selectedClass && $canApplyExample)
                        <form method="POST" action="{{ route('timetables.example') }}">
                            @csrf
                            <input type="hidden" name="school_class_id" value="{{ $selectedClass->id }}">
                            <button class="btn btn-subtle" type="submit">Appliquer l'exemple 2025-2026</button>
                        </form>
                    @endif
                @endcan
            </div>
        @endif
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>{{ $selectedClass ? 'Grille de ' . $selectedClass->name : 'Grille' }}</h2>
            @if ($timetable)
                <span class="badge {{ $timetable->status === 'active' ? '' : 'badge-warning' }}">
                    {{ $timetable->status === 'active' ? 'Active' : ($timetable->status === 'archived' ? 'Archivee' : 'Brouillon') }}
                </span>
            @endif
        </div>

        @if (! $timetable)
            <div class="empty">Aucun emploi du temps pour cette classe. Cree une grille vide ou applique l'exemple Word.</div>
        @else
            <div class="summary-row" style="margin-bottom:16px">
                <div class="stat">
                    <span>Titre</span>
                    <strong style="font-size:18px">{{ $timetable->title }}</strong>
                </div>
                <div class="stat">
                    <span>Professeur principal / equipe</span>
                    <strong style="font-size:16px">{{ $timetable->principal_teacher ?: '-' }}</strong>
                </div>
                <div class="stat">
                    <span>Derniere modification</span>
                    <strong style="font-size:18px">{{ $timetable->updated_at?->format('d/m/Y H:i') }}</strong>
                </div>
            </div>

            <div class="subject-list-scroll">
                <table class="table timetable-table" style="min-width:1080px">
                    <thead>
                        <tr>
                            <th>Horaire</th>
                            @foreach ($days as $dayLabel)
                                <th>{{ $dayLabel }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grid as $row)
                            @if ($row['is_break'])
                                <tr>
                                    <td><strong>{{ $row['period_label'] }}</strong></td>
                                    <td colspan="{{ count($days) }}" style="text-align:center;font-weight:800;color:#85600f;background:#faf3df">{{ $row['period_label'] }}</td>
                                </tr>
                            @else
                                <tr>
                                    <td><strong>{{ $row['period_label'] }}</strong></td>
                                    @foreach (array_keys($days) as $dayKey)
                                        @php($entry = $row['days'][$dayKey] ?? null)
                                        <td>
                                            <strong>{{ $entry?->subject_name ?: '-' }}</strong>
                                            @if ($entry?->teacher_name)
                                                <br><span style="color:var(--muted)">{{ $entry->teacher_name }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($timetable->notes)
                <div class="notice" style="margin-top:16px">{{ $timetable->notes }}</div>
            @endif
        @endif
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Emplois du temps crees</h2>
            <span class="badge">{{ $timetables->count() }} grille(s)</span>
        </div>

        @if ($timetables->isEmpty())
            <div class="empty">Aucune grille enregistree pour le moment.</div>
        @else
            <div class="subject-list-scroll">
                <table class="table" style="min-width:760px">
                    <thead>
                        <tr>
                            <th>Classe</th>
                            <th>Titre</th>
                            <th>Statut</th>
                            <th>Mis a jour</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($timetables as $item)
                            <tr>
                                <td><strong>{{ $item->schoolClass?->name }}</strong></td>
                                <td>{{ $item->title }}</td>
                                <td><span class="badge">{{ $item->status }}</span></td>
                                <td>{{ $item->updated_at?->format('d/m/Y H:i') }}</td>
                                <td>
                                    <div class="page-actions">
                                        <a class="btn btn-subtle" href="{{ route('timetables.index', ['school_class_id' => $item->school_class_id]) }}">Voir</a>
                                        @can('timetables.print')
                                            <a class="btn btn-subtle" href="{{ route('timetables.pdf', $item) }}" data-download-feedback="Telechargement PDF de l emploi du temps lance.">PDF</a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
