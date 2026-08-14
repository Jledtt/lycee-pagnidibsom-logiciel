@extends('layouts.app', [
    'title' => 'Nouveau responsable légal',
    'active' => 'guardians',
    'pageTitle' => 'Nouveau responsable légal',
    'pageSubtitle' => 'Créer une fiche administrative et la rattacher à un élève',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('guardians.index') }}">Retour</a>
@endsection

@section('content')
    <form method="POST" action="{{ route('guardians.store') }}" data-prevent-double-submit>
        @csrf
        @include('guardians._form')

        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>Premier élève rattaché</h2>
            </div>

            <div class="form-grid">
                <div class="field wide">
                    <label for="student_id">Élève</label>
                    <select id="student_id" name="student_id" required>
                        <option value="">Choisir un élève</option>
                        @foreach ($students as $student)
                            @php($enrollment = $student->enrollments->first())
                            <option value="{{ $student->id }}" @selected((string) old('student_id', request('student_id')) === (string) $student->id)>
                                {{ $student->last_name }} {{ $student->first_name }} · {{ $student->matricule }} · {{ $enrollment?->schoolClass?->name ?? 'Sans classe active' }}
                            </option>
                        @endforeach
                    </select>
                    @error('student_id') <small class="error">{{ $message }}</small> @enderror
                </div>

                <div class="field">
                    <label for="relationship">Lien avec l’élève</label>
                    <select id="relationship" name="relationship" required>
                        @foreach (['father' => 'Père', 'mother' => 'Mère', 'tutor' => 'Tuteur ou tutrice', 'other' => 'Autre responsable'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('relationship', 'father') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('relationship') <small class="error">{{ $message }}</small> @enderror
                </div>

                <div class="field">
                    <span class="field-label">Autorisations</span>
                    <label class="check"><input type="checkbox" name="is_primary" value="1" @checked(old('is_primary', true))> Contact principal</label>
                    <label class="check"><input type="checkbox" name="can_receive_sms" value="1" @checked(old('can_receive_sms', true))> Peut recevoir les notifications</label>
                    <label class="check"><input type="checkbox" name="can_pickup_child" value="1" @checked(old('can_pickup_child'))> Peut récupérer l’élève</label>
                </div>
            </div>
        </section>

        <div class="form-actions">
            <a class="btn btn-subtle" href="{{ route('guardians.index') }}">Annuler</a>
            <button class="btn btn-primary" type="submit">Créer la fiche</button>
        </div>
    </form>
@endsection
