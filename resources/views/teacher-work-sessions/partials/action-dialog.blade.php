<x-ui.modal
    id="teacher-work-session-action-dialog"
    title="Confirmer l’émargement"
    description="Vérifie la séance avant de continuer."
    size="small"
    data-teacher-session-action-dialog
>
    <div class="follow-up-summary-list">
        <div><span>Professeur</span><strong data-teacher-session-teacher>-</strong></div>
        <div><span>Cours</span><strong data-teacher-session-course>-</strong></div>
        <div><span>Date et horaire</span><strong data-teacher-session-time>-</strong></div>
        <div><span>Heures</span><strong data-teacher-session-hours>-</strong></div>
    </div>

    <div class="dialog-action-note" data-teacher-session-validate-note>
        <strong>Valider l’émargement</strong>
        <span>Les heures pourront ensuite être intégrées à un ordre d’honoraires.</span>
    </div>
    <div class="dialog-action-note dialog-action-note--danger" data-teacher-session-delete-note>
        <strong>Supprimer définitivement</strong>
        <span>Cette action retire la ligne tant qu’elle n’est liée à aucun honoraire.</span>
    </div>

    <form id="teacher-session-validate-form" method="POST" action="{{ route('teacher-work-sessions.validate', 0) }}" data-teacher-session-validate-form data-prevent-double-submit>
        @csrf
        @method('PUT')
    </form>
    <form id="teacher-session-delete-form" method="POST" action="{{ route('teacher-work-sessions.destroy', 0) }}" data-teacher-session-delete-form data-prevent-double-submit>
        @csrf
        @method('DELETE')
    </form>

    <x-slot:footer>
        <button class="btn btn-subtle" type="button" data-dialog-close>Fermer</button>
        <button class="btn btn-danger" type="submit" form="teacher-session-delete-form" data-teacher-session-delete-command data-submitting-label="Suppression…">Confirmer la suppression</button>
        <button class="btn btn-primary" type="submit" form="teacher-session-validate-form" data-teacher-session-validate-command data-submitting-label="Validation…">Confirmer la validation</button>
    </x-slot:footer>
</x-ui.modal>
