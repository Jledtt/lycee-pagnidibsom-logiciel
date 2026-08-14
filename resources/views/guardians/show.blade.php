@extends('layouts.app', [
    'title' => $guardian->full_name . ' - Responsables légaux',
    'active' => 'guardians',
    'pageTitle' => $guardian->full_name,
    'pageSubtitle' => 'Fiche administrative du responsable légal',
])

@php
    $relationshipLabels = [
        'father' => 'Père',
        'mother' => 'Mère',
        'tutor' => 'Tuteur ou tutrice',
        'other' => 'Autre responsable',
    ];
@endphp

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('guardians.index') }}">Retour</a>
    @can('guardians.manage')
        <a class="btn btn-primary" href="{{ route('guardians.edit', $guardian) }}">Modifier</a>
    @endcan
@endsection

@section('content')
    <section class="stats">
        <div class="stat"><span>Élèves rattachés</span><strong>{{ $guardian->students->count() }}</strong></div>
        <div class="stat"><span>Téléphone principal</span><strong>{{ $guardian->phone_primary }}</strong></div>
        <div class="stat"><span>Profession</span><strong>{{ $guardian->profession ?: 'Non renseignée' }}</strong></div>
        <div class="stat"><span>Statut</span><strong>{{ $guardian->status === 'active' ? 'Actif' : 'Inactif' }}</strong></div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head"><h2>Coordonnées</h2></div>
        <div class="detail-grid">
            <div class="detail-item"><span>Téléphone secondaire</span><strong>{{ $guardian->phone_secondary ?: '-' }}</strong></div>
            <div class="detail-item"><span>E-mail</span><strong>{{ $guardian->email ?: '-' }}</strong></div>
            <div class="detail-item"><span>Service ou employeur</span><strong>{{ $guardian->service ?: '-' }}</strong></div>
            <div class="detail-item wide"><span>Adresse</span><strong>{{ $guardian->address ?: '-' }}</strong></div>
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Élèves rattachés</h2>
            <span class="badge">{{ $guardian->students->count() }}</span>
        </div>

        @if ($guardian->students->isEmpty())
            <div class="empty">Aucun élève n’est actuellement rattaché à cette fiche.</div>
        @else
            <div class="ledger-list">
                @foreach ($guardian->students as $student)
                    @php($enrollment = $student->enrollments->first())
                    <details class="ledger-item">
                        <summary class="ledger-summary" style="grid-template-columns:minmax(220px,1.5fr) repeat(3,minmax(120px,.8fr)) minmax(80px,.4fr)">
                            <div class="ledger-person">
                                <strong>{{ $student->full_name }}</strong>
                                <span>{{ $student->matricule }}</span>
                            </div>
                            <div class="ledger-metric"><span>Classe</span><strong>{{ $enrollment?->schoolClass?->name ?? '-' }}</strong></div>
                            <div class="ledger-metric"><span>Lien</span><strong>{{ $relationshipLabels[$student->pivot->relationship] ?? 'Autre' }}</strong></div>
                            <div class="ledger-metric"><span>Contact</span><strong>{{ $student->pivot->is_primary ? 'Principal' : 'Secondaire' }}</strong></div>
                            <span class="ledger-toggle" aria-hidden="true">⌄</span>
                        </summary>

                        <div class="ledger-detail">
                            <div class="ledger-detail-head">
                                <h3>Relation et autorisations</h3>
                                <a class="btn btn-subtle" href="{{ route('students.show', $student) }}">Voir le dossier élève</a>
                            </div>

                            @can('guardians.manage')
                                <form method="POST" action="{{ route('guardians.students.update', [$guardian, $student]) }}" data-prevent-double-submit>
                                    @csrf
                                    @method('PUT')
                                    <div class="form-grid">
                                        <div class="field">
                                            <label for="relationship-{{ $student->id }}">Lien avec l’élève</label>
                                            <select id="relationship-{{ $student->id }}" name="relationship" required>
                                                @foreach ($relationshipLabels as $value => $label)
                                                    <option value="{{ $value }}" @selected($student->pivot->relationship === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <span class="field-label">Autorisations</span>
                                            <label class="check"><input type="checkbox" name="is_primary" value="1" @checked($student->pivot->is_primary)> Contact principal</label>
                                            <label class="check"><input type="checkbox" name="can_receive_sms" value="1" @checked($student->pivot->can_receive_sms)> Peut recevoir les notifications</label>
                                            <label class="check"><input type="checkbox" name="can_pickup_child" value="1" @checked($student->pivot->can_pickup_child)> Peut récupérer l’élève</label>
                                        </div>
                                    </div>
                                    <div class="form-actions"><button class="btn btn-subtle" type="submit">Mettre à jour</button></div>
                                </form>

                                <form
                                    method="POST"
                                    action="{{ route('guardians.students.destroy', [$guardian, $student]) }}"
                                    data-confirm
                                    data-confirm-title="Retirer l’élève"
                                    data-confirm-object="{{ $student->full_name }} — {{ $guardian->full_name }}"
                                    data-confirm-message="Seul le rattachement sera supprimé. Ni la fiche de l’élève ni celle du responsable ne sera effacée."
                                    data-confirm-action="Retirer le rattachement"
                                    data-prevent-double-submit
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger" type="submit">Retirer de cette fiche</button>
                                </form>
                            @endcan
                        </div>
                    </details>
                @endforeach
            </div>
        @endif
    </section>

    @can('guardians.manage')
        <section class="panel" style="margin-top:16px">
            <div class="panel-head"><h2>Rattacher un autre élève</h2></div>

            @if ($availableStudents->isEmpty())
                <div class="empty">Aucun autre élève actif n’est disponible.</div>
            @else
                <form method="POST" action="{{ route('guardians.students.store', $guardian) }}" data-prevent-double-submit>
                    @csrf
                    <div class="form-grid">
                        <div class="field wide">
                            <label for="student_id">Élève</label>
                            <select id="student_id" name="student_id" required>
                                <option value="">Choisir un élève</option>
                                @foreach ($availableStudents as $student)
                                    @php($enrollment = $student->enrollments->first())
                                    <option value="{{ $student->id }}">
                                        {{ $student->last_name }} {{ $student->first_name }} · {{ $student->matricule }} · {{ $enrollment?->schoolClass?->name ?? 'Sans classe active' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="relationship">Lien avec l’élève</label>
                            <select id="relationship" name="relationship" required>
                                @foreach ($relationshipLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div class="field">
                            <span class="field-label">Autorisations</span>
                            <label class="check"><input type="checkbox" name="is_primary" value="1"> Contact principal</label>
                            <label class="check"><input type="checkbox" name="can_receive_sms" value="1" checked> Peut recevoir les notifications</label>
                            <label class="check"><input type="checkbox" name="can_pickup_child" value="1"> Peut récupérer l’élève</label>
                        </div>
                    </div>
                    <div class="form-actions"><button class="btn btn-primary" type="submit">Rattacher l’élève</button></div>
                </form>
            @endif
        </section>
    @endcan
@endsection
