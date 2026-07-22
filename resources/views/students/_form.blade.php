@php
    $fatherGuardian = $fatherGuardian ?? null;
    $motherGuardian = $motherGuardian ?? null;
    $conditions = old('health_conditions', $student->health_conditions ?? []);
@endphp

@if ($errors->any())
    <div class="error">
        {{ $errors->first() }}
    </div>
@endif

<div class="panel">
    <div class="panel-head">
        <h2>1- Identite de l’élève</h2>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="desired_class">Classe demandee</label>
            <input id="desired_class" name="desired_class" value="{{ old('desired_class', $student->desired_class) }}" placeholder="Ex: 6e A, 2nde, Tle D">
        </div>

        <div class="field">
            <label for="origin_school">École d’origine</label>
            <input id="origin_school" name="origin_school" value="{{ old('origin_school', $student->origin_school) }}">
        </div>

        <div class="field">
            <label for="last_name">Nom</label>
            <input id="last_name" name="last_name" value="{{ old('last_name', $student->last_name) }}" required>
        </div>

        <div class="field">
            <label for="first_name">Prénom(s)</label>
            <input id="first_name" name="first_name" value="{{ old('first_name', $student->first_name) }}" required>
        </div>

        <div class="field">
            <label for="birth_date">Date de naissance</label>
            <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', $student->birth_date?->format('Y-m-d')) }}">
        </div>

        <div class="field">
            <label for="birth_place">Lieu de naissance</label>
            <input id="birth_place" name="birth_place" value="{{ old('birth_place', $student->birth_place) }}">
        </div>

        <div class="field">
            <label for="gender">Sexe</label>
            <select id="gender" name="gender">
                <option value="">Non renseign?</option>
                <option value="male" @selected(old('gender', $student->gender) === 'male')>Garcon</option>
                <option value="female" @selected(old('gender', $student->gender) === 'female')>Fille</option>
            </select>
        </div>

        <div class="field">
            <label for="nationality">Nationalite</label>
            <input id="nationality" name="nationality" value="{{ old('nationality', $student->nationality) }}">
        </div>

        <div class="field">
            <label for="ethnicity">Ethnie</label>
            <input id="ethnicity" name="ethnicity" value="{{ old('ethnicity', $student->ethnicity) }}">
        </div>

        <div class="field">
            <label for="religion">Religion</label>
            <input id="religion" name="religion" value="{{ old('religion', $student->religion) }}">
        </div>

        <div class="field">
            <label for="previous_class">Classe frequentee</label>
            <input id="previous_class" name="previous_class" value="{{ old('previous_class', $student->previous_class) }}">
        </div>

        <div class="field">
            <label for="repeated_class">Classe déjà redoublee</label>
            <input id="repeated_class" name="repeated_class" value="{{ old('repeated_class', $student->repeated_class) }}">
        </div>

        <div class="field">
            <label for="sector">Secteur</label>
            <input id="sector" name="sector" value="{{ old('sector', $student->sector) }}">
        </div>

        <div class="field">
            <label for="district">Quartier</label>
            <input id="district" name="district" value="{{ old('district', $student->district) }}">
        </div>

        <div class="field">
            <label for="home_phone">Tel domicile</label>
            <input id="home_phone" name="home_phone" value="{{ old('home_phone', $student->home_phone) }}">
        </div>

        <div class="field">
            <label for="status">Statut</label>
            <select id="status" name="status">
                <option value="active" @selected(old('status', $student->status ?? 'active') === 'active')>Actif</option>
                <option value="transferred" @selected(old('status', $student->status) === 'transferred')>Transfere</option>
                <option value="dropped" @selected(old('status', $student->status) === 'dropped')>Abandon</option>
                <option value="graduated" @selected(old('status', $student->status) === 'graduated')>Diplome</option>
                <option value="suspended" @selected(old('status', $student->status) === 'suspended')>Suspendu</option>
            </select>
        </div>

        <div class="field wide">
            <label for="address">Adresse</label>
            <input id="address" name="address" value="{{ old('address', $student->address) }}">
        </div>
    </div>
</div>

