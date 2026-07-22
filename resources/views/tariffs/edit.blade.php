@extends('layouts.app', [
    'title' => 'Modifier tarifs - Lycée Privé Pagnidibsom',
    'active' => 'tariffs',
    'pageTitle' => 'Tarifs ' . $schoolClass->name,
    'pageSubtitle' => ($schoolClass->level?->name ?? 'Niveau') . ' - ' . ($academicYear?->name ?? 'Année active'),
])

@section('page_actions')
    <form method="POST" action="{{ route('tariffs.class-defaults', $schoolClass) }}" onsubmit="return confirm('Appliquer les tarifs officiels pour cette classe ? Les lignes existantes avec les mêmes périodes seront mises à jour.')">
        @csrf
        <button class="btn btn-primary" type="submit">Appliquer les tarifs officiels</button>
    </form>
    <a class="btn btn-subtle" href="{{ route('tariffs.index') }}">Retour</a>
@endsection

@section('content')
    <form method="POST" action="{{ route('tariffs.update', $schoolClass) }}">
        @csrf
        @method('PUT')

        <section class="panel">
            <div class="panel-head">
                <h2>Lignes de tarifs</h2>
                <span class="badge">{{ $schedules->count() }} ligne(s)</span>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Type de frais</th>
                        <th>Période</th>
                        <th>Montant</th>
                        <th>Echeance</th>
                        <th>Supprimer</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($schedules as $index => $schedule)
                        <tr>
                            <td>
                                <input type="hidden" name="lines[{{ $index }}][id]" value="{{ $schedule->id }}">
                                <select name="lines[{{ $index }}][fee_type_id]" required>
                                    @foreach ($feeTypes as $feeType)
                                        <option value="{{ $feeType->id }}" @selected($schedule->fee_type_id === $feeType->id)>{{ $feeType->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input name="lines[{{ $index }}][period]" value="{{ $schedule->period }}" placeholder="Ex: Novembre 2026"></td>
                            <td><input name="lines[{{ $index }}][amount]" type="number" min="0" step="1" value="{{ (int) $schedule->amount }}"></td>
                            <td><input name="lines[{{ $index }}][due_date]" type="date" value="{{ $schedule->due_date?->format('Y-m-d') }}"></td>
                            <td><label class="check"><input type="checkbox" name="lines[{{ $index }}][delete]" value="1"> Oui</label></td>
                        </tr>
                    @endforeach

                    @for ($i = 0; $i < 4; $i++)
                        @php($index = $schedules->count() + $i)
                        <tr>
                            <td>
                                <select name="lines[{{ $index }}][fee_type_id]">
                                    <option value="">Ajouter un type</option>
                                    @foreach ($feeTypes as $feeType)
                                        <option value="{{ $feeType->id }}">{{ $feeType->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input name="lines[{{ $index }}][period]" placeholder="Ex: Frais annexes"></td>
                            <td><input name="lines[{{ $index }}][amount]" type="number" min="0" step="1" placeholder="Montant"></td>
                            <td><input name="lines[{{ $index }}][due_date]" type="date"></td>
                            <td></td>
                        </tr>
                    @endfor
                </tbody>
            </table>

            @error('lines') <p class="error">{{ $message }}</p> @enderror

            <div class="form-grid" style="margin-top:16px">
                <div class="field wide">
                    <label for="new_fee_type_name">Créer un nouveau type de frais</label>
                    <input id="new_fee_type_name" name="new_fee_type_name" placeholder="Ex: Assurance scolaire">
                    @error('new_fee_type_name') <small class="error">{{ $message }}</small> @enderror
                </div>
            </div>

            <div class="form-actions">
                <a class="btn btn-subtle" href="{{ route('tariffs.index') }}">Annuler</a>
                <button class="btn btn-primary" type="submit">Enregistrer les tarifs</button>
            </div>
        </section>
    </form>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Conseil</h2>
        </div>
        <div class="empty">Pour modifier les tarifs plus tard, reviens ici, change les montants, puis enregistre. Les restes à payer et les non-redevances utiliseront automatiquement les nouveaux montants.</div>
    </section>
@endsection
