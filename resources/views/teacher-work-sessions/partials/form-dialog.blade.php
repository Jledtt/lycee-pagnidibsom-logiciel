<x-ui.modal
    id="teacher-work-session-form-dialog"
    title="Saisir les heures effectuées"
    description="Enregistre un cours réellement assuré par le professeur."
    size="large"
    :open="session('teacher_work_session_open') || $errors->hasAny(['teacher_id', 'school_class_id', 'subject_id', 'session_date', 'starts_at', 'ends_at', 'hours_worked', 'hourly_rate', 'status', 'teacher_signed', 'notes'])"
>
    @include('teacher-work-sessions.partials.form', [
        'formId' => 'teacher-work-session-form',
        'teachers' => $teachers,
        'classes' => $classes,
        'subjects' => $subjects,
        'filters' => $filters,
    ])

    <x-slot:footer>
        <button class="btn btn-subtle" type="button" data-dialog-close>Annuler</button>
        <button class="btn btn-primary" type="submit" form="teacher-work-session-form" data-submitting-label="Enregistrement…">Enregistrer les heures</button>
    </x-slot:footer>
</x-ui.modal>
