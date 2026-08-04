@extends('layouts.app', [
    'title' => 'Créneaux des emplois du temps - Lycée Privé Pagnidibsom',
    'active' => 'timetables',
    'pageTitle' => 'Créneaux des emplois du temps',
    'pageSubtitle' => 'Horaires communs utilisés pour toutes les classes de ' . $academicYear->name,
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('timetables.index') }}">Retour aux emplois du temps</a>
@endsection

@section('content')
    @php($periodRows = old('periods', $periods))
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Journée scolaire</h2>
                <p class="muted">Les changements sont répercutés sur les grilles existantes de cette année.</p>
            </div>
            <span class="badge">{{ $academicYear->name }}</span>
        </div>

        <form method="POST" action="{{ route('timetables.periods.update') }}">
            @csrf
            @method('PUT')

            <div class="subject-list-scroll">
                <table class="table" style="min-width:860px">
                    <thead>
                        <tr>
                            <th>Ordre</th>
                            <th>Libellé</th>
                            <th>Début</th>
                            <th>Fin</th>
                            <th>Type</th>
                            <th>État</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($periodRows as $index => $period)
                            <tr>
                                <td>
                                    <input type="hidden" name="periods[{{ $index }}][id]" value="{{ $period['id'] ?? '' }}">
                                    <input type="number" name="periods[{{ $index }}][sort_order]" min="1" max="99" value="{{ $period['sort_order'] }}" required style="width:86px">
                                </td>
                                <td><input name="periods[{{ $index }}][label]" value="{{ $period['label'] }}" required></td>
                                <td><input type="time" name="periods[{{ $index }}][starts_at]" value="{{ $period['starts_at'] ?? '' }}"></td>
                                <td><input type="time" name="periods[{{ $index }}][ends_at]" value="{{ $period['ends_at'] ?? '' }}"></td>
                                <td>
                                    <select name="periods[{{ $index }}][is_break]">
                                        <option value="0" @selected(! $period['is_break'])>Cours</option>
                                        <option value="1" @selected($period['is_break'])>Pause</option>
                                    </select>
                                </td>
                                <td>
                                    <select name="periods[{{ $index }}][is_active]">
                                        <option value="1" @selected($period['is_active'] ?? true)>Actif</option>
                                        <option value="0" @selected(! ($period['is_active'] ?? true))>Inactif</option>
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="page-actions" style="margin-top:12px">
                <button class="btn btn-subtle" type="button" data-add-timetable-period>Ajouter un créneau</button>
            </div>

            <template data-timetable-period-template>
                <tr>
                    <td>
                        <input type="hidden" name="periods[__INDEX__][id]" value="">
                        <input type="number" name="periods[__INDEX__][sort_order]" min="1" max="99" value="__ORDER__" required style="width:86px">
                    </td>
                    <td><input name="periods[__INDEX__][label]" placeholder="Ex : 17h00-18h00" required></td>
                    <td><input type="time" name="periods[__INDEX__][starts_at]"></td>
                    <td><input type="time" name="periods[__INDEX__][ends_at]"></td>
                    <td><select name="periods[__INDEX__][is_break]"><option value="0">Cours</option><option value="1">Pause</option></select></td>
                    <td><select name="periods[__INDEX__][is_active]"><option value="1">Actif</option><option value="0">Inactif</option></select></td>
                </tr>
            </template>

            <div class="notice" style="margin-top:16px">
                Une pause n’exige pas d’heure. Désactiver un créneau le masque sans effacer son historique.
            </div>

            <div class="form-actions" style="margin-top:16px">
                <button class="btn btn-primary" type="submit">Enregistrer les créneaux</button>
            </div>
        </form>
    </section>
@endsection
