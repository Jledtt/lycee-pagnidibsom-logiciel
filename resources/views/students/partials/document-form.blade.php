<form
    id="{{ $formId }}"
    method="POST"
    action="{{ route('students.documents.store', $student) }}"
    enctype="multipart/form-data"
    data-student-document-form
    data-prevent-double-submit
>
    @csrf

    <div class="form-grid student-document-fields">
        <div class="field wide">
            <label for="{{ $formId }}-name">Nom du document</label>
            <input id="{{ $formId }}-name" name="name" value="{{ old('name') }}" placeholder="Exemple : Acte de naissance" required data-document-name>
            @error('name') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="{{ $formId }}-type">Type</label>
            <select id="{{ $formId }}-type" name="document_type" required data-document-type>
                @foreach ($documentTypeLabels as $type => $label)
                    <option value="{{ $type }}" @selected(old('document_type') === $type)>{{ $label }}</option>
                @endforeach
            </select>
            @error('document_type') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="{{ $formId }}-status">Statut</label>
            <select id="{{ $formId }}-status" name="status" required data-document-status>
                <option value="received" @selected(old('status', 'received') === 'received')>Reçu</option>
                <option value="missing" @selected(old('status') === 'missing')>Manquant</option>
                <option value="expired" @selected(old('status') === 'expired')>Expiré</option>
            </select>
            @error('status') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="{{ $formId }}-received-at">Date de réception</label>
            <input id="{{ $formId }}-received-at" type="date" name="received_at" value="{{ old('received_at', now()->toDateString()) }}">
            @error('received_at') <small class="error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="{{ $formId }}-file">Fichier PDF ou image</label>
            <input id="{{ $formId }}-file" type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png,.webp" data-document-file>
            <small>10 Mo maximum.</small>
            @error('document_file') <small class="error">{{ $message }}</small> @enderror
        </div>
    </div>
</form>
