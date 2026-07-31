<x-ui.modal
    id="student-document-dialog"
    title="Ajouter une pièce"
    description="Le fichier sera protégé dans le dossier de {{ $student->full_name }}."
    size="medium"
    :open="session('student_document_open') || $errors->hasAny(['name', 'document_type', 'status', 'received_at', 'document_file'])"
>
    @include('students.partials.document-form', [
        'student' => $student,
        'documentTypeLabels' => $documentTypeLabels,
        'formId' => 'student-document-form',
    ])

    <x-slot:footer>
        <button class="btn btn-subtle" type="button" data-dialog-close>Annuler</button>
        <button class="btn btn-primary" type="submit" form="student-document-form" data-submitting-label="Ajout…">Ajouter au dossier</button>
    </x-slot:footer>
</x-ui.modal>
