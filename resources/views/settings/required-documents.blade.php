@extends('layouts.app', [
    'title' => 'Pièces obligatoires - Lycée Privé Pagnidibsom',
    'active' => 'settings',
    'pageTitle' => 'Pièces obligatoires',
    'pageSubtitle' => 'Parametrage des documents exiges dans les dossiers élèves',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('settings.edit') }}">Paramètres école</a>
    <a class="btn btn-subtle" href="{{ route('reports.missing-documents') }}">Rapport pièces manquantes</a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <section class="grid two-col">
        <div class="panel">
            <div class="panel-head">
                <h2>Nouvelle pièce</h2>
            </div>

            <form method="POST" action="{{ route('settings.required-documents.store') }}">
                @csrf

                <div class="form-grid">
                    <div class="field wide">
                        <label>Nom affiche</label>
                        <input name="name" value="{{ old('name') }}" placeholder="Ex: Certificat médical" required>
                    </div>

                    <div class="field wide">
                        <label>Type / code</label>
                        <input name="document_type" value="{{ old('document_type') }}" placeholder="Laisse vide pour générer automatiquement">
                    </div>

                    <div class="field">
                        <label>Portee</label>
                        <select name="scope" required>
                            <option value="all" @selected(old('scope', 'all') === 'all')>Tous les élèves</option>
                            <option value="cycle" @selected(old('scope') === 'cycle')>Un cycle</option>
                            <option value="class" @selected(old('scope') === 'class')>Une classe</option>
                        </select>
                    </div>

                    <div class="field">
                        <label>Cycle</label>
                        <select name="level_cycle">
                            <option value="">Non concerne</option>
                            @foreach ($cycles as $cycle)
                                <option value="{{ $cycle }}" @selected(old('level_cycle') === $cycle)>{{ $cycle }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field wide">
                        <label>Classe</label>
                        <select name="school_class_id">
                            <option value="">Non concernee</option>
                            @foreach ($classes as $class)
                                <option value="{{ $class->id }}" @selected((int) old('school_class_id') === $class->id)>
                                    {{ $class->name }}{{ $class->level ? ' - ' . $class->level->name : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label>Ordre</label>
                        <input name="position" type="number" min="1" max="999" value="{{ old('position', 10) }}" required>
                    </div>

                    <div class="field">
                        <label>Statut</label>
                        <select name="status" required>
                            <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                            <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Ajouter la pièce</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Types utilisables</h2>
                <span class="badge">{{ count($documentTypes) }} type(s)</span>
            </div>

            <div class="subject-list-scroll" style="max-height:420px">
                <table class="table" style="min-width:520px">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Libelle</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documentTypes as $type => $label)
                            <tr>
                                <td><span class="badge">{{ $type }}</span></td>
                                <td><strong>{{ $label }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Pieces configurées</h2>
            <span class="badge">{{ $requiredDocuments->count() }} regle(s)</span>
        </div>

        @if ($requiredDocuments->isEmpty())
            <div class="empty">Aucune pièce obligatoire configurée.</div>
        @else
            <div class="subject-list-scroll">
                <table class="table" style="min-width:1180px">
                    <thead>
                        <tr>
                            <th>Piece</th>
                            <th>Portee</th>
                            <th>Cycle</th>
                            <th>Classe</th>
                            <th>Ordre</th>
                            <th>Statut</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requiredDocuments as $document)
                            <tr>
                                @php($formId = 'required-document-' . $document->id)
                                <td>
                                    <input form="{{ $formId }}" name="name" value="{{ old('name', $document->name) }}" required>
                                    <input form="{{ $formId }}" name="document_type" value="{{ old('document_type', $document->document_type) }}" style="margin-top:8px">
                                </td>
                                <td>
                                    <select form="{{ $formId }}" name="scope" required>
                                        <option value="all" @selected($document->scope === 'all')>Tous</option>
                                        <option value="cycle" @selected($document->scope === 'cycle')>Cycle</option>
                                        <option value="class" @selected($document->scope === 'class')>Classe</option>
                                    </select>
                                </td>
                                <td>
                                    <select form="{{ $formId }}" name="level_cycle">
                                        <option value="">Non concerne</option>
                                        @foreach ($cycles as $cycle)
                                            <option value="{{ $cycle }}" @selected($document->level_cycle === $cycle)>{{ $cycle }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select form="{{ $formId }}" name="school_class_id">
                                        <option value="">Non concernee</option>
                                        @foreach ($classes as $class)
                                            <option value="{{ $class->id }}" @selected((int) $document->school_class_id === $class->id)>
                                                {{ $class->name }}{{ $class->level ? ' - ' . $class->level->name : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input form="{{ $formId }}" name="position" type="number" min="1" max="999" value="{{ $document->position }}" required></td>
                                <td>
                                    <select form="{{ $formId }}" name="status" required>
                                        <option value="active" @selected($document->status === 'active')>Active</option>
                                        <option value="inactive" @selected($document->status === 'inactive')>Inactive</option>
                                    </select>
                                </td>
                                <td>
                                    <div class="page-actions" style="justify-content:flex-end">
                                        <form id="{{ $formId }}" method="POST" action="{{ route('settings.required-documents.update', $document) }}">
                                            @csrf
                                            @method('PUT')
                                            <button class="btn btn-primary" type="submit">Enregistrer</button>
                                        </form>
                                            <form method="POST" action="{{ route('settings.required-documents.destroy', $document) }}" onsubmit="return confirm('Supprimer cette pièce obligatoire ?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger" type="submit">Supprimer</button>
                                            </form>
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
