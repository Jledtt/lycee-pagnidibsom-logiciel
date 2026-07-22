@extends('layouts.app', [
    'title' => 'Numérotation - Lycée Privé Pagnidibsom',
    'active' => 'settings',
    'pageTitle' => 'Paramètres de numérotation',
    'pageSubtitle' => 'Formats des matricules, reçus et certificats officiels',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('settings.edit') }}">Paramètres école</a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <section class="panel">
        <div class="panel-head">
            <h2>Formats disponibles</h2>
            <span class="badge">{{ $academicYear?->name ?? 'Année active' }}</span>
        </div>

        <div class="grid modules">
            <div class="module">
                <strong>{PREFIX}</strong>
                <span>Préfixe configuré</span>
            </div>
            <div class="module">
                <strong>{YEAR}</strong>
                <span>Année scolaire en 4 chiffres</span>
            </div>
            <div class="module">
                <strong>{DATE}</strong>
                <span>Date du jour AAAAMMJJ</span>
            </div>
            <div class="module">
                <strong>{NUMBER}</strong>
                <span>Numéro séquentiel obligatoire</span>
            </div>
        </div>
    </section>

    <form method="POST" action="{{ route('settings.numbering.update') }}" style="margin-top:16px">
        @csrf
        @method('PUT')

        <section class="panel">
            <div class="panel-head">
                <h2>Sequences</h2>
                <button class="btn btn-primary" type="submit">Enregistrer</button>
            </div>

            <div class="subject-list-scroll">
                <table class="table" style="min-width:1040px">
                    <thead>
                        <tr>
                            <th>Document</th>
                            <th>Préfixe</th>
                            <th>Format</th>
                            <th>Chiffres</th>
                            <th>Prochain numéro</th>
                            <th>Statut</th>
                            <th>Aperçu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($settings as $index => $setting)
                            <tr>
                                <td>
                                    <strong>{{ $setting->label }}</strong><br>
                                    <span class="badge">{{ $setting->type }}</span>
                                    <input type="hidden" name="settings[{{ $index }}][id]" value="{{ $setting->id }}">
                                </td>
                                <td><input name="settings[{{ $index }}][prefix]" value="{{ old("settings.$index.prefix", $setting->prefix) }}"></td>
                                <td><input name="settings[{{ $index }}][format]" value="{{ old("settings.$index.format", $setting->format) }}" required></td>
                                <td><input name="settings[{{ $index }}][padding]" type="number" min="1" max="10" value="{{ old("settings.$index.padding", $setting->padding) }}" required></td>
                                <td><input name="settings[{{ $index }}][next_number]" type="number" min="1" max="9999999" value="{{ old("settings.$index.next_number", $setting->next_number) }}" required></td>
                                <td>
                                    <select name="settings[{{ $index }}][status]" required>
                                        <option value="active" @selected(old("settings.$index.status", $setting->status) === 'active')>Active</option>
                                        <option value="inactive" @selected(old("settings.$index.status", $setting->status) === 'inactive')>Inactive</option>
                                    </select>
                                </td>
                                <td><span class="badge">{{ $previews[$setting->id] ?? '-' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </form>
@endsection