<div class="panel" style="margin-top:16px">
    <div class="panel-head">
        <h2>2- Parents / Tuteur</h2>
    </div>

    <div class="form-grid">
        <div class="field wide">
            <h3 style="margin:0;color:var(--forest)">Pere / Tuteur</h3>
        </div>

        <div class="field">
            <label for="father_last_name">Nom</label>
            <input id="father_last_name" name="father_last_name" value="{{ old('father_last_name', $fatherGuardian?->last_name) }}">
        </div>

        <div class="field">
            <label for="father_first_name">Prénom(s)</label>
            <input id="father_first_name" name="father_first_name" value="{{ old('father_first_name', $fatherGuardian?->first_name) }}">
        </div>

        <div class="field">
            <label for="father_profession">Profession</label>
            <input id="father_profession" name="father_profession" value="{{ old('father_profession', $fatherGuardian?->profession) }}">
        </div>

        <div class="field">
            <label for="father_service">Service</label>
            <input id="father_service" name="father_service" value="{{ old('father_service', $fatherGuardian?->service) }}">
        </div>

        <div class="field">
            <label for="father_phone_primary">Tel portable</label>
            <input id="father_phone_primary" name="father_phone_primary" value="{{ old('father_phone_primary', $fatherGuardian?->phone_primary) }}">
        </div>

        <div class="field">
            <label for="father_email">E-mail</label>
            <input id="father_email" name="father_email" type="email" value="{{ old('father_email', $fatherGuardian?->email) }}">
        </div>

        <div class="field wide">
            <h3 style="margin:12px 0 0;color:var(--forest)">Mere / Tutrice</h3>
        </div>

        <div class="field">
            <label for="mother_last_name">Nom</label>
            <input id="mother_last_name" name="mother_last_name" value="{{ old('mother_last_name', $motherGuardian?->last_name) }}">
        </div>

        <div class="field">
            <label for="mother_first_name">Prénom(s)</label>
            <input id="mother_first_name" name="mother_first_name" value="{{ old('mother_first_name', $motherGuardian?->first_name) }}">
        </div>

        <div class="field">
            <label for="mother_profession">Profession</label>
            <input id="mother_profession" name="mother_profession" value="{{ old('mother_profession', $motherGuardian?->profession) }}">
        </div>

        <div class="field">
            <label for="mother_service">Service</label>
            <input id="mother_service" name="mother_service" value="{{ old('mother_service', $motherGuardian?->service) }}">
        </div>

        <div class="field">
            <label for="mother_phone_primary">Tel portable</label>
            <input id="mother_phone_primary" name="mother_phone_primary" value="{{ old('mother_phone_primary', $motherGuardian?->phone_primary) }}">
        </div>

        <div class="field">
            <label for="mother_email">E-mail</label>
            <input id="mother_email" name="mother_email" type="email" value="{{ old('mother_email', $motherGuardian?->email) }}">
        </div>
    </div>
</div>

<div class="panel" style="margin-top:16px">
    <div class="panel-head">
        <h2>3- Observations particulieres</h2>
    </div>

    <div class="form-grid">
        <div class="field wide">
            <label>Etat de sante - pathologie connue</label>
            <div class="searchbar">
                @foreach ([
                    'asthme' => 'Asthme',
                    'drepanocytose' => 'Drepanocytose',
                    'cardiopathie' => 'Cardiopathie',
                    'hta' => 'HTA',
                    'diabete' => 'Diabete',
                    'epilepsie' => 'Epilepsie',
                ] as $value => $label)
                    <label class="check">
                        <input type="checkbox" name="health_conditions[]" value="{{ $value }}" @checked(in_array($value, $conditions ?? [], true))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="field">
            <label>Aptitude au sport</label>
            <div class="searchbar">
                <label class="check">
                    <input type="radio" name="sport_aptitude" value="1" @checked((string) old('sport_aptitude', $student->sport_aptitude) === '1')>
                    <span>Oui</span>
                </label>
                <label class="check">
                    <input type="radio" name="sport_aptitude" value="0" @checked((string) old('sport_aptitude', $student->sport_aptitude) === '0')>
                    <span>Non</span>
                </label>
            </div>
        </div>

        <div class="field wide">
            <label for="health_notes">Autres observations</label>
            <textarea id="health_notes" name="health_notes">{{ old('health_notes', $student->health_notes) }}</textarea>
        </div>
    </div>
</div>

<div class="panel" style="margin-top:16px">
    <div class="panel-head">
        <h2>4- Personne a prevenir en cas de besoin</h2>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="emergency_contact_name">Mr / Mme</label>
            <input id="emergency_contact_name" name="emergency_contact_name" value="{{ old('emergency_contact_name', $student->emergency_contact_name) }}">
        </div>

        <div class="field">
            <label for="emergency_contact_phone">Contact</label>
            <input id="emergency_contact_phone" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $student->emergency_contact_phone) }}">
        </div>

        <div class="field wide">
            <label for="school_info_whatsapp">No WhatsApp pour les infos de l’école</label>
            <input id="school_info_whatsapp" name="school_info_whatsapp" value="{{ old('school_info_whatsapp', $student->school_info_whatsapp) }}">
        </div>
    </div>
</div>

<div class="form-actions">
    <a class="btn btn-subtle" href="{{ route('students.index') }}">Annuler</a>
    <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
</div>
