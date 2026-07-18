@extends('layouts.app', [
    'title' => 'Parametres - ' . ($settings->school_name ?? 'Lycee Prive Pagnidibsom'),
    'active' => 'settings',
    'pageTitle' => 'Parametres de l ecole',
    'pageSubtitle' => 'Informations officielles utilisees dans les fiches, recus et certificats',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('settings.required-documents.index') }}">Pieces obligatoires</a>
@endsection

@section('content')
    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <section class="grid two-col">
            <div class="panel">
                <div class="panel-head">
                    <h2>Identite</h2>
                </div>

                <div class="form-grid">
                    <div class="field wide">
                        <label for="school_name">Nom officiel</label>
                        <input id="school_name" name="school_name" value="{{ old('school_name', $settings->school_name) }}" required>
                        @error('school_name') <small class="error">{{ $message }}</small> @enderror
                    </div>

                    <div class="field">
                        <label for="short_name">Sigle</label>
                        <input id="short_name" name="short_name" value="{{ old('short_name', $settings->short_name) }}" placeholder="LPP">
                        @error('short_name') <small class="error">{{ $message }}</small> @enderror
                    </div>

                    <div class="field">
                        <label for="currency">Devise</label>
                        <input id="currency" name="currency" value="{{ old('currency', $settings->currency) }}" required>
                        @error('currency') <small class="error">{{ $message }}</small> @enderror
                    </div>

                    <div class="field wide">
                        <label for="motto">Devise de l'ecole</label>
                        <input id="motto" name="motto" value="{{ old('motto', $settings->motto) }}" placeholder="Batir l'excellence">
                        @error('motto') <small class="error">{{ $message }}</small> @enderror
                    </div>

                    <div class="field">
                        <label for="country">Pays</label>
                        <input id="country" name="country" value="{{ old('country', $settings->country) }}">
                        @error('country') <small class="error">{{ $message }}</small> @enderror
                    </div>

                    <div class="field">
                        <label for="national_motto">Devise nationale</label>
                        <input id="national_motto" name="national_motto" value="{{ old('national_motto', $settings->national_motto) }}">
                        @error('national_motto') <small class="error">{{ $message }}</small> @enderror
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2>Logo</h2>
                </div>

                @if ($settings->logo_path)
                    <div class="detail-item" style="margin-bottom:16px">
                        <span>Logo actuel</span>
                        <img src="{{ asset($settings->logo_path) }}" alt="Logo" style="width:92px;height:92px;object-fit:contain">
                    </div>
                @endif

                <div class="field">
                    <label for="logo">Remplacer le logo</label>
                    <input id="logo" name="logo" type="file" accept="image/*">
                    @error('logo') <small class="error">{{ $message }}</small> @enderror
                </div>

                <div class="field">
                    <label for="active_academic_year_id">Annee scolaire active</label>
                    <select id="active_academic_year_id" name="active_academic_year_id" required>
                        @foreach ($academicYears as $year)
                            <option value="{{ $year->id }}" @selected(old('active_academic_year_id', $academicYear?->id) == $year->id)>
                                {{ $year->name }}{{ $year->is_active ? ' - active' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('active_academic_year_id') <small class="error">{{ $message }}</small> @enderror
                </div>
            </div>
        </section>

        <section class="grid two-col" style="margin-top:16px">
            <div class="panel">
                <div class="panel-head">
                    <h2>Contacts</h2>
                </div>

                <div class="form-grid">
                    <div class="field wide">
                        <label for="address">Adresse</label>
                        <input id="address" name="address" value="{{ old('address', $settings->address) }}">
                        @error('address') <small class="error">{{ $message }}</small> @enderror
                    </div>

                    <div class="field">
                        <label for="city">Ville</label>
                        <input id="city" name="city" value="{{ old('city', $settings->city) }}">
                        @error('city') <small class="error">{{ $message }}</small> @enderror
                    </div>

                    <div class="field">
                        <label for="postal_box">Boite postale</label>
                        <input id="postal_box" name="postal_box" value="{{ old('postal_box', $settings->postal_box) }}">
                        @error('postal_box') <small class="error">{{ $message }}</small> @enderror
                    </div>

                    <div class="field">
                        <label for="phone">Telephone</label>
                        <input id="phone" name="phone" value="{{ old('phone', $settings->phone) }}">
                        @error('phone') <small class="error">{{ $message }}</small> @enderror
                    </div>

                    <div class="field">
                        <label for="email">E-mail</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $settings->email) }}">
                        @error('email') <small class="error">{{ $message }}</small> @enderror
                    </div>

                    <div class="field wide">
                        <label for="website">Site web</label>
                        <input id="website" name="website" value="{{ old('website', $settings->website) }}" placeholder="Optionnel">
                        @error('website') <small class="error">{{ $message }}</small> @enderror
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2>Signature officielle</h2>
                </div>

                <div class="form-grid">
                    <div class="field wide">
                        <label for="principal_title">Titre du signataire</label>
                        <input id="principal_title" name="principal_title" value="{{ old('principal_title', $settings->principal_title) }}" placeholder="Le Proviseur">
                        @error('principal_title') <small class="error">{{ $message }}</small> @enderror
                    </div>

                    <div class="field wide">
                        <label for="principal_name">Nom du signataire</label>
                        <input id="principal_name" name="principal_name" value="{{ old('principal_name', $settings->principal_name) }}" placeholder="Yamdaogo TINTILA">
                        @error('principal_name') <small class="error">{{ $message }}</small> @enderror
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Enregistrer les parametres</button>
                </div>
            </div>
        </section>
    </form>
@endsection
